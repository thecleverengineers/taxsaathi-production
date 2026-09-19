<?php
declare(strict_types=1);
use App\Controllers\PaymentMethodController;
use App\Controllers\AdminPartnerOrderReviewController;
use App\Controllers\PartnerAssignedOrderController;
use App\Controllers\NotificationManagerController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\SupportChatController;
use App\Controllers\AdvancedReportsController;
use App\Controllers\PushController;
use App\Controllers\AuthController2;
use App\Controllers\AdminPartnerWiseOrderController;
use App\Controllers\AdminExecutivePayoutController;
use App\Controllers\AdminExecutiveController;
use App\Controllers\ExecutivePayoutController;
use App\Controllers\ClientPortalController;
use App\Controllers\AdminSubscriptionPlanController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\OrdersController;
use App\Services\OrderWorkflowReminderService;
use App\Controllers\UiServicesController;
use App\Controllers\AdminStaffController;
use App\Controllers\AdminRolePermissionController;
use App\Controllers\OrderServiceController;
use App\Controllers\SeoController;
use App\Controllers\PagesController;
use App\Controllers\ServiceDetailsController;
use App\Controllers\AdminServiceController;
use App\Controllers\AdminUserController;
use App\Controllers\NotificationController;
use App\Controllers\AdminServiceCategoryController;
use App\Controllers\ClientProfileController;
use App\Controllers\AdminInquiryController;
use App\Controllers\AdminUserRoleController;
use App\Controllers\RazorpayController;
use App\Controllers\ServiceOrderController;
use App\Controllers\RanksController;
use App\Controllers\PartnerProfileController;
use App\Controllers\OrderWorkflowReminderController;
$router = app('router');
$router->get('/admin/users/roles', [AdminUserRoleController::class, 'index']);
$router->get('/admin/users/roles/edit', [AdminUserRoleController::class, 'edit']);
$router->post('/admin/users/roles/update', [AdminUserRoleController::class, 'update']);

$router->get('/admin/partner-wise-orders', [
    \App\Controllers\AdminPartnerWiseOrderController::class,
    'index',
]);

$router->get('/admin/partner-wise-orders/show', [
    \App\Controllers\AdminPartnerWiseOrderController::class,
    'show',
]);
$router->get('/admin/roles/permissions', [AdminUserRoleController::class, 'permissions']);
$router->post('/admin/roles/permissions/update', [AdminUserRoleController::class, 'updatePermissions']);
$router->get('/cron/order-workflow-reminders', [OrderWorkflowReminderController::class, 'run']);
$router->get('/admin/subscription-plans', [\App\Controllers\AdminSubscriptionPlanController::class, 'index']);
$router->post('/admin/subscription-plans/store', [\App\Controllers\AdminSubscriptionPlanController::class, 'store']);
$router->post('/admin/subscription-plans/update', [\App\Controllers\AdminSubscriptionPlanController::class, 'update']);
$router->post('/admin/subscription-plans/delete', [\App\Controllers\AdminSubscriptionPlanController::class, 'delete']);
$router->post('/admin/subscription-plans/toggle', [\App\Controllers\AdminSubscriptionPlanController::class, 'toggle']);

$router->get(
    '/notifications/poll',
    [\App\Controllers\NotificationsController::class, 'poll']
);

$router->post(
    '/notifications/mark-read',
    [\App\Controllers\NotificationsController::class, 'markRead']
);

$router->post(
    '/notifications/mark-unread',
    [\App\Controllers\NotificationsController::class, 'markUnread']
);

$router->post(
    '/notifications/mark-all-read',
    [\App\Controllers\NotificationsController::class, 'markAllRead']
);

$router->get('/admin/partner-orders', [AdminPartnerOrderReviewController::class, 'index']);
$router->get('/admin/partner-orders/show', [AdminPartnerOrderReviewController::class, 'show']);
$router->post('/admin/partner-orders/accept', [AdminPartnerOrderReviewController::class, 'accept']);

$router->get('admin/users/roles', [AdminUserRoleController::class, 'index']);
$router->get('admin/users/roles/edit', [AdminUserRoleController::class, 'edit']);
$router->post('admin/users/roles/update', [AdminUserRoleController::class, 'update']);
$router->get('admin/users/roles/effective', [AdminUserRoleController::class, 'effectivePermissionsJson']);

