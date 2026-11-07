<?php

/**
 * 导入模板配置文件
 *
 * 每个模板是一个关联数组：[源字段名(中文表头) => 目标字段名(英文)]
 * 支持多个模板，导入时自动根据文件表头匹配匹配度最高的模板
 */

return [
    // 模板 0：简单订单模板
    [
        '订单号' => 'order_no',
        '收件人' => 'receiver_name',
        '电话'   => 'receiver_phone',
        '省'     => 'receiver_province',
        '市'     => 'receiver_city',
        '区'     => 'receiver_district',
        '地址'   => 'receiver_address',
        '邮编'   => 'receiver_postal_code',
        '重量'   => 'weight',
        '运费'   => 'total_amount',
        '备注'   => 'remark',
    ],

    // 模板 1：物流追踪模板
    [
        '关联单号'               => 'order_no',
        '追踪单号'               => 'tracking_number',
        '发件人'                 => 'shipper_name',
        '发件人地址'             => 'shipper_address',
        '发件人电话'             => 'shipper_phone',
        '发件人国家'             => 'shipper_country',
        '发件人城市'             => 'shipper_city',
        '收件人'                 => 'receiver_name',
        '身份证号码'             => 'receiver_id_number',
        '收件人电话'             => 'receiver_phone',
        '城市'                   => 'receiver_city',
        '省份'                   => 'receiver_province',
        '地区'                   => 'receiver_district',
        '邮编'                   => 'receiver_postal_code',
        '收件人地址'             => 'receiver_address',
        '本包裹总毛重量(KG)'     => 'weight',
        '币别'                   => 'currency',
        '代付税金(Y/N)'         => 'is_tax_paid',
        '箱数'                   => 'boxes',
        '国家'                   => 'receiver_country',
    ],
];
