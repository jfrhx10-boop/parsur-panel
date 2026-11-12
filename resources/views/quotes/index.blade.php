@extends('layout')
@section('title', 'Presupuestos')
@section('content')
@if($drafts->isNotEmpty())<div class="alert ok"><b>Sin terminar:</b> @foreach($drafts as $d)<a href="/presupuestos/{{ $d->id }}">{{ $d->kind === 'pedido' ? 'Pedido' : 'Albarán' }} de {{ $d->customer?->displayName() }}</a>@if(! $loop->last) · @endif @endforeach</div>@endif
<div class="head">
  <div><h1>Presupuestos</h1><div class="muted small">Un solo sitio: lo que haces aquí queda guardado en FactuSOL y lo que haces en FactuSOL aparece aquí.</div></div>
</div>
@include('partials_newbtns')
@if($importing)<div class="alert ok">Estoy trayendo los presupuestos antiguos de FactuSOL (primero los más recientes). <a href="/presupuestos">Recarga</a> para ver más; en unas pocas recargas están todos.</div>@endif
<div class="tabs">
  <a href="/presupuestos" class="{{ $st === '' ? 'on' : '' }}">Todos</a>
  @foreach(\App\Models\Quote::STATUSES as $k => $v)<a href="/presupuestos?estado={{ $k }}" class="{{ $st === $k ? 'on' : '' }}">{{ $v }} ({{ $counts[$k] ?? 0 }})</a>@endforeach
</div>
<form method="get" action="/presupuestos" class="row" style="margin:10px 0">
  @if($st !== '')<input type="hidden" name="estado" value="{{ $st }}">@endif
  <input type="search" name="q" value="{{ $term }}" placeholder="Buscar por cliente, teléfono, número o referencia…" style="flex:1;min-width:200px">
  <input type="date" name="desde" value="{{ $from }}" title="Desde" aria-label="Desde" style="flex:0 1 150px">
  <input type="date" name="hasta" value="{{ $to }}" title="Hasta" aria-label="Hasta" style="flex:0 1 150px">
  <label class="small" style="display:flex;align-items:center;gap:6px;white-space:nowrap"><input type="checkbox" name="sinficha" value="1" @checked($sinficha) onchange="this.form.submit()"> Solo sin ficha de cliente</label>
  <button class="btn" type="submit">Buscar</button>
  @include('partials_dates', ['base' => '/presupuestos'])
</form>
@if($sum)<div class="small muted" style="margin:-2px 0 10px">{{ number_format($sum['n'], 0, ',', '.') }} presupuestos · {{ eur($sum['total']) }} con IVA</div>@endif
<div class="card"><div class="scroll">
  <table class="tbl qlist">
    <thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Máquina / ref.</th><th>Estado</th><th class="num">Total</th><th></th></tr></thead>
    <tbody>
    @forelse($quotes as $q)
      <tr data-print="/presupuestos/{{ $q->id }}/pdf">
        <td><a href="/presupuestos/{{ $q->id }}"><strong>{{ $q->number }}</strong></a>
          @if($q->fs_order_code)<div class="small"><span class="badge ok">Pedido {{ $q->fs_order_code }}</span></div>@elseif(! $q->fs_code)<div class="small"><span class="badge warn" title="Todavía no está en FactuSOL">Sin enviar a FactuSOL</span></div>@endif @if($q->fs_error)<div class="small"><span class="badge bad" title="{{ $q->fs_error }}">⚠ Cambio sin guardar en FactuSOL</span></div>@endif</td>
        <td class="small">{{ $q->date?->format('d/m/Y') }}@if($q->valid_until)<div class="muted">válido hasta {{ $q->valid_until->format('d/m/Y') }}</div>@endif</td>
        <td>@if($q->noClient())<a href="/presupuestos/{{ $q->id }}">{{ $q->customer_name }}</a> <span class="badge warn" title="En FactuSOL este presupuesto no tiene cliente: solo un nombre o un teléfono">Sin ficha</span>@if($q->customer?->mobile) <a class="wa-mini" target="_blank" rel="noopener" href="https://wa.me/{{ str_starts_with($q->customer->mobile, '34') && strlen($q->customer->mobile) > 9 ? $q->customer->mobile : '34'.$q->customer->mobile }}">WhatsApp</a>@endif
          @else<a href="/clientes/{{ $q->customer_id }}">{{ $q->customer?->displayName() }}</a>@if($q->customer?->codcli) <span class="small muted">#{{ $q->customer->codcli }}</span>@endif @endif</td>
        <td class="small">{{ $q->ref }}</td>
        <td><span class="badge {{ $q->statusClass() }}">{{ $q->statusLabel() }}</span></td>
        <td class="num"><strong>{{ eur($q->total) }}</strong></td>
        <td class="num" style="white-space:nowrap">
          <a class="btn ghost sm" href="/presupuestos/{{ $q->id }}/pdf" target="_blank">PDF</a>
          @if(auth()->user()->canSeeSales() && ! $q->fs_order_code)<form method="post" action="/presupuestos/{{ $q->id }}/borrar" style="display:inline" onsubmit="return confirm('{{ $q->fs_code ? 'Se borra el presupuesto '.$q->number.' del panel y también de FactuSOL. No se puede deshacer. ¿Seguro?' : '¿Borrar este presupuesto?' }}')">@csrf<button class="btn ghost sm" type="submit" title="Borrar" style="color:var(--bad)">🗑 Borrar</button></form>@endif
        </td>
      </tr>
    @empty<tr><td colspan="7" class="muted">No hay presupuestos que coincidan.</td></tr>@endforelse
    </tbody>
  </table>
</div></div>
@if($quotes->hasPages())<div class="pager">@if(!$quotes->onFirstPage())<a class="btn ghost sm" href="{{ $quotes->previousPageUrl() }}">← Anteriores</a>@endif @if($quotes->hasMorePages())<a class="btn ghost sm" href="{{ $quotes->nextPageUrl() }}">Siguientes →</a>@endif</div>@endif
@endsection
