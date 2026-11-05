@extends('layout')
@section('title', 'Primer acceso')
@section('content')
<div class="auth">
  <div class="card">
    <div class="logo"><img src="/img/logo.png" alt="Recambios Parsur"><small>Panel de gestión</small></div>
    <p class="muted" style="margin-top:0">Crea la cuenta de administrador. Esta pantalla solo aparece una vez: en cuanto exista el primer usuario, desaparece.</p>
    @if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
    <form method="post" action="/instalar">
      @csrf
      <label for="name">Tu nombre</label>
      <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>
      <label for="email">Email</label>
      <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
      <label for="password">Contraseña <span class="muted small">(mínimo 10, con letras y números)</span></label>
      <input id="password" type="password" name="password" required autocomplete="new-password">
      <label for="password_confirmation">Repite la contraseña</label>
      <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
      <button class="btn y" type="submit" style="width:100%;margin-top:16px">Crear cuenta de administrador</button>
    </form>
  </div>
</div>
@endsection
