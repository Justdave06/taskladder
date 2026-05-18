<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'content' => ['required', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $maxPos = Note::where('project_id', $validated['project_id'])->max('position') ?? 0;

        Note::create([
            'project_id' => $validated['project_id'],
            'content' => $validated['content'],
            'color' => $validated['color'] ?? '#FEEBC8',
            'position' => $maxPos + 1,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $note->update(array_filter($validated));

        return redirect()->back();
    }

    public function destroy(Request $request, Note $note): RedirectResponse
    {
        $note->delete();

        return redirect()->back();
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'array'],
            'notes.*.id' => ['required', 'exists:notes,id'],
            'notes.*.position' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['notes'] as $item) {
            Note::where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return redirect()->back();
    }
}
