<?php

use App\Http\Controllers\Admin\Api\AccountCategoryController;
use App\Http\Controllers\Admin\Api\AccountController;
use App\Http\Controllers\Admin\Api\AccountTaskController;
use App\Http\Controllers\Admin\Api\ArticleController;
use App\Http\Controllers\Admin\Api\ArticleFileController;
use App\Http\Controllers\Admin\Api\BacklinkController;
use App\Http\Controllers\Admin\Api\DashboardController;
use App\Http\Controllers\Admin\Api\LeadController;
use App\Http\Controllers\Admin\Api\MarketingChecklistController;
use App\Http\Controllers\Admin\Api\MarketingMethodController;
use App\Http\Controllers\Admin\Api\ProjectController;
use App\Http\Controllers\Admin\Api\ProjectFileController;
use App\Http\Controllers\Admin\Api\PropertyController;
use App\Http\Controllers\Admin\Api\PropertyFileController;
use App\Http\Controllers\Admin\Api\PropertyImageController;
use App\Http\Controllers\Admin\Api\RevealPinController;
use App\Http\Controllers\Admin\Api\SessionController;
use App\Http\Controllers\Admin\Api\SubscriptionController;
use App\Http\Controllers\Admin\Api\TaskTemplateController;
use App\Http\Controllers\Admin\Api\UsefulLinkController;
use App\Http\Controllers\Admin\Api\VisitorController;
use App\Http\Controllers\Admin\Api\WeeklyTaskController;
use App\Http\Controllers\Admin\Api\WhatsappController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\Auth\RegisteredAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->middleware('guest:admin')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('admin.login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('admin.login.store');

    Route::get('register', [RegisteredAdminController::class, 'create'])->name('admin.register');
    Route::post('register', [RegisteredAdminController::class, 'store'])->name('admin.register.store');
});

Route::post('admin/logout', [AuthenticatedSessionController::class, 'destroy'])->name('admin.logout');

Route::middleware('auth:admin')->group(function () {
    Route::view('dashboard', 'admin.dashboard')->name('dashboard');
    Route::view('projects-dashboard', 'admin.projects')->name('projects-dashboard');
    Route::view('articles-dashboard', 'admin.articles')->name('articles-dashboard');
    Route::view('visitors-dashboard', 'admin.visitors')->name('visitors-dashboard');
    Route::view('leads-dashboard', 'admin.leads')->name('leads-dashboard');
    Route::view('whatsapp-dashboard', 'admin.whatsapp')->name('whatsapp-dashboard');
    Route::view('whatsapp-messages', 'admin.whatsapp-messages')->name('whatsapp-messages');
    Route::view('accounts', 'admin.accounts')->name('accounts');
    Route::view('subscriptions', 'admin.subscriptions')->name('subscriptions');
    Route::view('useful-links', 'admin.useful-links')->name('useful-links');
    Route::view('backlinks', 'admin.backlinks')->name('backlinks');
    Route::view('marketing-tools', 'admin.marketing-tools')->name('marketing-tools');
    Route::view('weekly-tasks', 'admin.weekly-tasks')->name('weekly-tasks');
});

