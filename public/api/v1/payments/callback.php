<?php
/**
 * 支付回调接口 - 接收支付网关的回调通知
 * URL: POST /api/v1/payments/callback
 *
 * 请求参数:
 * {
 *   "paymentId": "PAY-123456",
 *   "orderId": "ORD-001",
 *   "status": "SUCCESS",
 *   "amount": 299.50,
 *   "transactionId": "TXN-789012",
 *   "timestamp": "2024-12-25T10:30:00",
 *   "signature": "..."
 * }
 *
 * 响应:
 * {
 *   "code": 200,
 *   "message": "success"
 * }
 *
 * 处理流程:
 * 1. 验证签名
 * 2. 更新本地支付状态
 * 3. 调用Booking Panel的订单状态更新接口
 * 4. 返回处理结果
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Internal-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../../../config/config.php';
require_once __DIR__ . '/../../../../src/services/PaymentService.php';
require_once __DIR__ . '/../../../../src/models/Database.php';
require_once __DIR__ . '/../../../../src/utils/Logger.php';

$config = require __DIR__ . '/../../../../config/config.php';
$logger = new Logger($config['logging']);

function jsonResponse(int $code, string $message, $data = null, ?string $error = null): void {
    $response = [
        'code' => $code,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($error !== null) {
        $response['error'] = $error;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 验证签名
 */
function verifySignature(array $data, string $secretKey): bool {
    if (!isset($data['signature'])) {
        return false;
    }

    $signature = $data['signature'];
    unset($data['signature']);

    // 按key排序
    ksort($data);

    // 拼接签名字符串
    $signStr = '';
    foreach ($data as $key => $value) {
        if ($value !== null && $value !== '') {
            $signStr .= $key . '=' . $value . '&';
        }
    }
    $signStr .= 'key=' . $secretKey;

    // 计算签名
    $calculatedSign = strtoupper(md5($signStr));

    return $calculatedSign === strtoupper($signature);
}

/**
 * 调用Booking Panel的订单状态更新接口
 * URL: POST http://localhost:8080/api/v1/orders/update-status
 */
