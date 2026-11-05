@extends('layout')
@section('title', 'Entrar')
@section('content')
<div class="auth">
  <div class="card">
    <div class="logo"><img src="/img/logo.png" alt="Recambios Parsur"><small>Panel de gestión</small></div>
    <p class="muted" style="margin-top:0">Acceso solo para el equipo de Recambios Parsur.</p>
    @if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
    <form method="post" action="/entrar">
      @csrf
      <label for="email">Email</label>
      <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
      <label for="password">Contraseña</label>
      <input id="password" type="password" name="password" required autocomplete="current-password">
      <label class="check"><input type="checkbox" name="remember" value="1"> Mantener la sesión en este dispositivo</label>
      <button class="btn y" type="submit" style="width:100%;margin-top:16px">Entrar</button>
    </form>
  </div>
</div>
@endsection
