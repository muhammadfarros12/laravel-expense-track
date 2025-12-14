<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Expense;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class ExpenseList extends Component
{
    use WithPagination;

    public $search = '';
    public $selectedCategory = '';
    public $startDate = "";
    public $endDate = '';
    public $sortBy = 'date';
    public $sortDirection = 'desc';
    public $showFilters = false;

    public function mount(){
        // default dates to current month
        if (empty($this->startDate)) {
            $this->startDate = now()->startOfMonth()->format('Y-m-d');
        }
        if (empty($this->endDate)) {
            $this->endDate = now()->endOfMonth()->format('Y-m-d');
        }
    }
    
    // sorting
    public function sortBy($field){
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'desc';
        }
    }
    
    // deleting the expenses
    public function deleteExpense($id){
        $expense = Expense::findOrFail($id);
        
        if($expense->user_id !== auth()->id()){
            abort(403, 'You are not authorized to perform this action.');
        }
        
        $expense->delete();
        session()->flash('success', 'Expense deleted successfully.');
    }


    // computed property of expenses
    #[Computed]
    public function expenses(){
        $query = Expense::with('category')
        ->forUser(auth()->id());

        // search & filter
        if ($this->search) {
            $query->where(function($q){
                $q->where('title', 'like', '%'.$this->search.'%')
                ->orWhere('description', 'like', '%'.$this->search.'%');
            }); // bentuk query menjadi: WHERE ('title' LIKE '%search%' OR 'description' LIKE '%search%') AND category_id = 5 // jadi dikelompokkan agar tidak terganggu dengan AND query
        }

        if ($this->selectedCategory) {
            $query->where('category_id', $this->selectedCategory);
        }

        if ($this->startDate) {
            $query->whereDate('date', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('date', '<=', $this->endDate);
        }

        return $query->orderBy($this->sortBy, $this->sortDirection)
        ->paginate(10);
    }
    
    #[Computed]
    public function total(){
        $query = Expense::forUser(auth()->id());
        
        if ($this->selectedCategory) {
            $query->where('category_id', $this->selectedCategory);
        }

        if ($this->startDate) {
            $query->whereDate('date', '>=', $this->startDate);
        }

        if ($this->startDate) {
            $query->whereDate('date', '<=', $this->startDate);
        }
        
        return $query->sum('amount');
    }
    
    #[Computed]
    public function categories(){
        return Category::forUser(auth()->id())
        ->orderBy('name')
        ->get();
    }
    
    public  function updatingSearch(){
        $this->resetPage();
    }
    public  function updatingSelectedCategory(){
        $this->resetPage();
    }
    public  function updatingStartDate(){
        $this->resetPage();
    }
    public  function updatingEndDate(){
        $this->resetPage();
    }
    
    public function clearFilters(){
        $this->search = '';
        $this->selectedCategory = '';
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
        $this->resetPage();
    }
 


    public function render()
    {
        return view('livewire.expense-list', [
        'categories' => $this->categories,
        'expenses' => $this->expenses,
        'total' => $this->total,
        ]);
    }
}