Route::prefix('api')->name('panel.api.')->group(function () {
    Route::get('session', [SessionController::class, 'show'])->name('session');

    // Pushed by the local Node gateway, not by a signed-in admin, so it is
    // authenticated by the shared key instead of the admin session.
    Route::post('whatsapp/ack', [WhatsappController::class, 'ack'])
        ->middleware('whatsapp.gateway')
        ->name('whatsapp.ack');

    Route::middleware('auth:admin')->group(function () {
        Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('properties', [PropertyController::class, 'index'])->name('properties.index');
        Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
        Route::get('visitors', [VisitorController::class, 'index'])->name('visitors.index');
        Route::get('leads', [LeadController::class, 'index'])->name('leads.index');

        Route::get('whatsapp/status', [WhatsappController::class, 'status'])->name('whatsapp.status');
        Route::post('whatsapp/disconnect', [WhatsappController::class, 'disconnect'])->name('whatsapp.disconnect');
        Route::post('whatsapp/reset', [WhatsappController::class, 'reset'])->name('whatsapp.reset');
        Route::post('whatsapp/start', [WhatsappController::class, 'start'])->name('whatsapp.start');
        Route::get('whatsapp/log', [WhatsappController::class, 'log'])->name('whatsapp.log');
        Route::get('whatsapp/messages', [WhatsappController::class, 'messages'])->name('whatsapp.messages');
        Route::post('whatsapp/sync-acks', [WhatsappController::class, 'syncAcknowledgements'])->name('whatsapp.sync-acks');
        Route::post('whatsapp/send', [WhatsappController::class, 'send'])->name('whatsapp.send');

        Route::middleware('idempotency')->group(function () {
            Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
            Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
            Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

            Route::post('properties', [PropertyController::class, 'store'])->name('properties.store');
            Route::put('properties/{property}', [PropertyController::class, 'update'])->name('properties.update');
            Route::delete('properties/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');

            Route::post('articles', [ArticleController::class, 'store'])->name('articles.store');
            Route::put('articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
            Route::delete('articles/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');

            Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
            Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
            Route::delete('leads/{lead}', [LeadController::class, 'destroy'])->name('leads.destroy');

            Route::post('projects/reorder', [ProjectController::class, 'reorder'])->name('projects.reorder');

            Route::delete('property-images/{image}', [PropertyImageController::class, 'destroy'])->name('property-images.destroy');
        });

        Route::get('accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('account-categories', [AccountCategoryController::class, 'index'])->name('account-categories.index');
        Route::get('reveal-pin', [RevealPinController::class, 'show'])->name('reveal-pin.show');
        Route::put('reveal-pin', [RevealPinController::class, 'store'])->name('reveal-pin.store');
        Route::post('accounts/{account}/reveal', [RevealPinController::class, 'reveal'])->name('accounts.reveal');

        Route::get('task-templates', [TaskTemplateController::class, 'index'])->name('task-templates.index');

        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions/{subscription}/reveal', [RevealPinController::class, 'revealPaymentAccount'])->name('subscriptions.reveal');
        Route::get('useful-links', [UsefulLinkController::class, 'index'])->name('useful-links.index');
        Route::get('backlinks', [BacklinkController::class, 'index'])->name('backlinks.index');
        Route::get('marketing-methods', [MarketingMethodController::class, 'index'])->name('marketing-methods.index');
        Route::get('marketing-checklists', [MarketingChecklistController::class, 'index'])->name('marketing-checklists.index');

        Route::get('employees', [WeeklyTaskController::class, 'employees'])->name('employees.index');
        Route::get('weekly-task-templates', [WeeklyTaskController::class, 'templates'])->name('weekly-task-templates.index');
        Route::get('weekly-tasks', [WeeklyTaskController::class, 'week'])->name('weekly-tasks.index');
        Route::get('weekly-report-settings', [WeeklyTaskController::class, 'settings'])->name('weekly-report-settings.show');
        Route::get('whatsapp/groups', [WeeklyTaskController::class, 'groups'])->name('whatsapp.groups');
        Route::get('weekly-tasks/preview', [WeeklyTaskController::class, 'preview'])->name('weekly-tasks.preview');

        Route::middleware('idempotency')->group(function () {
            Route::post('accounts', [AccountController::class, 'store'])->name('accounts.store');
            Route::put('accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
            Route::delete('accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');

            Route::post('task-templates', [TaskTemplateController::class, 'store'])->name('task-templates.store');
            Route::put('task-templates/{taskTemplate}', [TaskTemplateController::class, 'update'])->name('task-templates.update');
            Route::delete('task-templates/{taskTemplate}', [TaskTemplateController::class, 'destroy'])->name('task-templates.destroy');

            Route::post('accounts/{account}/tasks', [AccountTaskController::class, 'store'])->name('accounts.tasks.store');
            Route::post('accounts/{account}/apply-templates', [AccountTaskController::class, 'applyTemplates'])->name('accounts.apply-templates');
            Route::put('account-tasks/{task}', [AccountTaskController::class, 'update'])->name('account-tasks.update');
            Route::delete('account-tasks/{task}', [AccountTaskController::class, 'destroy'])->name('account-tasks.destroy');

            Route::post('account-categories', [AccountCategoryController::class, 'store'])->name('account-categories.store');
            Route::put('account-categories/{accountCategory}', [AccountCategoryController::class, 'update'])->name('account-categories.update');
            Route::delete('account-categories/{accountCategory}', [AccountCategoryController::class, 'destroy'])->name('account-categories.destroy');

            Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
            Route::put('subscriptions/{subscription}', [SubscriptionController::class, 'update'])->name('subscriptions.update');
            Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');

            Route::post('useful-links', [UsefulLinkController::class, 'store'])->name('useful-links.store');
            Route::put('useful-links/{usefulLink}', [UsefulLinkController::class, 'update'])->name('useful-links.update');
            Route::delete('useful-links/{usefulLink}', [UsefulLinkController::class, 'destroy'])->name('useful-links.destroy');

            Route::post('backlinks', [BacklinkController::class, 'store'])->name('backlinks.store');
            Route::put('backlinks/{backlink}', [BacklinkController::class, 'update'])->name('backlinks.update');
            Route::delete('backlinks/{backlink}', [BacklinkController::class, 'destroy'])->name('backlinks.destroy');

            Route::post('marketing-methods', [MarketingMethodController::class, 'store'])->name('marketing-methods.store');
            Route::put('marketing-methods/{marketingMethod}', [MarketingMethodController::class, 'update'])->name('marketing-methods.update');
            Route::delete('marketing-methods/{marketingMethod}', [MarketingMethodController::class, 'destroy'])->name('marketing-methods.destroy');

            Route::post('marketing-checklists', [MarketingChecklistController::class, 'store'])->name('marketing-checklists.store');
            Route::put('marketing-checklists/{marketingChecklist}', [MarketingChecklistController::class, 'update'])->name('marketing-checklists.update');
            Route::delete('marketing-checklists/{marketingChecklist}', [MarketingChecklistController::class, 'destroy'])->name('marketing-checklists.destroy');
            Route::post('marketing-checklists/{marketingChecklist}/methods', [MarketingChecklistController::class, 'addMethods'])->name('marketing-checklists.methods');
            Route::post('marketing-checklists/{marketingChecklist}/items', [MarketingChecklistController::class, 'storeItem'])->name('marketing-checklists.items.store');
            Route::put('marketing-checklist-items/{item}', [MarketingChecklistController::class, 'updateItem'])->name('marketing-checklist-items.update');
            Route::delete('marketing-checklist-items/{item}', [MarketingChecklistController::class, 'destroyItem'])->name('marketing-checklist-items.destroy');

            Route::post('employees', [WeeklyTaskController::class, 'storeEmployee'])->name('employees.store');
            Route::put('employees/{employee}', [WeeklyTaskController::class, 'updateEmployee'])->name('employees.update');
            Route::delete('employees/{employee}', [WeeklyTaskController::class, 'destroyEmployee'])->name('employees.destroy');

            Route::post('weekly-task-templates', [WeeklyTaskController::class, 'storeTemplate'])->name('weekly-task-templates.store');
            Route::delete('weekly-task-templates/{template}', [WeeklyTaskController::class, 'destroyTemplate'])->name('weekly-task-templates.destroy');

            Route::post('weekly-tasks/generate', [WeeklyTaskController::class, 'generate'])->name('weekly-tasks.generate');
            Route::post('weekly-tasks/{list}/items', [WeeklyTaskController::class, 'storeItem'])->name('weekly-tasks.items.store');
            Route::put('weekly-task-items/{item}', [WeeklyTaskController::class, 'updateItem'])->name('weekly-task-items.update');
            Route::delete('weekly-task-items/{item}', [WeeklyTaskController::class, 'destroyItem'])->name('weekly-task-items.destroy');

            Route::put('weekly-report-settings', [WeeklyTaskController::class, 'updateSettings'])->name('weekly-report-settings.update');
            Route::post('weekly-tasks/send', [WeeklyTaskController::class, 'send'])->name('weekly-tasks.send');
        });

        Route::post('projects/{project}/image', [ProjectFileController::class, 'storeImage'])->name('projects.image');
        Route::post('projects/{project}/pdf', [ProjectFileController::class, 'storePdf'])->name('projects.pdf');
        Route::post('properties/{property}/images', [PropertyFileController::class, 'storeImages'])->name('properties.images');
        Route::post('properties/{property}/pdf', [PropertyFileController::class, 'storePdf'])->name('properties.pdf');
        Route::post('articles/{article}/image', [ArticleFileController::class, 'storeImage'])->name('articles.image');
    });
});
