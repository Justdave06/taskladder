<?php

namespace App\Http\Controllers;

use App\Models\EdtsDepartment;
use App\Models\EdtsDepartmentAdmin;
use App\Models\EdtsDocType;
use App\Models\EdtsDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EdtsController extends Controller
{
    public function index()
    {
        $user = request()->user();

        $documents = EdtsDocument::with(['user', 'departmentFrom', 'docTypes', 'assignedTo', 'forwardedTo', 'departmentTo', 'forwardedFrom'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $departments = EdtsDepartment::with('admins.user')->orderBy('name')->get();
        $docTypes = EdtsDocType::orderBy('name')->get();

        $adminDepartments = EdtsDepartmentAdmin::with('department')
            ->where('user_id', $user->id)
            ->get()
            ->pluck('department');

        $isAdmin = $adminDepartments->isNotEmpty() || $user->is_superadmin;

        $adminDocs = collect();
        $metrics = [];
        $deptIds = $adminDepartments->pluck('id');
        if ($isAdmin) {
            $allDocs = EdtsDocument::with(['user', 'departmentFrom', 'docTypes', 'assignedTo', 'departmentTo', 'forwardedFrom']);

            if ($user->is_superadmin) {
                $adminDocs = (clone $allDocs)->latest()->get();
            } else {
                $adminDocs = (clone $allDocs)
                    ->where(function ($q) use ($deptIds) {
                        $q->whereIn('department_from_id', $deptIds)
                          ->orWhereIn('department_to_id', $deptIds);
                    })
                    ->latest()
                    ->get();
            }

            $baseQuery = $user->is_superadmin
                ? EdtsDocument::query()
                : EdtsDocument::where(function ($q) use ($deptIds) {
                    $q->whereIn('department_from_id', $deptIds)
                      ->orWhereIn('department_to_id', $deptIds);
                });

            $metrics = [
                'total' => (clone $baseQuery)->count(),
                'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
                'review' => (clone $baseQuery)->where('status', 'review')->count(),
                'receive' => (clone $baseQuery)->where('status', 'receive')->count(),
                'in_transit' => (clone $baseQuery)->where('status', 'in_transit')->count(),
                'ready' => (clone $baseQuery)->where('status', 'ready')->count(),
                'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
                'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
                'requestees' => (clone $baseQuery)->distinct('user_id')->count('user_id'),
                'high' => (clone $baseQuery)->where('priority', 'high')->count(),
                'medium' => (clone $baseQuery)->where('priority', 'medium')->count(),
                'low' => (clone $baseQuery)->where('priority', 'low')->count(),
            ];
        }

        return Inertia::render('edts/Index', [
            'documents' => $documents,
            'departments' => $departments,
            'docTypes' => $docTypes,
            'isAdmin' => $isAdmin,
            'adminDepartments' => $adminDepartments->values(),
            'adminDocs' => $adminDocs,
            'metrics' => $metrics,
            'allUsers' => \App\Models\User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function pendingCount()
    {
        $count = EdtsDocument::where('user_id', request()->user()->id)
            ->whereIn('status', ['pending', 'in_transit'])
            ->count();

        return response()->json(['count' => $count]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'requester_name' => 'required|string|max:255',
            'requester_email' => 'required|email|max:255',
            'requester_phone' => 'nullable|string|max:20',
            'department_from_id' => 'required|exists:edts_departments,id',
            'title' => 'required|string|max:255',
            'purpose' => 'required|string|max:500',
            'priority' => 'nullable|in:low,medium,high',
            'notes' => 'nullable|string|max:1000',
            'doc_type_ids' => 'required|array|min:1',
            'doc_type_ids.*' => 'exists:edts_doc_types,id',
            'doc_type_other' => 'nullable|string|max:255',
        ]);

        $referenceNumber = 'EDTS-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $document = EdtsDocument::create([
            'reference_number' => $referenceNumber,
            'user_id' => $request->user()->id,
            'department_from_id' => $validated['department_from_id'],
            'requester_name' => $validated['requester_name'],
            'requester_email' => $validated['requester_email'],
            'requester_phone' => $validated['requester_phone'] ?? null,
            'title' => $validated['title'],
            'type' => null,
            'recipient_office' => null,
            'purpose' => $validated['purpose'],
            'priority' => $validated['priority'] ?? 'medium',
            'notes' => $validated['notes'] ?? null,
            'doc_type_other' => $validated['doc_type_other'] ?? null,
            'status' => 'pending',
        ]);

        $document->docTypes()->attach($validated['doc_type_ids']);

        return redirect()->back()->with('success', 'Document request submitted. Reference: ' . $referenceNumber);
    }

    public function show($referenceNumber)
    {
        $document = EdtsDocument::with(['user', 'departmentFrom', 'docTypes', 'assignedTo', 'forwardedTo', 'departmentTo', 'forwardedFrom'])
            ->where('reference_number', $referenceNumber)
            ->firstOrFail();

        return response()->json($document);
    }

    public function update(Request $request, EdtsDocument $document)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,review,receive,in_transit,ready,completed,rejected',
            'notes' => 'nullable|string|max:1000',
        ]);

        $data = ['status' => $validated['status']];
        if ($validated['status'] === 'receive') {
            $data['received_at'] = now();
            $data['assigned_to_id'] = $request->user()->id;
        } elseif ($validated['status'] === 'review') {
            $data['reviewed_at'] = now();
            $data['assigned_to_id'] ??= $request->user()->id;
        } elseif ($validated['status'] === 'in_transit') {
            $data['received_at'] ??= now();
        } elseif ($validated['status'] === 'ready') {
            $data['ready_at'] = now();
        } elseif ($validated['status'] === 'completed') {
            $data['completed_at'] = now();
        }

        $document->update($data);

        return redirect()->back()->with('success', 'Document status updated.');
    }

    public function receive(EdtsDocument $document)
    {
        $document->update([
            'status' => 'in_transit',
            'assigned_to_id' => request()->user()->id,
            'received_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Document received.');
    }

    public function forward(Request $request, EdtsDocument $document)
    {
        $validated = $request->validate([
            'department_to_id' => 'required|exists:edts_departments,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $adminDeptIds = EdtsDepartmentAdmin::where('user_id', $request->user()->id)->pluck('department_id');
        $forwardedFromId = $adminDeptIds->contains($document->department_from_id)
            ? $document->department_from_id
            : $adminDeptIds->first();

        $document->update([
            'status' => 'in_transit',
            'department_to_id' => $validated['department_to_id'],
            'forwarded_from_id' => $forwardedFromId,
            'forwarded_to_id' => $request->user()->id,
            'notes' => $validated['notes'] ?? $document->notes,
        ]);

        return redirect()->back()->with('success', 'Document forwarded.');
    }

    public function storeDepartment(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        EdtsDepartment::create($validated);

        return redirect()->back()->with('success', 'Department created.');
    }

    public function updateDepartment(Request $request, EdtsDepartment $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $department->update($validated);

        return redirect()->back()->with('success', 'Department updated.');
    }

    public function destroyDepartment(EdtsDepartment $department)
    {
        $department->delete();

        return redirect()->back()->with('success', 'Department deleted.');
    }

    public function storeDocType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        EdtsDocType::create($validated);

        return redirect()->back()->with('success', 'Document type created.');
    }

    public function updateDocType(Request $request, EdtsDocType $docType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $docType->update($validated);

        return redirect()->back()->with('success', 'Document type updated.');
    }

    public function destroyDocType(EdtsDocType $docType)
    {
        $docType->delete();

        return redirect()->back()->with('success', 'Document type deleted.');
    }

    public function assignAdmin(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'department_id' => 'required|exists:edts_departments,id',
        ]);

        EdtsDepartmentAdmin::firstOrCreate($validated);

        return redirect()->back()->with('success', 'Admin assigned.');
    }

    public function removeAdmin(EdtsDepartmentAdmin $admin)
    {
        $admin->delete();

        return redirect()->back()->with('success', 'Admin removed.');
    }
}
