<?php
/**
 * 支付网关接口
 */
interface PaymentGateway {
    /**
     * 创建支付订单
     * @param array $orderData 订单数据
     * @return array 支付结果
     */
    public function createPayment(array $orderData): array;

    /**
     * 查询支付状态
     * @param string $transactionNo 交易号
     * @return array 支付状态
     */
    public function queryPayment(string $transactionNo): array;

    /**
     * 验证签名
     * @param array $data 数据
     * @param string $signature 签名
     * @return bool 是否有效
     */
    public function verifySignature(array $data, string $signature): bool;

    /**
     * 生成签名
     * @param array $data 数据
     * @return string 签名
     */
    public function generateSignature(array $data): string;
}
