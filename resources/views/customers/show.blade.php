@extends('layout')
@section('title', $customer->displayName())
@section('content')
@php $c = $customer; $tel = preg_replace('/\D+/', '', (string) $c->bestPhone()); @endphp
<div class="head">
  <div>
    <a class="small" href="/clientes">← Clientes</a>
    <h1>{{ $c->displayName() }}</h1>
    <div class="muted small">@if($c->nif){{ $c->nif }} · @endif @if($c->codcli)Código FactuSOL {{ $c->codcli }}@else Cliente solo del panel @endif @if($c->synced_at)· sincronizado {{ $c->synced_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}@endif
      @if($c->tariff)@php $tf = \App\Models\Tariff::all_()[$c->tariff] ?? null; @endphp · <a href="/tarifas" class="badge" style="text-decoration:none" title="{{ $tf?->name }}">Tarifa {{ $c->tariff }}@if($tf) · {{ $tf->detail() }} · margen {{ rtrim(rtrim(number_format($tf->margin, 2, ',', '.'), '0'), ',') }} %@endif</a>@endif</div>
  </div>
</div>

@if($c->alerts->isNotEmpty())
<div class="alerts">
  @foreach($c->alerts as $al)
    <div class="al al-{{ $al->kind }}">
      <span class="al-k">{{ $al->kindLabel() }}</span>
      <span class="al-t">{{ $al->text }}</span>
      <form method="post" action="/clientes/{{ $c->id }}/avisos/{{ $al->id }}/quitar" onsubmit="return confirm('¿Quitar este aviso?')">@csrf<button class="linkbtn" type="submit" title="Quitar aviso">×</button></form>
    </div>
  @endforeach
</div>
@endif

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
    @if($c->paymentTerms() || $c->tariff)<p class="small" style="margin:10px 0 0">@if($c->paymentTerms())<span class="badge {{ str_contains(mb_strtolower($c->paymentTerms()), 'contado') ? 'warn' : '' }}">💳 {{ $c->paymentTerms() }}</span> @endif</p>@endif
    <details style="margin-top:10px"><summary class="small" style="cursor:pointer;font-weight:600">+ Añadir aviso destacado</summary>
      <form method="post" action="/clientes/{{ $c->id }}/avisos" class="row" style="margin-top:6px">
        @csrf
        <div style="flex:0 1 170px"><label for="ak">Tipo</label><select id="ak" name="kind">@foreach(\App\Models\CustomerAlert::KINDS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div style="flex:3 1 220px"><label for="at">Aviso</label><input id="at" type="text" name="text" required maxlength="500" placeholder="DESCUENTO 160 € PENDIENTE DE APLICAR"></div>
        <button class="btn y sm" type="submit">Guardar</button>
      </form>
    </details>
  </div>
  <div class="card">
    <h2>Observaciones de FactuSOL <span class="badge">solo lectura</span></h2>
    @if(!$c->factusol_notes)<p class="muted" style="margin:0">Sin observaciones en FactuSOL.</p>
    @elseif($c->fleet_imported_at)
      <p class="small muted" style="margin:0 0 8px">Las máquinas, piezas y filtros de estas observaciones ya están en la flota de abajo; el resto, en las notas del panel.</p>
      <details><summary class="small" style="cursor:pointer;font-weight:600">Ver el texto original</summary><p class="small" style="margin:8px 0 0;white-space:pre-line">{{ $c->factusol_notes }}</p></details>
    @else<p style="margin:0;white-space:pre-line">{{ $c->factusol_notes }}</p>@endif
  </div>
</div>

<div class="card mt">
  @php $pend = $c->machines->where('review', true)->count(); @endphp
  <div class="head" style="margin-bottom:10px;align-items:center">
    <h2 style="margin:0">Flota <span class="muted small">({{ $c->machines->count() }})</span></h2>
    <div class="small muted">
      @if($refCount){{ $refCount }} referencias apuntadas @endif
      @if($pend) · <span class="badge warn">{{ $pend }} por revisar</span>@endif
    </div>
  </div>
  @if($pend)<p class="small muted" style="margin:0 0 10px">Las máquinas «por revisar» vienen de las observaciones de FactuSOL y les falta la marca o el nº de serie. Si está bien, pulsa <strong>Confirmar</strong>; si no, corrígela a mano o quítala.</p>@endif
  @if($c->machines->isNotEmpty())
  <div class="fleet">
    @foreach($c->machines as $m)
      <div class="fl-row{{ $m->review ? ' rev' : '' }}">
        <div class="fl-name">
          <span class="fl-ico">{!! \App\Services\Icons::machine($m->type, $m->model) !!}</span>
          <div>
            @if($m->brand)<div style="margin-bottom:3px">{!! \App\Services\Icons::brand($m->brand, 'sm') !!}</div>@endif
            <strong>{{ $m->model }}</strong>
            <div class="small muted">{{ $m->type ?: 'Máquina' }}@if($m->year) · {{ $m->year }}@endif</div>
            @if(!empty($sales[$m->id]))<div class="small" style="margin-top:3px">{{ $sales[$m->id]['n'] }} {{ $sales[$m->id]['n'] === 1 ? 'albarán' : 'albaranes' }} · {{ eur($sales[$m->id]['total']) }} <span class="muted">· último {{ $sales[$m->id]['last'] }}</span></div>@endif
            @if($m->review)<span class="badge warn" style="margin-top:4px">por revisar</span>@endif
          </div>
        </div>
        <div class="fl-sn">@if($m->serial)<span class="small muted">Nº serie</span><code>{{ $m->serial }}</code>@else<span class="small muted">sin nº de serie</span>@endif</div>
        <div class="fl-parts">
          @php $list = $items[$m->id] ?? []; $groups = ['filtro' => 'Filtros', 'desgaste' => 'Desgaste', 'recambio' => 'Recambios']; @endphp
          @if(!$list)<span class="small muted">Sin piezas apuntadas</span>@endif
          @foreach($groups as $gk => $gname)
            @php $gi = array_values(array_filter($list, fn ($x) => ($x['kind'] ?? 'recambio') === $gk)); @endphp
            @if($gi)
            <div class="pg">
              <span class="pg-l">{{ $gname }}</span>
              <div class="pg-items">
              @foreach($gi as $it)
                @php $k = $known[$it['ref']] ?? null; @endphp
                <span class="pt {{ $gk }}">{!! \App\Services\Icons::part($it['label'], $it['filter']) !!}<em>{{ $it['label'] }}</em>@if($it['ref']) @if($k)<a href="/proveedores/{{ $k->supplier_id }}/referencias/{{ $k->id }}" title="Ver consumo de esta referencia">{{ $it['ref'] }}</a>@else<b>{{ $it['ref'] }}</b>@endif @endif @if($it['qty'])<small>×{{ $it['qty'] }}</small>@endif</span>
              @endforeach
              </div>
            </div>
            @endif
          @endforeach
          @if($m->notes)<div class="small" style="white-space:pre-line;margin-top:4px">{{ $m->notes }}</div>@endif
        </div>
        <div class="fl-act">
          @if($m->review)<form method="post" action="/clientes/{{ $c->id }}/maquinas/{{ $m->id }}/confirmar">@csrf<button class="btn sm" type="submit">Confirmar</button></form>@endif
          <form method="post" action="/clientes/{{ $c->id }}/maquinas/{{ $m->id }}/borrar" onsubmit="return confirm('¿Quitar esta máquina de la flota?')">@csrf<button class="btn ghost sm" type="submit">Quitar</button></form>
        </div>
      </div>
    @endforeach
  </div>
  @else<p class="muted" style="margin:0 0 10px">Todavía no hay máquinas. Añade las que tiene este cliente.</p>@endif
  <h3 class="small muted" style="margin:16px 0 0;text-transform:uppercase;letter-spacing:.4px">Añadir máquina</h3>
  <form method="post" action="/clientes/{{ $c->id }}/maquinas" class="row" style="margin-top:8px">
    @csrf
    <div><label for="mt">Tipo</label><input id="mt" type="text" name="type" placeholder="Miniexcavadora, dumper…" list="tipos"></div>
    <div><label for="mb">Marca</label><input id="mb" type="text" name="brand" placeholder="Kubota"></div>
    <div><label for="mm">Modelo</label><input id="mm" type="text" name="model" required placeholder="KX016-4"></div>
    <div style="flex:0 1 110px"><label for="my">Año</label><input id="my" type="number" name="year" min="1950" max="2100" placeholder="2015"></div>
    <div><label for="ms">Nº serie</label><input id="ms" type="text" name="serial"></div>
    <div style="flex:2 1 220px"><label for="mp">Piezas / medidas</label><input id="mp" type="text" name="parts" placeholder="F ACEITE SO115 F AIRE SA17118 PUNTAS TSJ450P"></div>
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

@if($c->codcli)
<div class="card mt">
  <div class="head" style="margin-bottom:10px;align-items:center">
    <h2 style="margin:0">Ventas en FactuSOL</h2>
    <div class="small muted">
      @if($albCount){{ $albCount }} albaranes · {{ date('Y') }}: <strong>{{ eur($yearTotal) }}</strong> @if($prevTotal)· {{ date('Y') - 1 }}: {{ eur($prevTotal) }}@endif <span class="muted">(sin IVA)</span>@else Sin albaranes sincronizados @endif
    </div>
  </div>

  @if($pending->isNotEmpty())
    <h3 class="small muted" style="margin:0 0 6px;text-transform:uppercase;letter-spacing:.4px">Pedidos pendientes de servir ({{ $pending->count() }})</h3>
    <div class="docs">
      @foreach($pending as $d)
        <details class="doc">
          <summary>
            <span class="doc-n">Pedido {{ $d->number() }}</span>
            <span class="small muted">{{ $d->date?->format('d/m/Y') }}</span>
            <span class="small">{{ $d->lines_count }} líneas @if($d->ref) · {{ $d->ref }}@endif</span>
            <span class="badge {{ $d->statusClass() }}">{{ $d->statusLabel() }}</span>
            <span class="doc-t">{{ eur($d->total) }}</span>
          </summary>
          @include('customers._doclines', ['d' => $d])
        </details>
      @endforeach
    </div>
  @endif

  <h3 class="small muted" style="margin:{{ $pending->isNotEmpty() ? '16px' : '0' }} 0 6px;text-transform:uppercase;letter-spacing:.4px">Últimos albaranes</h3>
  @if($albaranes->isEmpty())
    <p class="muted" style="margin:0">Todavía no hay albaranes de este cliente. Sincroniza los documentos en Ajustes.</p>
  @else
    <div class="docs">
      @foreach($albaranes as $d)
        <details class="doc"{{ $loop->first ? ' open' : '' }}>
          <summary>
            <span class="doc-n">Albarán {{ $d->number() }}</span>
            <span class="small muted">{{ $d->date?->format('d/m/Y') }}</span>
            <span class="small">{{ $d->lines_count }} líneas @if($d->ref) · {{ $d->ref }}@endif</span>
            <span class="badge {{ $d->statusClass() }}">{{ $d->statusLabel() }}</span>
            <span class="doc-t">{{ eur($d->total) }}</span>
          </summary>
          @include('customers._doclines', ['d' => $d])
        </details>
      @endforeach
    </div>
  @endif
</div>
@endif

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
@if(auth()->user()->isAdmin() && !$c->codcli)
<form method="post" action="/clientes/{{ $c->id }}/borrar" class="mt" onsubmit="return confirm('¿Borrar este cliente con su flota y sus notas?')">@csrf<button class="btn ghost sm" type="submit">Borrar cliente</button></form>
@endif
@endsection
