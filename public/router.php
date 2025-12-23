<?php
/**
 * PHP内置服务器路由文件
 * 使用方法: php -S localhost:8080 router.php
 */

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// API路由映射
$apiRoutes = [
    // 创建支付订单
    '/api/v1/payments/create' => '/api/v1/payments/create.php',

    // 支付回调
    '/api/v1/payments/callback' => '/api/v1/payments/callback.php',

    // 查询支付状态
    '/api/v1/payments/status' => '/api/v1/payments/status.php',

    // 取消支付
    '/api/v1/payments/cancel' => '/api/v1/payments/cancel.php',

    // 旧的API路由（兼容）
    '/api/pay.php' => '/api/pay.php',
    '/api/callback.php' => '/api/callback.php',
];

// 检查是否匹配API路由
foreach ($apiRoutes as $route => $file) {
    if (strpos($path, $route) === 0) {
        $filePath = __DIR__ . $file;
        if (file_exists($filePath)) {
            require $filePath;
            return true;
        }
    }
}

// 静态文件处理
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/i', $path)) {
    return false; // 让PHP内置服务器处理静态文件
}

// PHP文件处理
if (preg_match('/\.php$/', $path)) {
    $filePath = __DIR__ . $path;
    if (file_exists($filePath)) {
        require $filePath;
        return true;
    }
}

// 默认路由到index.php
$indexPath = __DIR__ . '/index.php';
if (file_exists($indexPath)) {
    require $indexPath;
    return true;
}

// 404处理
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'code' => 404,
    'message' => 'Not Found',
    'error' => 'ROUTE_NOT_FOUND'
], JSON_UNESCAPED_UNICODE);
return true;
