@extends('layout')
@section('title', $reference->ref)
@section('content')
@php
  $maxA = max(1, (float) $rows->max('amount')); $maxQ = max(1, (int) $rows->max('quantity'));
@endphp
<div class="head"><div><a class="small" href="/proveedores/{{ $supplier->id }}">← {{ $supplier->name }}</a>
<h1>{{ $reference->ref }}</h1><div class="muted small">Familia {{ $reference->family ?: '—' }}{{ $reference->label ? ' · '.$reference->label : '' }}</div></div></div>
<div class="grid g2">
  <div class="card"><h2>Importe por año (€ {{ $supplier->vat === 'con' ? 'con IVA' : 'sin IVA' }})</h2>
    <div class="bars">@foreach($years as $y)<div class="col"><div class="val">{{ isset($rows[$y]) ? eur($rows[$y]->amount) : '—' }}</div><div class="bar" style="height:{{ isset($rows[$y]) ? round($rows[$y]->amount / $maxA * 150) : 0 }}px"></div></div>@endforeach</div>
    <div class="bars-x">@foreach($years as $y)<div>{{ $y }}</div>@endforeach</div></div>
  <div class="card"><h2>Unidades por año</h2>
    <div class="bars">@foreach($years as $y)<div class="col"><div class="val">{{ $rows[$y]->quantity ?? '—' }}</div><div class="bar c1" style="height:{{ isset($rows[$y]) ? round($rows[$y]->quantity / $maxQ * 150) : 0 }}px"></div></div>@endforeach</div>
    <div class="bars-x">@foreach($years as $y)<div>{{ $y }}</div>@endforeach</div></div>
</div>
<div class="card mt"><div class="scroll"><table class="tbl"><thead><tr><th>Año</th><th class="num">Unidades</th><th class="num">Importe</th><th class="num">Precio medio</th></tr></thead><tbody>
@foreach($years as $y)@php $r = $rows[$y] ?? null; @endphp
<tr><td>{{ $y }}</td><td class="num">{{ $r->quantity ?? '—' }}</td><td class="num">{{ $r ? eur($r->amount) : '—' }}</td><td class="num">{{ $r && $r->quantity ? eur($r->amount / $r->quantity) : '—' }}</td></tr>
@endforeach</tbody></table></div></div>
@endsection
