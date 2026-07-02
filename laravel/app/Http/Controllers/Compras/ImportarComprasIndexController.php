<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImportarComprasIndexController extends Controller
{
    public function __invoke(Request $request)
    {
        return Inertia::render('Compras/Importar');
    }
}
