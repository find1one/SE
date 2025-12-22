<?php
require_once __DIR__ . '/AlipayGateway.php';
require_once __DIR__ . '/WechatGateway.php';
require_once __DIR__ . '/BankCardGateway.php';
require_once __DIR__ . '/CreditCardGateway.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/FraudDetectionService.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * 支付服务类 - 核心业务逻辑
 */
class PaymentService {
    private Database $db;
    private array $config;
    private NotificationService $notificationService;
    private FraudDetectionService $fraudDetection;
    private Logger $logger;

    public function __construct(array $config) {
        $this->config = $config;
        $this->db = new Database($config['database']);
        $this->notificationService = new NotificationService($config['notification']);
        $this->fraudDetection = new FraudDetectionService($config['security']['fraud_detection']);
        $this->logger = new Logger($config['logging']);
    }

    /**
     * 创建支付订单
     */
    public function createPaymentOrder(int $userId, float $amount, string $paymentMethod, string $description = ''): array {
        try {
            // 风险检测
            $fraudCheck = $this->fraudDetection->checkTransaction($userId, $amount, $_SERVER['REMOTE_ADDR'] ?? '');

            if ($fraudCheck['risk_level'] === 'critical' || $fraudCheck['risk_level'] === 'high') {
                $this->logFraudAttempt(null, $userId, $fraudCheck);
                return [
                    'success' => false,
                    'error' => '交易存在风险，已被系统拦截',
                    'risk_level' => $fraudCheck['risk_level']
                ];
            }

            // 生成唯一交易流水号
            $transactionNo = $this->generateTransactionNo();

            // 插入交易记录
            $sql = "INSERT INTO transactions (transaction_no, user_id, amount, payment_method, status, description)
                    VALUES (?, ?, ?, ?, 'pending', ?)";

            $transactionId = $this->db->insert($sql, [
                $transactionNo,
                $userId,
                $amount,
                $paymentMethod,
                $description
            ]);

            // 记录支付详情
            $this->savePaymentDetails($transactionId, [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);

            $this->logger->info("创建支付订单: $transactionNo, 用户: $userId, 金额: $amount");

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'transaction_no' => $transactionNo,
                'amount' => $amount,
                'payment_method' => $paymentMethod
            ];

        } catch (Exception $e) {
            $this->logger->error("创建订单失败: " . $e->getMessage());
            return [
                'success' => false,
                'error' => '创建订单失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 执行支付
     */
    public function processPayment(string $transactionNo, array $paymentData = []): array {
        try {
            // 获取交易信息
            $transaction = $this->getTransactionByNo($transactionNo);

            if (!$transaction) {
                return ['success' => false, 'error' => '交易不存在'];
            }

            if ($transaction['status'] !== 'pending') {
                return ['success' => false, 'error' => '交易状态异常'];
            }

            // 更新状态为处理中
            $this->updateTransactionStatus($transaction['id'], 'processing');

            // 获取对应的支付网关
            $gateway = $this->getPaymentGateway($transaction['payment_method']);

            // 准备订单数据
            $orderData = [
                'transaction_no' => $transactionNo,
                'amount' => $transaction['amount'],
                'description' => $transaction['description'],
                'timestamp' => time()
            ];

            // 调用支付网关
            $this->logger->info("开始支付: $transactionNo, 方式: {$transaction['payment_method']}");
            $result = $gateway->createPayment($orderData);

            // 更新支付详情
            $this->updatePaymentDetails($transaction['id'], $result);

            if ($result['status'] === 'success') {
                // 支付成功
                $this->updateTransactionStatus($transaction['id'], 'success');
                $this->db->update(
                    "UPDATE transactions SET payment_time = NOW() WHERE id = ?",
                    [$transaction['id']]
                );

                // 发送成功通知
                $this->notificationService->sendPaymentSuccessNotification(
                    $transaction['user_id'],
                    $transactionNo,
                    $transaction['amount']
                );

                $this->logger->info("支付成功: $transactionNo");

                return [
                    'success' => true,
                    'message' => '支付成功',
                    'transaction_no' => $transactionNo,
                    'payment_no' => $result['payment_no'] ?? '',
                    'amount' => $transaction['amount']
                ];

            } else {
                // 支付失败
                $this->updateTransactionStatus($transaction['id'], 'failed');

                // 增加重试计数
                $this->incrementRetryCount($transaction['id']);

                $this->logger->warning("支付失败: $transactionNo, 原因: " . ($result['error_message'] ?? '未知'));

                return [
                    'success' => false,
                    'error' => $result['error_message'] ?? '支付失败',
                    'error_code' => $result['error_code'] ?? 'UNKNOWN',
                    'can_retry' => $this->canRetry($transaction['id'])
                ];
            }

        } catch (Exception $e) {
            $this->logger->error("支付处理异常: " . $e->getMessage());
            if (isset($transaction)) {
                $this->updateTransactionStatus($transaction['id'], 'failed');
            }
            return [
                'success' => false,
                'error' => '支付处理异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 查询交易状态
     */
    public function queryTransactionStatus(string $transactionNo): array {
        $transaction = $this->getTransactionByNo($transactionNo);

        if (!$transaction) {
            return ['success' => false, 'error' => '交易不存在'];
        }

        return [
            'success' => true,
            'transaction_no' => $transaction['transaction_no'],
            'status' => $transaction['status'],
            'amount' => $transaction['amount'],
            'payment_method' => $transaction['payment_method'],
            'created_at' => $transaction['created_at'],
            'payment_time' => $transaction['payment_time']
        ];
    }

    /**
     * 获取用户交易历史
     */
    public function getUserTransactions(int $userId, int $limit = 10, int $offset = 0): array {
        $sql = "SELECT * FROM transactions WHERE user_id = ?
                ORDER BY created_at DESC LIMIT ? OFFSET ?";

        $transactions = $this->db->select($sql, [$userId, $limit, $offset]);

        return [
            'success' => true,
            'transactions' => $transactions,
            'total' => $this->db->selectOne(
                "SELECT COUNT(*) as count FROM transactions WHERE user_id = ?",
                [$userId]
            )['count']
        ];
    }

    /**
     * 检查交易超时
     */
    public function checkTimeoutTransactions(): int {
        $timeout = $this->config['payment']['timeout'];
        $sql = "UPDATE transactions
                SET status = 'timeout'
                WHERE status IN ('pending', 'processing')
                AND TIMESTAMPDIFF(SECOND, created_at, NOW()) > ?";

        $affected = $this->db->update($sql, [$timeout]);

        if ($affected > 0) {
            $this->logger->info("处理超时订单: $affected 笔");

            // 发送超时提醒
            $timeoutTransactions = $this->db->select(
                "SELECT * FROM transactions WHERE status = 'timeout'
                 AND updated_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
            );

            foreach ($timeoutTransactions as $transaction) {
                $this->notificationService->sendPaymentTimeoutNotification(
                    $transaction['user_id'],
                    $transaction['transaction_no']
                );
            }
        }

        return $affected;
    }

    // ========== 私有辅助方法 ==========

    private function getPaymentGateway(string $paymentMethod): PaymentGateway {
        $gateways = [
            'alipay' => new AlipayGateway($this->config['payment_gateways']['alipay']),
            'wechat' => new WechatGateway($this->config['payment_gateways']['wechat']),
            'bank_card' => new BankCardGateway($this->config['payment_gateways']['bank_card']),
            'credit_card' => new CreditCardGateway($this->config['payment_gateways']['credit_card'])
        ];

        if (!isset($gateways[$paymentMethod])) {
            throw new Exception("不支持的支付方式: $paymentMethod");
        }

        return $gateways[$paymentMethod];
    }

    private function generateTransactionNo(): string {
        return 'TXN' . date('YmdHis') . rand(100000, 999999);
    }

    private function getTransactionByNo(string $transactionNo): ?array {
        return $this->db->selectOne(
            "SELECT * FROM transactions WHERE transaction_no = ?",
            [$transactionNo]
        );
    }

    private function updateTransactionStatus(int $transactionId, string $status): void {
        $this->db->update(
            "UPDATE transactions SET status = ?, updated_at = NOW() WHERE id = ?",
            [$status, $transactionId]
        );
    }

    private function savePaymentDetails(int $transactionId, array $details): void {
        $this->db->insert(
            "INSERT INTO payment_details (transaction_id, ip_address, user_agent) VALUES (?, ?, ?)",
            [$transactionId, $details['ip_address'], $details['user_agent']]
        );
    }

    private function updatePaymentDetails(int $transactionId, array $result): void {
        $response = json_encode($result, JSON_UNESCAPED_UNICODE);
        $signature = $result['signature'] ?? '';
        $errorMessage = $result['error_message'] ?? null;

        $this->db->update(
            "UPDATE payment_details
             SET payment_gateway_response = ?, signature = ?, error_message = ?
             WHERE transaction_id = ?",
            [$response, $signature, $errorMessage, $transactionId]
        );
    }

    private function incrementRetryCount(int $transactionId): void {
        $this->db->update(
            "UPDATE payment_details SET retry_count = retry_count + 1 WHERE transaction_id = ?",
            [$transactionId]
        );
    }

    private function canRetry(int $transactionId): bool {
        $details = $this->db->selectOne(
            "SELECT retry_count FROM payment_details WHERE transaction_id = ?",
            [$transactionId]
        );

        return ($details['retry_count'] ?? 0) < $this->config['payment']['retry_times'];
    }

    private function logFraudAttempt(?int $transactionId, int $userId, array $fraudCheck): void {
        $this->db->insert(
            "INSERT INTO fraud_logs (transaction_id, user_id, risk_level, risk_type, description, ip_address, action_taken)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $transactionId,
                $userId,
                $fraudCheck['risk_level'],
                $fraudCheck['risk_type'],
                $fraudCheck['description'],
                $_SERVER['REMOTE_ADDR'] ?? '',
                $fraudCheck['action']
            ]
        );
    }
}
