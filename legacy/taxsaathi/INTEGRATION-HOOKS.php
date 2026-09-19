<?php

declare(strict_types=1);

use App\Core\NotificationService;

/*
 * Existing controllers that already call NotificationService::trigger(...)
 * automatically use RolePushService after installing the supplied facade.
 * These examples cover additional events.
 */

// New regular order: Admin/Manager + owning Client/Partner.
NotificationService::trigger('order_placed', [
    'order_id' => $orderId,
    'order_no' => $orderNo,
    'service' => $serviceTitle,
    'payment_status' => 'waiting_for_payment',
]);

// Order assigned: assigned Executive receives it; Admin/Manager also see the update.
NotificationService::trigger('order_assigned', [
    'order_id' => $orderId,
    'executive_user_id' => $assignedUserId,
    'title' => 'Order assigned to you',
    'message' => 'Order ' . $orderNo . ' has been assigned for processing.',
    'status_label' => 'assigned',
    'url' => '/admin/orders/show?id=' . $orderId,
]);

// Wrong client document: Client sees reason, Partner/Executive see context.
NotificationService::trigger('client_document_wrong', [
    'order_id' => $orderId,
    'order_no' => $orderNo,
    'document_id' => $documentId,
    'document_label' => $documentLabel,
    'reason' => $wrongReason,
]);

// Corrected document: Admin/Manager + assigned Executive are notified.
NotificationService::trigger('client_document_replaced', [
    'order_id' => $orderId,
    'order_no' => $orderNo,
    'document_id' => $oldDocumentId,
    'document_label' => $documentLabel,
    'old_original_name' => $oldName,
    'new_original_name' => $newName,
]);

// Partner order: Admin/Manager + owning Partner.
NotificationService::trigger('partner_order_created', [
    'partner_order_id' => $partnerOrderId,
    'order_no' => $partnerOrderNo,
    'title' => 'New partner order',
    'message' => 'A partner submitted order ' . $partnerOrderNo . '.',
]);

// Direct role broadcast from internal code.
NotificationService::toRoles(
    ['admin', 'manager'],
    'System maintenance',
    'A scheduled maintenance window starts at 10:00 PM.',
    [
        'event_key' => 'system.maintenance',
        'severity' => 'warning',
        'url' => '/notifications',
    ]
);

// Direct notification to one user.
NotificationService::toUser(
    $userId,
    'Action required',
    'Please review the latest update on your order.',
    [
        'event_key' => 'user.action_required',
        'severity' => 'high',
        'order_id' => $orderId,
    ]
);
