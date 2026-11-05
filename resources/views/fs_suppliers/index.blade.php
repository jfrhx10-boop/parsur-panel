@extends('layout')
@section('title', 'Proveedores')
@section('content')
<div class="head">
  <div><h1>Proveedores</h1><div class="muted small">{{ $suppliers->count() }} proveedores de FactuSOL, ordenados por compras de {{ $y }} (sin IVA) · <a href="/proveedores">consumo HIFI por referencia</a></div></div>
</div>
<form class="card row" method="get" action="/proveedores-factusol" style="margin-bottom:14px">
  <div style="flex:3 1 260px"><label for="q">Buscar</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nombre, NIF o población"></div>
  <button class="btn" type="submit">Buscar</button>
</form>
<div class="card"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Proveedor</th><th class="hide-m">Contacto</th><th class="num">Entradas {{ $y }}</th><th class="num">Compras {{ $y }}</th><th class="num hide-m">Compras {{ $y - 1 }}</th><th>Última entrada</th></tr></thead>
    <tbody>
    @forelse($suppliers as $s)@php $a = $byPro[$s->codpro][$y] ?? null; $b = $byPro[$s->codpro][$y - 1] ?? null; @endphp
      <tr>
        <td><a href="/proveedores-factusol/{{ $s->id }}">{{ $s->displayName() }}</a><div class="small muted">{{ $s->nif }} @if($s->city)· {{ $s->city }}@endif</div></td>
        <td class="small hide-m">{{ $s->phone }}@if($s->email)<br><span class="muted">{{ $s->email }}</span>@endif</td>
        <td class="num">{{ $a?->n ?? '—' }}</td>
        <td class="num">{{ $a ? eur($a->base) : '—' }}</td>
        <td class="num hide-m muted">{{ $b ? eur($b->base) : '—' }}</td>
        <td class="small">{{ $a?->last ? \Carbon\Carbon::parse($a->last)->format('d/m/Y') : ($b?->last ? \Carbon\Carbon::parse($b->last)->format('d/m/Y') : '') }}</td>
      </tr>
    @empty<tr><td colspan="6" class="muted">Sin proveedores. Sincroniza artículos y proveedores desde Ajustes.</td></tr>@endforelse
    </tbody>
  </table>
</div></div>
@endsection
