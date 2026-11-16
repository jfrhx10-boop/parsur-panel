@extends('layout')
@section('title', 'Nuevo cliente')
@section('content')
<div class="head"><div><h1>Nuevo cliente</h1><div class="muted small">Se da de alta en FactuSOL. Todo se guarda en mayúsculas salvo el correo. Comprueba antes con el buscador que no exista ya.</div></div><a class="btn ghost sm" href="/clientes">← Clientes</a></div>
<form class="card" method="post" action="/clientes/nuevo" style="max-width:760px">@csrf
  <div class="row"><div style="flex:2 1 320px"><label for="cn">Nombre fiscal *</label><input id="cn" name="name" value="{{ old('name') }}" required maxlength="100" style="text-transform:uppercase"></div>
    <div style="flex:1 1 160px"><label for="cnif">NIF / DNI</label><input id="cnif" name="nif" value="{{ old('nif') }}" maxlength="20" style="text-transform:uppercase"></div></div>
  <div class="row"><div style="flex:1 1 100%"><label for="cd">Dirección</label><input id="cd" name="address" value="{{ old('address') }}" maxlength="100" style="text-transform:uppercase"></div></div>
  <div class="row"><div style="flex:0 1 110px"><label for="cp">C. P.</label><input id="cp" name="postcode" value="{{ old('postcode') }}" maxlength="10"></div>
    <div style="flex:1 1 200px"><label for="cc">Población</label><input id="cc" name="city" value="{{ old('city') }}" maxlength="60" style="text-transform:uppercase"></div>
    <div style="flex:1 1 160px"><label for="cpr">Provincia</label><input id="cpr" name="province" value="{{ old('province') }}" maxlength="60" style="text-transform:uppercase"></div></div>
  <div class="row"><div style="flex:1 1 160px"><label for="ct">Teléfono</label><input id="ct" name="phone" value="{{ old('phone') }}" maxlength="30" inputmode="tel"></div>
    <div style="flex:2 1 260px"><label for="ce">Correo</label><input id="ce" type="email" name="email" value="{{ old('email') }}" maxlength="100"></div>
    <div style="flex:0 1 110px"><label for="ctar">Tarifa</label><select id="ctar" name="tariff">@for($i = 1; $i <= 6; $i++)<option value="{{ $i }}" @selected((int) old('tariff', 1) === $i)>{{ $i }}</option>@endfor</select></div></div>
  <div class="row" style="margin-top:10px"><button class="btn y" type="submit">Crear cliente en FactuSOL</button></div>
</form>
@endsection