$router->get('admin/roles/permissions', [AdminUserRoleController::class, 'permissions']);
$router->post('admin/roles/permissions/update', [AdminUserRoleController::class, 'updatePermissions']);
/*
 * Backward fallback.
 * Old forms using /admin/partner-orders/status will still work.
 */
$router->post('/client/orders/reupload-document', [ClientPortalController::class, 'reuploadDocument']);
$router->post('/client/orders/upload-document',[ClientPortalController::class, 'uploadOrderDocument']);
 
 
 $router->get('/support/chat/poll', [SupportChatController::class, 'poll'], [$authOnlyGet ?? null]);
$router->post('/support/chat/send', [SupportChatController::class, 'send'], [$authRequired ?? null]);
$router->post('/support/chat/read', [SupportChatController::class, 'markRead'], [$authRequired ?? null]);
$router->get('/support/chat/attachment', [SupportChatController::class, 'attachment'], [$authOnlyGet ?? null]);

/*
|--------------------------------------------------------------------------
| Support team inbox: Admin / Manager / Telecaller
|--------------------------------------------------------------------------
*/
$router->get('/support', [SupportChatController::class, 'staffIndex'], [$authOnlyGet ?? null]);
$router->get('/support/poll', [SupportChatController::class, 'staffPoll'], [$authOnlyGet ?? null]);
$router->post('/support/reply', [SupportChatController::class, 'staffReply'], [$authRequired ?? null]);
$router->post('/support/update', [SupportChatController::class, 'staffUpdate'], [$authRequired ?? null]);
 
$router->get('/admin/notifications', [NotificationsController::class, 'index']);
$router->post('/admin/notifications/save-settings', [NotificationsController::class, 'saveSettings']);
$router->post('/admin/notifications/templates/store', [NotificationsController::class, 'storeTemplate']);
$router->post('/admin/notifications/templates/delete', [NotificationsController::class, 'deleteTemplate']);
$router->post('/admin/notifications/send-test', [NotificationsController::class, 'sendTest']);

$router->post('/admin/partner-orders/status', [AdminPartnerOrderReviewController::class, 'accept']);

$router->get('notifications/health', [NotificationController::class, 'health']);
$router->get('notifications/poll', [NotificationController::class, 'poll']);
$router->post('notifications/mark-read', [NotificationController::class, 'markRead']);
$router->post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);

