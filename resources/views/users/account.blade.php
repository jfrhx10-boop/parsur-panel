@extends('layout')
@section('title', 'Mi cuenta')
@section('content')
<div class="head"><div><h1>Mi cuenta</h1><div class="muted small">{{ auth()->user()->email }} · {{ auth()->user()->roleLabel() }}</div></div></div>
<div class="card" style="max-width:520px">
  <h2>Cambiar contraseña</h2>
  <form method="post" action="/mi-cuenta/contrasena">
    @csrf
    <label for="c">Contraseña actual</label>
    <input id="c" type="password" name="current_password" required autocomplete="current-password">
    <label for="p">Nueva contraseña <span class="muted small">(mínimo 10, con letras y números)</span></label>
    <input id="p" type="password" name="password" required autocomplete="new-password">
    <label for="p2">Repite la nueva contraseña</label>
    <input id="p2" type="password" name="password_confirmation" required autocomplete="new-password">
    <button class="btn" type="submit" style="margin-top:14px">Guardar</button>
  </form>
</div>
@endsection
