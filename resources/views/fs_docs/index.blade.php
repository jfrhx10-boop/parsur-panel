@extends('layout')
@section('title', \App\Http\Controllers\FsDocsController::TYPES[$type])
@section('content')
@php $label = \App\Http\Controllers\FsDocsController::TYPES[$type]; $isEntrada = in_array($type, ['entrada', 'facturarec', 'pedidoprov'], true); @endphp
<div class="head">
  <div><h1>Pedidos y documentos</h1><div class="muted small">{{ $label }}: {{ number_format($count, 0, ',', '.') }} documentos · {{ eur($sumBase) }} sin IVA @if($year)en {{ $year }}@endif</div></div>
  <button class="btn ghost sm no-print" type="button" onclick="window.print()">🖨 Imprimir lista</button>
</div>
@if($type === 'pedido')
  @include('fs_docs._ordertabs')
@else
  @include('fs_docs._tabs')
@endif
@if($type === 'albaran' && auth()->user()->canSeeSales())
  @php $mine = \App\Models\PanelAlbaran::whereNull('removed_at')->orderByDesc('id')->limit(8)->get(); @endphp
  @if($mine->isNotEmpty())
  <div class="card" style="margin-bottom:12px"><div class="small muted" style="margin-bottom:6px">Albaranes creados desde el panel (por si hay que deshacer alguno):</div>
    @foreach($mine as $pa)
    <form method="post" action="/albaranes-panel/{{ $pa->id }}/quitar" style="display:inline-block;margin:2px 8px 2px 0" onsubmit="return confirm('Se borra el albarán {{ $pa->albaran_code }} de FactuSOL. ¿Seguir?')">@csrf Albarán <b>{{ $pa->albaran_code }}</b> · {{ eur($pa->total) }} <button class="btn ghost sm" type="submit">Quitar</button></form>
    @endforeach
  </div>
  @endif
@endif

<form class="card row" method="get" action="/documentos/{{ $type }}" style="margin-bottom:14px">
  <div style="flex:3 1 240px"><label for="q">Buscar</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nº (2026/1764), {{ $isEntrada ? 'proveedor, su albarán' : 'cliente, máquina' }}, referencia o artículo"></div>
  <div style="flex:0 1 120px"><label for="anio">Ejercicio</label><select id="anio" name="anio"><option value="">Todos</option>@foreach($years as $y)<option value="{{ $y }}" {{ $year === (int) $y ? 'selected' : '' }}>{{ $y }}</option>@endforeach</select></div>
  <div style="flex:0 1 180px"><label for="estado">Estado</label>
    <select id="estado" name="estado">
      <option value="">Todos</option>
      @if($type === 'presupuesto')<option value="pendiente" {{ $st === 'pendiente' ? 'selected' : '' }}>Pendientes</option><option value="1" {{ $st === '1' ? 'selected' : '' }}>Aceptados</option>
      @elseif($type === 'pedido')<option value="pendiente" {{ $st === 'pendiente' ? 'selected' : '' }}>En curso (pendientes de servir)</option><option value="0" {{ $st === '0' ? 'selected' : '' }}>Sin servir nada</option><option value="1" {{ $st === '1' ? 'selected' : '' }}>Parcialmente servidos</option><option value="2" {{ $st === '2' ? 'selected' : '' }}>Servidos</option>
      @elseif($type === 'pedidoprov')<option value="pendiente" {{ $st === 'pendiente' ? 'selected' : '' }}>Pendientes de recibir</option><option value="2" {{ $st === '2' ? 'selected' : '' }}>Recibidos</option>
      @elseif($type === 'albaran')<option value="sinfacturar" {{ $st === 'sinfacturar' ? 'selected' : '' }}>Sin facturar</option><option value="1" {{ $st === '1' ? 'selected' : '' }}>Facturados</option>
      @elseif($type === 'factura')<option value="sincobrar" {{ $st === 'sincobrar' ? 'selected' : '' }}>Pendientes de cobro</option><option value="2" {{ $st === '2' ? 'selected' : '' }}>Cobradas</option>
      @endif
    </select>
  </div>
  <button class="btn" type="submit">Filtrar</button>
</form>

<div class="card">
  @if($docs->isEmpty())<p class="muted" style="margin:0">Nada con ese filtro. Si es la primera vez, sincroniza los documentos desde Ajustes.</p>
  @else
  <div class="docs">
    @foreach($docs as $d)
      @php $p = $parties[$isEntrada ? $d->codpro : $d->codcli] ?? null; @endphp
      @if($type === 'pedido' && $d->status !== 4)
        @include('fs_docs._order', ['d' => $d, 'p' => $progress[$d->id], 'party' => $p, 'partyUrl' => $p ? '/clientes/'.$p->id : null, 'hideParty' => false])
      @else
        @include('fs_docs._row', ['d' => $d, 'party' => $p, 'partyUrl' => $p ? ($isEntrada ? '/proveedores-factusol/'.$p->id : '/clientes/'.$p->id) : null])
      @endif
    @endforeach
  </div>
  @endif
</div>
@if($docs->hasPages())
  <div class="pager">
    @if(!$docs->onFirstPage())<a class="btn ghost sm" href="{{ $docs->previousPageUrl() }}">← Anteriores</a>@endif
    <span class="muted small" style="align-self:center">Página {{ $docs->currentPage() }} de {{ $docs->lastPage() }}</span>
    @if($docs->hasMorePages())<a class="btn ghost sm" href="{{ $docs->nextPageUrl() }}">Siguientes →</a>@endif
  </div>
@endif
@endsection
