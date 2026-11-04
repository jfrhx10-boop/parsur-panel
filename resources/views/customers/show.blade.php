@extends('layout')
@section('title', $customer->displayName())
@section('content')
@php $c = $customer; $tel = preg_replace('/\D+/', '', (string) $c->bestPhone()); @endphp
<div class="head">
  <div>
    <a class="small" href="/clientes">← Clientes</a>
    <h1>{{ $c->displayName() }}</h1>
    <div class="muted small">@if($c->nif){{ $c->nif }} · @endif @if($c->codcli)Código FactuSOL {{ $c->codcli }}@else Cliente solo del panel @endif @if($c->synced_at)· sincronizado {{ $c->synced_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}@endif</div>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <h2>Contacto</h2>
    <p style="margin:0">
      @if($c->bestPhone())📞 <a href="tel:{{ $tel }}">{{ $c->bestPhone() }}</a>
        @if($c->phone && $c->mobile && $c->phone !== $c->mobile) · {{ $c->phone }}@endif
        · <a href="https://wa.me/{{ str_starts_with($tel, '34') ? $tel : '34'.$tel }}" target="_blank" rel="noopener">WhatsApp</a><br>@endif
      @if($c->email)✉️ <a href="mailto:{{ $c->email }}">{{ $c->email }}</a><br>@endif
      @if($c->address || $c->city)<span class="muted">{{ $c->address }} {{ $c->postcode }} {{ $c->city }} @if($c->province)({{ $c->province }})@endif</span>@endif
    </p>
    @if($c->name !== $c->displayName())<p class="small muted" style="margin:8px 0 0">Razón social: {{ $c->name }}</p>@endif
  </div>
  <div class="card">
    <h2>Observaciones de FactuSOL <span class="badge">solo lectura</span></h2>
    @if($c->factusol_notes)<p style="margin:0;white-space:pre-line">{{ $c->factusol_notes }}</p>
    @else<p class="muted" style="margin:0">Sin observaciones en FactuSOL.</p>@endif
  </div>
</div>

<div class="card mt">
  <h2>Flota <span class="muted small">({{ $c->machines->count() }})</span></h2>
  @if($c->machines->isNotEmpty())
  <div class="scroll"><table class="tbl">
    <thead><tr><th>Máquina</th><th>Año</th><th>Nº serie</th><th>Piezas / medidas</th><th></th></tr></thead>
    <tbody>
    @foreach($c->machines as $m)
      <tr>
        <td><strong>{{ trim($m->brand.' '.$m->model) }}</strong>@if($m->type)<div class="small muted">{{ $m->type }}</div>@endif
          @if($m->notes)<div class="small" style="white-space:pre-line">{{ $m->notes }}</div>@endif</td>
        <td>{{ $m->year }}</td><td class="small">{{ $m->serial }}</td><td class="small">{{ $m->parts }}</td>
        <td class="r"><form method="post" action="/clientes/{{ $c->id }}/maquinas/{{ $m->id }}/borrar" onsubmit="return confirm('¿Quitar esta máquina de la flota?')">@csrf<button class="btn ghost sm" type="submit">Quitar</button></form></td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  @else<p class="muted" style="margin:0 0 10px">Todavía no hay máquinas. Añade las que tiene este cliente.</p>@endif
  <form method="post" action="/clientes/{{ $c->id }}/maquinas" class="row" style="margin-top:12px">
    @csrf
    <div><label for="mt">Tipo</label><input id="mt" type="text" name="type" placeholder="Miniexcavadora, dumper…" list="tipos"></div>
    <div><label for="mb">Marca</label><input id="mb" type="text" name="brand" placeholder="Kubota"></div>
    <div><label for="mm">Modelo</label><input id="mm" type="text" name="model" required placeholder="KX016-4"></div>
    <div style="flex:0 1 90px"><label for="my">Año</label><input id="my" type="number" name="year" min="1950" max="2100"></div>
    <div><label for="ms">Nº serie</label><input id="ms" type="text" name="serial"></div>
    <div style="flex:2 1 220px"><label for="mp">Piezas / medidas</label><input id="mp" type="text" name="parts" placeholder="Cadena 230x48x70"></div>
    <div style="flex:3 1 300px"><label for="mn">Notas</label><input id="mn" type="text" name="notes"></div>
    <button class="btn y" type="submit">Añadir máquina</button>
  </form>
  <datalist id="tipos"><option>Miniexcavadora</option><option>Excavadora</option><option>Dumper</option><option>Minicargadora</option><option>Pala cargadora</option><option>Bulldozer</option><option>Motoniveladora</option><option>Compactador</option><option>Tractor</option></datalist>
</div>

<div class="card mt">
  <h2>Notas del panel</h2>
  <form method="post" action="/clientes/{{ $c->id }}/notas" class="row" style="margin-bottom:10px">
    @csrf
    <div style="flex:5 1 320px"><label for="nb" class="muted small">Nueva nota</label><input id="nb" type="text" name="body" required maxlength="5000" placeholder="Qué ha pasado, qué necesita, qué se le prometió…"></div>
    <button class="btn y" type="submit">Guardar</button>
  </form>
  @forelse($c->notes as $n)
    <div style="border-top:1px solid var(--line);padding:10px 0">
      <div class="small muted">{{ $n->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }} · {{ $n->user?->name ?? '—' }}
        @if(auth()->user()->isAdmin() || $n->user_id === auth()->id())
          · <form method="post" action="/clientes/{{ $c->id }}/notas/{{ $n->id }}/borrar" style="display:inline" onsubmit="return confirm('¿Borrar la nota?')">@csrf<button class="linkbtn" type="submit">borrar</button></form>
        @endif</div>
      <div style="white-space:pre-line">{{ $n->body }}</div>
    </div>
  @empty
    <p class="muted" style="margin:0">Aún no hay notas.</p>
  @endforelse
</div>

<div class="card mt">
  <h2>Pedidos de la web</h2>
  @if($ordersError)<div class="alert err">{{ $ordersError }}</div>
  @elseif(!$c->email && !$c->bestPhone())<p class="muted" style="margin:0">Sin email ni teléfono no se pueden buscar sus pedidos.</p>
  @elseif(count($orders) === 0)<p class="muted" style="margin:0">No se han encontrado pedidos de la web para este cliente.</p>
  @else
  <div class="scroll"><table class="tbl">
    <thead><tr><th>Pedido</th><th>Fecha</th><th>Productos</th><th>Estado</th><th class="r">Total</th></tr></thead>
    <tbody>
    @foreach($orders as $o)
      <tr>
        <td><a href="/pedidos/{{ $o['id'] }}">#{{ $o['number'] }}</a></td>
        <td class="small">{{ fecha($o['date_created']) }}</td>
        <td class="small">@foreach($o['line_items'] as $li){{ $li['quantity'] }}× {{ \Illuminate\Support\Str::limit($li['name'], 50) }}<br>@endforeach</td>
        <td><span class="badge b-{{ $o['status'] }}">{{ estado_pedido($o['status']) }}</span></td>
        <td class="r">{{ eur($o['total']) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  @endif
</div>
@endsection
