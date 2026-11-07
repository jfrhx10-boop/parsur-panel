@extends('portal.layout')
@section('title', ucfirst($d->typeLabel()).' '.$d->number())
@section('content')
<a class="small" href="/portal/gastos?anio={{ $d->year }}">← Gastos</a>
<div class="head" style="margin-top:6px"><div><h1>{{ ucfirst($d->typeLabel()) }} {{ $d->number() }}</h1><div class="muted small">{{ $d->date?->format('d/m/Y') }}@if($d->ref) · {{ \App\Services\Portal::clean($d->ref) }}@endif</div></div>
@if($d->type === 'albaran')<a class="btn y" href="/portal/pedidos/nuevo?repetir={{ $d->year }}/{{ $d->code }}">Repetir este pedido</a>@endif</div>
<div class="card">
  <table class="tbl"><thead><tr><th>Concepto</th><th class="num">Cant.</th><th class="num">Importe</th></tr></thead><tbody>
  @foreach($d->lines as $l)<tr><td>{{ \App\Services\Portal::clean($l->description) ?: 'Artículo' }}</td><td class="num">{{ rtrim(rtrim(number_format((float) $l->qty, 2, ',', '.'), '0'), ',') }}</td><td class="num">{{ eur($l->total) }}</td></tr>@endforeach
  </tbody><tfoot><tr><td colspan="2" class="num">Base imponible</td><td class="num"><b>{{ eur($d->base) }}</b></td></tr><tr><td colspan="2" class="num">Total con IVA</td><td class="num"><b>{{ eur($d->total) }}</b></td></tr></tfoot></table>
</div>
@endsection