/* Optional aliases if your dashboard uses admin-prefixed routes */
$router->get('admin/notifications/health', [NotificationController::class, 'health']);
$router->get('admin/notifications/poll', [NotificationController::class, 'poll']);
$router->post('admin/notifications/mark-read', [NotificationController::class, 'markRead']);
$router->post('admin/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);




/* Notification inbox and realtime APIs */
$router->get('/notifications', [PushController::class, 'index']);
$router->get('/notifications/manage', [PushController::class, 'manage']); // Admin/Manager only
$router->get('/notifications/poll', [PushController::class, 'poll']);
$router->get('/push/stream', [PushController::class, 'stream']);
$router->get('/push/config', [PushController::class, 'config']);
$router->get('/push/health', [PushController::class, 'health']);

/* Must remain at the domain root so Firebase can control the whole site scope. */
$router->get('/firebase-messaging-sw.js', [PushController::class, 'serviceWorker']);

/* Device-token lifecycle */
$router->post('/push/token/register', [PushController::class, 'registerToken']);
$router->post('/push/token/unregister', [PushController::class, 'unregisterToken']);

/* Read state */
$router->post('/notifications/mark-read', [PushController::class, 'markRead']);
$router->post('/notifications/mark-unread', [PushController::class, 'markUnread']);
$router->post('/notifications/mark-all-read', [PushController::class, 'markAllRead']);

/* Sending */
$router->post('/push/send', [PushController::class, 'send']);      // Admin/Manager only
$router->post('/push/test', [PushController::class, 'test']);      // Current user only
/* Partner */
$router->get('/partner/dashboard', [\App\Controllers\PartnerOrderController::class, 'dashboard']);
$router->get('/partner/services', [\App\Controllers\PartnerOrderController::class, 'services']);
$router->get('/partner/orders', [ClientPortalController::class, 'legacyPartnerOrders']);
$router->get('/partner/orders/create', [\App\Controllers\PartnerOrderController::class, 'create']);
$router->post('/partner/orders/store', [\App\Controllers\PartnerOrderController::class, 'store']);
$router->post('/partner/orders/update', [\App\Controllers\PartnerOrderController::class, 'update']);
$router->post('/partner/orders/reupload-document', [\App\Controllers\PartnerOrderController::class, 'reuploadDocument']);
$router->post('/partner/orders/upload-document', [\App\Controllers\PartnerOrderController::class, 'uploadDocument']);
$router->post('/partner/orders/resubmit', [\App\Controllers\PartnerOrderController::class, 'resubmit']);
$router->get('/partner/orders/payment', [\App\Controllers\OrderServiceController::class, 'payment']);
$router->post('/partner/orders/payment/submit', [\App\Controllers\OrderServiceController::class, 'submitPayment']);
$router->get('/partner/orders/show', [ClientPortalController::class, 'legacyPartnerOrderShow']);
$router->get('/partner/orders/download', [\App\Controllers\PartnerOrderController::class, 'download']);
$router->get('/partner/profile', [\App\Controllers\PartnerProfileController::class, 'index']);
$router->post('/partner/profile/update', [\App\Controllers\PartnerProfileController::class, 'update']);
/* Admin partner management - no subscription */
$router->get('/admin/partners', [\App\Controllers\AdminPartnerManagementController::class, 'index']);
$router->get('/admin/partners/show', [\App\Controllers\AdminPartnerManagementController::class, 'show']);
$router->post('/admin/partners/update', [\App\Controllers\AdminPartnerManagementController::class, 'update']);
$router->post('/admin/partners/make-partner', [\App\Controllers\AdminPartnerManagementController::class, 'makePartner']);
$router->get('notifications', [NotificationManagerController::class, 'index']);
$router->get('notifications/feed', [NotificationManagerController::class, 'feed']);
$router->get('notifications/count', [NotificationManagerController::class, 'count']);
$router->post('notifications/mark-read', [NotificationManagerController::class, 'markRead']);
$router->post('notifications/mark-unread', [NotificationManagerController::class, 'markUnread']);
$router->post('notifications/mark-all-read', [NotificationManagerController::class, 'markAllRead']);
$router->get('notifications/open', [NotificationManagerController::class, 'open']);
$router->get('notifications/cron', [NotificationManagerController::class, 'cron']);
$router->get('notifications/health', [NotificationManagerController::class, 'health']);

/* Admin coupons */
$router->get('/admin/partner-coupons', [\App\Controllers\AdminPartnerCouponController::class, 'index']);
$router->post('/admin/partner-coupons/store', [\App\Controllers\AdminPartnerCouponController::class, 'store']);
$router->post('/admin/partner-coupons/toggle', [\App\Controllers\AdminPartnerCouponController::class, 'toggle']);
$router->post('/admin/partner-coupons/delete', [\App\Controllers\AdminPartnerCouponController::class, 'delete']);

/* Admin partner order approval */
$router->get('/admin/partner-orders', [\App\Controllers\AdminPartnerOrderReviewController::class, 'index']);
$router->get('/admin/partner-orders/show', [\App\Controllers\AdminPartnerOrderReviewController::class, 'show']);
$router->post('/admin/partner-orders/status', [\App\Controllers\AdminPartnerOrderReviewController::class, 'updateStatus']);

$router->post('/razorpay/create-order', [\App\Controllers\RazorpayController::class, 'createOrder']);
$router->get('/client/profile', [ClientProfileController::class, 'index']);
$router->post('/client/profile/update', [ClientProfileController::class, 'update']);
$router->post('/client/profile/password', [ClientProfileController::class, 'password']);
$router->get('/admin/service-categories', [\App\Controllers\AdminServiceCategoryController::class, 'index']);
$router->get('/admin/service-categories/create', [\App\Controllers\AdminServiceCategoryController::class, 'create']);
$router->get('/admin/service-categories/edit', [\App\Controllers\AdminServiceCategoryController::class, 'edit']);

$router->post('/admin/service-categories/save', [\App\Controllers\AdminServiceCategoryController::class, 'save']);
$router->post('/admin/service-categories/toggle', [\App\Controllers\AdminServiceCategoryController::class, 'toggle']);
$router->post('/admin/service-categories/delete', [\App\Controllers\AdminServiceCategoryController::class, 'delete']);


$router->get('/admin/partner-wise-orders', [AdminPartnerWiseOrderController::class, 'index']);
$router->get('/admin/partner-wise-orders/show', [AdminPartnerWiseOrderController::class, 'show']);
$router->get('/admin/executives', [AdminExecutiveController::class, 'index']);
$router->get('/admin/executives/show', [AdminExecutiveController::class, 'show']);
$router->post('/admin/executives/update', [AdminExecutiveController::class, 'update']);
$router->post('/admin/executives/toggle', [AdminExecutiveController::class, 'toggle']);
$router->post('/admin/executives/salary/pay', [AdminExecutiveController::class, 'paySalary']);
$router->get('/admin/executive-payouts', [AdminExecutivePayoutController::class, 'index']);
$router->post('/admin/executive-payouts/pay', [AdminExecutivePayoutController::class, 'pay']);
$router->post('/admin/executive-payouts/pay-many', [AdminExecutivePayoutController::class, 'payMany']);
$router->get('/executive/payouts', [ExecutivePayoutController::class, 'index']);
$router->post('/executive/payouts/payment-profile', [ExecutivePayoutController::class, 'savePaymentProfile']);
/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

$router->get('/admin/dashboard', [\App\Controllers\AdminPartnerController::class, 'index']);
$router->get('/admin/partners', [\App\Controllers\AdminPartnerController::class, 'index']);
$router->post('/admin/partners/subscription/store', [\App\Controllers\AdminPartnerController::class, 'storeSubscription']);
$router->post('/admin/partners/subscription/deactivate', [\App\Controllers\AdminPartnerController::class, 'deactivateSubscription']);
$router->post('/admin/orders/assign', [OrdersController::class, 'assign']);
// notification center


$router->get('notifications/poll', [NotificationController::class, 'poll']);
$router->get('notifications/stream', [NotificationController::class, 'stream']);
$router->get('notifications/open', [NotificationController::class, 'open']);

$router->post('notifications/save-token', [NotificationController::class, 'saveToken']);
$router->post('notifications/delete-token', [NotificationController::class, 'deleteToken']);
$router->post('notifications/mark-read', [NotificationController::class, 'markRead']);
$router->post('notifications/mark-unread', [NotificationController::class, 'markUnread']);
$router->post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);

