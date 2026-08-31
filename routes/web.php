<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\Admin\AdminCatalogController;
use App\Controllers\Admin\AdminCmsController;
use App\Controllers\Admin\AdminContentController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AdminEverflowController;
use App\Controllers\Admin\AdminGeoController;
use App\Controllers\Admin\AdminPickController;
use App\Controllers\Admin\AdminPlanController;
use App\Controllers\Admin\AdminSubscriptionController;
use App\Controllers\Admin\AdminSyncController;
use App\Controllers\Admin\AdminTransactionController;
use App\Controllers\Admin\AdminUserController;
use App\Controllers\AuthController;
use App\Controllers\CheckoutController;
use App\Controllers\ContentController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\PageController;
use App\Controllers\PerformanceController;
use App\Controllers\PickController;
use App\Controllers\PricingController;
use App\Controllers\ThankYouController;
use App\Controllers\WebhookController;
use App\Controllers\EverflowController;

/** @var \App\Core\Router $router */

$router->get('/', [HomeController::class, 'index']);
$router->get('/how-it-works', [PageController::class, 'howItWorks']);
$router->get('/picks', [PickController::class, 'index']);
$router->get('/playbook', [PickController::class, 'playbook']);
$router->get('/picks/{slug}', [PickController::class, 'show']);
$router->get('/results', [PickController::class, 'results']);
$router->get('/performance', [PerformanceController::class, 'index']);
$router->get('/the-playbook', [PricingController::class, 'index']);
$router->get('/theplaybook-store', [PricingController::class, 'index']);
$router->get('/pricing', [PricingController::class, 'index']);
$router->get('/about', [PageController::class, 'about']);
$router->get('/affiliates', [PageController::class, 'affiliates']);
$router->get('/affiliate', [PageController::class, 'affiliates']);
$router->get('/faq', [ContentController::class, 'faq']);
$router->get('/contact', [ContentController::class, 'contact']);
$router->post('/contact', [ContentController::class, 'submitContact'], ['csrf', 'throttle:8,300']);
$router->get('/privacy', [PageController::class, 'privacy']);
$router->get('/terms', [PageController::class, 'terms']);
$router->get('/disclaimer', [PageController::class, 'disclaimer']);
$router->get('/cookies', [PageController::class, 'cookies']);

