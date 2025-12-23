# 支付系统 API 测试说明

## 测试前准备

### 1. 启动 MySQL 数据库
确保 MySQL 服务正在运行，并执行数据库初始化脚本：

```bash
mysql -u root -p < ../database/schema.sql
```

### 2. 启动 PHP 内置服务器

```bash
cd ../public
php -S localhost:8080
```

或者使用路由文件（支持URL重写）：

```bash
cd ../public
php -S localhost:8080 router.php
```

## 测试方式

### 方式一：使用 Shell 脚本（推荐录视频）

```bash
chmod +x run_api_tests.sh
./run_api_tests.sh
```

脚本会逐步执行每个测试，每个测试后暂停等待按回车继续，方便录制视频。

### 方式二：使用 HTTP 文件（VS Code / JetBrains IDE）

1. 在 VS Code 中安装 **REST Client** 插件
2. 打开 `api_test.http` 文件
3. 点击每个请求上方的 "Send Request" 链接

### 方式三：使用 curl 手动测试

```bash
# 创建支付订单
curl -X POST http://localhost:8080/api/v1/payments/create \
  -H "Content-Type: application/json" \
  -d '{
    "orderId": "ORD-TEST-001",
    "userId": "USER-001",
    "amount": 99.99,
    "paymentMethod": "ALIPAY",
    "subject": "测试商品"
  }'

# 查询支付状态
curl http://localhost:8080/api/v1/payments/status?paymentId=PAY-xxx

# 支付回调
curl -X POST http://localhost:8080/api/v1/payments/callback \
  -H "Content-Type: application/json" \
  -d '{
    "paymentId": "PAY-xxx",
    "status": "SUCCESS",
    "transactionId": "TXN-001"
  }'

# 取消支付
curl -X POST http://localhost:8080/api/v1/payments/cancel \
  -H "Content-Type: application/json" \
  -d '{
    "paymentId": "PAY-xxx",
    "reason": "用户取消"
  }'
```

## 测试用例说明

### 正常流程测试

| 序号 | 测试项 | 预期结果 |
|------|--------|----------|
| 1 | 创建支付宝订单 | 返回 paymentId, paymentUrl, qrCode |
| 2 | 创建微信订单 | 返回 paymentId, paymentUrl, qrCode |
| 3 | 创建银联订单 | 返回 paymentId, paymentUrl |
| 4 | 查询待支付订单状态 | status = PENDING |
| 5 | 模拟支付成功回调 | code = 200, 状态更新成功 |
| 6 | 查询已支付订单状态 | status = SUCCESS |
| 7 | 尝试取消已支付订单 | 返回错误，无法取消 |
| 8 | 取消待支付订单 | status = CANCELLED |
| 9 | 查询已取消订单状态 | status = CANCELLED |
| 10 | 重复创建相同订单 | 返回已存在的订单信息（幂等性） |

### 错误场景测试

| 序号 | 测试项 | 预期结果 |
|------|--------|----------|
| 11 | 缺少必填参数 | 返回参数缺失错误 |
| 12 | 金额为0 | 返回金额错误 |
| 13 | 查询不存在的订单 | 返回订单不存在 |
| 14 | 回调不存在的订单 | 返回订单不存在 |

## API 响应格式

### 成功响应

```json
{
  "code": 200,
  "message": "success",
  "data": {
    // 具体数据
  }
}
```

### 错误响应

```json
{
  "code": 400,
  "message": "错误描述",
  "error": "ERROR_CODE"
}
```

## 状态映射

| 内部状态 | API返回状态 |
|----------|-------------|
| pending | PENDING |
| processing | PROCESSING |
| success | SUCCESS |
| failed | FAILED |
| cancelled | CANCELLED |
| timeout | TIMEOUT |
