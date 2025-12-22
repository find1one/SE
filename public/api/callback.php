<?php
/**
 * 支付回调API - 供外部系统(如火车票订单系统)调用
 *
 * 接口地址: POST /api/callback.php
 *
 * 请求格式:
 * {
 *     "orderId": 123,              // 外部系统订单ID
 *     "transactionNo": "TXN...",   // 本支付系统的交易流水号
 *     "payUrl": "https://...",     // 外部系统提供的支付链接(可选)
 *     "action": "notify"           // 动作: notify(支付成功通知), query(查询状态)
 * }
 *
 * 响应格式:
 * {
 *     "code": 200,
 *     "message": "success",
 *     "data": {
 *         "orderId": 123,
 *         "paymentStatus": "PAID",
 *         "transactionNo": "TXN..."
 *     }
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/services/PaymentService.php';
require_once __DIR__ . '/../../src/utils/Logger.php';

$config = require __DIR__ . '/../../config/config.php';
$logger = new Logger($config['logging']);

/**
 * 返回JSON响应
 */
function jsonResponse(int $code, string $message, array $data = []): void {
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 只接受POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, 'Method Not Allowed');
    }

    // 获取请求数据
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $logger->warning("无效的JSON请求: $rawInput");
        jsonResponse(400, '无效的请求格式');
    }

    $logger->info("收到外部回调请求: " . json_encode($input, JSON_UNESCAPED_UNICODE));

    $action = $input['action'] ?? 'notify';

    switch ($action) {
        case 'notify':
            // 支付成功回调通知
            handlePaymentNotify($input, $config, $logger);
            break;

        case 'query':
            // 查询支付状态
            handleQueryStatus($input, $config, $logger);
            break;

        case 'create':
            // 创建支付订单(供外部系统调用)
            handleCreatePayment($input, $config, $logger);
            break;

        default:
            jsonResponse(400, '不支持的操作类型: ' . $action);
    }

} catch (Exception $e) {
    $logger->error("API处理异常: " . $e->getMessage());
    jsonResponse(500, '服务器内部错误: ' . $e->getMessage());
}

/**
 * 处理支付成功通知
 */
function handlePaymentNotify(array $input, array $config, Logger $logger): void {
    $orderId = $input['orderId'] ?? null;
    $transactionNo = $input['transactionNo'] ?? null;
    $paymentStatus = $input['paymentStatus'] ?? 'PAID';

    if (!$orderId && !$transactionNo) {
        jsonResponse(400, '缺少订单ID或交易流水号');
    }

    $paymentService = new PaymentService($config);

    // 如果提供了交易流水号，查询我们系统的交易状态
    if ($transactionNo) {
        $result = $paymentService->queryTransactionStatus($transactionNo);

        if (!$result['success']) {
            jsonResponse(404, '交易不存在');
        }

        $logger->info("支付回调成功: orderId=$orderId, transactionNo=$transactionNo, status={$result['status']}");

        jsonResponse(200, 'success', [
            'orderId' => $orderId,
            'transactionNo' => $transactionNo,
            'paymentStatus' => strtoupper($result['status']) === 'SUCCESS' ? 'PAID' : strtoupper($result['status']),
            'amount' => $result['amount'] ?? 0,
            'paymentTime' => $result['payment_time'] ?? null
        ]);
    }

    // 仅有orderId的情况，记录外部订单的支付通知
    $logger->info("收到外部订单支付通知: orderId=$orderId, status=$paymentStatus");

    jsonResponse(200, 'success', [
        'orderId' => $orderId,
        'paymentStatus' => $paymentStatus,
        'received' => true
    ]);
}

/**
 * 处理支付状态查询
 */
function handleQueryStatus(array $input, array $config, Logger $logger): void {
    $transactionNo = $input['transactionNo'] ?? null;

    if (!$transactionNo) {
        jsonResponse(400, '缺少交易流水号');
    }

    $paymentService = new PaymentService($config);
    $result = $paymentService->queryTransactionStatus($transactionNo);

    if (!$result['success']) {
        jsonResponse(404, $result['error']);
    }

    $logger->info("查询交易状态: transactionNo=$transactionNo, status={$result['status']}");

    jsonResponse(200, 'success', [
        'transactionNo' => $result['transaction_no'],
        'status' => $result['status'],
        'paymentStatus' => strtoupper($result['status']) === 'SUCCESS' ? 'PAID' : strtoupper($result['status']),
        'amount' => $result['amount'],
        'paymentMethod' => $result['payment_method'],
        'createdAt' => $result['created_at'],
        'paymentTime' => $result['payment_time']
    ]);
}

/**
 * 处理创建支付请求(供外部系统调用)
 */
function handleCreatePayment(array $input, array $config, Logger $logger): void {
    $userId = $input['userId'] ?? null;
    $amount = $input['amount'] ?? null;
    $paymentMethod = $input['paymentMethod'] ?? 'alipay';
    $orderId = $input['orderId'] ?? null;  // 外部系统订单ID
    $description = $input['description'] ?? '';

    if (!$userId || !$amount) {
        jsonResponse(400, '缺少必要参数: userId, amount');
    }

    if ($amount <= 0) {
        jsonResponse(400, '金额必须大于0');
    }

    $validMethods = ['alipay', 'wechat', 'bank_card', 'credit_card'];
    if (!in_array($paymentMethod, $validMethods)) {
        jsonResponse(400, '不支持的支付方式: ' . $paymentMethod);
    }

    $paymentService = new PaymentService($config);

    // 创建支付订单
    $result = $paymentService->createPaymentOrder(
        (int)$userId,
        (float)$amount,
        $paymentMethod,
        $description ?: "外部订单#$orderId"
    );

    if (!$result['success']) {
        $logger->warning("创建支付订单失败: " . ($result['error'] ?? '未知错误'));
        jsonResponse(500, $result['error'] ?? '创建订单失败');
    }

    $logger->info("为外部系统创建支付订单: orderId=$orderId, transactionNo={$result['transaction_no']}");

    // 构建支付URL
    $baseUrl = $config['app']['base_url'] ?? 'http://localhost:8080';
    $payUrl = $baseUrl . '/payment.php?transaction_no=' . urlencode($result['transaction_no']);

    jsonResponse(200, 'success', [
        'orderId' => $orderId,
        'transactionId' => $result['transaction_id'],
        'transactionNo' => $result['transaction_no'],
        'amount' => $result['amount'],
        'paymentMethod' => $result['payment_method'],
        'payUrl' => $payUrl,
        'status' => 'PENDING'
    ]);
}
