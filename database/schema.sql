-- 支付系统数据库架构

-- 创建数据库
CREATE DATABASE IF NOT EXISTS payment_system
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE payment_system;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 交易订单表
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_no VARCHAR(64) NOT NULL UNIQUE COMMENT '交易流水号',
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT '交易金额',
    payment_method ENUM('alipay', 'wechat', 'bank_card', 'credit_card') NOT NULL,
    status ENUM('pending', 'processing', 'success', 'failed', 'cancelled', 'timeout') DEFAULT 'pending',
    description VARCHAR(255) COMMENT '交易描述',
    payment_time DATETIME NULL COMMENT '支付完成时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_transaction_no (transaction_no),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 支付详情表
CREATE TABLE IF NOT EXISTS payment_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    payment_gateway_response TEXT COMMENT '支付网关响应',
    signature VARCHAR(512) COMMENT '交易签名',
    ip_address VARCHAR(45) COMMENT '支付IP地址',
    user_agent VARCHAR(512) COMMENT '用户代理',
    retry_count INT DEFAULT 0 COMMENT '重试次数',
    error_message TEXT COMMENT '错误信息',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    INDEX idx_transaction_id (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 通知记录表
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    type ENUM('email', 'sms') NOT NULL,
    recipient VARCHAR(100) NOT NULL COMMENT '接收者',
    content TEXT NOT NULL COMMENT '通知内容',
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_at DATETIME NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 风险控制日志表
CREATE TABLE IF NOT EXISTS fraud_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NULL,
    user_id INT NULL,
    risk_level ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    risk_type VARCHAR(50) COMMENT '风险类型',
    description TEXT COMMENT '风险描述',
    ip_address VARCHAR(45),
    action_taken ENUM('allowed', 'blocked', 'manual_review') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_risk_level (risk_level),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 支付方式配置表
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_code VARCHAR(20) NOT NULL UNIQUE,
    method_name VARCHAR(50) NOT NULL,
    enabled TINYINT(1) DEFAULT 1,
    icon_url VARCHAR(255),
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认支付方式
INSERT INTO payment_methods (method_code, method_name, icon_url, display_order) VALUES
('alipay', '支付宝', '/images/alipay.png', 1),
('wechat', '微信支付', '/images/wechat.png', 2),
('bank_card', '银行卡', '/images/bank.png', 3),
('credit_card', '信用卡', '/images/credit.png', 4);

-- ==================== 外部系统对接支持 ====================

-- 外部订单关联表 (记录外部系统订单与本支付系统交易的关联)
CREATE TABLE IF NOT EXISTS external_order_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL COMMENT '本系统交易ID',
    payment_id VARCHAR(64) NOT NULL COMMENT '支付ID(PAY-xxx)',
    external_order_id VARCHAR(64) NOT NULL COMMENT '外部系统订单ID',
    external_system VARCHAR(50) NOT NULL DEFAULT 'booking_panel' COMMENT '外部系统标识',
    callback_url VARCHAR(500) COMMENT '支付完成通知地址(notifyUrl)',
    return_url VARCHAR(500) COMMENT '支付完成跳转地址(returnUrl)',
    callback_status ENUM('pending', 'success', 'failed') DEFAULT 'pending' COMMENT '回调状态',
    callback_response TEXT COMMENT '回调响应内容',
    callback_retry_count INT DEFAULT 0 COMMENT '回调重试次数',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    UNIQUE KEY uk_transaction_id (transaction_id),
    UNIQUE KEY uk_payment_id (payment_id),
    INDEX idx_external_order_id (external_order_id),
    INDEX idx_external_system (external_system),
    INDEX idx_callback_status (callback_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='外部订单关联表';

-- 支付回调日志表 (用于记录所有回调请求，支持幂等性和审计)
CREATE TABLE IF NOT EXISTS payment_callback_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(64) NOT NULL COMMENT '支付ID',
    callback_type ENUM('payment_notify', 'order_update', 'refund_notify') NOT NULL COMMENT '回调类型',
    request_data TEXT COMMENT '请求数据',
    response_data TEXT COMMENT '响应数据',
    http_status INT COMMENT 'HTTP状态码',
    is_success TINYINT(1) DEFAULT 0 COMMENT '是否成功',
    error_message TEXT COMMENT '错误信息',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_id (payment_id),
    INDEX idx_callback_type (callback_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付回调日志表';

-- 待处理任务队列表 (用于记录需要重试或人工处理的任务)
CREATE TABLE IF NOT EXISTS pending_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_type VARCHAR(50) NOT NULL COMMENT '任务类型',
    payment_id VARCHAR(64) COMMENT '关联支付ID',
    external_order_id VARCHAR(64) COMMENT '外部订单ID',
    task_data TEXT COMMENT '任务数据(JSON)',
    retry_count INT DEFAULT 0 COMMENT '重试次数',
    max_retries INT DEFAULT 3 COMMENT '最大重试次数',
    next_retry_at DATETIME COMMENT '下次重试时间',
    status ENUM('pending', 'processing', 'success', 'failed', 'manual') DEFAULT 'pending' COMMENT '任务状态',
    error_message TEXT COMMENT '错误信息',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_next_retry_at (next_retry_at),
    INDEX idx_payment_id (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='待处理任务队列表';

-- 创建测试用户
INSERT INTO users (username, email, phone, password_hash) VALUES
('testuser', 'test@example.com', '13800138000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
