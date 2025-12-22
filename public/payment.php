<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/services/PaymentService.php';

$config = require __DIR__ . '/../config/config.php';
$paymentService = new PaymentService($config);

// 获取交易号
$transactionNo = $_GET['transaction_no'] ?? $_POST['transaction_no'] ?? '';

if (empty($transactionNo)) {
    header('Location: index.php');
    exit;
}

// 获取交易信息
$transactionInfo = $paymentService->queryTransactionStatus($transactionNo);

if (!$transactionInfo['success']) {
    $error = $transactionInfo['error'];
    header('Location: index.php');
    exit;
}

$transaction = $transactionInfo;
$message = '';
$messageType = '';

// 处理支付请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    $result = $paymentService->processPayment($transactionNo);

    if ($result['success']) {
        header('Location: success.php?transaction_no=' . $transactionNo);
        exit;
    } else {
        $message = $result['error'];
        $messageType = 'error';

        // 重新获取交易状态
        $transactionInfo = $paymentService->queryTransactionStatus($transactionNo);
        $transaction = $transactionInfo;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付确认 - <?= htmlspecialchars($transactionNo) ?></title>
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
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
            <?php endif; ?>

            <div class="payment-container">
                <div class="payment-info">
                    <h2>订单信息</h2>
                    <div class="info-row">
                        <span class="label">交易流水号:</span>
                        <span class="value"><?= htmlspecialchars($transaction['transaction_no']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">支付金额:</span>
                        <span class="value amount">￥<?= number_format($transaction['amount'], 2) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">支付方式:</span>
                        <span class="value">
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
                    <div class="info-row">
                        <span class="label">订单状态:</span>
                        <span class="value">
                            <span class="status status-<?= $transaction['status'] ?>">
                                <?php
                                $statuses = [
                                    'pending' => '待支付',
                                    'processing' => '支付中',
                                    'success' => '支付成功',
                                    'failed' => '支付失败',
                                    'timeout' => '已超时',
                                    'cancelled' => '已取消'
                                ];
                                echo $statuses[$transaction['status']] ?? $transaction['status'];
                                ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="label">创建时间:</span>
                        <span class="value"><?= htmlspecialchars($transaction['created_at']) ?></span>
                    </div>
                    <?php if ($transaction['payment_time']): ?>
                    <div class="info-row">
                        <span class="label">支付时间:</span>
                        <span class="value"><?= htmlspecialchars($transaction['payment_time']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($transaction['status'] === 'pending' || $transaction['status'] === 'processing'): ?>
                <div class="payment-action">
                    <form method="POST" action="" id="paymentForm">
                        <input type="hidden" name="action" value="process_payment">
                        <input type="hidden" name="transaction_no" value="<?= htmlspecialchars($transactionNo) ?>">

                        <div class="security-notice">
                            <p><strong>🔒 安全提示</strong></p>
                            <ul>
                                <li>本系统采用SSL/TLS加密传输</li>
                                <li>所有交易数据经过签名验证</li>
                                <li>支付过程受风控系统实时监控</li>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-primary btn-large" id="payBtn">
                            <span id="btnText">立即支付 ￥<?= number_format($transaction['amount'], 2) ?></span>
                            <span id="btnLoading" style="display: none;">处理中...</span>
                        </button>

                        <div class="payment-timeout">
                            <small>订单将在 <span id="countdown">15:00</span> 后自动关闭</small>
                        </div>
                    </form>
                </div>
                <?php elseif ($transaction['status'] === 'success'): ?>
                <div class="payment-result success">
                    <div class="result-icon">✓</div>
                    <h3>支付成功！</h3>
                    <p>您的支付已完成，感谢使用本系统</p>
                    <a href="history.php" class="btn btn-primary">查看交易记录</a>
                </div>
                <?php elseif ($transaction['status'] === 'failed'): ?>
                <div class="payment-result failed">
                    <div class="result-icon">✗</div>
                    <h3>支付失败</h3>
                    <p>很抱歉，支付未能完成</p>
                    <div class="result-actions">
                        <a href="index.php" class="btn btn-primary">重新下单</a>
                        <a href="query.php" class="btn btn-secondary">查询订单</a>
                    </div>
                </div>
                <?php elseif ($transaction['status'] === 'timeout'): ?>
                <div class="payment-result timeout">
                    <div class="result-icon">⏱</div>
                    <h3>订单已超时</h3>
                    <p>订单未在有效期内完成支付</p>
                    <a href="index.php" class="btn btn-primary">重新下单</a>
                </div>
                <?php endif; ?>
            </div>
        </main>

        <footer>
            <p>&copy; 2024 支付交易管理系统 | 仅供学习演示使用</p>
        </footer>
    </div>

    <script>
        // 倒计时功能
        let timeLeft = 900; // 15分钟
        const countdownEl = document.getElementById('countdown');

        function updateCountdown() {
            if (timeLeft <= 0) {
                window.location.reload();
                return;
            }

            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            timeLeft--;
        }

        if (countdownEl) {
            setInterval(updateCountdown, 1000);
            updateCountdown();
        }

        // 支付按钮处理
        const paymentForm = document.getElementById('paymentForm');
        if (paymentForm) {
            paymentForm.addEventListener('submit', function(e) {
                const payBtn = document.getElementById('payBtn');
                const btnText = document.getElementById('btnText');
                const btnLoading = document.getElementById('btnLoading');

                payBtn.disabled = true;
                btnText.style.display = 'none';
                btnLoading.style.display = 'inline';
            });
        }
    </script>
</body>
</html>
