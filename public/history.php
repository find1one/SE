<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/services/PaymentService.php';

$config = require __DIR__ . '/../config/config.php';
$paymentService = new PaymentService($config);

// 模拟登录用户
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = 10;
$offset = ($page - 1) * $pageSize;

// 筛选参数
$filters = [
    'status' => $_GET['status'] ?? '',
    'payment_method' => $_GET['payment_method'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// 获取交易历史
$transactions = $paymentService->getUserTransactionHistory(
    $_SESSION['user_id'],
    $pageSize,
    $offset,
    $filters
);

// 获取总记录数（用于分页）
$totalCount = $paymentService->getUserTransactionCount($_SESSION['user_id'], $filters);
$totalPages = ceil($totalCount / $pageSize);

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
    <title>交易历史</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>💳 支付交易管理系统</h1>
            <nav>
                <a href="index.php">创建订单</a>
                <a href="history.php" class="active">交易历史</a>
                <a href="query.php">查询订单</a>
            </nav>
        </header>

        <main>
            <div class="card">
                <h2>交易历史记录</h2>

                <!-- 筛选器 -->
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group">
                        <div class="filter-item">
                            <label for="status">交易状态</label>
                            <select name="status" id="status">
                                <option value="">全部状态</option>
                                <?php foreach ($statuses as $key => $value): ?>
                                <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>>
                                    <?= $value ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-item">
                            <label for="payment_method">支付方式</label>
                            <select name="payment_method" id="payment_method">
                                <option value="">全部方式</option>
                                <?php foreach ($paymentMethods as $key => $value): ?>
                                <option value="<?= $key ?>" <?= $filters['payment_method'] === $key ? 'selected' : '' ?>>
                                    <?= $value ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-item">
                            <label for="date_from">开始日期</label>
                            <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>

                        <div class="filter-item">
                            <label for="date_to">结束日期</label>
                            <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">筛选</button>
                            <a href="history.php" class="btn btn-secondary">重置</a>
                        </div>
                    </div>
                </form>

                <!-- 交易统计 -->
                <div class="stats-summary">
                    <div class="stat-item">
                        <div class="stat-label">总交易笔数</div>
                        <div class="stat-value"><?= $totalCount ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">成功交易</div>
                        <div class="stat-value success">
                            <?= $paymentService->getUserTransactionCount($_SESSION['user_id'], ['status' => 'success']) ?>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">失败交易</div>
                        <div class="stat-value failed">
                            <?= $paymentService->getUserTransactionCount($_SESSION['user_id'], ['status' => 'failed']) ?>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">待支付</div>
                        <div class="stat-value pending">
                            <?= $paymentService->getUserTransactionCount($_SESSION['user_id'], ['status' => 'pending']) ?>
                        </div>
                    </div>
                </div>

                <!-- 交易列表 -->
                <?php if (!empty($transactions)): ?>
                <div class="table-responsive">
                    <table class="transaction-table">
                        <thead>
                            <tr>
                                <th>交易流水号</th>
                                <th>金额</th>
                                <th>支付方式</th>
                                <th>状态</th>
                                <th>创建时间</th>
                                <th>支付时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td>
                                    <span class="transaction-no" onclick="PaymentSystem.copyTransactionNo('<?= htmlspecialchars($transaction['transaction_no']) ?>')" style="cursor: pointer;" title="点击复制">
                                        <?= htmlspecialchars($transaction['transaction_no']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="amount">￥<?= number_format($transaction['amount'], 2) ?></span>
                                </td>
                                <td>
                                    <?= $paymentMethods[$transaction['payment_method']] ?? $transaction['payment_method'] ?>
                                </td>
                                <td>
                                    <span class="status status-<?= $transaction['status'] ?>">
                                        <?= $statuses[$transaction['status']] ?? $transaction['status'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($transaction['created_at']) ?></td>
                                <td>
                                    <?= $transaction['payment_time'] ? htmlspecialchars($transaction['payment_time']) : '-' ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($transaction['status'] === 'pending' || $transaction['status'] === 'processing'): ?>
                                        <a href="payment.php?transaction_no=<?= urlencode($transaction['transaction_no']) ?>" class="btn-link">继续支付</a>
                                        <?php else: ?>
                                        <a href="query.php?transaction_no=<?= urlencode($transaction['transaction_no']) ?>" class="btn-link">查看详情</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 分页 -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>" class="page-link">上一页</a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    if ($startPage > 1): ?>
                        <a href="?page=1<?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>" class="page-link">1</a>
                        <?php if ($startPage > 2): ?>
                            <span class="page-ellipsis">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?page=<?= $i ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>"
                           class="page-link <?= $i === $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span class="page-ellipsis">...</span>
                        <?php endif; ?>
                        <a href="?page=<?= $totalPages ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>" class="page-link"><?= $totalPages ?></a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= http_build_query($filters) ? '&' . http_build_query($filters) : '' ?>" class="page-link">下一页</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📋</div>
                    <h3>暂无交易记录</h3>
                    <p>您还没有任何交易记录</p>
                    <a href="index.php" class="btn btn-primary">立即创建订单</a>
                </div>
                <?php endif; ?>
            </div>

            <div class="info-panel">
                <h3>💡 功能说明</h3>
                <ul>
                    <li>点击交易流水号可复制到剪贴板</li>
                    <li>使用筛选器可以按状态、支付方式和日期范围筛选交易</li>
                    <li>点击"继续支付"可完成未支付的订单</li>
                    <li>点击"查看详情"可查看交易的详细信息</li>
                    <li>交易记录保留180天，过期自动清理</li>
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
