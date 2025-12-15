<?php

use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\TwoFactor;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('categories', App\Livewire\Categories::class)->name('categories.index');
    Route::get('budgets', App\Livewire\BudgetList::class)->name('budgets.index');
    Route::get('budget/create', App\Livewire\BudgetForm::class)->name('budgets.create');
    Route::get('budget/{budgetId}/edit', App\Livewire\BudgetForm::class)->name('budgets.edit');

    Route::get('expenses', App\Livewire\ExpenseList::class)->name('expenses.index');
    Route::get('expenses/create', App\Livewire\ExpenseForm::class)->name('expenses.create');
    Route::get('expenses/{expenseId}/edit', App\Livewire\ExpenseForm::class)->name('expenses.edit');
    Route::get('recurring-expenses', App\Livewire\RecurringExpense::class)->name('recurring-expenses.index');


    Route::get('settings/profile', Profile::class)->name('profile.edit');
    Route::get('settings/password', Password::class)->name('user-password.edit');
    Route::get('settings/appearance', Appearance::class)->name('appearance.edit');

    Route::get('settings/two-factor', TwoFactor::class)
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});
