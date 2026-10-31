<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MachineController extends Controller
{
    public function index()
    {
        $machines = Machine::orderBy('machine_code')->get();

        return Inertia::render('Production/Machines', [
            'machines' => $machines,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'machine_code' => 'required|string|unique:machines,machine_code|max:255',
            'name' => 'required|string|unique:machines,name|max:255',
            'type' => 'required|string|in:cutter,polishing,crane,loader,other',
            'capacity_note' => 'nullable|string|max:255',
            'operator_name' => 'nullable|string|max:255',
            'status' => 'required|string|in:active,inactive,maintenance',
            'remarks' => 'nullable|string',
        ]);

        Machine::create($validated);

        return back()->with('success', 'Machine created successfully in the master register.');
    }

    public function update(Request $request, $id)
    {
        $machine = Machine::findOrFail($id);

        $validated = $request->validate([
            'machine_code' => 'required|string|max:255|unique:machines,machine_code,'.$id,
            'name' => 'required|string|max:255|unique:machines,name,'.$id,
            'type' => 'required|string|in:cutter,polishing,crane,loader,other',
            'capacity_note' => 'nullable|string|max:255',
            'operator_name' => 'nullable|string|max:255',
            'status' => 'required|string|in:active,inactive,maintenance',
            'remarks' => 'nullable|string',
        ]);

        $machine->update($validated);

        return back()->with('success', 'Machine master updated successfully.');
    }
}
