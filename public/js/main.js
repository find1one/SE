/**
 * 支付系统主要JavaScript文件
 * 提供表单验证、用户交互和实时反馈
 */

document.addEventListener('DOMContentLoaded', function() {
    // 初始化所有功能
    initOrderForm();
    initAmountFormatting();
    initPaymentMethodSelection();
});

/**
 * 初始化订单创建表单
 */
function initOrderForm() {
    const orderForm = document.getElementById('orderForm');
    if (!orderForm) return;

    orderForm.addEventListener('submit', function(e) {
        if (!validateOrderForm()) {
            e.preventDefault();
            return false;
        }

        // 显示加载状态
        const submitBtn = orderForm.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>创建中...</span>';
        }
    });

    // 实时验证金额输入
    const amountInput = document.getElementById('amount');
    if (amountInput) {
        amountInput.addEventListener('input', function() {
            validateAmount(this);
        });

        amountInput.addEventListener('blur', function() {
            formatAmount(this);
        });
    }
}

/**
 * 验证订单表单
 */
function validateOrderForm() {
    const amountInput = document.getElementById('amount');
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');

    // 清除之前的错误提示
    clearErrors();

    let isValid = true;

    // 验证金额
    if (!amountInput || !amountInput.value) {
        showError(amountInput, '请输入支付金额');
        isValid = false;
    } else {
        const amount = parseFloat(amountInput.value);
        if (amount < 0.01) {
            showError(amountInput, '支付金额不能小于 0.01 元');
            isValid = false;
        } else if (amount > 50000) {
            showError(amountInput, '支付金额不能超过 50,000 元');
            isValid = false;
        }
    }

    // 验证支付方式
    if (!paymentMethod) {
        const paymentMethodsDiv = document.querySelector('.payment-methods');
        showError(paymentMethodsDiv, '请选择支付方式');
        isValid = false;
    }

    return isValid;
}

/**
 * 验证金额输入
 */
function validateAmount(input) {
    const value = parseFloat(input.value);
    const errorEl = input.parentElement.querySelector('.error-message');

    if (errorEl) {
        errorEl.remove();
    }

    if (input.value === '') return;

    if (isNaN(value) || value < 0.01) {
        showError(input, '金额不能小于 0.01 元');
    } else if (value > 50000) {
        showError(input, '金额不能超过 50,000 元');
    } else {
        // 显示实时提示
        const small = input.parentElement.querySelector('small');
        if (small) {
            const percentage = ((value / 50000) * 100).toFixed(1);
            small.textContent = `单笔最高限额: ￥50,000 (已使用 ${percentage}%)`;
        }
    }
}

/**
 * 格式化金额显示
 */
function formatAmount(input) {
    if (input.value === '') return;

    const value = parseFloat(input.value);
    if (!isNaN(value)) {
        input.value = value.toFixed(2);
    }
}

/**
 * 初始化金额格式化
 */
function initAmountFormatting() {
    const amountInputs = document.querySelectorAll('input[type="number"][name="amount"]');

    amountInputs.forEach(input => {
        // 只允许输入数字和小数点
        input.addEventListener('keypress', function(e) {
            const char = String.fromCharCode(e.which);
            if (!/[\d.]/.test(char)) {
                e.preventDefault();
            }

            // 只允许一个小数点
            if (char === '.' && this.value.indexOf('.') !== -1) {
                e.preventDefault();
            }
        });

        // 限制小数位数
        input.addEventListener('input', function() {
            const parts = this.value.split('.');
            if (parts.length > 1 && parts[1].length > 2) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });
}

/**
 * 初始化支付方式选择
 */
function initPaymentMethodSelection() {
    const paymentMethods = document.querySelectorAll('.payment-method');

    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            // 清除支付方式错误提示
            const paymentMethodsDiv = document.querySelector('.payment-methods');
            const errorEl = paymentMethodsDiv?.parentElement.querySelector('.error-message');
            if (errorEl) {
                errorEl.remove();
            }

            // 添加视觉反馈
            const methodCard = this.querySelector('.method-card');
            if (methodCard) {
                methodCard.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    methodCard.style.transform = 'scale(1)';
                }, 100);
            }
        });
    });
}

/**
 * 显示错误信息
 */
function showError(element, message) {
    // 移除已存在的错误信息
    const existingError = element.parentElement.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }

    // 创建错误信息元素
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.style.color = '#f5222d';
    errorDiv.style.fontSize = '14px';
    errorDiv.style.marginTop = '5px';
    errorDiv.textContent = message;

    // 添加到父元素
    if (element.parentElement) {
        element.parentElement.appendChild(errorDiv);
    }

    // 添加错误样式到输入框
    if (element.tagName === 'INPUT') {
        element.style.borderColor = '#f5222d';
    }
}

/**
 * 清除所有错误信息
 */
function clearErrors() {
    const errorMessages = document.querySelectorAll('.error-message');
    errorMessages.forEach(error => error.remove());

    // 重置输入框边框颜色
    const inputs = document.querySelectorAll('input[type="number"], input[type="text"], textarea');
    inputs.forEach(input => {
        input.style.borderColor = '';
    });
}

/**
 * 格式化货币显示
 */
function formatCurrency(amount) {
    return '￥' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * 快捷金额选择
 */
function setQuickAmount(amount) {
    const amountInput = document.getElementById('amount');
    if (amountInput) {
        amountInput.value = amount.toFixed(2);
        validateAmount(amountInput);

        // 添加视觉反馈
        amountInput.style.transform = 'scale(1.05)';
        setTimeout(() => {
            amountInput.style.transform = 'scale(1)';
        }, 200);
    }
}

/**
 * 复制交易号到剪贴板
 */
function copyTransactionNo(transactionNo) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(transactionNo).then(() => {
            showToast('交易号已复制到剪贴板', 'success');
        }).catch(() => {
            fallbackCopy(transactionNo);
        });
    } else {
        fallbackCopy(transactionNo);
    }
}

/**
 * 降级方案：复制到剪贴板
 */
function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    try {
        document.execCommand('copy');
        showToast('交易号已复制到剪贴板', 'success');
    } catch (err) {
        showToast('复制失败，请手动复制', 'error');
    }

    document.body.removeChild(textarea);
}

/**
 * 显示提示消息
 */
function showToast(message, type = 'info') {
    // 移除已存在的提示
    const existingToast = document.querySelector('.toast-message');
    if (existingToast) {
        existingToast.remove();
    }

    // 创建提示元素
    const toast = document.createElement('div');
    toast.className = `toast-message toast-${type}`;
    toast.textContent = message;

    // 样式
    Object.assign(toast.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '12px 24px',
        borderRadius: '4px',
        backgroundColor: type === 'success' ? '#52c41a' : type === 'error' ? '#f5222d' : '#1890ff',
        color: 'white',
        fontSize: '14px',
        boxShadow: '0 4px 12px rgba(0, 0, 0, 0.15)',
        zIndex: '9999',
        animation: 'slideInRight 0.3s ease',
        transition: 'opacity 0.3s ease'
    });

    document.body.appendChild(toast);

    // 3秒后自动消失
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 300);
    }, 3000);
}

// 添加动画样式
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;
document.head.appendChild(style);

/**
 * 确认对话框
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * 防抖函数
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * 节流函数
 */
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// 导出函数供其他脚本使用
if (typeof window !== 'undefined') {
    window.PaymentSystem = {
        setQuickAmount,
        copyTransactionNo,
        showToast,
        confirmAction,
        formatCurrency,
        debounce,
        throttle
    };
}
