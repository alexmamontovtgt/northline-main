<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(): JsonResponse
    {
        $columns = array( 'id', 'name', 'email' );

        $customers = Customer::query()
            ->orderBy('name')
            ->get($columns);

        return response()->json([
            'data' => $customers,
        ]);
    }
}
