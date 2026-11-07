@extends('portal.layout')
@section('title', 'PIN')
@section('content')
<div class="card" style="max-width:420px;margin:30px auto;text-align:center">
  <h1 style="margin:0 0 6px">{{ $customer->displayName() }}</h1>
  <p class="muted">Introduce tu PIN para entrar.</p>
  @if(!empty($error))<div class="alert err">{{ $error }}</div>@endif
  <form method="post" action="/portal/pin">@csrf
    <input type="password" name="pin" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" maxlength="8" style="font-size:26px;letter-spacing:8px;text-align:center;max-width:220px;margin:0 auto 12px" autofocus>
    <div><button class="btn y" type="submit">Entrar</button></div>
  </form>
</div>
@endsection
