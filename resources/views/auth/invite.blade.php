@extends('layout')
@section('title', 'Crear contraseña')
@section('content')
<div class="auth">
  <div class="card">
    <div class="logo"><img src="/img/logo.png" alt="Recambios Parsur"><small>Panel de gestión</small></div>
    <p style="margin-top:0">Hola, <strong>{{ $user->name }}</strong>. Elige tu contraseña para entrar al panel con <strong>{{ $user->email }}</strong>.</p>
    @if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ request()->fullUrl() }}">
      @csrf
      <label for="password">Contraseña <span class="muted small">(mínimo 10, con letras y números)</span></label>
      <input id="password" type="password" name="password" required autofocus autocomplete="new-password">
      <label for="password_confirmation">Repite la contraseña</label>
      <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
      <button class="btn y" type="submit" style="width:100%;margin-top:16px">Guardar y entrar</button>
    </form>
  </div>
</div>
@endsection
