<?php
require_once __DIR__ . '/PaymentGateway.php';

/**
 * 支付宝支付网关模拟类
 */
class AlipayGateway implements PaymentGateway {
    private array $config;
    private float $successRate;

    public function __construct(array $config) {
        $this->config = $config;
        $this->successRate = $config['success_rate'] ?? 0.85;
    }

    public function createPayment(array $orderData): array {
        // 模拟网络延迟
        usleep(rand(500000, 1500000)); // 0.5-1.5秒

        // 生成签名
        $signature = $this->generateSignature($orderData);

        // 模拟支付结果
        $random = mt_rand() / mt_getrandmax();
        $success = $random < $this->successRate;

        if ($success) {
            return [
                'status' => 'success',
                'transaction_no' => $orderData['transaction_no'],
                'payment_no' => 'ALIPAY_' . time() . rand(1000, 9999),
                'amount' => $orderData['amount'],
                'signature' => $signature,
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => '支付成功'
            ];
        } else {
            $errorCodes = [
                'INSUFFICIENT_BALANCE' => '余额不足',
                'PAYMENT_TIMEOUT' => '支付超时',
                'NETWORK_ERROR' => '网络异常',
                'INVALID_ACCOUNT' => '账户异常'
            ];

            $errorCode = array_rand($errorCodes);

            return [
                'status' => 'failed',
                'transaction_no' => $orderData['transaction_no'],
                'error_code' => $errorCode,
                'error_message' => $errorCodes[$errorCode],
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    public function queryPayment(string $transactionNo): array {
        // 模拟查询延迟
        usleep(rand(100000, 500000));

        return [
            'transaction_no' => $transactionNo,
            'status' => 'success',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    public function verifySignature(array $data, string $signature): bool {
        $expectedSignature = $this->generateSignature($data);
        return hash_equals($expectedSignature, $signature);
    }

    public function generateSignature(array $data): string {
        ksort($data);
        $signString = http_build_query($data);
        return hash_hmac('sha256', $signString, $this->config['private_key']);
    }
}
