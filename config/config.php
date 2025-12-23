<?php
/**
 * 支付系统配置文件
 */

return [
    // 数据库配置
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'payment_system',
        'username' => 'root',
        'password' => '123456',
        'charset' => 'utf8mb4'
    ],

    // 支付配置
    'payment' => [
        'timeout' => 900, // 支付超时时间（秒）15分钟
        'retry_times' => 3, // 重试次数
        'supported_methods' => ['alipay', 'wechat', 'bank_card', 'credit_card']
    ],

    // 模拟支付接口配置
    'payment_gateways' => [
        'alipay' => [
            'app_id' => 'mock_alipay_app_id',
            'private_key' => 'mock_alipay_private_key',
            'public_key' => 'mock_alipay_public_key',
            'success_rate' => 0.85 // 模拟成功率
        ],
        'wechat' => [
            'app_id' => 'mock_wechat_app_id',
            'mch_id' => 'mock_wechat_mch_id',
            'api_key' => 'mock_wechat_api_key',
            'success_rate' => 0.80
        ],
        'bank_card' => [
            'merchant_id' => 'mock_bank_merchant_id',
            'secret_key' => 'mock_bank_secret_key',
            'success_rate' => 0.90
        ],
        'credit_card' => [
            'merchant_id' => 'mock_credit_merchant_id',
            'secret_key' => 'mock_credit_secret_key',
            'success_rate' => 0.88
        ]
    ],

    // 通知配置
    'notification' => [
        'email' => [
            'enabled' => true,
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_user' => 'noreply@example.com',
            'smtp_pass' => 'password',
            'from_email' => 'noreply@example.com',
            'from_name' => '支付系统'
        ],
        'sms' => [
            'enabled' => true,
            'api_key' => 'mock_sms_api_key',
            'api_secret' => 'mock_sms_api_secret'
        ]
    ],

    // 安全配置
    'security' => [
        'ssl_enabled' => true,
        'encryption_key' => 'your-32-character-encryption-key-here',
        'callback_secret_key' => 'your-callback-secret-key-for-signature',
        'internal_token' => 'internal-api-token-for-service-calls',
        'verify_callback_signature' => false, // 生产环境应设为true
        'fraud_detection' => [
            'max_amount_per_transaction' => 50000, // 单笔最大金额
            'max_transactions_per_hour' => 10, // 每小时最大交易次数
            'max_failed_attempts' => 5 // 最大失败次数
        ]
    ],

    // 日志配置
    'logging' => [
        'enabled' => true,
        'path' => __DIR__ . '/../logs/',
        'level' => 'info' // debug, info, warning, error
    ],

    // 应用配置
    'app' => [
        'name' => '支付交易管理系统',
        'base_url' => 'http://localhost:8080', // 本系统访问地址
        'api_version' => '1.0'
    ],

    // 外部系统回调配置(支付完成后通知外部系统)
    'external_callback' => [
        // Booking Panel订单系统回调配置
        'booking_panel' => [
            'enabled' => true,
            // 订单状态更新接口 - 支付完成后调用此接口通知Booking Panel
            'order_update_url' => 'http://localhost:8080/api/v1/orders/update-status',
            'timeout' => 5, // 超时时间(秒)
            'max_retries' => 3, // 最大重试次数
            'retry_interval' => 5 // 重试间隔(秒)
        ]
    ],

    // API配置
    'api' => [
        'version' => 'v1',
        'rate_limit' => [
            'enabled' => true,
            'max_requests_per_minute' => 60
        ],
        'timeout' => [
            'create_payment' => 5000, // 创建支付订单超时(ms)
            'callback' => 10000, // 回调接口超时(ms)
            'query_status' => 3000 // 查询状态超时(ms)
        ]
    ]
];