/* Firebase service worker file served by PHP controller. */
$router->get('firebase-messaging-sw.js', [NotificationController::class, 'serviceWorker']);


$router->add( 'GET', '/reports/advanced', [AdvancedReportsController::class, 'index'] ); 
$router->add( 'GET', '/reports/advanced/export', [AdvancedReportsController::class, 'export'] );
/*
|--------------------------------------------------------------------------
| AI Order Workflow Reminder Cron Endpoint
|--------------------------------------------------------------------------
| aaPanel cron should call this route twice daily:
|
| Morning:
| curl -fsS "https://yourdomain.com/notifications/order-workflow-reminders?token=YOUR_SECRET_TOKEN&slot=morning" >/dev/null 2>&1
|
| Evening:
| curl -fsS "https://yourdomain.com/notifications/order-workflow-reminders?token=YOUR_SECRET_TOKEN&slot=evening" >/dev/null 2>&1
|
| Token source priority:
| 1) ORDER_WORKFLOW_REMINDER_TOKEN environment variable
| 2) $_ENV / $_SERVER
| 3) defined('ORDER_WORKFLOW_REMINDER_TOKEN')
| 4) setting('order_workflow_reminder_token')
*/
$router->get('notifications/order-workflow-reminders', [NotificationController::class, 'runOrderWorkflowReminders']);

/* Optional manual POST trigger for admin/server-side use. */
$router->post('notifications/order-workflow-reminders', [NotificationController::class, 'runOrderWorkflowReminders']);
$router->get('/client/orders/invoice-preview', [\App\Controllers\OrdersController::class, 'invoicePreview']);
$router->get('/client/orders/invoice-download', [\App\Controllers\OrdersController::class, 'invoiceDownload']);

$router->get('/client/orders/invoice-preview', [\App\Controllers\OrdersController::class, 'invoicePreview']);
$router->get('/client/orders/invoice-download', [\App\Controllers\OrdersController::class, 'invoiceDownload']);

$router->get('/payment-method', [\App\Controllers\PaymentMethodController::class, 'index']);
$router->post('/payment-method', [\App\Controllers\PaymentMethodController::class, 'store']);

$router->post('/razorpay/create-order', [\App\Controllers\RazorpayController::class, 'createOrder']);
$router->get('/client/dashboard', [ClientPortalController::class, 'index']);

