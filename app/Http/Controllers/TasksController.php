<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use App\Services\Activity;
use Illuminate\Http\Request;

class TasksController extends Controller
{
    public function index(Request $request)
    {
        $filter = (string) $request->query('ver', 'pendientes');
        $q = Task::with(['customer', 'assignee', 'creator'])->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderByDesc('id');
        if ($filter === 'hechas') {
            $q->whereNotNull('done_at')->orderByDesc('done_at')->limit(100);
        } elseif ($filter === 'mias') {
            $q->pending()->where('assigned_to', auth()->id());
        } else {
            $q->pending();
        }
        $tasks = $q->get();
        $users = User::orderBy('name')->get(['id', 'name']);
        $c = Task::counters();

        return view('tasks.index', compact('tasks', 'filter', 'users', 'c'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
        ]);
        $t = Task::create($data + ['created_by' => auth()->id()]);
        Activity::log('pendiente_creado', $t->title);

        return back()->with('ok', 'Pendiente guardado. Lo veis todos.');
    }

    public function done(Task $task)
    {
        $task->update(['done_at' => now(), 'done_by' => auth()->id()]);

        return back()->with('ok', 'Hecho: '.$task->title);
    }

    public function reopen(Task $task)
    {
        $task->update(['done_at' => null, 'done_by' => null]);

        return back()->with('ok', 'Pendiente reabierto.');
    }

    public function destroy(Task $task)
    {
        abort_unless(auth()->user()->isAdmin() || $task->created_by === auth()->id(), 403);
        $task->delete();

        return back()->with('ok', 'Pendiente borrado.');
    }
}