$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest', 'csrf', 'throttle:8,300']);
$router->get('/register', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/register', [AuthController::class, 'register'], ['guest', 'csrf', 'throttle:6,300']);
$router->get('/auth/discord', [AuthController::class, 'discordRedirect'], ['guest', 'throttle:12,300']);
$router->get('/auth/discord/callback', [AuthController::class, 'discordCallback'], ['guest', 'throttle:12,300']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);
$router->get('/forgot-password', [AuthController::class, 'showForgot'], ['guest']);
$router->post('/forgot-password', [AuthController::class, 'forgot'], ['guest', 'csrf', 'throttle:5,300']);
$router->get('/reset-password', [AuthController::class, 'showReset'], ['guest']);
$router->post('/reset-password', [AuthController::class, 'reset'], ['guest', 'csrf']);
$router->get('/verify-email', [AuthController::class, 'verify']);

        $router->post('/checkout/start', [CheckoutController::class, 'start'], ['csrf', 'throttle:12,120']);
        $router->post('/checkout/paypal/create-order', [CheckoutController::class, 'paypalCreateOrder'], ['csrf', 'throttle:12,120']);
        $router->post('/checkout/paypal/capture-order', [CheckoutController::class, 'paypalCaptureOrder'], ['csrf', 'throttle:12,120']);
        $router->get('/checkout/status', [CheckoutController::class, 'status'], ['throttle:40,60']);
$router->get('/checkout/complete', [CheckoutController::class, 'complete']);
$router->get('/thank-you', [ThankYouController::class, 'index']);
$router->post('/everflow/ingest', [EverflowController::class, 'ingest'], ['throttle:60,60']);
$router->post('/webhooks/upgrade-chat', [WebhookController::class, 'upgradeChat']);
$router->get('/webhooks/upgrade-chat', [WebhookController::class, 'upgradeChatPing']);
$router->post('/webhooks/upgradechat', [WebhookController::class, 'upgradeChat']);
$router->get('/webhooks/upgradechat', [WebhookController::class, 'upgradeChatPing']);
$router->post('/api/webhook-upgradechat', [WebhookController::class, 'upgradeChat']);
$router->get('/api/webhook-upgradechat', [WebhookController::class, 'upgradeChatPing']);
$router->post('/api/webhook-upgradechat.php', [WebhookController::class, 'upgradeChat']);
$router->get('/api/webhook-upgradechat.php', [WebhookController::class, 'upgradeChatPing']);

$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
$router->get('/dashboard/picks', [DashboardController::class, 'picks'], ['auth']);
$router->get('/dashboard/results', [DashboardController::class, 'results'], ['auth']);
$router->get('/account/settings', [AccountController::class, 'settings'], ['auth']);
$router->post('/account/settings', [AccountController::class, 'updateSettings'], ['auth', 'csrf']);
$router->post('/account/password', [AccountController::class, 'changePassword'], ['auth', 'csrf']);
$router->post('/account/password/reset-request', [AccountController::class, 'requestPasswordReset'], ['auth', 'csrf', 'throttle:5,300']);
$router->post('/account/delete', [AccountController::class, 'deleteAccount'], ['auth', 'csrf']);
$router->get('/account/subscription', [AccountController::class, 'subscription'], ['auth']);
$router->post('/account/subscription', [AccountController::class, 'updateSubscription'], ['auth', 'csrf']);

$router->get('/admin', [AdminDashboardController::class, 'index'], ['auth', 'admin']);
$router->get('/admin/users', [AdminUserController::class, 'index'], ['auth', 'admin', 'role:admin,super_admin']);
$router->get('/admin/users/export-csv', [AdminUserController::class, 'exportCsv'], ['auth', 'admin', 'role:admin,super_admin']);
$router->get('/admin/users/{id}', [AdminUserController::class, 'show'], ['auth', 'admin', 'role:admin,super_admin']);
$router->post('/admin/users/{id}', [AdminUserController::class, 'update'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/users/{id}/suspend', [AdminUserController::class, 'suspend'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/users/{id}/unsuspend', [AdminUserController::class, 'unsuspend'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/users/{id}/delete', [AdminUserController::class, 'destroy'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/users/{id}/restore', [AdminUserController::class, 'restore'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);

$router->get('/admin/transactions', [AdminTransactionController::class, 'index'], ['auth', 'admin', 'role:admin,super_admin']);
$router->get('/admin/transactions/export-csv', [AdminTransactionController::class, 'exportCsv'], ['auth', 'admin', 'role:admin,super_admin']);

$router->get('/admin/plans', [AdminPlanController::class, 'index'], ['auth', 'admin', 'role:admin,super_admin']);
$router->get('/admin/plans/export-csv', [AdminPlanController::class, 'exportCsv'], ['auth', 'admin', 'role:admin,super_admin']);
$router->post('/admin/plans/store', [AdminPlanController::class, 'store'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/plans/{id}/update', [AdminPlanController::class, 'update'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/plans/{id}/toggle-status', [AdminPlanController::class, 'toggleStatus'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/plans/{id}/delete', [AdminPlanController::class, 'destroy'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);

$router->get('/admin/cms', [AdminCmsController::class, 'index'], ['auth', 'admin', 'role:admin,super_admin']);
$router->post('/admin/cms', [AdminCmsController::class, 'update'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);

$router->get('/admin/picks', [AdminPickController::class, 'index'], ['auth', 'admin']);
$router->get('/admin/picks/create', [AdminPickController::class, 'create'], ['auth', 'admin']);
$router->post('/admin/picks', [AdminPickController::class, 'store'], ['auth', 'admin', 'csrf']);
$router->get('/admin/picks/{id}/edit', [AdminPickController::class, 'edit'], ['auth', 'admin']);
$router->post('/admin/picks/{id}', [AdminPickController::class, 'update'], ['auth', 'admin', 'csrf']);
$router->post('/admin/picks/{id}/update', [AdminPickController::class, 'update'], ['auth', 'admin', 'csrf']);
$router->post('/admin/picks/{id}/toggle-status', [AdminPickController::class, 'toggleStatus'], ['auth', 'admin', 'csrf']);
$router->post('/admin/picks/{id}/delete', [AdminPickController::class, 'destroy'], ['auth', 'admin', 'csrf']);
$router->post('/admin/picks/{id}/restore', [AdminPickController::class, 'restore'], ['auth', 'admin', 'csrf']);

$router->get('/admin/events', [AdminCatalogController::class, 'events'], ['auth', 'admin']);
$router->post('/admin/events/{id}/toggle-status', [AdminCatalogController::class, 'toggleEventStatus'], ['auth', 'admin', 'csrf']);
$router->post('/admin/events/{id}/delete', [AdminCatalogController::class, 'destroyEvent'], ['auth', 'admin', 'csrf']);

$router->get('/admin/subscriptions', [AdminSubscriptionController::class, 'index'], ['auth', 'admin', 'role:admin,super_admin']);
$router->post('/admin/subscriptions/plans', [AdminSubscriptionController::class, 'storePlan'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/subscriptions/plans/{id}', [AdminSubscriptionController::class, 'updatePlan'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);

$router->get('/admin/faqs', [AdminContentController::class, 'faqs'], ['auth', 'admin']);
$router->post('/admin/faqs', [AdminContentController::class, 'storeFaq'], ['auth', 'admin', 'csrf']);
$router->post('/admin/faqs/{id}/delete', [AdminContentController::class, 'deleteFaq'], ['auth', 'admin', 'csrf']);
$router->get('/admin/messages', [AdminContentController::class, 'messages'], ['auth', 'admin']);
$router->post('/admin/messages/{id}', [AdminContentController::class, 'updateMessage'], ['auth', 'admin', 'csrf']);
$router->get('/admin/settings', [AdminContentController::class, 'settings'], ['auth', 'admin', 'role:admin,super_admin']);
$router->post('/admin/settings', [AdminContentController::class, 'updateSettings'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/legal', [AdminContentController::class, 'updateLegal'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);

$router->get('/admin/geo', [AdminGeoController::class, 'index'], ['auth', 'admin', 'role:admin,super_admin']);
$router->get('/admin/geo/countries', [AdminGeoController::class, 'countries'], ['auth', 'admin', 'role:admin,super_admin']);
$router->get('/admin/geo/states', [AdminGeoController::class, 'states'], ['auth', 'admin', 'role:admin,super_admin']);
$router->get('/admin/geo/cities', [AdminGeoController::class, 'cities'], ['auth', 'admin', 'role:admin,super_admin']);
$router->get('/admin/geo/rules', [AdminGeoController::class, 'rules'], ['auth', 'admin', 'role:admin,super_admin']);
$router->get('/admin/geo/lookup', [AdminGeoController::class, 'lookup'], ['auth', 'admin', 'role:admin,super_admin']);
$router->post('/admin/geo/rules', [AdminGeoController::class, 'saveRule'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/geo/rules/{id}/delete', [AdminGeoController::class, 'deleteRule'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/geo/settings', [AdminGeoController::class, 'updateSettings'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/geo/preview', [AdminGeoController::class, 'preview'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);

$router->get('/admin/everflow', [AdminEverflowController::class, 'index'], ['auth', 'admin', 'role:admin,super_admin']);
$router->get('/admin/everflow/export-csv', [AdminEverflowController::class, 'exportCsv'], ['auth', 'admin', 'role:admin,super_admin']);
$router->post('/admin/everflow/retry-postback/{id}', [AdminEverflowController::class, 'retryPostback'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);

$router->get('/admin/sync', [AdminSyncController::class, 'index'], ['auth', 'admin', 'role:admin,super_admin']);
$router->get('/admin/sync/action-network/status', [AdminSyncController::class, 'status'], ['auth', 'admin', 'role:admin,super_admin']);
$router->post('/admin/sync/action-network', [AdminSyncController::class, 'run'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/sync/action-network-backfill', [AdminSyncController::class, 'backfill'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/sync/action-network/tick', [AdminSyncController::class, 'tick'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
$router->post('/admin/sync/action-network/pause', [AdminSyncController::class, 'pause'], ['auth', 'admin', 'csrf', 'role:admin,super_admin']);