// inquiries
$router->get('/admin/inquiries', [AdminInquiryController::class, 'index']);
$router->get('/admin/inquiries/show', [AdminInquiryController::class, 'show']);
$router->post('/admin/inquiries/mark-read', [AdminInquiryController::class, 'markRead']);
$router->post('/admin/inquiries/mark-unread', [AdminInquiryController::class, 'markUnread']);
$router->post('/admin/inquiries/update-status', [AdminInquiryController::class, 'updateStatus']);
$router->post('/admin/inquiries/delete', [AdminInquiryController::class, 'deleteInquiry']);


$router->get('/admin/service-categories', [AdminServiceCategoryController::class, 'index']);
$router->get('/admin/service-categories/create', [AdminServiceCategoryController::class, 'create']);
$router->post('/admin/service-categories/save', [AdminServiceCategoryController::class, 'save']);

$router->get('/admin/service-categories/edit', [AdminServiceCategoryController::class, 'edit']);
$router->post('/admin/service-categories/toggle', [AdminServiceCategoryController::class, 'toggle']);
$router->post('/admin/service-categories/delete', [AdminServiceCategoryController::class, 'delete']);
// orders
$router->get('/admin/orders', [OrdersController::class, 'index']);
$router->get('/admin/orders/show', [OrdersController::class, 'show']);
$router->post('/admin/orders/approve', [OrdersController::class, 'approve']);
$router->post('/admin/orders/update-status', [OrdersController::class, 'updateStatus']);
/* Wrong client document: admin/manager/assigned executive marks upload as wrong */
$router->post('/admin/orders/mark-client-document-wrong', [OrdersController::class, 'markClientDocumentWrong']);
$router->post('/admin/orders/upload-output', [OrdersController::class, 'uploadOutput']);
$router->post('/admin/orders/store-invoice', [OrdersController::class, 'storeInvoice']);
$router->post('/admin/orders/store-payment', [OrdersController::class, 'storePayment']);
$router->get('/admin/orders/download', [OrdersController::class, 'download']);
$router->get('/admin/users', [AdminUserController::class, 'index']);
$router->post('/admin/users/save-user', [AdminUserController::class, 'saveUser']);
$router->post('/admin/users/delete-user', [AdminUserController::class, 'deleteUser']);
$router->post('/admin/users/toggle-user', [AdminUserController::class, 'toggleUser']);

$router->get('/admin/services', [AdminServiceController::class, 'index']);
$router->get('/admin/services/manage', [AdminServiceController::class, 'manage']);

/*
|--------------------------------------------------------------------------
| Grow Ranking / Rank Management
|--------------------------------------------------------------------------
| Full ranking tracker module for SEO growth.
*/
$router->get('/admin/seo', [SeoController::class, 'manage']);
$router->get('/admin/seo-keywords', [SeoController::class, 'manage']);
$router->post('/admin/seo/save', [SeoController::class, 'save']);
$router->post('/admin/seo-keywords/save', [SeoController::class, 'save']);
$router->post('/admin/seo/toggle', [SeoController::class, 'toggle']);
$router->post('/admin/seo-keywords/toggle', [SeoController::class, 'toggle']);
$router->post('/admin/seo/delete', [SeoController::class, 'delete']);
$router->post('/admin/seo-keywords/delete', [SeoController::class, 'delete']);
$router->post('/admin/seo/generate-from-services', [SeoController::class, 'generateFromServices']);
$router->post('/admin/seo-keywords/generate-from-services', [SeoController::class, 'generateFromServices']);

$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);
$router->get('/sitemap-services.xml', [SeoController::class, 'sitemapServices']);
$router->get('/robots.txt', [SeoController::class, 'robots']);

$router->get('/admin/ranks', [RanksController::class, 'index']);
$router->post('/admin/ranks/save-keyword', [RanksController::class, 'saveKeyword']);
$router->post('/admin/ranks/toggle-keyword', [RanksController::class, 'toggleKeyword']);
$router->post('/admin/ranks/delete-keyword', [RanksController::class, 'deleteKeyword']);
$router->post('/admin/ranks/save-log', [RanksController::class, 'saveLog']);
$router->post('/admin/ranks/delete-log', [RanksController::class, 'deleteLog']);
$router->post('/admin/ranks/save-task', [RanksController::class, 'saveTask']);
$router->post('/admin/ranks/delete-task', [RanksController::class, 'deleteTask']);
$router->post('/admin/ranks/generate-from-services', [RanksController::class, 'generateFromServices']);

