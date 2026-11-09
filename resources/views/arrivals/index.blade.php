@extends('layout')
@section('title', 'Llegada de mercancía')
@section('content')
<div class="head"><div><h1>Llegada de mercancía</h1><div class="muted small">Escribe lo que acaba de llegar (una referencia por línea, con la cantidad). El panel lo cruza con los pedidos de clientes que lo esperan.</div></div></div>
<div class="card">
  <form method="post" action="/llegadas/revisar">
    @csrf
    <label for="t">Referencias y cantidades</label>
    <textarea id="t" name="text" rows="6" placeholder="TS4K6936HD 10&#10;4K41 10&#10;4K43 10" style="width:100%;font:inherit">{{ $text }}</textarea>
    <div class="row" style="margin-top:10px"><button class="btn y" type="submit">Ver a qué pedidos corresponde</button></div>
  </form>
</div>
@if($plan)
<div class="card">
  <h2 style="margin-top:0">Reparto propuesto</h2>
  <table class="tbl">
    <thead><tr><th>Referencia</th><th class="num">Llega</th><th>Pedidos que lo esperan</th><th class="num">Sobra</th></tr></thead>
    <tbody>
    @foreach($plan['items'] as $it)
      <tr>
        <td>@if($it['article'])<code class="art">{{ $it['article']->codart }}</code> <span class="small muted">{{ $it['article']->description }}</span>@else<b>{{ $it['ref'] }}</b> <span class="badge bad">no está en el catálogo</span>@endif</td>
        <td class="num">{{ \App\Services\OrderProgress::fmt($it['qty']) }}</td>
        <td class="small">
          @forelse($it['alloc'] as $a)
            <div><b>{{ \App\Services\OrderProgress::fmt($a['qty']) }}</b> → pedido {{ $a['doc']->code }} · {{ $a['customer'] }}</div>
          @empty<span class="muted">Ningún pedido abierto lo espera</span>@endforelse
        </td>
        <td class="num">{{ $it['rest'] > 0 ? \App\Services\OrderProgress::fmt($it['rest']) : '–' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
  <p class="small muted">Lo que sobra va a stock (no se asigna a ningún pedido).</p>
  <form method="post" action="/llegadas/confirmar">
    @csrf
    <input type="hidden" name="text" value="{{ $text }}">
    @if(auth()->user()->canSeeSales())<label class="small" style="display:block;margin:8px 0"><input type="checkbox" name="albaranes" value="1" checked> Crear además los albaranes en FactuSOL con lo asignado</label>@endif
    <button class="btn y" type="submit">✓ Validar llegada</button>
  </form>
</div>
@endif
@endsection
