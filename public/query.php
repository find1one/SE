<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/services/PaymentService.php';

$config = require __DIR__ . '/../config/config.php';
$paymentService = new PaymentService($config);

$transactionInfo = null;
$error = '';
$searched = false;

// 处理查询请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'query_transaction') {
    $transactionNo = trim($_POST['transaction_no'] ?? '');
    $searched = true;

    if (empty($transactionNo)) {
        $error = '请输入交易流水号';
    } else {
        $result = $paymentService->queryTransactionStatus($transactionNo);

        if ($result['success']) {
            $transactionInfo = $result;
        } else {
            $error = $result['error'] ?? '未找到该交易记录';
        }
    }
}

// 处理URL参数中的交易号
if (!$searched && isset($_GET['transaction_no'])) {
    $transactionNo = trim($_GET['transaction_no']);

    if (!empty($transactionNo)) {
        $result = $paymentService->queryTransactionStatus($transactionNo);
        $searched = true;

        if ($result['success']) {
            $transactionInfo = $result;
        } else {
            $error = $result['error'] ?? '未找到该交易记录';
        }
    }
}

// 支付方式映射
$paymentMethods = [
    'alipay' => '支付宝',
    'wechat' => '微信支付',
    'bank_card' => '银行卡',
    'credit_card' => '信用卡'
];

