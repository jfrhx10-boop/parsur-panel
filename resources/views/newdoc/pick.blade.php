@extends('layout')
@section('title', 'Nuevo '.$label)
@section('content')
<div class="head"><div><h1>Nuevo {{ $label }}</h1><div class="muted small">Busca al cliente. Después añades las referencias y cantidades; los precios salen de su tarifa.</div></div></div>
<div class="card">
  <form method="get" action="/nuevo/{{ $kind }}" class="row">
    <input type="search" name="q" value="{{ $q }}" placeholder="Nombre, NIF, teléfono o nº de cliente…" autofocus style="flex:1;min-width:220px">
    <button class="btn y" type="submit">Buscar cliente</button>
  </form>
  @if($q !== '')
    <table class="tbl" style="margin-top:12px"><tbody>
    @forelse($found as $c)
      <tr>
        <td><b>{{ $c->displayName() }}</b>@if($c->codcli) <span class="badge" title="Número de cliente en FactuSOL">nº {{ $c->codcli }}</span>@endif<div class="small muted">{{ $c->nif }} · {{ $c->city }} @unless($c->codcli)· <span class="badge warn">solo en el panel</span>@endunless</div></td>
        <td class="num"><form method="post" action="/nuevo/{{ $kind }}">@csrf<input type="hidden" name="customer_id" value="{{ $c->id }}"><button class="btn" type="submit">Elegir</button></form></td>
      </tr>
    @empty<tr><td class="muted">No encuentro ningún cliente con «{{ $q }}».</td></tr>@endforelse
    </tbody></table>
  @endif
</div>
@if($kind === 'presupuesto')
<div class="card" style="margin-top:12px">
  <h3 style="margin:0 0 4px">Sin ficha de cliente</h3>
  <div class="muted small" style="margin-bottom:8px">Para un teléfono o un nombre suelto, sin dar de alta al cliente (en FactuSOL queda igual que los que se hacen allí sin cliente). Precios de tarifa 1.</div>
  <form method="post" action="/nuevo/presupuesto" class="row">@csrf
    <input type="text" name="sin_ficha" maxlength="100" placeholder="Teléfono y/o nombre, p. ej. 636 97 57 69 o JUAN PÉREZ 636975769" style="flex:1;min-width:220px" required>
    <button class="btn" type="submit">Crear sin ficha</button>
  </form>
</div>
@endif
@endsection
