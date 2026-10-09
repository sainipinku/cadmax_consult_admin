<?php

namespace App\Http\Controllers;

use App\Services\EmulationService;
use Illuminate\Http\Request;

class EmulationController extends Controller
{
    protected $emulationService;

    public function __construct(EmulationService $emulationService)
    {
        $this->emulationService = $emulationService;
    }

    public function start(Request $request)
    {
        $request->validate([
            'target_type' => 'required|string',
            'target_id' => 'required',
        ]);

        $result = $this->emulationService->startEmulation(
            $request->target_type,
            $request->target_id
        );

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect($result['redirect'])->with('success', $result['message']);
    }

    public function exit(Request $request)
    {
        $result = $this->emulationService->exitEmulation();

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return redirect($result['redirect'])->with('success', $result['message']);
    }

    public function switchRole(Request $request)
    {
        $request->validate([
            'role' => 'required|string',
        ]);

        $role = strtolower($request->role);
        $request->session()->put('active_role', $role);
        $request->session()->save();

        $redirectUrl = match ($role) {
            'surveyor', 'survey_man' => route('member.construction.dashboard', ['role' => 'surveyor']),
            'site_employee', 'execution' => route('member.construction.execution.index'),
            'vehicle_driver', 'driver' => route('member.construction.vehicles.index'),
            'draft_person', 'draft_man' => route('member.construction.dashboard', ['role' => 'draft_person']),
            'admin', 'project_admin' => route('admin.dashboard'),
            'superadmin', 'super_admin' => route('super.dashboard'),
            default => route('member.construction.dashboard', ['role' => $role]),
        };

        return redirect($redirectUrl)->with('success', 'Switched active role context.');
    }
}