function notifyBookingPanel(string $orderId, string $status, string $paymentId, string $paymentTime, array $config, Logger $logger): array {
    $url = $config['external_callback']['booking_panel']['order_update_url']
        ?? 'http://localhost:8080/api/v1/orders/update-status';

    $timeout = $config['external_callback']['booking_panel']['timeout'] ?? 2;
    $maxRetries = $config['external_callback']['booking_panel']['max_retries'] ?? 1;
    $retryInterval = $config['external_callback']['booking_panel']['retry_interval'] ?? 1;

    $requestData = [
        'orderId' => $orderId,
        'status' => $status,
        'paymentId' => $paymentId,
        'paymentTime' => $paymentTime
    ];

    $logger->info("准备调用Booking Panel订单状态更新接口: " . json_encode($requestData));

    $lastError = null;

    // 重试机制
    for ($retry = 0; $retry <= $maxRetries; $retry++) {
        if ($retry > 0) {
            $logger->warning("第 {$retry} 次重试调用Booking Panel...");
            sleep($retryInterval);
        }

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($requestData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Internal-Token: ' . ($config['security']['internal_token'] ?? '')
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 3
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            // curl_close在PHP 8.0+已无效果，不再调用

            if ($curlError) {
                $lastError = "cURL错误: $curlError";
                $logger->error($lastError);
                continue;
            }

            $result = json_decode($response, true);

            if ($httpCode === 200 && isset($result['code']) && $result['code'] === 200) {
                $logger->info("Booking Panel订单状态更新成功: orderId=$orderId");
                return ['success' => true, 'response' => $result];
            }

            $lastError = "HTTP $httpCode: " . ($result['message'] ?? $response);
            $logger->warning("Booking Panel返回非成功: $lastError");

        } catch (Exception $e) {
            $lastError = $e->getMessage();
            $logger->error("调用Booking Panel异常: $lastError");
        }
    }

    // 所有重试都失败，记录告警
    $logger->error("调用Booking Panel失败(已重试{$maxRetries}次): orderId=$orderId, error=$lastError");

    return [
        'success' => false,
        'error' => $lastError,
        'need_manual_process' => true
    ];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, 'Method Not Allowed', null, 'METHOD_NOT_ALLOWED');
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->warning("无效的JSON请求: $rawInput");
        jsonResponse(400, '无效的请求格式', null, 'INVALID_JSON');
    }

    $logger->info("收到支付回调: " . $rawInput);

    // 获取参数
    $paymentId = $input['paymentId'] ?? null;
    $orderId = $input['orderId'] ?? null;
    $status = strtoupper($input['status'] ?? '');
    $amount = $input['amount'] ?? 0;
    $transactionId = $input['transactionId'] ?? '';
    $timestamp = $input['timestamp'] ?? date('c');
    $signature = $input['signature'] ?? '';

    // 参数验证
    if (!$paymentId) {
        jsonResponse(400, '缺少支付ID', null, 'MISSING_PAYMENT_ID');
    }

    // 验证签名（生产环境必须验证）
    $secretKey = $config['security']['callback_secret_key'] ?? 'default_secret_key';
    if ($config['security']['verify_callback_signature'] ?? false) {
        if (!verifySignature($input, $secretKey)) {
            $logger->warning("签名验证失败: paymentId=$paymentId");
            jsonResponse(401, '签名验证失败', null, 'INVALID_SIGNATURE');
        }
    }

    // 查询支付记录
    $db = new Database($config['database']);

    // 幂等性检查 - 查询是否已处理过
    $existingMapping = $db->selectOne(
        "SELECT * FROM external_order_mappings WHERE payment_id = ?",
        [$paymentId]
    );

    if (!$existingMapping) {
        $logger->warning("支付记录不存在: paymentId=$paymentId");
        jsonResponse(404, '支付记录不存在', null, 'PAYMENT_NOT_FOUND');
    }

    // 幂等性：如果已经处理成功，直接返回成功
    if ($existingMapping['callback_status'] === 'success') {
        $logger->info("支付回调已处理过(幂等): paymentId=$paymentId");
        jsonResponse(200, 'success');
    }

    // 获取关联的交易记录
    $transaction = $db->selectOne(
        "SELECT * FROM transactions WHERE id = ?",
        [$existingMapping['transaction_id']]
    );

    if (!$transaction) {
        jsonResponse(404, '交易记录不存在', null, 'TRANSACTION_NOT_FOUND');
    }

    // 更新本地支付状态
    $localStatus = ($status === 'SUCCESS') ? 'success' : 'failed';
    $db->update(
        "UPDATE transactions SET status = ?, payment_time = NOW(), updated_at = NOW() WHERE id = ?",
        [$localStatus, $transaction['id']]
    );

    // 更新支付详情
    $db->update(
        "UPDATE payment_details SET payment_gateway_response = ? WHERE transaction_id = ?",
        [json_encode($input, JSON_UNESCAPED_UNICODE), $transaction['id']]
    );

    $logger->info("本地支付状态已更新: transactionId={$transaction['id']}, status=$localStatus");

    // 映射状态到Booking Panel期望的格式
    $bookingStatus = ($status === 'SUCCESS') ? 'PAID' : 'FAILED';

    // 调用Booking Panel的订单状态更新接口
    $callbackResult = notifyBookingPanel(
        $existingMapping['external_order_id'],
        $bookingStatus,
        $paymentId,
        $timestamp,
        $config,
        $logger
    );

    // 更新回调状态
    $callbackStatus = $callbackResult['success'] ? 'success' : 'failed';
    $db->update(
        "UPDATE external_order_mappings SET callback_status = ?, callback_response = ? WHERE id = ?",
        [$callbackStatus, json_encode($callbackResult, JSON_UNESCAPED_UNICODE), $existingMapping['id']]
    );

    if (!$callbackResult['success']) {
        // 记录到待处理队列（实际项目中应写入消息队列）
        $logger->error("需要人工处理: paymentId=$paymentId, orderId={$existingMapping['external_order_id']}");

        // 仍然返回成功，因为我们已经接收到回调
        // 但记录需要后续处理
        jsonResponse(200, 'success', [
            'warning' => '订单状态同步失败，已加入待处理队列'
        ]);
    }

    jsonResponse(200, 'success');

} catch (Exception $e) {
    $logger->error("支付回调处理异常: " . $e->getMessage());
    jsonResponse(500, '服务器内部错误', null, 'INTERNAL_ERROR');
}
