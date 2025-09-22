<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('agents');
        $agents = $user->agents()->latest()->get();
        
        $apiKey = $user->apiKeys()
            ->where('active', true)
            ->orderByDesc('created_at')
            ->first();

        return view('dashboard', [
            'user' => $user,
            'agents' => $agents,
            'apiKey' => $apiKey?->key_hash,
        ]);
    }
}
