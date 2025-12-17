<?php

namespace App\Livewire;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Dashboard extends Component
{
    public $selectedMonth;
    public $selectedYear;
    public $totalSpent;
    public $monthlyBudget;
    public $percentageUsed;
    public $expenseByCategory;
    public $recentExpenses;
    public $monthlyComparison;
    public $topCategories;
    public $recurringExpenseCount;

    public function mount(){
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
        $this->loadDashboardData();
    }

    #[Computed]
    public function loadDashboardData()
    {
        $userId = auth()->id();

        // Ambil semua budget bulan ini (termasuk tanpa kategori)
        $budgets = Budget::with('category')
            ->where('user_id', $userId)
            ->where('month', $this->selectedMonth)
            ->where('year', $this->selectedYear)
            ->get()
            ->map(function ($budget) {
                $budget->spent = $budget->getSpendAmount();
                $budget->remaining = $budget->getRemainingAmount();
                $budget->percentage = $budget->getPercentageUsage();
                $budget->is_over = $budget->isOverBudget();
                return $budget;
            });

        // Total Budget (kategori + tanpa kategori)
        $this->monthlyBudget = $budgets->sum('amount');

        // Total Spent dari semua budget
        $this->totalSpent = $budgets->sum('spent');

        // Total Remaining
        $totalRemaining = $budgets->sum('remaining');

        // Percentage Used
        $this->percentageUsed = $this->monthlyBudget > 0
            ? round(($this->totalSpent / $this->monthlyBudget) * 100, 1)
            : 0;

        // Expense by category (untuk chart / tampilan)
        $this->expenseByCategory = Expense::select('categories.name', 'categories.color', \DB::raw('SUM(expenses.amount) as total'))
            ->join('categories', 'expenses.category_id', '=', 'categories.id')
            ->where('expenses.user_id', $userId)
            ->whereMonth('expenses.date', $this->selectedMonth)
            ->whereYear('expenses.date', $this->selectedYear)
            ->groupBy('categories.id', 'categories.name', 'categories.color')
            ->orderBy('total', 'desc')
            ->get();

        // Recent Expenses
        $this->recentExpenses = Expense::with('category')
            ->forUser($userId)
            ->whereMonth('date', $this->selectedMonth)
            ->whereYear('date', $this->selectedYear)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Monthly Comparison
        $this->monthlyComparison = collect();
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->subMonths($i);
            $total = Expense::forUser($userId)
                ->inMonth($date->month, $date->year)
                ->sum('amount');
            $this->monthlyComparison->push([
                'month' => $date->format('M'),
                'total' => $total,
            ]);
        }

        // Top Categories
        $this->topCategories = $this->expenseByCategory->take(3);

        // Recurring Expenses Count
        $this->recurringExpenseCount = Expense::forUser($userId)
            ->recurring()
            ->count();
    }

    public function previousMonth(){
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->subMonth();
        $this->selectedMonth = $date->month;
        $this->selectedYear = $date->year;
        $this->loadDashboardData();
    }

    public function nextMonth(){
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->addMonth();
        $this->selectedMonth = $date->month;
        $this->selectedYear = $date->year;
        $this->loadDashboardData();
    }

    public function updatedSelectedMonth(){
        $this->loadDashboardData();

    }
    public function updatedSelectedYear(){
        $this->loadDashboardData();

    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}


