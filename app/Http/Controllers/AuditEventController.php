<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\View\View;

class AuditEventController extends Controller
{
    public function index(): View
    {
        return view('audit.index', [
            'events' => AuditEvent::query()->latest('id')->paginate(50),
        ]);
    }
}
