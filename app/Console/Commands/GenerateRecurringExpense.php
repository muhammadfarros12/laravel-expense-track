<?php

namespace App\Console\Commands;

use App\Models\Expense;
use Illuminate\Console\Command;
use Log;

class GenerateRecurringExpense extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expenses:generate-recurring-expense';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate recurring expense based on their schedule';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to generate recurring expenses...');

        $recurring_expenses = Expense::recurring()
            ->whereNull('deleted_at')
            ->get();

        $generatedCount = 0;

        foreach ($recurring_expenses as $recurring_expense) {
            $generated = $this->generateExpenseForRecurring($recurring_expense);

            $generatedCount += $generated;
        }

        $this->info('Successfully generated ' . $generatedCount . ' recurring expenses.');

        Log::info("Generated {$generatedCount} recurring expenses", [
            'command' => 'expenses:generate-recurring',
            'timestamp' => now(),
        ]);

        return Command::SUCCESS;
    }

    public function generateExpenseForRecurring(Expense $recurring_expense)
    {
        if (!$recurring_expense->shouldGenerateNextOccurence()) {
            return 0;
        }

        $next_date = $recurring_expense->getNextOccurrenceDate();
        Log::info('next date: ' . $next_date);
        $generated_count = 0;

        //generate all missing occurrences until current date(up to today)
        while ($next_date && $next_date->lte(now())) {
            $exist = Expense::where('parent_expense_id', $recurring_expense->id)
                ->whereDate('date', $next_date)
                ->exists();

            if (!$exist) {
                $this->createExpenseOccurrance($recurring_expense, $next_date);
                $generated_count++;


                $this->line('Generated: ' . $recurring_expense->id . ' for ' . $next_date->format('Y-m-d'));
            }

            // calculate the next occurrence
            $next_date = match ($recurring_expense->recurrence) {
                'daily' => $next_date->copy()->addDay(),
                'weekly' => $next_date->copy()->addWeek(),
                'monthly' => $next_date->copy()->addMonth(),
                'yearly' => $next_date->copy()->addYear(),
                default => null,
            };

            if ($recurring_expense->recurring_end_date && $next_date && $next_date->gt($recurring_expense->recurring_end_date)) {
                break;
            }

            if ($next_date && $next_date->gt(now())) {
                break;
            }

        }
        return $generated_count;
    }

    public function createExpenseOccurrance(Expense $recurring_expense, $next_date)
    {
        Expense::create([
            'user_id' => $recurring_expense->user_id,
            'category_id' => $recurring_expense->category_id,
            'amount' => $recurring_expense->amount,
            'title' => $recurring_expense->title,
            'description' => $recurring_expense->description,
            'date' => $next_date,
            'type' => 'one-time',
            'parent_expense_id' => $recurring_expense->id,
            'is_auto_generated' => true,
        ]);
    }
}
