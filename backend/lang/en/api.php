<?php

return [
    // General
    'success' => 'Operation successful',
    'error' => 'Server error',
    'created' => 'Created successfully',
    'updated' => 'Updated successfully',
    'deleted' => 'Deleted successfully',
    'not_found' => 'Resource not found',
    'forbidden' => 'Access denied',
    'unauthorized' => 'Unauthenticated, please login',
    'method_not_allowed' => 'Method not allowed',

    // Auth
    'login_success' => 'Login successful',
    'logout_success' => 'Logged out successfully',
    'invalid_credentials' => 'Invalid username or password',
    'account_disabled' => 'Account has been disabled',
    'token_expired' => 'Token expired, please login again',

    // User
    'status_updated' => 'Status updated successfully',
    'password_reset' => 'Password reset successfully',

    // Role
    'menu_assigned' => 'Menus assigned successfully',

    // Menu
    'menu_has_children' => 'Cannot delete menu with sub-menus',

    // Dept
    'dept_has_children' => 'Cannot delete department with sub-departments',

    // Notice
    'notice_published' => 'Published successfully',
    'notice_revoked' => 'Revoked successfully',

    // Tenant
    'tenant_disabled' => 'Tenant has been disabled',
    'tenant_expired' => 'Tenant has expired',

    // Shop resolution
    'shop_not_resolved' => 'Unable to resolve shop from request host',
    'shop_not_found' => 'Shop not found or disabled',

    // Plans
    'plan_in_use' => 'This plan is in use by tenants and cannot be deleted',

    // Exchange rates
    'no_rates_supplied' => 'No exchange rates supplied',
    'sync_dispatched' => 'Sync job dispatched',

    // Mall categories
    'category_has_children' => 'Cannot delete category with sub-categories',
    'category_has_products' => 'Cannot delete category with associated products',
    'category_cycle' => 'Parent category cannot be self or descendant',
    'invalid_parent_category' => 'Parent category does not exist or belongs to another tenant',

    // Mall brands
    'brand_has_products' => 'Cannot delete brand with associated products',

    // Mall carts
    'cart_identity_required' => 'Cart requires user identity (customer_id or session_id)',
    'invalid_cart_quantity' => 'Cart item quantity must be greater than 0',
    'product_variant_not_found' => 'SKU variant not found or belongs to another tenant',
    'cart_not_found' => 'Cart not found',
    'cart_is_empty' => 'Cart is empty, cannot create order',

    // Mall orders
    'order_not_found' => 'Order not found',
    'invalid_order_status_transition' => 'Order status transition not allowed',

    // Mall inventory
    'insufficient_stock' => 'Insufficient stock to reserve',

    // Mall shipping
    'shipping_rate_zone_required' => 'Shipping rate must specify a zone',
    'shipping_rate_zone_not_found' => 'Specified zone does not exist',
    'shipping_rate_weight_range_invalid' => 'Shipping rate weight_max must be greater than weight_min (or 0 for unlimited)',
    'order_cannot_ship' => 'Order status does not allow shipping',
    'shipment_carrier_tracking_required' => 'Shipment requires carrier and tracking_no',
    'shipment_cannot_deliver' => 'Shipment status does not allow marking as delivered',
    'shipment_cannot_cancel' => 'Shipment status does not allow cancellation',

    // Mall customers
    'customer_group_not_found' => 'Customer group not found or belongs to another tenant',
    'customer_group_has_customers' => 'Cannot delete group with associated customers',
    'tenant_required' => 'Tenant identifier (X-Tenant-Id) is required',
    'verification_code_invalid' => 'Verification code is invalid or expired',
    'verification_code_sent' => 'Verification code sent',
    'code_target_required' => 'Verification code target is required',
    'code_type_invalid' => 'Code type must be email or phone',
    'account_already_exists' => 'Account already exists, please log in',
    'register_success' => 'Registration successful',

    // Mall payment / refund
    'payment_method_not_found' => 'Payment method not found or disabled',
    'payment_driver_unavailable' => 'Payment driver is unavailable',
    'order_cannot_refund' => 'Order status does not allow refund',
    'no_payment_to_refund' => 'No refundable payment found for this order',
    'invalid_refund_amount' => 'Refund amount must be greater than 0 and not exceed original payment',
    'refund_failed' => 'Refund failed',
];
