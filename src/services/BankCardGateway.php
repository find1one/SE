<?php
require_once __DIR__ . '/PaymentGateway.php';

/**
 * 银行卡支付网关模拟类
 */
class BankCardGateway implements PaymentGateway {
    private array $config;
    private float $successRate;

    public function __construct(array $config) {
        $this->config = $config;
        $this->successRate = $config['success_rate'] ?? 0.90;
    }

    public function createPayment(array $orderData): array {
        usleep(rand(800000, 2000000)); // 银行卡支付通常较慢

        $signature = $this->generateSignature($orderData);

        $random = mt_rand() / mt_getrandmax();
        $success = $random < $this->successRate;

        if ($success) {
            return [
                'status' => 'success',
                'transaction_no' => $orderData['transaction_no'],
                'payment_no' => 'BANK_' . time() . rand(1000, 9999),
                'amount' => $orderData['amount'],
                'signature' => $signature,
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => '银行卡支付成功',
                'bank_name' => $this->getRandomBankName()
            ];
        } else {
            $errorCodes = [
                'CARD_INVALID' => '银行卡无效',
                'INSUFFICIENT_FUNDS' => '余额不足',
                'CARD_LOCKED' => '银行卡已冻结',
                'PIN_ERROR' => '密码错误',
                'TRANSACTION_LIMIT' => '超过交易限额'
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
        usleep(rand(200000, 600000));

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
        $signString = json_encode($data, JSON_UNESCAPED_UNICODE);
        return hash_hmac('sha256', $signString, $this->config['secret_key']);
    }

    private function getRandomBankName(): string {
        $banks = ['工商银行', '建设银行', '农业银行', '中国银行', '招商银行', '交通银行'];
        return $banks[array_rand($banks)];
    }
}
