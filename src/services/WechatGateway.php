<?php
require_once __DIR__ . '/PaymentGateway.php';

/**
 * 微信支付网关模拟类
 */
class WechatGateway implements PaymentGateway {
    private array $config;
    private float $successRate;

    public function __construct(array $config) {
        $this->config = $config;
        $this->successRate = $config['success_rate'] ?? 0.80;
    }

    public function createPayment(array $orderData): array {
        usleep(rand(600000, 1600000));

        $signature = $this->generateSignature($orderData);

        $random = mt_rand() / mt_getrandmax();
        $success = $random < $this->successRate;

        if ($success) {
            return [
                'status' => 'success',
                'transaction_no' => $orderData['transaction_no'],
                'payment_no' => 'WECHAT_' . time() . rand(1000, 9999),
                'amount' => $orderData['amount'],
                'signature' => $signature,
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => '微信支付成功'
            ];
        } else {
            $errorCodes = [
                'USER_CANCEL' => '用户取消支付',
                'BALANCE_NOT_ENOUGH' => '余额不足',
                'SYSTEM_ERROR' => '系统繁忙',
                'AUTH_FAILED' => '认证失败'
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
        $signString = '';
        foreach ($data as $key => $value) {
            if ($value !== '' && $key !== 'signature') {
                $signString .= $key . '=' . $value . '&';
            }
        }
        $signString .= 'key=' . $this->config['api_key'];
        return strtoupper(md5($signString));
    }
}
