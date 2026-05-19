<?php

return [
    // 通用
    'success' => '操作成功',
    'error' => '服务器错误',
    'created' => '创建成功',
    'updated' => '更新成功',
    'deleted' => '删除成功',
    'not_found' => '资源不存在',
    'forbidden' => '无权限访问',
    'unauthorized' => '未认证，请先登录',
    'method_not_allowed' => '请求方法不允许',

    // 认证
    'login_success' => '登录成功',
    'logout_success' => '退出成功',
    'invalid_credentials' => '用户名或密码错误',
    'account_disabled' => '账号已被禁用',
    'token_expired' => 'Token 已过期，请重新登录',

    // 用户
    'status_updated' => '状态更新成功',
    'password_reset' => '密码重置成功',

    // 角色
    'menu_assigned' => '菜单分配成功',

    // 菜单
    'menu_has_children' => '存在子菜单，不能删除',

    // 部门
    'dept_has_children' => '存在子部门，不能删除',

    // 公告
    'notice_published' => '发布成功',
    'notice_revoked' => '撤回成功',

    // 租户
    'tenant_disabled' => '租户已被禁用',
    'tenant_expired' => '租户已过期',

    // 店铺解析
    'shop_not_resolved' => '无法从请求 Host 识别店铺',
    'shop_not_found' => '店铺不存在或已关闭',

    // 套餐
    'plan_in_use' => '该套餐已被租户使用，不能删除',

    // 汇率
    'no_rates_supplied' => '未提供汇率数据',
    'sync_dispatched' => '同步任务已发起',

    // 商城类目
    'category_has_children' => '存在子类目，不能删除',
    'category_has_products' => '类目下存在商品，不能删除',
    'category_cycle' => '父类目不能是自身或子类目',
    'invalid_parent_category' => '父类目不存在或属于其他租户',

    // 商城品牌
    'brand_has_products' => '品牌下存在商品，不能删除',

    // 商城购物车
    'cart_identity_required' => '购物车需要指定用户身份 (customer_id 或 session_id)',
    'invalid_cart_quantity' => '购物车数量必须大于 0',
    'product_variant_not_found' => 'SKU 变体不存在或属于其他租户',
    'cart_not_found' => '购物车不存在',
    'cart_is_empty' => '购物车为空，无法下单',

    // 商城订单
    'order_not_found' => '订单不存在',
    'invalid_order_status_transition' => '订单状态不允许该转移',

    // 商城库存
    'insufficient_stock' => '库存不足，无法预占',

    // 商城支付 / 退款
    'payment_method_not_found' => '支付方式不存在或未启用',
    'payment_driver_unavailable' => '支付驱动暂不可用',
    'order_cannot_refund' => '订单当前状态不允许退款',
    'no_payment_to_refund' => '订单没有可退款的支付记录',
    'invalid_refund_amount' => '退款金额必须大于 0 且不超过原支付金额',
    'refund_failed' => '退款失败',
];
