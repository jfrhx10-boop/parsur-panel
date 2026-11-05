@forelse($tasks as $t)
  <div class="task{{ $t->isOverdue() ? ' overdue' : ($t->isToday() ? ' today' : '') }}{{ $t->done_at ? ' done' : '' }}">
    <form method="post" action="/pendientes/{{ $t->id }}/{{ $t->done_at ? 'reabrir' : 'hecho' }}">@csrf<button class="chk" type="submit" title="{{ $t->done_at ? 'Reabrir' : 'Marcar como hecho' }}">{{ $t->done_at ? '✓' : '' }}</button></form>
    <div class="task-b">
      <div class="task-t">{{ $t->title }}</div>
      <div class="small muted">
        @if($t->due_date)<span class="{{ $t->isOverdue() ? 'bad' : '' }}">{{ $t->isOverdue() ? 'Venció el ' : ($t->isToday() ? 'Hoy · ' : '') }}{{ $t->due_date->locale('es')->isoFormat('ddd D MMM') }}</span> · @endif
        @if(empty($hideCustomer) && $t->customer)<a href="/clientes/{{ $t->customer->id }}">{{ $t->customer->displayName() }}</a> · @endif
        {{ $t->assignee?->name ?? 'cualquiera' }}@if($t->creator) · apuntado por {{ $t->creator->name }}@endif
        @if($t->done_at) · hecho {{ $t->done_at->timezone('Europe/Madrid')->format('d/m H:i') }}@endif
      </div>
      @if($t->notes)<div class="small" style="white-space:pre-line">{{ $t->notes }}</div>@endif
    </div>
    @if(auth()->user()->isAdmin() || $t->created_by === auth()->id())<form method="post" action="/pendientes/{{ $t->id }}/borrar" onsubmit="return confirm('¿Borrar este pendiente?')">@csrf<button class="linkbtn muted" type="submit" title="Borrar">×</button></form>@endif
  </div>
@empty
  <p class="muted" style="margin:0">{{ $emptyText ?? 'Nada pendiente.' }}</p>
@endforelse
