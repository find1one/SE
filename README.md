## 支付交易管理系统（Payment System）

一个基于 **PHP + MySQL** 的支付交易管理演示系统，模拟支付宝、微信支付、银行卡、信用卡等多种支付方式，包含交易创建、支付处理、状态查询、风险控制、通知推送及对外统一支付 API，适合作为支付网关 / 聚合支付的教学示例或 Demo。

> ⚠️ 本项目仅用于学习与演示，不会产生真实支付行为，请勿用于生产环境。

---

### 功能特性

- **多支付方式模拟**
  - 支付宝（`alipay`）
  - 微信支付（`wechat`）
  - 银行卡（`bank_card`）
  - 信用卡（`credit_card`）
- **交易管理**
  - 创建支付订单
  - 支付确认与处理
  - 交易状态查询
  - 交易历史列表
- **风险控制（防刷、防欺诈）**
  - 单笔最大金额限制
  - 每小时最大交易次数限制
  - 最大失败次数限制
  - 风控日志记录（`fraud_logs`）
- **通知中心**
  - 支付成功邮件通知（模拟）
  - 支付超时通知（模拟）
  - 短信通知配置（模拟）
- **统一支付 API（对接火车票订单系统等外部系统）**
  - `POST /public/api/pay.php`：创建支付订单
  - `GET  /public/api/pay.php?action=query&transactionNo=...`：查询支付状态
  - 支持 CORS、JSON 接口，返回统一结构
- **基础设施**
  - `config/config.php` 统一配置（数据库 / 支付 / 日志 / 安全 / 回调等）
  - `src/models/Database.php` 封装 PDO
  - `src/utils/Logger.php` 日志记录到 `logs/`
  - `database/schema.sql` 初始化数据库结构

---

### 技术栈

- **语言**：PHP 7.4+（推荐 8.x）
- **数据库**：MySQL 5.7+ / MariaDB
- **前端**：原生 HTML + CSS + JavaScript（位于 `public/`）
- **运行环境**：任意支持 PHP 的 Web 服务器（Apache / Nginx + PHP-FPM / PHP 内置服务器）

---

### 目录结构

```text
.
├─ config/
│  └─ config.php          # 全局配置（数据库、支付、日志、安全、外部回调等）
├─ database/
│  └─ schema.sql          # 数据库结构初始化脚本
├─ logs/                  # 日志目录（运行时生成）
├─ public/                # Web 访问入口（Document Root 建议指向这里）
│  ├─ api/
│  │  ├─ pay.php          # 统一支付 API（对外接口）
│  │  └─ callback.php     # 模拟支付回调（如第三方回调）
│  ├─ css/
│  │  └─ style.css        # 页面样式
│  ├─ js/
│  │  └─ main.js          # 前端交互逻辑
│  ├─ index.php           # 创建订单页面（系统首页）
│  ├─ payment.php         # 支付确认 / 倒计时页面
│  ├─ success.php         # 支付成功页面
│  ├─ history.php         # 交易历史
│  └─ query.php           # 交易查询
├─ src/
│  ├─ models/
│  │  └─ Database.php     # 数据库访问封装 (PDO)
│  ├─ services/
│  │  ├─ PaymentService.php       # 核心业务：创建订单/执行支付/查询/超时处理等
│  │  ├─ PaymentGateway.php       # 支付网关接口定义
│  │  ├─ AlipayGateway.php        # 支付宝模拟网关
│  │  ├─ WechatGateway.php        # 微信支付模拟网关
│  │  ├─ BankCardGateway.php      # 银行卡模拟网关
│  │  ├─ CreditCardGateway.php    # 信用卡模拟网关
│  │  ├─ NotificationService.php  # 通知服务（邮件/短信等）
│  │  └─ FraudDetectionService.php# 风控服务
│  ├─ utils/
│  │  └─ Logger.php       # 简单文件日志工具
│  └─ views/              #（预留）视图模板目录
└─ README.md              # 项目说明文档
```

---

### 安装与运行

#### 1. 克隆项目

```bash
git clone https://github.com/your-username/payment-system.git
cd payment-system
```

> 将上面的仓库地址替换为你自己的 GitHub 仓库地址。

#### 2. 配置数据库

