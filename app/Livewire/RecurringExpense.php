<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Expense;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RecurringExpense extends Component
{
    
    public $showDeleteModal = false;
    public $expenseToDelete = null;
    
    public function confirmDelete($expenseId)
    {
        $this->expenseToDelete = $expenseId;
        $this->showDeleteModal = true;
    }
    
    public function deleteExpense(){
        if($this->expenseToDelete){
            $expense = Expense::find($this->expenseToDelete);
            if($expense->user_id !== auth()->id()){
                abort(403);
            }
            // digunakan untuk menghapus child expenses
            $expense->childExpenses()->delete();
            $expense->delete();
            
            session()->flash('message', 'Expense deleted successfully');
            
            $this->expenseToDelete = null;
            $this->showDeleteModal = false;
        }
    }
    
    #[Computed]
    public function recurringExpenses(){
        return Expense::with(['category', 'childExpenses'])
        ->forUser(auth()->id())
        ->recurring()
        ->get();
    }
    
    #[Computed]
    public function categories(){
        return Category::where('user_id', auth()->id())
        ->orderBy('name')
        ->get();
    }
    
    public function render()
    {
        return view('livewire.recurring-expense', [
            'recurringExpenses' => $this->recurringExpenses,
            'categories' => $this->categories,
        ]);
    }
}
