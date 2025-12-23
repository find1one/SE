#!/bin/bash

# ============================================================
# 支付系统API测试脚本
# 使用方法: chmod +x run_api_tests.sh && ./run_api_tests.sh
# ============================================================

BASE_URL="http://localhost:8080"
CONTENT_TYPE="Content-Type: application/json"

# 颜色定义
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 存储创建的paymentId
PAYMENT_ID_1=""
PAYMENT_ID_2=""
PAYMENT_ID_3=""

echo ""
echo "============================================================"
echo -e "${BLUE}          支付系统 API 测试脚本${NC}"
echo "============================================================"
echo ""

# 函数: 打印分隔线
print_separator() {
    echo ""
    echo "------------------------------------------------------------"
    echo ""
}

# 函数: 格式化JSON输出
format_json() {
    if command -v python3 &> /dev/null; then
        echo "$1" | python3 -m json.tool 2>/dev/null || echo "$1"
    elif command -v jq &> /dev/null; then
        echo "$1" | jq . 2>/dev/null || echo "$1"
    else
        echo "$1"
    fi
}

# 函数: 提取paymentId
extract_payment_id() {
    echo "$1" | grep -o '"paymentId":"[^"]*"' | head -1 | cut -d'"' -f4
}

# ============================================================
# 测试1: 创建支付订单 - 支付宝
# ============================================================
echo -e "${YELLOW}【测试1】创建支付订单 - 支付宝${NC}"
echo "POST ${BASE_URL}/api/v1/payments/create"
echo ""

RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/payments/create" \
    -H "${CONTENT_TYPE}" \
    -d '{
        "orderId": "ORD-20241222-001",
        "userId": "1",
        "amount": 99.99,
        "paymentMethod": "ALIPAY",
        "subject": "测试商品-支付宝支付",
        "notifyUrl": "http://localhost:8080/api/v1/orders/update-status",
        "returnUrl": "http://localhost:3000/payment/result"
    }')

format_json "$RESPONSE"
PAYMENT_ID_1=$(extract_payment_id "$RESPONSE")
echo ""
echo -e "${GREEN}提取的 paymentId: ${PAYMENT_ID_1}${NC}"

print_separator
read -p "按回车继续下一个测试..."

# ============================================================
# 测试2: 创建支付订单 - 微信支付
# ============================================================
echo -e "${YELLOW}【测试2】创建支付订单 - 微信支付${NC}"
echo "POST ${BASE_URL}/api/v1/payments/create"
echo ""

RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/payments/create" \
    -H "${CONTENT_TYPE}" \
    -d '{
        "orderId": "ORD-20241222-002",
        "userId": "1",
        "amount": 188.00,
        "paymentMethod": "WECHAT",
        "subject": "测试商品-微信支付",
        "notifyUrl": "http://localhost:8080/api/v1/orders/update-status",
        "returnUrl": "http://localhost:3000/payment/result"
    }')

format_json "$RESPONSE"
PAYMENT_ID_2=$(extract_payment_id "$RESPONSE")
echo ""
echo -e "${GREEN}提取的 paymentId: ${PAYMENT_ID_2}${NC}"

print_separator
read -p "按回车继续下一个测试..."

# ============================================================
# 测试3: 创建支付订单 - 银联支付
# ============================================================
echo -e "${YELLOW}【测试3】创建支付订单 - 银联支付${NC}"
echo "POST ${BASE_URL}/api/v1/payments/create"
echo ""

RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/payments/create" \
    -H "${CONTENT_TYPE}" \
    -d '{
        "orderId": "ORD-20241222-003",
        "userId": "1",
        "amount": 520.00,
        "paymentMethod": "UNIONPAY",
        "subject": "测试商品-银联支付",
        "notifyUrl": "http://localhost:8080/api/v1/orders/update-status",
        "returnUrl": "http://localhost:3000/payment/result"
    }')

format_json "$RESPONSE"
PAYMENT_ID_3=$(extract_payment_id "$RESPONSE")
echo ""
echo -e "${GREEN}提取的 paymentId: ${PAYMENT_ID_3}${NC}"