/*
|--------------------------------------------------------------------------
| Backward-compatible Grow Ranking URLs
|--------------------------------------------------------------------------
*/
$router->get('/admin/grow-ranking', [RanksController::class, 'index']);
$router->post('/admin/grow-ranking/save-keyword', [RanksController::class, 'saveKeyword']);
$router->post('/admin/grow-ranking/toggle-keyword', [RanksController::class, 'toggleKeyword']);
$router->post('/admin/grow-ranking/delete-keyword', [RanksController::class, 'deleteKeyword']);
$router->post('/admin/grow-ranking/save-log', [RanksController::class, 'saveLog']);
$router->post('/admin/grow-ranking/delete-log', [RanksController::class, 'deleteLog']);
$router->post('/admin/grow-ranking/save-task', [RanksController::class, 'saveTask']);
$router->post('/admin/grow-ranking/delete-task', [RanksController::class, 'deleteTask']);
$router->post('/admin/grow-ranking/generate-from-services', [RanksController::class, 'generateFromServices']);


$router->post('/admin/services/save-service', [AdminServiceController::class, 'saveService']);
$router->post('/admin/services/delete-service', [AdminServiceController::class, 'deleteService']);

$router->post('/admin/services/save-banner', [AdminServiceController::class, 'saveBanner']);
$router->post('/admin/services/delete-banner', [AdminServiceController::class, 'deleteBanner']);

$router->post('/admin/services/save-benefit', [AdminServiceController::class, 'saveBenefit']);
$router->post('/admin/services/delete-benefit', [AdminServiceController::class, 'deleteBenefit']);

$router->post('/admin/services/save-type', [AdminServiceController::class, 'saveType']);
$router->post('/admin/services/delete-type', [AdminServiceController::class, 'deleteType']);

$router->post('/admin/services/save-requirement', [AdminServiceController::class, 'saveRequirement']);
$router->post('/admin/services/delete-requirement', [AdminServiceController::class, 'deleteRequirement']);
$router->get('/service-category', [HomeController::class, 'serviceCategory']);
$router->post('/admin/services/save-review', [AdminServiceController::class, 'saveReview']);
$router->post('/admin/services/delete-review', [AdminServiceController::class, 'deleteReview']);
$router->get('/service-category', [HomeController::class, 'serviceCategory']);
$router->get('/contact', [PagesController::class, 'contact']);
$router->post('/contact', [PagesController::class, 'submitContact']);
$router->get('/terms-conditions', [PagesController::class, 'terms']);
$router->get('/about', [PagesController::class, 'about']);
$router->get('/privacy-policy', [PagesController::class, 'privacyPolicy']);
$router->get('/refund-policy', [PagesController::class, 'refundPolicy']);
$router->get('/payment-method', [PaymentMethodController::class, 'show']);
$router->post('/payment-method', [PaymentMethodController::class, 'store']);
$router->get('/', [HomeController::class, 'index']);
$router->get('/contact', [HomeController::class, 'contact']);
$router->get('/services', [HomeController::class, 'services']);
$router->post('/contact-submit', [HomeController::class, 'contactSubmit']);
$router->get('/tax-calculators', [HomeController::class, 'calculators']);
$router->post('/tax-calculators', [HomeController::class, 'calculators']);

