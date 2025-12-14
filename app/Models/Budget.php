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

    public function getSpendAmount(): float{
        // terdapat issue untuk budget yang tidak memiliki kategori, akan dihitung sebagai semua expense
        $query = Expense::where('user_id', $this->user_id)
            ->whereMonth('date', $this->month)
            ->whereYear('date', $this->year);

        if ($this->category_id) {
            $query->where('category_id', $this->category_id);
        } else {
            $query->whereNull('category_id');
        }

        return $query->sum('amount');
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