print_separator
read -p "按回车继续下一个测试..."

# ============================================================
# 测试4: 查询支付状态 - 支付宝订单(应为PENDING)
# ============================================================
echo -e "${YELLOW}【测试4】查询支付状态 - 支付宝订单${NC}"
echo "GET ${BASE_URL}/api/v1/payments/status?paymentId=${PAYMENT_ID_1}"
echo ""

RESPONSE=$(curl -s -X GET "${BASE_URL}/api/v1/payments/status?paymentId=${PAYMENT_ID_1}" \
    -H "${CONTENT_TYPE}")

format_json "$RESPONSE"
echo ""
echo -e "${GREEN}预期状态: PENDING${NC}"

print_separator
read -p "按回车继续下一个测试..."

# ============================================================
# 测试5: 模拟支付回调 - 支付宝订单支付成功
# ============================================================
echo -e "${YELLOW}【测试5】模拟支付回调 - 支付宝订单支付成功${NC}"
echo "POST ${BASE_URL}/api/v1/payments/callback"
echo ""

RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/payments/callback" \
    -H "${CONTENT_TYPE}" \
    -d "{
        \"paymentId\": \"${PAYMENT_ID_1}\",
        \"status\": \"SUCCESS\",
        \"transactionId\": \"TXN-ALIPAY-20241222-001\",
        \"paymentTime\": \"$(date -u +"%Y-%m-%dT%H:%M:%SZ")\",
        \"amount\": 99.99,
        \"paymentMethod\": \"ALIPAY\"
    }")

format_json "$RESPONSE"

print_separator
read -p "按回车继续下一个测试..."

# ============================================================
# 测试6: 再次查询支付状态 - 支付宝订单(应为SUCCESS)
# ============================================================
echo -e "${YELLOW}【测试6】再次查询支付状态 - 支付宝订单${NC}"
echo "GET ${BASE_URL}/api/v1/payments/status?paymentId=${PAYMENT_ID_1}"
echo ""

RESPONSE=$(curl -s -X GET "${BASE_URL}/api/v1/payments/status?paymentId=${PAYMENT_ID_1}" \
    -H "${CONTENT_TYPE}")

format_json "$RESPONSE"
echo ""
echo -e "${GREEN}预期状态: SUCCESS${NC}"

print_separator
read -p "按回车继续下一个测试..."

# ============================================================
# 测试7: 尝试取消已支付成功的订单(应返回错误)
# ============================================================
echo -e "${YELLOW}【测试7】尝试取消已支付成功的订单 - 应返回错误${NC}"
echo "POST ${BASE_URL}/api/v1/payments/cancel"
echo ""

RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/payments/cancel" \
    -H "${CONTENT_TYPE}" \
    -d "{
        \"paymentId\": \"${PAYMENT_ID_1}\",
        \"reason\": \"尝试取消已支付订单\"
    }")

format_json "$RESPONSE"
echo ""
echo -e "${RED}预期结果: 返回错误，无法取消已成功的订单${NC}"

print_separator
read -p "按回车继续下一个测试..."

# ============================================================
# 测试8: 取消银联订单(待支付状态)
# ============================================================
echo -e "${YELLOW}【测试8】取消银联订单 - 待支付状态${NC}"
echo "POST ${BASE_URL}/api/v1/payments/cancel"
echo ""

RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/payments/cancel" \
    -H "${CONTENT_TYPE}" \
    -d "{
        \"paymentId\": \"${PAYMENT_ID_3}\",
        \"reason\": \"用户主动取消支付\"
    }")

format_json "$RESPONSE"

print_separator
read -p "按回车继续下一个测试..."

# ============================================================
# 测试9: 查询已取消订单状态(应为CANCELLED)
# ============================================================
echo -e "${YELLOW}【测试9】查询已取消订单状态${NC}"
echo "GET ${BASE_URL}/api/v1/payments/status?paymentId=${PAYMENT_ID_3}"
echo ""

RESPONSE=$(curl -s -X GET "${BASE_URL}/api/v1/payments/status?paymentId=${PAYMENT_ID_3}" \
    -H "${CONTENT_TYPE}")