$router->get('/service-details', [ServiceDetailsController::class, 'show']);
$router->post('/service-apply', [ServiceDetailsController::class, 'submit']);

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
$router->get('/auth', [AuthController::class, 'showAuth']);
$router->post('/auth/register/send-otp', [AuthController::class, 'sendRegisterOtp']);
$router->post('/auth/register/resend-otp', [AuthController::class, 'resendRegisterOtp']);
$router->get('/auth/verify', [AuthController::class, 'showVerify']);
$router->post('/auth/verify', [AuthController::class, 'verifyRegisterOtp']);
$router->post('/auth/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

/* Guest password recovery */
$router->get('/auth/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/auth/forgot-password/send-otp', [AuthController::class, 'sendForgotPasswordOtp']);
$router->get('/auth/forgot-password/verify', [AuthController::class, 'showForgotPasswordVerify']);
$router->post('/auth/forgot-password/verify', [AuthController::class, 'verifyForgotPasswordOtp']);
$router->post('/auth/forgot-password/resend-otp', [AuthController::class, 'resendForgotPasswordOtp']);
$router->get('/auth/reset-password', [AuthController::class, 'showResetPassword']);
$router->post('/auth/reset-password', [AuthController::class, 'resetPassword']);

/* Logged-in password change */
$router->get('/auth/change-password', [AuthController::class, 'showChangePassword']);
$router->post('/auth/change-password', [AuthController::class, 'changePassword']);

$router->get('/auth', [AuthController2::class, 'showAuth']);
$router->post('/auth/register/send-otp', [AuthController2::class, 'sendRegisterOtp']);
$router->post('/auth/register/resend-otp', [AuthController2::class, 'resendRegisterOtp']);
$router->get('/auth/verify', [AuthController2::class, 'showVerify']);
$router->post('/auth/verify', [AuthController2::class, 'verifyRegisterOtp']);
$router->post('/auth/login', [AuthController2::class, 'login']);
$router->post('/logout', [AuthController2::class, 'logout']);
/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/
$router->get('/dashboard', [DashboardController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Client Portal
|--------------------------------------------------------------------------
*/
$router->get('/client/orders', [ClientPortalController::class, 'index']);
$router->get('/client/orders/view', [ClientPortalController::class, 'show']);
$router->get('/client/orders/download', [ClientPortalController::class, 'download']);
$router->get('/client/orders/payment', [OrderServiceController::class, 'payment']);
$router->post('/client/orders/payment/submit', [OrderServiceController::class, 'submitPayment']);
/* Wrong client document: client re-uploads corrected document */
$router->post('/client/orders/reupload-document', [ClientPortalController::class, 'reuploadDocument']);
$router->post('/client/orders/update', [ClientPortalController::class, 'update']);
$router->post('/client/orders/resubmit', [ClientPortalController::class, 'resubmit']);

/*
|--------------------------------------------------------------------------
| Orders Admin
|--------------------------------------------------------------------------
*/
$router->get('/admin/orders', [OrdersController::class, 'index']);
$router->get('/admin/orders/show', [OrdersController::class, 'show']);
$router->post('/admin/orders/approve', [OrdersController::class, 'approve']);
$router->post('/admin/orders/update-status', [OrdersController::class, 'updateStatus']);
$router->post('/admin/orders/upload-output', [OrdersController::class, 'uploadOutput']);
$router->post('/admin/orders/save-invoice', [OrdersController::class, 'storeInvoice']);
$router->post('/admin/orders/save-payment', [OrdersController::class, 'storePayment']);
$router->get('/admin/orders/download', [OrdersController::class, 'download']);

/*
|--------------------------------------------------------------------------
| Content Admin
|--------------------------------------------------------------------------
*/
$router->get('/admin/content', [AdminController::class, 'content']);
$router->post('/admin/content/save-settings', [AdminController::class, 'saveSettings']);
$router->post('/admin/content/save-service', [AdminController::class, 'saveService']);
$router->post('/admin/content/delete-service', [AdminController::class, 'deleteService']);
$router->post('/admin/content/save-requirement', [AdminController::class, 'saveRequirement']);
$router->post('/admin/content/delete-requirement', [AdminController::class, 'deleteRequirement']);
$router->post('/admin/content/save-faq', [AdminController::class, 'saveFaq']);
$router->post('/admin/content/delete-faq', [AdminController::class, 'deleteFaq']);
$router->post('/admin/content/save-testimonial', [AdminController::class, 'saveTestimonial']);
$router->post('/admin/content/delete-testimonial', [AdminController::class, 'deleteTestimonial']);

/*
|--------------------------------------------------------------------------
| Staff Admin
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Role Permissions Admin
|--------------------------------------------------------------------------
| Dedicated controller for role create/edit/delete and permission mapping.
*/
$router->get('/admin/role-permissions', [AdminRolePermissionController::class, 'index']);
$router->get('/admin/role-permissions/create', [AdminRolePermissionController::class, 'create']);
$router->get('/admin/role-permissions/edit', [AdminRolePermissionController::class, 'edit']);
$router->post('/admin/role-permissions/store', [AdminRolePermissionController::class, 'store']);
$router->post('/admin/role-permissions/update', [AdminRolePermissionController::class, 'update']);
$router->post('/admin/role-permissions/delete', [AdminRolePermissionController::class, 'delete']);

/*
|--------------------------------------------------------------------------
| Staff Admin
|--------------------------------------------------------------------------
| Dedicated controller for staff create/edit/delete/toggle.
*/
$router->get('/admin/staff', [AdminStaffController::class, 'index']);
$router->get('/admin/staff/create', [AdminStaffController::class, 'create']);
$router->get('/admin/staff/edit', [AdminStaffController::class, 'edit']);
$router->post('/admin/staff/store', [AdminStaffController::class, 'store']);
$router->post('/admin/staff/update', [AdminStaffController::class, 'update']);
$router->post('/admin/staff/toggle', [AdminStaffController::class, 'toggle']);
$router->post('/admin/staff/delete', [AdminStaffController::class, 'delete']);
/*
|--------------------------------------------------------------------------
| Calculator Admin
|--------------------------------------------------------------------------
*/
$router->get('/admin/calculators', [AdminController::class, 'calculators']);
$router->post('/admin/calculators/save-settings', [AdminController::class, 'saveCalcSettings']);
$router->post('/admin/calculators/save-rule', [AdminController::class, 'saveTaxRule']);
$router->post('/admin/calculators/delete-rule', [AdminController::class, 'deleteTaxRule']);

/*
|--------------------------------------------------------------------------
| Notifications Admin
|--------------------------------------------------------------------------
*/
$router->get('/admin/notifications', [AdminController::class, 'notifications']);
$router->post('/admin/notifications/save-settings', [AdminController::class, 'saveNotificationSettings']);
$router->post('/admin/notifications/save-template', [AdminController::class, 'saveNotificationTemplate']);
$router->post('/admin/notifications/test-otp', [AdminController::class, 'sendTestOtp']);

$router->post('/logout', [\App\Controllers\AuthController::class, 'logout']);
$router->get('/logout', [\App\Controllers\AuthController::class, 'logout']);

/*
|--------------------------------------------------------------------------
| Service Order - Final Routes
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Inline Auth for Order Page
| Use unique URLs so router does not return service-order HTML.
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| Service Order Inline Auth
|--------------------------------------------------------------------------
*/

$router->get('/order-inline-ping', [\App\Controllers\OrderServiceController::class, 'inlinePing']);
$router->post('/order-inline-login', [\App\Controllers\OrderServiceController::class, 'inlineLogin']);
$router->post('/order-inline-signup', [\App\Controllers\OrderServiceController::class, 'inlineSignup']);
$router->post('/order-inline-login', [OrderServiceController::class, 'inlineLogin']);
$router->post('/order-inline-signup', [OrderServiceController::class, 'inlineSignup']);
$router->get('/order-inline-ping', [OrderServiceController::class, 'inlinePing']);

/*
|--------------------------------------------------------------------------
| Partner Assigned Orders
|--------------------------------------------------------------------------
*/
$router->get('/partner/assigned-orders', [PartnerAssignedOrderController::class, 'index']);
$router->get('/partner/assigned-orders-show', [PartnerAssignedOrderController::class, 'show']);

$router->post('/partner/assigned-orders/status', [PartnerAssignedOrderController::class, 'updateStatus']);
$router->post('/partner/assigned-orders/upload-output', [PartnerAssignedOrderController::class, 'uploadOutput']);
$router->post('/partner/assigned-orders/mark-document-wrong', [PartnerAssignedOrderController::class, 'markClientDocumentWrong']);
$router->get('/partner/assigned-orders/download', [PartnerAssignedOrderController::class, 'download']);
 $router->get('/notifications/health', [NotificationController::class, 'health']);
$router->get('/notifications/poll', [NotificationController::class, 'poll']);
$router->post('/notifications/mark-read', [NotificationController::class, 'markRead']);
$router->post('/notifications/mark-unread', [NotificationController::class, 'markUnread']);
$router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
$router->get('/service-order', [\App\Controllers\OrderServiceController::class, 'orderForm']);
$router->post('/service-order', [\App\Controllers\OrderServiceController::class, 'store']);
$router->get('/service-order/payment', [\App\Controllers\OrderServiceController::class, 'payment']);
$router->post('/service-order/payment', [\App\Controllers\OrderServiceController::class, 'submitPayment']);
$router->post('/auth/logout', [\App\Controllers\AuthController::class, 'logout']);
$router->get('/auth/logout', [\App\Controllers\AuthController::class, 'logout']);
$router->get('/admin/reports', [AdminController::class, 'reports']);