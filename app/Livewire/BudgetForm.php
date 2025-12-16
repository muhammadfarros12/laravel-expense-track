<?php

namespace App\Livewire;

use App\Models\Budget;
use App\Models\Category;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Title;

#[Title('Budget Form - Expense Tracker')]
class BudgetForm extends Component
{
    public $budgetId;
    public $amount = '';
    public $month;
    public $year;
    public $category_id = '';
    public $isEdit = false;

    protected function rules()
    {
        $rules = [
            'amount' => 'required|numeric|min:1000',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'category_id' => 'required|exists:categories,id'
        ];

        $uniqueRule = 'unique:budgets,category_id,NULL,id,user_id,' . auth()->id() . ',month,' . $this->month . ',year,' . $this->year;

        if ($this->isEdit) {
            $uniqueRule = 'unique:budgets,category_id,' . $this->budgetId . ',id,user_id,' . auth()->id() . ',month,' . $this->month . ',year,' . $this->year;
        }

        $rules['category_id'] =
            $this->category_id
            ? 'required|exists:categories,id|' . $uniqueRule
            : 'nullable|' . $uniqueRule;


        return $rules;
    }

    protected $messages = [
        'amount.required' => 'Budget amount is required.',
        'amount.numeric' => 'Budget amount must be a number.',
        'amount.min' => 'Budget amount must be at least Rp.1000.',
        'month.required' => 'Month is required.',
        'year.required' => 'Year is required.',
        'category_id.unique' => 'A budget for this category already exists for the selected month and year.'
    ];

    public function mount($budgetId = null){
        if ($budgetId) {
            $this->isEdit = true;
            $this->budgetId = $budgetId;
            $this->loadBudget();
        } else{
            $this->month = now()->month;
            $this->year = now()->year;
        }
    }

    public function loadBudget(){
        $budget = Budget::findOrFail($this->budgetId);
        if ($budget->user_id != auth()->id()) {
            abort(403);
        }

        $this->amount = $budget->amount;
        $this->month = $budget->month;
        $this->year = $budget->year;
        $this->category_id = $budget->category_id;
    }

    public function save(){
        $this->validate();

        $data = [
            'amount' => $this->amount,
            'month' => $this->month,
            'year' => $this->year,
            'category_id' => $this->category_id ?: null,
            'user_id' => auth()->id(),
        ];

        if ($this->isEdit) {
            $budget = Budget::findOrFail($this->budgetId);
            if ($budget->user_id != auth()->id()) {
                abort(403);
            }

            $budget->update($data);
            session()->flash('message', 'Budget updated successfully.');
        } else {
            Budget::create($data);
            session()->flash('message', 'Budget created successfully.');
        }
        return redirect()->route('budgets.index');
    }
    #[Computed]
    public function months(){
        return collect(range(1, 12))->map(function ($month){
            return [
                'value' => $month,
                'name' => Carbon::create(null, $month, 1)->format('F')
            ];
        });
    }

    #[Computed]
    public function categories(){
        return Category::where('user_id', auth()->id())
        ->orderBy('name')
        ->get();
    }
    #[Computed]
    public function years(){
        $currentYear = now()->year;
        return collect(range($currentYear - 1, $currentYear + 2));
    }

    #[Computed]
    public function dailyAmount($amount, $month, $year){
        $days = Carbon::create($year, $month)->daysInMonth;
        return $amount / $days;
    }


    public function render()
    {
        return view('livewire.budget-form', [
            'categories' => $this->categories,
            'months' => $this->months,
            'years' => $this->years,
        ]);
    }
}
