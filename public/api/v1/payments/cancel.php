<?php
/**
 * 取消支付接口
 * URL: POST /api/v1/payments/{paymentId}/cancel
 *
 * 请求参数:
 * {
 *   "paymentId": "PAY-123456",
 *   "reason": "用户取消支付"
 * }
 *
 * 响应:
 * {
 *   "code": 200,
 *   "message": "success",
 *   "data": {
 *     "paymentId": "PAY-123456",
 *     "status": "CANCELLED",
 *     "cancelTime": "2024-12-02T10:20:00Z"
 *   }
 * }
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
 * 通知Booking Panel订单已取消
 */
function notifyBookingPanelCancel(string $orderId, string $paymentId, array $config, Logger $logger): array {
    $url = $config['external_callback']['booking_panel']['order_update_url']
        ?? 'http://localhost:8080/api/v1/orders/update-status';

    $timeout = $config['external_callback']['booking_panel']['timeout'] ?? 2;
    $maxRetries = $config['external_callback']['booking_panel']['max_retries'] ?? 1;
    $retryInterval = $config['external_callback']['booking_panel']['retry_interval'] ?? 1;

    $requestData = [
        'orderId' => $orderId,
        'status' => 'CANCELLED',
        'paymentId' => $paymentId,
        'paymentTime' => date('c')
    ];

    $lastError = null;

    for ($retry = 0; $retry <= $maxRetries; $retry++) {
        if ($retry > 0) {
            $logger->warning("第 {$retry} 次重试通知Booking Panel取消...");
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
            // curl_close在PHP 8.0+已无效果

            if ($curlError) {
                $lastError = "cURL错误: $curlError";
                continue;
            }

            $result = json_decode($response, true);

            if ($httpCode === 200 && isset($result['code']) && $result['code'] === 200) {
                $logger->info("Booking Panel订单取消通知成功: orderId=$orderId");
                return ['success' => true];
            }

            $lastError = "HTTP $httpCode: " . ($result['message'] ?? $response);

        } catch (Exception $e) {
            $lastError = $e->getMessage();
        }
    }

    $logger->error("通知Booking Panel取消失败: orderId=$orderId, error=$lastError");
    return ['success' => false, 'error' => $lastError];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, 'Method Not Allowed', null, 'METHOD_NOT_ALLOWED');
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(400, '无效的请求格式', null, 'INVALID_JSON');
    }

    // 获取paymentId（支持body参数或PATH_INFO）
    $paymentId = $input['paymentId'] ?? $input['payment_id'] ?? null;

    if (!$paymentId && isset($_SERVER['PATH_INFO'])) {
        $pathParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
        if (count($pathParts) >= 1) {
            $paymentId = $pathParts[0];
        }
    }

    if (!$paymentId) {
        jsonResponse(400, '缺少支付ID', null, 'MISSING_PAYMENT_ID');
    }

    $reason = $input['reason'] ?? '用户取消支付';

    $logger->info("取消支付请求: paymentId=$paymentId, reason=$reason");

    $db = new Database($config['database']);

    // 查询支付记录
    $mapping = $db->selectOne(
        "SELECT eom.*, t.status as transaction_status
         FROM external_order_mappings eom
         LEFT JOIN transactions t ON eom.transaction_id = t.id
         WHERE eom.payment_id = ?",
        [$paymentId]
    );

    if (!$mapping) {
        jsonResponse(404, '支付记录不存在', null, 'PAYMENT_NOT_FOUND');
    }

    // 检查是否可以取消
    $cancelableStatuses = ['pending', 'processing'];
    if (!in_array($mapping['transaction_status'], $cancelableStatuses)) {
        $statusText = [
            'success' => '已支付成功',
            'failed' => '已失败',
            'cancelled' => '已取消',
            'timeout' => '已超时'
        ];
        $msg = $statusText[$mapping['transaction_status']] ?? '状态异常';
        jsonResponse(4001, "无法取消: 订单{$msg}", null, 'CANNOT_CANCEL');
    }

    // 更新本地状态为已取消
    $cancelTime = date('c');

    $db->update(
        "UPDATE transactions SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
        [$mapping['transaction_id']]
    );

    $db->update(
        "UPDATE payment_details SET error_message = ? WHERE transaction_id = ?",
        [$reason, $mapping['transaction_id']]
    );

    $logger->info("支付已取消: paymentId=$paymentId");

    // 通知Booking Panel
    $notifyResult = notifyBookingPanelCancel(
        $mapping['external_order_id'],
        $paymentId,
        $config,
        $logger
    );

    // 更新回调状态
    $callbackStatus = $notifyResult['success'] ? 'success' : 'failed';
    $db->update(
        "UPDATE external_order_mappings SET callback_status = ?, updated_at = NOW() WHERE id = ?",
        [$callbackStatus, $mapping['id']]
    );

    jsonResponse(200, 'success', [
        'paymentId' => $paymentId,
        'status' => 'CANCELLED',
        'cancelTime' => $cancelTime
    ]);

} catch (Exception $e) {
    $logger->error("取消支付异常: " . $e->getMessage());
    jsonResponse(500, '服务器内部错误', null, 'INTERNAL_ERROR');
}
