<?php

namespace App\Providers;

use App\Models\Brand;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryMovement;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Unit;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        foreach ([Product::class, User::class, Role::class, Customer::class, Category::class, Brand::class, Unit::class, PaymentMethod::class, ExpenseCategory::class, Sale::class, SaleReturn::class, CashRegister::class, CashMovement::class, InventoryMovement::class, Expense::class, Quote::class] as $model) {
            $model::observe(AuditObserver::class);
        }
        Event::listen(Login::class, fn ($event) => AuditLogger::record('login', $event->user));
        Event::listen(Logout::class, fn ($event) => AuditLogger::record('logout', $event->user));
        Event::listen(Failed::class, function ($event) {
            AuditLogger::record('login_failed', $event->user, new: ['email' => mb_substr((string) ($event->credentials['email'] ?? ''), 0, 255)]);
        });
        Event::listen(Lockout::class, fn ($event) => AuditLogger::record('login_lockout'));

        Gate::define('operate-pos', fn (User $user) => $user->isAdministrator() || $user->hasRole('Vendedor'));
        foreach (['view-audit', 'manage-sales', 'manage-users', 'manage-products', 'manage-inventory', 'manage-expenses', 'manage-catalogs'] as $ability) {
            Gate::define($ability, fn (User $user) => $user->isAdministrator());
        }
    }
}
