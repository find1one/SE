<?php
/**
 * 创建支付订单接口
 * URL: POST /api/v1/payments/create
 *
 * 请求参数:
 * {
 *   "orderId": "ORD-001",
 *   "userId": "1001",
 *   "amount": 299.50,
 *   "currency": "CNY",
 *   "description": "火车票订单 G101 北京南-上海虹桥",
 *   "paymentMethod": "ALIPAY",
 *   "notifyUrl": "https://booking-api.com/api/v1/payments/callback",
 *   "returnUrl": "https://booking-app.com/payment/success"
 * }
 *
 * 响应:
 * {
 *   "code": 200,
 *   "message": "success",
 *   "data": {
 *     "paymentId": "PAY-123456",
 *     "paymentUrl": "http://payment.example.com/pay?paymentId=PAY-123456",
 *     "qrCode": "data:image/png;base64,...",
 *     "expireTime": "2024-12-02T10:35:00Z"
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
require_once __DIR__ . '/../../../../src/services/PaymentService.php';
require_once __DIR__ . '/../../../../src/models/Database.php';
require_once __DIR__ . '/../../../../src/utils/Logger.php';

$config = require __DIR__ . '/../../../../config/config.php';
$logger = new Logger($config['logging']);

/**
 * 返回JSON响应
 */
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
 * 生成支付ID
 */
function generatePaymentId(): string {
    return 'PAY-' . date('YmdHis') . rand(1000, 9999);
}

/**
 * 生成简单的二维码数据（模拟）
 */
function generateQrCode(string $paymentUrl): string {
    // 实际项目中应使用二维码生成库
    // 这里返回模拟的base64数据
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
}

try {
    // 只接受POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, 'Method Not Allowed', null, 'METHOD_NOT_ALLOWED');
    }

    // 获取请求数据
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->warning("无效的JSON请求: $rawInput");
        jsonResponse(400, '无效的请求格式', null, 'INVALID_JSON');
    }

    $logger->info("收到创建支付请求: " . $rawInput);

    // 参数验证
    $orderId = $input['orderId'] ?? null;
    $userId = $input['userId'] ?? null;
    $amount = $input['amount'] ?? null;
    $currency = $input['currency'] ?? 'CNY';
    $description = $input['description'] ?? '';
    $paymentMethod = strtoupper($input['paymentMethod'] ?? 'ALIPAY');
    $notifyUrl = $input['notifyUrl'] ?? null;
    $returnUrl = $input['returnUrl'] ?? null;

    // 必填参数验证
    if (!$orderId) {
        jsonResponse(400, '缺少订单ID', null, 'MISSING_ORDER_ID');
    }
    if (!$userId) {
        jsonResponse(400, '缺少用户ID', null, 'MISSING_USER_ID');
    }
    if (!$amount || $amount <= 0) {
        jsonResponse(400, '金额无效', null, 'INVALID_AMOUNT');
    }

    // 支付方式映射
    $methodMapping = [
        'ALIPAY' => 'alipay',
        'WECHAT' => 'wechat',
        'UNIONPAY' => 'bank_card',
        'CREDIT_CARD' => 'credit_card'
    ];

    if (!isset($methodMapping[$paymentMethod])) {
        jsonResponse(400, '不支持的支付方式: ' . $paymentMethod, null, 'INVALID_PAYMENT_METHOD');
    }

    $internalMethod = $methodMapping[$paymentMethod];

    // 幂等性检查 - 检查是否已存在相同订单
    $db = new Database($config['database']);
    $existingMapping = $db->selectOne(
        "SELECT eom.*, t.transaction_no, t.status
         FROM external_order_mappings eom
         LEFT JOIN transactions t ON eom.transaction_id = t.id
         WHERE eom.external_order_id = ? AND eom.external_system = 'booking_panel'",
        [$orderId]
    );

    if ($existingMapping) {
        $logger->info("订单已存在(幂等): orderId=$orderId, paymentId={$existingMapping['payment_id']}");

        $baseUrl = $config['app']['base_url'] ?? 'http://localhost:8080';
        $paymentUrl = $baseUrl . '/payment.php?transaction_no=' . urlencode($existingMapping['transaction_no']);

        $responseData = [
            'paymentId' => $existingMapping['payment_id'],
            'paymentUrl' => $paymentUrl,
            'expireTime' => date('c', strtotime('+15 minutes')),
            'existing' => true
        ];

        if (in_array($paymentMethod, ['ALIPAY', 'WECHAT'])) {
            $responseData['qrCode'] = generateQrCode($paymentUrl);
        }

        jsonResponse(200, 'success', $responseData);
    }

    // 创建支付服务
    $paymentService = new PaymentService($config);

    // 创建支付订单
    $result = $paymentService->createPaymentOrder(
        (int)$userId,
        (float)$amount,
        $internalMethod,
        $description
    );

    if (!$result['success']) {
        $logger->warning("创建支付订单失败: " . ($result['error'] ?? '未知'));

        // 检查是否是风控拦截
        if (isset($result['risk_level'])) {
            jsonResponse(4003, '交易被风控拦截', null, 'RISK_CONTROL_BLOCKED');
        }

        jsonResponse(500, $result['error'] ?? '创建订单失败', null, 'CREATE_ORDER_FAILED');
    }

    // 生成支付ID
    $paymentId = generatePaymentId();

    // 保存外部订单关联
    $db = new Database($config['database']);
    $db->insert(
        "INSERT INTO external_order_mappings (transaction_id, external_order_id, external_system, callback_url, payment_id, return_url)
         VALUES (?, ?, 'booking_panel', ?, ?, ?)",
        [
            $result['transaction_id'],
            $orderId,
            $notifyUrl,
            $paymentId,
            $returnUrl
        ]
    );

    $logger->info("支付订单创建成功: paymentId=$paymentId, orderId=$orderId, transactionNo={$result['transaction_no']}");

    // 构建支付URL
    $baseUrl = $config['app']['base_url'] ?? 'http://localhost:8080';
    $paymentUrl = $baseUrl . '/payment.php?transaction_no=' . urlencode($result['transaction_no']);

    // 计算过期时间（15分钟后）
    $expireTime = date('c', strtotime('+15 minutes'));

    // 返回响应
    $responseData = [
        'paymentId' => $paymentId,
        'paymentUrl' => $paymentUrl,
        'expireTime' => $expireTime
    ];

    // 如果是扫码支付方式，生成二维码
    if (in_array($paymentMethod, ['ALIPAY', 'WECHAT'])) {
        $responseData['qrCode'] = generateQrCode($paymentUrl);
    }

    jsonResponse(200, 'success', $responseData);

} catch (Exception $e) {
    $logger->error("创建支付订单异常: " . $e->getMessage());
    jsonResponse(500, '服务器内部错误', null, 'INTERNAL_ERROR');
}
