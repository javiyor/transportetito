<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImportarFacturasIndexController extends Controller
{
    public function __invoke(Request $request)
    {
        return Inertia::render('Facturacion/Importar');
    }
}
