<?php
require_once __DIR__ . '/../models/Database.php';

/**
 * 风险检测服务类
 */
class FraudDetectionService {
    private array $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * 检测交易风险
     */
    public function checkTransaction(int $userId, float $amount, string $ipAddress): array {
        $risks = [];

        // 1. 检查交易金额
        if ($amount > $this->config['max_amount_per_transaction']) {
            $risks[] = [
                'type' => 'AMOUNT_EXCEED',
                'level' => 'high',
                'description' => "交易金额超过限制: ￥{$amount}"
            ];
        }

        // 2. 检查频率限制
        $hourlyCount = $this->getHourlyTransactionCount($userId);
        if ($hourlyCount >= $this->config['max_transactions_per_hour']) {
            $risks[] = [
                'type' => 'FREQUENCY_EXCEED',
                'level' => 'high',
                'description' => "小时交易次数超限: {$hourlyCount}次"
            ];
        }

        // 3. 检查失败次数
        $failedCount = $this->getRecentFailedCount($userId);
        if ($failedCount >= $this->config['max_failed_attempts']) {
            $risks[] = [
                'type' => 'MULTIPLE_FAILURES',
                'level' => 'medium',
                'description' => "近期失败次数过多: {$failedCount}次"
            ];
        }

        // 4. 检查IP异常
        if ($this->isAbnormalIP($userId, $ipAddress)) {
            $risks[] = [
                'type' => 'ABNORMAL_IP',
                'level' => 'medium',
                'description' => "检测到异常IP地址: {$ipAddress}"
            ];
        }

        // 5. 检查小额高频
        if ($this->isSmallAmountHighFrequency($userId, $amount)) {
            $risks[] = [
                'type' => 'SMALL_HIGH_FREQ',
                'level' => 'low',
                'description' => '小额高频交易模式'
            ];
        }

        return $this->generateRiskReport($risks);
    }

    /**
     * 生成风险报告
     */
    private function generateRiskReport(array $risks): array {
        if (empty($risks)) {
            return [
                'risk_level' => 'low',
                'risk_type' => 'NONE',
                'description' => '无风险',
                'action' => 'allowed',
                'risks' => []
            ];
        }

        // 计算最高风险等级
        $levels = array_column($risks, 'level');
        $maxLevel = 'low';

        if (in_array('critical', $levels)) {
            $maxLevel = 'critical';
        } elseif (in_array('high', $levels)) {
            $maxLevel = 'high';
        } elseif (in_array('medium', $levels)) {
            $maxLevel = 'medium';
        }

        // 确定处理动作
        $action = 'allowed';
        if ($maxLevel === 'critical') {
            $action = 'blocked';
        } elseif ($maxLevel === 'high') {
            $action = 'blocked';
        } elseif ($maxLevel === 'medium') {
            $action = 'manual_review';
        }

        return [
            'risk_level' => $maxLevel,
            'risk_type' => $risks[0]['type'],
            'description' => implode('; ', array_column($risks, 'description')),
            'action' => $action,
            'risks' => $risks
        ];
    }

    /**
     * 获取小时内交易次数
     */
    private function getHourlyTransactionCount(int $userId): int {
        try {
            $config = require __DIR__ . '/../../config/config.php';
            $db = new Database($config['database']);

            $result = $db->selectOne(
                "SELECT COUNT(*) as count FROM transactions
                 WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                [$userId]
            );

            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 获取近期失败次数
     */
    private function getRecentFailedCount(int $userId): int {
        try {
            $config = require __DIR__ . '/../../config/config.php';
            $db = new Database($config['database']);

            $result = $db->selectOne(
                "SELECT COUNT(*) as count FROM transactions
                 WHERE user_id = ? AND status = 'failed'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                [$userId]
            );

            return (int)($result['count'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * 检查IP是否异常
     */
    private function isAbnormalIP(int $userId, string $ipAddress): bool {
        try {
            $config = require __DIR__ . '/../../config/config.php';
            $db = new Database($config['database']);

            // 获取用户常用IP
            $commonIPs = $db->select(
                "SELECT DISTINCT pd.ip_address
                 FROM payment_details pd
                 JOIN transactions t ON pd.transaction_id = t.id
                 WHERE t.user_id = ? AND t.status = 'success'
                 ORDER BY pd.created_at DESC LIMIT 5",
                [$userId]
            );

            if (empty($commonIPs)) {
                return false; // 新用户
            }

            $ips = array_column($commonIPs, 'ip_address');
            return !in_array($ipAddress, $ips);

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 检查小额高频
     */
    private function isSmallAmountHighFrequency(int $userId, float $amount): bool {
        if ($amount > 100) {
            return false; // 金额不算小
        }

        $count = $this->getHourlyTransactionCount($userId);
        return $count >= 3; // 小时内3次以上
    }
}
