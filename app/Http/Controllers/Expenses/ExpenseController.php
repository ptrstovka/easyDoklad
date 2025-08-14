<?php


namespace App\Http\Controllers\Expenses;


use Inertia\Inertia;

class ExpenseController
{
    public function index()
    {
        return Inertia::render('Expenses/ExpenseList');
    }
}