1. 新建数据库（名称可与默认一致：`payment_system`）：

   ```sql
   CREATE DATABASE payment_system DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. 导入表结构：

   ```bash
   # 使用 MySQL 命令行示例
   mysql -u root -p payment_system < database/schema.sql
   ```

3. 根据本地环境修改 `config/config.php` 中的数据库配置：

   ```php
   'database' => [
       'host' => 'localhost',
       'port' => 3306,
       'dbname' => 'payment_system',
       'username' => 'root',
       'password' => '你的数据库密码',
       'charset' => 'utf8mb4'
   ],
   ```

#### 3. 配置应用参数（可选）

在 `config/config.php` 中可根据需要调整：

- **支付相关**
  - `payment.timeout`：支付超时时间（秒），默认 900 秒（15 分钟）
  - `payment.retry_times`：失败后最大重试次数
  - `payment.supported_methods`：支持的支付方式列表
- **模拟支付网关成功率**
  - `payment_gateways.*.success_rate`：不同支付方式的成功概率，用于模拟真实场景
- **通知相关**
  - `notification.email` / `notification.sms`：邮件和短信的开关与参数（演示用）
- **安全相关**
  - `security.ssl_enabled`：是否启用 SSL
  - `security.encryption_key`：加密密钥（务必替换为你自己的 32 位随机字符串）
  - `security.fraud_detection`：风控策略（单笔金额 / 每小时交易次数 / 最大失败次数）
- **应用基本信息**
  - `app.base_url`：本系统对外访问地址（如 `http://localhost:8080`）
  - `external_callback.train_order.callback_url`：支付完成后通知外部系统的回调地址

#### 4. 启动服务

你可以使用任何 Web 服务器来运行本项目，这里以 PHP 内置服务器为例（仅适合开发环境）：

```bash
cd public
php -S 0.0.0.0:8080
```

然后在浏览器访问：

```text
http://localhost:8080
```

> 如果使用 Nginx/Apache，请将站点根目录指向项目的 `public/` 目录。

---

### 使用说明

#### 1. Web 界面

- 访问 `index.php`：创建新的支付订单
  - 输入支付金额
  - 选择支付方式（支付宝 / 微信 / 银行卡 / 信用卡）
  - 填写订单描述（可选）
- 提交后跳转到 `payment.php`：
  - 显示订单详情、状态、倒计时（15 分钟）
  - 点击“立即支付”触发模拟支付流程
- 支付成功后跳转到 `success.php`
- 通过 `history.php` 查看当前用户的交易历史
- 通过 `query.php` 根据交易流水号查询订单状态

> 系统内部使用 Session 简单模拟登录用户（`user_id = 1`），不包含完整用户体系。

#### 2. 统一支付 API（对外接口）

入口文件：`public/api/pay.php`

- **创建支付订单**

  - **URL**

    ```text
    POST /api/pay.php
    ```

  - **请求头**

    ```text
    Content-Type: application/json
    ```

  - **请求体示例**

    ```json
    {
      "userId": 1,
      "amount": 100.00,
      "paymentMethod": "alipay",
      "orderId": 123,
      "description": "火车票订单支付",
      "callbackUrl": "http://127.0.0.1:8081/api/order/callback/notify"
    }
    ```

  - **成功响应示例**

    ```json
    {
      "code": 200,
      "message": "success",
      "data": {
        "orderId": 123,
        "transactionNo": "TXN20240101000000123456",
        "transactionId": 1,
        "amount": 100,
        "paymentMethod": "alipay",
        "payUrl": "http://localhost:8080/payment.php?transaction_no=TXN2024...",
        "status": "PENDING",
        "paymentStatus": "UNPAID"
      }
    }
    ```

- **查询支付状态**

  - **URL**

    ```text
    GET /api/pay.php?action=query&transactionNo=TXN20240101000000123456
    ```

  - **成功响应示例**

    ```json
    {
      "code": 200,
      "message": "success",
      "data": {
        "transactionNo": "TXN20240101000000123456",
        "status": "success",
        "paymentStatus": "PAID",
        "amount": 100,
        "paymentMethod": "alipay",
        "paymentTime": "2024-01-01 12:00:00"
      }
    }
    ```

  - 状态映射（内部状态 → 外部系统状态）：
    - `pending` / `processing` → `UNPAID`
    - `success` → `PAID`
    - `failed` → `UNPAID`
    - `timeout` → `EXPIRED`
    - `cancelled` → `CANCELLED`

---

### 日志与风控

- 日志默认写入 `logs/` 目录，以 `payment_YYYY-MM-DD.log` 命名。
- 日志级别由 `config/config.php` 的 `logging.level` 控制（`debug` / `info` / `warning` / `error`）。
- 风控规则由 `security.fraud_detection` 配置：
  - `max_amount_per_transaction`：单笔最大金额
  - `max_transactions_per_hour`：每小时最大交易次数
  - `max_failed_attempts`：最大失败次数
- 高频或异常交易会被 `FraudDetectionService` 拦截，并记录到 `fraud_logs`。

---

### 注意事项

- 本项目为教学 / 演示用途 **不包含真实的三方支付 SDK 接入**。
- 若要用于真实支付，需要替换各个 `*Gateway.php` 中的逻辑，并接入真实支付渠道（如支付宝开放平台、微信支付等）。
- 请勿在公网环境暴露默认配置中的敏感信息（如加密密钥、邮件密码等），务必自行替换为安全的配置。

---

### 许可协议

若无特别说明，可视为在 MIT 协议下开源使用。你可以自由地学习、修改和二次开发本项目，但请勿直接用于生产支付业务。


