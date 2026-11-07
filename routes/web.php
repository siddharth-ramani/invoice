<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VendorLoginController;
use App\Http\Controllers\Business\AnalyticsController;
use App\Http\Controllers\Business\ExpenseController;
use App\Http\Controllers\Business\InventoryController;
use App\Http\Controllers\Business\ProductController;
use App\Http\Controllers\Business\QuotationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GstSlabController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Platform\AdminController;
use App\Http\Controllers\Platform\BusinessSetupController;
use App\Http\Controllers\Platform\IntegrationController;
use App\Http\Controllers\Platform\NotificationController;
use App\Http\Controllers\Platform\SupportController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\VendorSubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function (Request $request) {
    app()->setLocale(in_array($request->query('lang'), ['en', 'gu', 'hi'], true) ? $request->query('lang') : 'en');

    return view('welcome');
})->name('home');

// --------------------
// AUTH ROUTES
// --------------------
Route::get('/login', [AuthenticatedSessionController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware(['guest', 'throttle:bizpilot-login']);

Route::get('/register', [RegisteredUserController::class, 'create'])->middleware('guest')->name('register');
Route::post('/register', [RegisteredUserController::class, 'store'])->middleware(['guest', 'throttle:bizpilot-register']);

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// --------------------
// ALL AUTHENTICATED ROUTES
// --------------------
Route::middleware(['auth'])->group(function () {

    Route::post('/admin/vendors/{vendor}/login', [VendorLoginController::class, 'store'])->middleware('admin')->name('admin.vendors.login');
    Route::post('/admin/vendor-login/return', [VendorLoginController::class, 'destroy'])->name('admin.vendors.return');

    // Dashboard (role handled inside view/controller)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Customers (Admin only handled inside controller)
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

    Route::get('/gst', [GstSlabController::class, 'index'])->name('gst.index');
    Route::get('/gst/create', [GstSlabController::class, 'create'])->name('gst.create');
    Route::post('/gst', [GstSlabController::class, 'store'])->name('gst.store');
    Route::get('/gst/{gst}/edit', [GstSlabController::class, 'edit'])->name('gst.edit');
    Route::put('/gst/{gst}', [GstSlabController::class, 'update'])->name('gst.update');
    Route::delete('/gst/{gst}', [GstSlabController::class, 'destroy'])->name('gst.destroy');

    // Settings (single route for both roles)
    Route::get('/settings', [SettingsController::class, 'settings'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'updateSettings'])->name('settings.update');

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/bulk-download', [InvoiceController::class, 'bulkDownload'])->name('invoices.bulkDownload');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');

    Route::get('/reports/sales', [ReportController::class, 'sales'])
        ->name('reports.sales');

    Route::get('/reports/gst', [ReportController::class, 'gst'])
        ->name('reports.gst');

    Route::resource('subscription-plans', SubscriptionPlanController::class);

    Route::get('/subscription', [SubscriptionController::class, 'index'])
        ->name('subscription');

    Route::post('/subscription/subscribe/{id}', [SubscriptionController::class, 'subscribe'])
        ->name('subscription.subscribe');

    Route::get('/vendor/subscription', [VendorSubscriptionController::class, 'index'])
        ->name('vendor.subscription');

});

Route::middleware(['auth', 'payments'])->group(function () {
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/export', [PaymentController::class, 'export'])->name('payments.export');
    Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])->name('payments.reverse');
    Route::get('/receivables', [ReceivableController::class, 'index'])->name('receivables.index');
    Route::post('/invoices/{invoice}/payment-review', [ReceivableController::class, 'review'])->name('receivables.review');
    Route::put('/invoices/{invoice}/due-date', [ReceivableController::class, 'dueDate'])->name('receivables.due-date');
    Route::get('/customers/{customer}/ledger', [ReceivableController::class, 'ledger'])->name('customers.ledger');
    Route::get('/customers/{customer}/ledger/export', [ReceivableController::class, 'export'])->name('customers.ledger.export');
});

