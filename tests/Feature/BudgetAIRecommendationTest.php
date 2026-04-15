<?php

use App\Livewire\BudgetForm;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Services\BudgetAIService;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('calculates ai history from the three months before the target month', function () {
    $user = User::factory()->create();
    $category = Category::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'color' => '#38B2F6',
    ]);

    Expense::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => 100000,
        'title' => 'Groceries January',
        'date' => '2025-01-10',
    ]);

    Expense::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => 300000,
        'title' => 'Groceries March',
        'date' => '2025-03-10',
    ]);

    $history = app(BudgetAIService::class)->getHistoricalSpendingData(
        $category->id,
        $user->id,
        4,
        2025,
    );

    expect($history['expenses'])->toHaveCount(3)
        ->and($history['expenses'][0]['month'])->toBe('March 2025')
        ->and($history['expenses'][1]['month'])->toBe('February 2025')
        ->and($history['expenses'][1]['total'])->toBe(0.0)
        ->and(round($history['average'], 2))->toBe(133333.33)
        ->and($history['months_with_data'])->toBe(2)
        ->and($history['total_expense_count'])->toBe(2)
        ->and($history['trend'])->toBe('increasing');
});

it('normalizes formatted numeric values from ai responses', function () {
    $user = User::factory()->create();
    $category = Category::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'color' => '#38B2F6',
    ]);

    Expense::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => 250000,
        'title' => 'Groceries',
        'date' => '2025-03-10',
    ]);

    Gemini::swap(new class
    {
        public function generativeModel(string $model): object
        {
            return new class
            {
                public function generateContent(string $prompt): object
                {
                    return new class
                    {
                        public function text(): string
                        {
                            return <<<'TEXT'
```json
{"recommended":"1,000,000","min":"800,000","max":"1,250,000","explanation":"Base this budget on your prior spending.","tip":"Review weekly."}
```
TEXT;
                        }
                    };
                }
            };
        }
    });

    $recommendation = app(BudgetAIService::class)->getBudgetRecomendation(
        $category->id,
        $user->id,
        4,
        2025,
    );

    expect($recommendation)->not->toBeNull()
        ->and($recommendation['recommended'])->toBe(1000000.0)
        ->and($recommendation['min'])->toBe(800000.0)
        ->and($recommendation['max'])->toBe(1250000.0)
        ->and($recommendation['confidence'])->toBe('low');
});

it('rejects budgets that use another users category', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherCategory = Category::create([
        'user_id' => $otherUser->id,
        'name' => 'Private Category',
        'color' => '#38B2F6',
    ]);

    $this->actingAs($user);

    Livewire::test(BudgetForm::class)
        ->set('amount', 500000)
        ->set('month', 4)
        ->set('year', 2025)
        ->set('category_id', $otherCategory->id)
        ->call('save')
        ->assertHasErrors(['category_id']);
});

it('falls back to historical recommendation when gemini quota is exceeded', function () {
    $user = User::factory()->create();
    $category = Category::create([
        'user_id' => $user->id,
        'name' => 'Food',
        'color' => '#38B2F6',
    ]);

    Expense::create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'amount' => 250000,
        'title' => 'Groceries',
        'date' => '2025-03-10',
    ]);

    Gemini::swap(new class
    {
        public function generativeModel(string $model): object
        {
            return new class
            {
                public function generateContent(string $prompt): never
                {
                    throw new Exception('Quota exceeded for metric: generativelanguage.googleapis.com/generate_content_free_tier_requests');
                }
            };
        }
    });

    $recommendation = app(BudgetAIService::class)->getBudgetRecomendation(
        $category->id,
        $user->id,
        4,
        2025,
    );

    expect($recommendation)->not->toBeNull()
        ->and($recommendation['source'])->toBe('fallback')
        ->and($recommendation['recommended'])->toBe(91666.67)
        ->and($recommendation['warning'])->toBe('Gemini quota exceeded. Check quota or billing in Google AI Studio, then try again.');
});
