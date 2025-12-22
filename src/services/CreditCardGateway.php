<?php
require_once __DIR__ . '/PaymentGateway.php';

/**
 * 信用卡支付网关模拟类
 */
class CreditCardGateway implements PaymentGateway {
    private array $config;
    private float $successRate;

    public function __construct(array $config) {
        $this->config = $config;
        $this->successRate = $config['success_rate'] ?? 0.88;
    }

    public function createPayment(array $orderData): array {
        usleep(rand(700000, 1800000));

        $signature = $this->generateSignature($orderData);

        $random = mt_rand() / mt_getrandmax();
        $success = $random < $this->successRate;

        if ($success) {
            return [
                'status' => 'success',
                'transaction_no' => $orderData['transaction_no'],
                'payment_no' => 'CREDIT_' . time() . rand(1000, 9999),
                'amount' => $orderData['amount'],
                'signature' => $signature,
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => '信用卡支付成功',
                'card_type' => $this->getRandomCardType()
            ];
        } else {
            $errorCodes = [
                'CARD_EXPIRED' => '信用卡已过期',
                'CVV_ERROR' => 'CVV验证失败',
                'CREDIT_LIMIT' => '超出信用额度',
                'CARD_BLOCKED' => '卡片被冻结',
                'ISSUER_DECLINED' => '发卡行拒绝交易'
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
        usleep(rand(150000, 550000));

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
        $signString = json_encode($data);
        return hash_hmac('sha512', $signString, $this->config['secret_key']);
    }

    private function getRandomCardType(): string {
        $cardTypes = ['VISA', 'MasterCard', 'American Express', 'UnionPay'];
        return $cardTypes[array_rand($cardTypes)];
    }
}
