<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'month',
        'year',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'month' => 'integer',
        'year' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    } // satu budget hanya dibuat 1 user

    public function category()
    {
        return $this->belongsTo(Category::class);
    } // satu budget hanya untuk 1 kategori

    public function getSpendAmount(){
        if ($this->category_id) {
            return $this->category->getTotalSpendForMonth($this->month, $this->year);
        }

        return Expense::forUser($this->user_id)
            ->inMonth($this->month, $this->year)
            ->sum('amount');
    }

    public function getRemainingAmount(){
        return $this->amount - $this->getSpendAmount();
    }

    public function getPercentageUsage() {
        if ($this->amount == 0) {
            return 0;
        }

        return ($this->getSpendAmount() / $this->amount) * 100;
    }

    public function isOverBudget(){
        return $this->getSpendAmount() > $this->amount;
    }
}
