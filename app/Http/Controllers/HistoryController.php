<?php

namespace App\Http\Controllers;

use App\Models\FasRun;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $runs = FasRun::orderBy('analysis_date', 'desc')->paginate(10);

        return view('history.index', compact('runs'));
    }
}
