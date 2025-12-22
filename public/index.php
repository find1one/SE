<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/services/PaymentService.php';

$config = require __DIR__ . '/../config/config.php';
$paymentService = new PaymentService($config);

// 模拟登录用户
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // 演示用户ID
}

$message = '';
$messageType = '';

// 处理创建订单请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_order') {
    $amount = floatval($_POST['amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $description = $_POST['description'] ?? '';

    if ($amount > 0 && !empty($paymentMethod)) {
        $result = $paymentService->createPaymentOrder(
            $_SESSION['user_id'],
            $amount,
            $paymentMethod,
            $description
        );

        if ($result['success']) {
            $_SESSION['transaction_no'] = $result['transaction_no'];
            header('Location: payment.php?transaction_no=' . $result['transaction_no']);
            exit;
        } else {
            $message = $result['error'];
            $messageType = 'error';
        }
    } else {
        $message = '请填写正确的金额和支付方式';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付系统 - 创建订单</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>💳 支付交易管理系统</h1>
            <nav>
                <a href="index.php" class="active">创建订单</a>
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

            <div class="card">
                <h2>创建支付订单</h2>
                <form method="POST" action="" id="orderForm">
                    <input type="hidden" name="action" value="create_order">

                    <div class="form-group">
                        <label for="amount">支付金额 (元) *</label>
                        <input type="number"
                               id="amount"
                               name="amount"
                               step="0.01"
                               min="0.01"
                               max="50000"
                               required
                               placeholder="请输入支付金额">
                        <small>单笔最高限额: ￥50,000</small>
                    </div>

                    <div class="form-group">
                        <label>选择支付方式 *</label>
                        <div class="payment-methods">
                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="alipay" required>
                                <div class="method-card">
                                    <div class="method-icon alipay">支</div>
                                    <div class="method-name">支付宝</div>
                                    <div class="method-desc">快捷安全</div>
                                </div>
                            </label>

                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="wechat" required>
                                <div class="method-card">
                                    <div class="method-icon wechat">微</div>
                                    <div class="method-name">微信支付</div>
                                    <div class="method-desc">便捷支付</div>
                                </div>
                            </label>

                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="bank_card" required>
                                <div class="method-card">
                                    <div class="method-icon bank">银</div>
                                    <div class="method-name">银行卡</div>
                                    <div class="method-desc">储蓄卡</div>
                                </div>
                            </label>

                            <label class="payment-method">
                                <input type="radio" name="payment_method" value="credit_card" required>
                                <div class="method-card">
                                    <div class="method-icon credit">信</div>
                                    <div class="method-name">信用卡</div>
                                    <div class="method-desc">分期付款</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">订单描述 (可选)</label>
                        <textarea id="description"
                                  name="description"
                                  rows="3"
                                  placeholder="请输入订单描述信息"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">创建订单并支付</button>
                        <button type="reset" class="btn btn-secondary">重置</button>
                    </div>
                </form>
            </div>

            <div class="info-panel">
                <h3>💡 温馨提示</h3>
                <ul>
                    <li>本系统为支付模拟演示系统，不会产生真实交易</li>
                    <li>支付订单有效期为15分钟，请及时完成支付</li>
                    <li>单笔交易最高限额为￥50,000</li>
                    <li>每小时最多进行10笔交易</li>
                    <li>支付成功后将发送邮件和短信通知</li>
                    <li>如遇支付失败，系统允许最多重试3次</li>
                </ul>
            </div>
        </main>

        <footer>
            <p>&copy; 2024 支付交易管理系统 | 仅供学习演示使用</p>
        </footer>
    </div>

    <script src="js/main.js"></script>
</body>
</html>
