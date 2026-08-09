<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class WhatsAppController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('settings.index', ['tab' => 'integration']);
    }
}