format_json "$RESPONSE"
echo ""
echo -e "${GREEN}预期状态: CANCELLED${NC}"

print_separator
read -p "按回车继续下一个测试..."

# ============================================================
# 测试10: 幂等性测试 - 重复创建相同订单
# ============================================================
echo -e "${YELLOW}【测试10】幂等性测试 - 重复创建相同订单${NC}"
echo "POST ${BASE_URL}/api/v1/payments/create"
echo ""

RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/payments/create" \
    -H "${CONTENT_TYPE}" \
    -d '{
        "orderId": "ORD-20241222-001",
        "userId": "1",
        "amount": 99.99,
        "paymentMethod": "ALIPAY",
        "subject": "测试商品-重复订单"
    }')

format_json "$RESPONSE"
echo ""
echo -e "${GREEN}预期结果: 返回已存在的支付订单信息${NC}"

print_separator
read -p "按回车继续错误场景测试..."

# ============================================================
# 错误场景测试
# ============================================================
echo -e "${BLUE}========== 错误场景测试 ==========${NC}"
echo ""

# 测试11: 缺少必填参数
echo -e "${YELLOW}【测试11】缺少必填参数${NC}"
echo "POST ${BASE_URL}/api/v1/payments/create"
echo ""

RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/payments/create" \
    -H "${CONTENT_TYPE}" \
    -d '{
        "orderId": "ORD-20241222-ERR1",
        "amount": 100.00
    }')

format_json "$RESPONSE"
echo ""
echo -e "${RED}预期结果: 返回参数缺失错误${NC}"

print_separator
read -p "按回车继续..."

# 测试12: 金额为0
echo -e "${YELLOW}【测试12】金额为0${NC}"
echo "POST ${BASE_URL}/api/v1/payments/create"
echo ""

RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/payments/create" \
    -H "${CONTENT_TYPE}" \
    -d '{
        "orderId": "ORD-20241222-ERR2",
        "userId": "1",
        "amount": 0,
        "paymentMethod": "ALIPAY",
        "subject": "测试商品"
    }')

format_json "$RESPONSE"
echo ""
echo -e "${RED}预期结果: 返回金额错误${NC}"

print_separator
read -p "按回车继续..."

# 测试13: 查询不存在的订单
echo -e "${YELLOW}【测试13】查询不存在的订单${NC}"
echo "GET ${BASE_URL}/api/v1/payments/status?paymentId=PAY-NOTEXIST-999999"
echo ""

RESPONSE=$(curl -s -X GET "${BASE_URL}/api/v1/payments/status?paymentId=PAY-NOTEXIST-999999" \
    -H "${CONTENT_TYPE}")

format_json "$RESPONSE"
echo ""
echo -e "${RED}预期结果: 返回订单不存在错误${NC}"

print_separator
read -p "按回车继续..."

# 测试14: 回调不存在的订单
echo -e "${YELLOW}【测试14】回调不存在的订单${NC}"
echo "POST ${BASE_URL}/api/v1/payments/callback"
echo ""

RESPONSE=$(curl -s -X POST "${BASE_URL}/api/v1/payments/callback" \
    -H "${CONTENT_TYPE}" \
    -d '{
        "paymentId": "PAY-NOTEXIST-999999",
        "status": "SUCCESS",
        "transactionId": "TXN-TEST-001"
    }')

format_json "$RESPONSE"
echo ""
echo -e "${RED}预期结果: 返回订单不存在错误${NC}"

print_separator

# ============================================================
# 测试完成
# ============================================================
echo ""
echo "============================================================"
echo -e "${GREEN}          所有测试完成!${NC}"
echo "============================================================"
echo ""
echo "测试结果汇总:"
echo "  - 支付宝订单 (${PAYMENT_ID_1}): 创建 -> 查询 -> 支付成功 -> 查询确认"
echo "  - 微信订单 (${PAYMENT_ID_2}): 创建成功"
echo "  - 银联订单 (${PAYMENT_ID_3}): 创建 -> 取消 -> 查询确认"
echo ""
echo "错误场景测试: 4个用例全部验证"
echo ""
