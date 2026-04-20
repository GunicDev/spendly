<?php

use App\Http\Controllers\ExpenseReportPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/spendly/reports/expenses/{expense}/print', ExpenseReportPrintController::class)
    ->name('expenses.report.print');