Route::middleware(['auth', 'business:quotations'])->group(function () {
    Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
    Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
    Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
    Route::post('/quotations/{quotation}/status', [QuotationController::class, 'status'])->name('quotations.status');
    Route::post('/quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');
    Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');
    Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
});
Route::middleware(['auth', 'business:products'])->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::get('/products/lookup', [ProductController::class, 'lookup'])->name('products.lookup');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventory/settings', [InventoryController::class, 'settings'])->name('inventory.settings');
    Route::get('/inventory/{product}', [InventoryController::class, 'show'])->name('inventory.show');
    Route::post('/inventory/{product}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');
});
Route::middleware(['auth', 'business:expenses'])->group(function () {
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::post('/expenses/categories', [ExpenseController::class, 'category'])->name('expenses.categories');
    Route::get('/expenses/export', [ExpenseController::class, 'export'])->name('expenses.export');
    Route::get('/expenses/attachments/{attachment}', [ExpenseController::class, 'attachment'])->name('expenses.attachment');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::post('/expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');
});
Route::middleware(['auth', 'business:expenses'])->group(function () {
    Route::get('/reports/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/reports/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
});
Route::middleware(['auth', 'platform'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/preferences', [NotificationController::class, 'preferences'])->name('preferences.update');
    Route::post('/preferences/locale', [NotificationController::class, 'locale'])->name('preferences.locale');
    Route::post('/notifications/reminders/generate', [NotificationController::class, 'reminders'])->name('notifications.reminders');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index');
    Route::post('/support', [SupportController::class, 'store'])->name('support.store');
    Route::get('/support/{ticket}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/reply', [SupportController::class, 'reply'])->name('support.reply');
    Route::middleware('admin')->prefix('/admin/platform')->name('platform.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/vendors/{vendor}', [AdminController::class, 'vendor'])->name('vendor');
        Route::post('/vendors/{vendor}/subscription', [AdminController::class, 'assign'])->name('assign');
        Route::post('/vendors/{vendor}/access', [AdminController::class, 'control'])->name('control');
        Route::get('/entitlements', [AdminController::class, 'plans'])->name('entitlements');
        Route::post('/entitlements/{plan}', [AdminController::class, 'entitlements'])->name('entitlements.update');
        Route::post('/billing/{billing}/reverse', [AdminController::class, 'reverse'])->name('billing.reverse');
        Route::get('/audit', [AdminController::class, 'audit'])->name('audit');
    });
});
Route::middleware(['auth', 'platform'])->group(function () {
    Route::middleware('admin')->group(function () {
        Route::get('/admin/platform/integrations', [IntegrationController::class, 'index'])->name('platform.integrations');
    });
});
Route::post('/notifications/{notification}/email', [NotificationController::class, 'email'])->middleware(['auth', 'platform'])->name('notifications.email');
Route::post('/admin/platform/email-settings', [IntegrationController::class, 'email'])->middleware(['auth', 'platform', 'admin'])->name('platform.email.update');
Route::post('/locale', [NotificationController::class, 'guestLocale'])->middleware('platform')->name('preferences.guest-locale');

Route::get('/business-setup', [BusinessSetupController::class, 'index'])->middleware(['auth', 'platform'])->name('business.setup');

Route::post('/subscription-plans/{subscriptionPlan}/archive', [SubscriptionPlanController::class, 'archive'])->middleware(['auth', 'admin'])->name('subscription-plans.archive');

// Public discovery never includes tenant or financial URLs.
Route::get('/sitemap.xml', function () {
    return response()->view('seo.sitemap', [
        'locales' => \App\Support\PublicSeo::indexable() ? ['en', 'gu', 'hi'] : [],
    ])->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('seo.sitemap');
Route::get('/robots.txt', function () {
    $body = \App\Support\PublicSeo::indexable()
        ? "User-agent: *\nAllow: /\nSitemap: ".\App\Support\PublicSeo::url('sitemap.xml')."\n"
        : "User-agent: *\nDisallow: /\n";

    return response($body, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('seo.robots');