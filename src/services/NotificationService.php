<?php
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../utils/Logger.php';

/**
 * 通知服务类 - 处理邮件和短信通知（模拟）
 */
class NotificationService {
    private array $config;
    private Logger $logger;

    public function __construct(array $config) {
        $this->config = $config;
        $this->logger = new Logger([
            'enabled' => true,
            'path' => __DIR__ . '/../../logs/',
            'level' => 'info'
        ]);
    }

    /**
     * 发送支付成功通知
     */
    public function sendPaymentSuccessNotification(int $userId, string $transactionNo, float $amount): void {
        $user = $this->getUserInfo($userId);

        if (!$user) {
            return;
        }

        // 发送邮件
        if ($this->config['email']['enabled'] && $user['email']) {
            $emailContent = $this->generateSuccessEmailContent($transactionNo, $amount);
            $this->sendEmail($user['email'], '支付成功通知', $emailContent, $transactionNo);
        }

        // 发送短信
        if ($this->config['sms']['enabled'] && $user['phone']) {
            $smsContent = "您的订单{$transactionNo}已支付成功，金额￥{$amount}元。";
            $this->sendSMS($user['phone'], $smsContent, $transactionNo);
        }
    }

    /**
     * 发送支付失败通知
     */
    public function sendPaymentFailedNotification(int $userId, string $transactionNo, string $reason): void {
        $user = $this->getUserInfo($userId);

        if (!$user || !$this->config['email']['enabled']) {
            return;
        }

        $emailContent = $this->generateFailedEmailContent($transactionNo, $reason);
        $this->sendEmail($user['email'], '支付失败提醒', $emailContent, $transactionNo);
    }

    /**
     * 发送支付超时提醒
     */
    public function sendPaymentTimeoutNotification(int $userId, string $transactionNo): void {
        $user = $this->getUserInfo($userId);

        if (!$user) {
            return;
        }

        // 发送邮件
        if ($this->config['email']['enabled'] && $user['email']) {
            $emailContent = $this->generateTimeoutEmailContent($transactionNo);
            $this->sendEmail($user['email'], '订单超时提醒', $emailContent, $transactionNo);
        }

        // 发送短信
        if ($this->config['sms']['enabled'] && $user['phone']) {
            $smsContent = "您的订单{$transactionNo}已超时未支付，请尽快完成支付。";
            $this->sendSMS($user['phone'], $smsContent, $transactionNo);
        }
    }

    /**
     * 模拟发送邮件
     */
    private function sendEmail(string $email, string $subject, string $content, string $transactionNo): void {
        // 模拟发送延迟
        usleep(rand(100000, 300000));

        // 模拟发送结果（90%成功率）
        $success = (mt_rand() / mt_getrandmax()) < 0.9;

        if ($success) {
            $this->logger->info("邮件发送成功: {$email}, 主题: {$subject}");
            $this->recordNotification($transactionNo, 'email', $email, $content, 'sent');
        } else {
            $this->logger->warning("邮件发送失败: {$email}");
            $this->recordNotification($transactionNo, 'email', $email, $content, 'failed', '发送失败');
        }
    }

    /**
     * 模拟发送短信
     */
    private function sendSMS(string $phone, string $content, string $transactionNo): void {
        // 模拟发送延迟
        usleep(rand(50000, 200000));

        // 模拟发送结果（85%成功率）
        $success = (mt_rand() / mt_getrandmax()) < 0.85;

        if ($success) {
            $this->logger->info("短信发送成功: {$phone}");
            $this->recordNotification($transactionNo, 'sms', $phone, $content, 'sent');
        } else {
            $this->logger->warning("短信发送失败: {$phone}");
            $this->recordNotification($transactionNo, 'sms', $phone, $content, 'failed', '发送失败');
        }
    }

    /**
     * 记录通知
     */
    private function recordNotification(string $transactionNo, string $type, string $recipient,
                                       string $content, string $status, string $error = null): void {
        try {
            $config = require __DIR__ . '/../../config/config.php';
            $db = new Database($config['database']);

            $transaction = $db->selectOne(
                "SELECT id FROM transactions WHERE transaction_no = ?",
                [$transactionNo]
            );

            if ($transaction) {
                $db->insert(
                    "INSERT INTO notifications (transaction_id, type, recipient, content, status, sent_at, error_message)
                     VALUES (?, ?, ?, ?, ?, NOW(), ?)",
                    [$transaction['id'], $type, $recipient, $content, $status, $error]
                );
            }
        } catch (Exception $e) {
            $this->logger->error("记录通知失败: " . $e->getMessage());
        }
    }

    /**
     * 获取用户信息
     */
    private function getUserInfo(int $userId): ?array {
        try {
            $config = require __DIR__ . '/../../config/config.php';
            $db = new Database($config['database']);
            return $db->selectOne("SELECT * FROM users WHERE id = ?", [$userId]);
        } catch (Exception $e) {
            $this->logger->error("获取用户信息失败: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 生成成功邮件内容
     */
    private function generateSuccessEmailContent(string $transactionNo, float $amount): string {
        return <<<HTML
<html>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #28a745;">支付成功通知</h2>
    <p>尊敬的用户，您的订单已支付成功！</p>
    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <p><strong>交易流水号：</strong>{$transactionNo}</p>
        <p><strong>支付金额：</strong>￥{$amount}元</p>
        <p><strong>支付时间：</strong>{{date('Y-m-d H:i:s')}}</p>
    </div>
    <p>感谢您的使用！</p>
    <hr>
    <p style="color: #666; font-size: 12px;">此邮件由系统自动发送，请勿回复。</p>
</body>
</html>
HTML;
    }

    /**
     * 生成失败邮件内容
     */
    private function generateFailedEmailContent(string $transactionNo, string $reason): string {
        return <<<HTML
<html>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #dc3545;">支付失败通知</h2>
    <p>尊敬的用户，您的订单支付失败。</p>
    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <p><strong>交易流水号：</strong>{$transactionNo}</p>
        <p><strong>失败原因：</strong>{$reason}</p>
    </div>
    <p>建议您检查支付方式后重新尝试，或选择其他支付方式。</p>
    <hr>
    <p style="color: #666; font-size: 12px;">此邮件由系统自动发送，请勿回复。</p>
</body>
</html>
HTML;
    }

    /**
     * 生成超时邮件内容
     */
    private function generateTimeoutEmailContent(string $transactionNo): string {
        return <<<HTML
<html>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #ffc107;">订单超时提醒</h2>
    <p>尊敬的用户，您的订单已超时未支付。</p>
    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <p><strong>交易流水号：</strong>{$transactionNo}</p>
    </div>
    <p>请尽快完成支付，或重新下单。</p>
    <hr>
    <p style="color: #666; font-size: 12px;">此邮件由系统自动发送，请勿回复。</p>
</body>
</html>
HTML;
    }
}
