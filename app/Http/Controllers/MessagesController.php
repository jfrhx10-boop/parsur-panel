<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Message;
use App\Models\Task;
use App\Services\Activity;
use Illuminate\Http\Request;

/** Lado panel del chat con clientes del portal. */
class MessagesController extends Controller
{
    public function index()
    {
        // Una fila por cliente con conversación: último mensaje y nº sin leer
        $last = Message::selectRaw('customer_id, MAX(id) as last_id, SUM(CASE WHEN "from" = \'cliente\' AND read_at IS NULL THEN 1 ELSE 0 END) as unread')->groupBy('customer_id')->get();
        $msgs = Message::whereIn('id', $last->pluck('last_id'))->with('customer')->get()->keyBy('customer_id');
        $threads = $last->map(fn ($r) => ['m' => $msgs[$r->customer_id] ?? null, 'unread' => (int) $r->unread])->filter(fn ($t) => $t['m'])->sortByDesc(fn ($t) => $t['m']->id)->values();

        return view('messages.index', compact('threads'));
    }

    public function show(Customer $customer)
    {
        Message::where('customer_id', $customer->id)->where('from', 'cliente')->whereNull('read_at')->update(['read_at' => now()]);
        Task::where('customer_id', $customer->id)->whereNull('done_at')->where('title', 'like', 'Mensaje del portal%')->update(['done_at' => now(), 'done_by' => auth()->id()]);
        $messages = Message::where('customer_id', $customer->id)->with('user')->orderBy('id')->limit(300)->get();

        return view('messages.show', compact('customer', 'messages'));
    }

    public function send(Request $request, Customer $customer)
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        Message::create(['customer_id' => $customer->id, 'from' => 'parsur', 'user_id' => auth()->id(), 'text' => trim($data['text'])]);
        Activity::log('mensaje_cliente', $customer->displayName());

        return redirect('/mensajes/'.$customer->id);
    }

    public function photo(Customer $customer, Message $message)
    {
        abort_unless($message->customer_id === $customer->id && $message->photo, 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($message->photo);
    }
}
