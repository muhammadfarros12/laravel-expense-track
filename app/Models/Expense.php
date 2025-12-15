<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model {
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'title',
        'description',
        'date',
        'type',
        'recurrence',
        'recurring_start_date',
        'recurring_end_date',
        'parent_expense_id',
        'is_auto_generated',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'recurring_start_date' => 'date',
        'recurring_end_date' => 'date',
        'is_auto_generated' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    } // satu expense hanya dibuat 1 user

    public function category()
    {
        return $this->belongsTo(Category::class);
    } // satu expense hanya untuk 1 kategori

    public function parentExpense()
    {
        return $this->belongsTo(Expense::class, 'parent_expense_id');
    } // untuk menghubungkan expense berulang dengan expense induknya

    public function childExpenses()
    {
        return $this->hasMany(Expense::class, 'parent_expense_id');
    }

    // digunakan untuk memfilter expense berdasarkan user (shortcut query yang sering dipakai)
    #[Scope]
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecurring($query)
    {
        return $query->where('type', 'recurring');
    }

    public function scopeOneTime($query)
    {
        return $query->where('type', 'one-time');
    }

    public function scopeInMonth($query, $month, $year)
    {
        return $query->whereMonth('date', $month)
            ->whereYear('date', $year);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    function isRecurring()
    {
        return $this->type === 'recurring';
    }

    function shouldGenerateNextOccurence()
    {
        if (!$this->isRecurring()) {
            return false;
        }

        if ($this->recurring_end_date && now()->isAfter($this->recurring_end_date)) {
            return false;
        }

        return true;

    }

    function getNextOccurrenceDate() : ?Carbon {
        if (!$this->isRecurring()) {
            return null;
        }

        $lastChildExpense = $this->childExpenses()->orderBy('date', 'desc')->first();

        $baseDate = $lastChildExpense ? $lastChildExpense->date : $this->recurring_start_date;

        return match ($this->recurrence) {
            'daily' => $baseDate->copy()->addDay(),
            'weekly' => $baseDate->copy()->addWeek(),
            'monthly' => $baseDate->copy()->addMonth(),
            'yearly' => $baseDate->copy()->addYear(),
            default => null,
        };
    }



}
