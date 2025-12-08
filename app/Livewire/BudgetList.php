<?php

namespace App\Livewire;

use App\Models\Budget;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class BudgetList extends Component
{

    public $selectedMonth;
    public $selectedYear;
    public $showCreateModal = false;

    public function mount(){
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
    }

    #[Computed]
    public function budget(){
        return Budget::with('category')
        ->where('user_id', auth()->id())
        ->where('month', $this->selectedMonth)
        ->where('year', $this->selectedYear)
        ->get()
        // map itu sebagai pengganti dari foreach; untuk setiap data budget yang diambil, kita tambahkan properti baru
        ->map(function($budget){
            $budget->spent = $budget->getSpendAmount();
            $budget->remaining = $budget->getRemainingAmount();
            $budget->percentage = $budget->getPercentageUsage();
            $budget->is_over = $budget->isOverBudget();
            return $budget;
        });
    }

    // digunakan untuk menghitung total budget, total spent, total remaining, dan overall percentage
    #[Computed]
    public function totalBudget(){
        return $this->budget()->sum('amount');
    }

    #[Computed]
    public function totalSpent(){
        return $this->budget()->sum('spent');
    }

    #[Computed]
    public function totalRemaining(){
        return $this->budget()->sum('remaining');
    }

    #[Computed]
    public function overallPercentage(){
        if ($this->totalBudget() == 0) {
            return 0;
        }

        return round($this->totalSpent() / $this->totalBudget() * 100, 1);
    }

    #[Computed]
    public function categories() {
        return \App\Models\Category::where('user_id', auth()->id())->orderby('name')->get();
    }

    public function previousMonth(){
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->subMonth();
        $this->selectedMonth = $date->month;
        $this->selectedYear = $date->year;
    }

    public function nextMonth(){
        $date = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->addMonth();
        $this->selectedMonth = $date->month;
        $this->selectedYear = $date->year;
    }

    public function currentMonth(){
        $this->selectedMonth = now()->month;
        $this->selectedYear = now()->year;
    }

    public function deleteBudget($budgetId){
        $budget = Budget::where('user_id', auth()->id())->findOrFail($budgetId);
        $budget->delete();

        session()->flash('message', 'Budget deleted successfully.');
    }

    public function render()
    {
        return view('livewire.budget-list', [
            'budgets' => $this->budget(),
            'totalBudget' => $this->totalBudget(),
            'totalSpent' => $this->totalSpent(),
            'totalRemaining' => $this->totalRemaining(),
            'overallPercentage' => $this->overallPercentage(),
            'categories' => $this->categories(),

        ]);
    }
}