// 状态映射
$statuses = [
    'pending' => '待支付',
    'processing' => '支付中',
    'success' => '支付成功',
    'failed' => '支付失败',
    'timeout' => '已超时',
    'cancelled' => '已取消'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查询订单</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>💳 支付交易管理系统</h1>
            <nav>
                <a href="index.php">创建订单</a>
                <a href="history.php">交易历史</a>
                <a href="query.php" class="active">查询订单</a>
            </nav>
        </header>

        <main>
            <div class="card">
                <h2>查询订单状态</h2>

                <!-- 查询表单 -->
                <form method="POST" action="" id="queryForm">
                    <input type="hidden" name="action" value="query_transaction">

                    <div class="form-group">
                        <label for="transaction_no">交易流水号 *</label>
                        <input type="text"
                               id="transaction_no"
                               name="transaction_no"
                               required
                               placeholder="请输入交易流水号"
                               value="<?= htmlspecialchars($_POST['transaction_no'] ?? $_GET['transaction_no'] ?? '') ?>">
                        <small>交易流水号是以 TXN 开头的唯一订单编号</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">查询订单</button>
                        <button type="reset" class="btn btn-secondary">清空</button>
                    </div>
                </form>

                <!-- 错误提示 -->
                <?php if ($error && $searched): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <!-- 查询结果 -->
                <?php if ($transactionInfo && !$error): ?>
                <div class="query-result">
                    <div class="result-header">
                        <h3>订单详情</h3>
                        <span class="status status-<?= $transactionInfo['status'] ?>">
                            <?= $statuses[$transactionInfo['status']] ?? $transactionInfo['status'] ?>
                        </span>
                    </div>

                    <div class="result-details">
                        <div class="detail-row">
                            <span class="detail-label">交易流水号</span>
                            <span class="detail-value">
                                <span class="transaction-no"
                                      onclick="PaymentSystem.copyTransactionNo('<?= htmlspecialchars($transactionInfo['transaction_no']) ?>')"
                                      style="cursor: pointer;"
                                      title="点击复制">
                                    <?= htmlspecialchars($transactionInfo['transaction_no']) ?>
                                </span>
                            </span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">支付金额</span>
                            <span class="detail-value amount">￥<?= number_format($transactionInfo['amount'], 2) ?></span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">支付方式</span>
                            <span class="detail-value">
                                <?= $paymentMethods[$transactionInfo['payment_method']] ?? $transactionInfo['payment_method'] ?>
                            </span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">订单描述</span>
                            <span class="detail-value">
                                <?= htmlspecialchars($transactionInfo['description'] ?? '-') ?>
                            </span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label">创建时间</span>
                            <span class="detail-value"><?= htmlspecialchars($transactionInfo['created_at']) ?></span>
                        </div>

                        <?php if ($transactionInfo['payment_time']): ?>
                        <div class="detail-row">
                            <span class="detail-label">支付时间</span>
                            <span class="detail-value"><?= htmlspecialchars($transactionInfo['payment_time']) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($transactionInfo['updated_at']): ?>
                        <div class="detail-row">
                            <span class="detail-label">更新时间</span>
                            <span class="detail-value"><?= htmlspecialchars($transactionInfo['updated_at']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- 操作按钮 -->
                    <div class="result-actions">
                        <?php if ($transactionInfo['status'] === 'pending' || $transactionInfo['status'] === 'processing'): ?>
                        <a href="payment.php?transaction_no=<?= urlencode($transactionInfo['transaction_no']) ?>" class="btn btn-primary">
                            继续支付
                        </a>
                        <?php elseif ($transactionInfo['status'] === 'success'): ?>
                        <a href="success.php?transaction_no=<?= urlencode($transactionInfo['transaction_no']) ?>" class="btn btn-primary">
                            查看凭证
                        </a>
                        <?php elseif ($transactionInfo['status'] === 'failed' || $transactionInfo['status'] === 'timeout'): ?>
                        <a href="index.php" class="btn btn-primary">
                            重新下单
                        </a>
                        <?php endif; ?>

                        <a href="query.php" class="btn btn-secondary">查询其他订单</a>
                        <a href="history.php" class="btn btn-secondary">查看历史记录</a>
                    </div>

                    <!-- 状态说明 -->
                    <div class="status-info">
                        <?php if ($transactionInfo['status'] === 'pending'): ?>
                        <p>💡 订单还未支付，请尽快完成支付。订单有效期为15分钟。</p>
                        <?php elseif ($transactionInfo['status'] === 'processing'): ?>
                        <p>⏳ 订单正在处理中，请稍候。如长时间未完成，请联系客服。</p>
                        <?php elseif ($transactionInfo['status'] === 'success'): ?>
                        <p>✅ 支付已成功完成，感谢您的使用！</p>
                        <?php elseif ($transactionInfo['status'] === 'failed'): ?>
                        <p>❌ 支付失败，您可以重新尝试支付或创建新订单。</p>
                        <?php elseif ($transactionInfo['status'] === 'timeout'): ?>
                        <p>⏱ 订单已超时，请重新创建订单。</p>
                        <?php elseif ($transactionInfo['status'] === 'cancelled'): ?>
                        <p>🚫 订单已取消，如需支付请重新创建订单。</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 未查询时的提示 -->
                <?php if (!$searched): ?>
                <div class="query-tips">
                    <div class="tip-icon">🔍</div>
                    <h3>快速查询订单</h3>
                    <p>输入交易流水号即可查询订单的详细信息和当前状态</p>
                    <ul>
                        <li>交易流水号可在订单创建后获得</li>
                        <li>也可在交易历史页面中找到</li>
                        <li>支持复制粘贴，方便快捷</li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

            <div class="info-panel">
                <h3>💡 常见问题</h3>
                <ul>
                    <li><strong>如何获取交易流水号？</strong><br>创建订单后会自动生成，也可在交易历史中查看</li>
                    <li><strong>订单多久会过期？</strong><br>待支付订单有效期为15分钟，超时后需重新下单</li>
                    <li><strong>支付失败怎么办？</strong><br>可以尝试重新支付，或更换支付方式后重新下单</li>
                    <li><strong>如何查看所有订单？</strong><br>访问"交易历史"页面可查看全部交易记录</li>
                    <li><strong>找不到订单记录？</strong><br>请确认交易流水号是否正确，或检查是否已过保留期(180天)</li>
                </ul>
            </div>
        </main>

        <footer>
            <p>&copy; 2024 支付交易管理系统 | 仅供学习演示使用</p>
        </footer>
    </div>

    <script src="js/main.js"></script>
    <script>
        // 自动聚焦到输入框
        document.addEventListener('DOMContentLoaded', function() {
            const transactionInput = document.getElementById('transaction_no');
            if (transactionInput && !transactionInput.value) {
                transactionInput.focus();
            }
        });

        // 清空按钮处理
        const queryForm = document.getElementById('queryForm');
        if (queryForm) {
            queryForm.addEventListener('reset', function() {
                setTimeout(() => {
                    window.location.href = 'query.php';
                }, 100);
            });
        }
    </script>
</body>
</html>
