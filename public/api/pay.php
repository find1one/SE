<?php
/**
 * 统一支付API入口 - 供外部系统(如火车票订单系统)调用
 *
 * 本支付系统提供的对外接口:
 *
 * 1. POST /api/pay.php - 创建支付订单
 *    请求: { "userId": 1, "amount": 100.00, "paymentMethod": "alipay", "orderId": 123, "description": "火车票" }
 *    响应: { "code": 200, "data": { "transactionNo": "TXN...", "payUrl": "http://..." } }
 *
 * 2. GET /api/pay.php?action=query&transactionNo=TXN... - 查询支付状态
 *    响应: { "code": 200, "data": { "status": "success", "paymentStatus": "PAID" } }
 *
 * 火车票订单系统对接流程:
 * 1. 用户在火车票系统下单后，火车票系统调用本接口创建支付订单
 * 2. 获取返回的 payUrl，跳转用户到支付页面
 * 3. 用户完成支付后，本系统会回调火车票系统的 /api/order/callback/notify 接口
 * 4. 火车票系统也可主动调用查询接口确认支付状态
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/services/PaymentService.php';
require_once __DIR__ . '/../../src/utils/Logger.php';

$config = require __DIR__ . '/../../config/config.php';
$logger = new Logger($config['logging']);

function jsonResponse(int $code, string $message, array $data = []): void {
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'create';

    if ($method === 'GET') {
        // 查询支付状态
        handleQuery($config, $logger);
    } elseif ($method === 'POST') {
        // 创建支付订单
        handleCreate($config, $logger);
    } else {
        jsonResponse(405, 'Method Not Allowed');
    }

} catch (Exception $e) {
    $logger->error("支付API异常: " . $e->getMessage());
    jsonResponse(500, '服务器错误: ' . $e->getMessage());
}

/**
 * 处理查询请求
 */
function handleQuery(array $config, Logger $logger): void {
    $transactionNo = $_GET['transactionNo'] ?? $_GET['transaction_no'] ?? null;

    if (!$transactionNo) {
        jsonResponse(400, '缺少交易流水号');
    }

    $paymentService = new PaymentService($config);
    $result = $paymentService->queryTransactionStatus($transactionNo);

    if (!$result['success']) {
        jsonResponse(404, $result['error']);
    }

    // 状态映射: 本系统状态 -> 火车票系统期望的状态
    $statusMap = [
        'pending' => 'UNPAID',
        'processing' => 'UNPAID',
        'success' => 'PAID',
        'failed' => 'UNPAID',
        'timeout' => 'EXPIRED',
        'cancelled' => 'CANCELLED'
    ];

    $logger->info("查询支付状态: $transactionNo -> {$result['status']}");

    jsonResponse(200, 'success', [
        'transactionNo' => $result['transaction_no'],
        'status' => $result['status'],
        'paymentStatus' => $statusMap[$result['status']] ?? 'UNPAID',
        'amount' => $result['amount'],
        'paymentMethod' => $result['payment_method'],
        'paymentTime' => $result['payment_time']
    ]);
}

/**
 * 处理创建支付订单请求
 */
function handleCreate(array $config, Logger $logger): void {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(400, '无效的JSON格式');
    }

    $logger->info("收到创建支付请求: " . $rawInput);

    // 参数验证
    $userId = $input['userId'] ?? null;
    $amount = $input['amount'] ?? null;
    $paymentMethod = $input['paymentMethod'] ?? $input['payment_method'] ?? 'alipay';
    $orderId = $input['orderId'] ?? $input['order_id'] ?? null;
    $description = $input['description'] ?? '';
    $callbackUrl = $input['callbackUrl'] ?? $input['callback_url'] ?? null;

    if (!$userId) {
        jsonResponse(400, '缺少用户ID (userId)');
    }

    if (!$amount || $amount <= 0) {
        jsonResponse(400, '金额无效 (amount)');
    }

    // 验证支付方式
    $validMethods = ['alipay', 'wechat', 'bank_card', 'credit_card'];
    $paymentMethod = strtolower($paymentMethod);
    if (!in_array($paymentMethod, $validMethods)) {
        jsonResponse(400, '不支持的支付方式: ' . $paymentMethod . '，支持: ' . implode(', ', $validMethods));
    }

    $paymentService = new PaymentService($config);

    // 构建描述信息
    $orderDesc = $description;
    if ($orderId && !$description) {
        $orderDesc = "订单支付 #$orderId";
    }

    // 创建支付订单
    $result = $paymentService->createPaymentOrder(
        (int)$userId,
        (float)$amount,
        $paymentMethod,
        $orderDesc
    );

    if (!$result['success']) {
        $logger->warning("创建支付订单失败: " . ($result['error'] ?? '未知'));
        jsonResponse(500, $result['error'] ?? '创建订单失败');
    }

    $logger->info("支付订单创建成功: transactionNo={$result['transaction_no']}, orderId=$orderId");

    // 构建支付页面URL
    $baseUrl = $config['app']['base_url'] ?? 'http://localhost:8080';
    $payUrl = $baseUrl . '/payment.php?transaction_no=' . urlencode($result['transaction_no']);

    // 如果有回调地址，保存起来(实际项目中应存入数据库)
    if ($callbackUrl) {
        $logger->info("支付完成后将回调: $callbackUrl");
    }

    jsonResponse(200, 'success', [
        'orderId' => $orderId,
        'transactionNo' => $result['transaction_no'],
        'transactionId' => $result['transaction_id'],
        'amount' => $result['amount'],
        'paymentMethod' => $result['payment_method'],
        'payUrl' => $payUrl,
        'status' => 'PENDING',
        'paymentStatus' => 'UNPAID'
    ]);
}
