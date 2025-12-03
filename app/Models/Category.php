<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'color',
        'icon',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    } // satu kategori hanya dibuat 1 user

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    } // satu kategori bisa memiliki banyak expense

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    } // satu kategori bisa memiliki banyak budget

    public function getTotalSpendForMonth($month, $year)
    {
        return $this->expenses()
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->sum('amount');
    } // mendapatkan total pengeluaran untuk kategori ini dalam bulan dan tahun tertentu

}
