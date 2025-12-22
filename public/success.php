<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/services/PaymentService.php';

$config = require __DIR__ . '/../config/config.php';
$paymentService = new PaymentService($config);

$transactionNo = $_GET['transaction_no'] ?? '';

if (empty($transactionNo)) {
    header('Location: index.php');
    exit;
}

$transactionInfo = $paymentService->queryTransactionStatus($transactionNo);

if (!$transactionInfo['success']) {
    header('Location: index.php');
    exit;
}

$transaction = $transactionInfo;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付成功</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>💳 支付交易管理系统</h1>
            <nav>
                <a href="index.php">创建订单</a>
                <a href="history.php">交易历史</a>
                <a href="query.php">查询订单</a>
            </nav>
        </header>

        <main>
            <div class="success-page">
                <div class="success-icon">✓</div>
                <h2>支付成功！</h2>
                <p class="success-message">您的订单已完成支付，感谢使用</p>

                <div class="transaction-details">
                    <div class="detail-item">
                        <span class="detail-label">交易流水号</span>
                        <span class="detail-value"><?= htmlspecialchars($transaction['transaction_no']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">支付金额</span>
                        <span class="detail-value highlight">￥<?= number_format($transaction['amount'], 2) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">支付方式</span>
                        <span class="detail-value">
                            <?php
                            $methods = [
                                'alipay' => '支付宝',
                                'wechat' => '微信支付',
                                'bank_card' => '银行卡',
                                'credit_card' => '信用卡'
                            ];
                            echo $methods[$transaction['payment_method']] ?? $transaction['payment_method'];
                            ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">支付时间</span>
                        <span class="detail-value"><?= htmlspecialchars($transaction['payment_time'] ?? $transaction['created_at']) ?></span>
                    </div>
                </div>

                <div class="notification-info">
                    <p>📧 支付凭证已发送至您的邮箱</p>
                    <p>📱 短信通知已发送至您的手机</p>
                </div>

                <div class="action-buttons">
                    <a href="index.php" class="btn btn-primary">继续支付</a>
                    <a href="history.php" class="btn btn-secondary">查看记录</a>
                    <a href="query.php" class="btn btn-secondary">查询订单</a>
                </div>
            </div>
        </main>

        <footer>
            <p>&copy; 2024 支付交易管理系统 | 仅供学习演示使用</p>
        </footer>
    </div>
</body>
</html>
