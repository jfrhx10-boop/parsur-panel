@extends('layout')
@section('title', $fac ? 'Nueva factura recibida' : 'Nueva entrada')
@section('content')
<div class="head"><div><h1>{{ $fac ? 'Nueva factura recibida' : 'Nueva entrada de proveedor' }}</h1><div class="muted small">Busca al proveedor. Después añades las referencias, cantidades y costes; se guarda en FactuSOL.</div></div></div>
<div class="card">
  <form method="get" action="/nueva-entrada" class="row">
    @if($fac)<input type="hidden" name="tipo" value="factura">@endif
    <input type="search" name="q" value="{{ $q }}" placeholder="Nombre, NIF o código…" autofocus style="flex:1;min-width:220px">
    <button class="btn y" type="submit">Buscar proveedor</button>
  </form>
  @if($q !== '')
    <table class="tbl" style="margin-top:12px"><tbody>
    @forelse($found as $s)
      <tr>
        <td><b>{{ $s->displayName() }}</b><div class="small muted">Cód. {{ $s->codpro }} · {{ $s->nif }} · {{ $s->city }}</div></td>
        <td class="num"><a class="btn" href="/nueva-entrada/{{ $s->id }}{{ $fac ? '?tipo=factura' : '' }}">Elegir</a></td>
      </tr>
    @empty<tr><td class="muted">No encuentro ningún proveedor con «{{ $q }}».</td></tr>@endforelse
    </tbody></table>
  @endif
</div>
@endsection
