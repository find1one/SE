<?php
/**
 * 查询支付状态接口
 * URL: GET /api/v1/payments/{paymentId}/status
 *
 * 请求参数:
 * - paymentId: 支付订单ID（路径参数，通过query string传递）
 *
 * 响应:
 * {
 *   "code": 200,
 *   "message": "success",
 *   "data": {
 *     "paymentId": "PAY-123456",
 *     "orderId": "ORD-001",
 *     "status": "SUCCESS",
 *     "amount": 299.50,
 *     "paidAmount": 299.50,
 *     "paymentMethod": "ALIPAY",
 *     "paidTime": "2024-12-25T10:30:00",
 *     "failReason": null
 *   }
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(405, 'Method Not Allowed', null, 'METHOD_NOT_ALLOWED');
    }

    // 获取paymentId（支持多种传参方式）
    $paymentId = $_GET['paymentId'] ?? $_GET['payment_id'] ?? null;

    // 也支持从PATH_INFO获取
    if (!$paymentId && isset($_SERVER['PATH_INFO'])) {
        $pathParts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
        if (count($pathParts) >= 1) {
            $paymentId = $pathParts[0];
        }
    }

    if (!$paymentId) {
        jsonResponse(400, '缺少支付ID', null, 'MISSING_PAYMENT_ID');
    }

    $logger->info("查询支付状态: paymentId=$paymentId");

    $db = new Database($config['database']);

    // 查询支付记录
    $mapping = $db->selectOne(
        "SELECT eom.*, t.amount, t.status as transaction_status, t.payment_method,
                t.payment_time, t.description, pd.error_message
         FROM external_order_mappings eom
         LEFT JOIN transactions t ON eom.transaction_id = t.id
         LEFT JOIN payment_details pd ON t.id = pd.transaction_id
         WHERE eom.payment_id = ?",
        [$paymentId]
    );

    if (!$mapping) {
        jsonResponse(404, '支付记录不存在', null, 'PAYMENT_NOT_FOUND');
    }

    // 状态映射: 本系统状态 -> 外部期望状态
    $statusMapping = [
        'pending' => 'PENDING',
        'processing' => 'PENDING',
        'success' => 'SUCCESS',
        'failed' => 'FAILED',
        'timeout' => 'CANCELLED',
        'cancelled' => 'CANCELLED'
    ];

    // 支付方式映射
    $methodMapping = [
        'alipay' => 'ALIPAY',
        'wechat' => 'WECHAT',
        'bank_card' => 'UNIONPAY',
        'credit_card' => 'CREDIT_CARD'
    ];

    $status = $statusMapping[$mapping['transaction_status']] ?? 'PENDING';
    $paymentMethod = $methodMapping[$mapping['payment_method']] ?? $mapping['payment_method'];

    $responseData = [
        'paymentId' => $paymentId,
        'orderId' => $mapping['external_order_id'],
        'status' => $status,
        'amount' => (float)$mapping['amount'],
        'paidAmount' => ($status === 'SUCCESS') ? (float)$mapping['amount'] : 0,
        'paymentMethod' => $paymentMethod,
        'paidTime' => $mapping['payment_time'] ? date('c', strtotime($mapping['payment_time'])) : null,
        'failReason' => ($status === 'FAILED') ? ($mapping['error_message'] ?? '支付失败') : null
    ];

    $logger->info("查询支付状态成功: paymentId=$paymentId, status=$status");

    jsonResponse(200, 'success', $responseData);

} catch (Exception $e) {
    $logger->error("查询支付状态异常: " . $e->getMessage());
    jsonResponse(500, '服务器内部错误', null, 'INTERNAL_ERROR');
}
