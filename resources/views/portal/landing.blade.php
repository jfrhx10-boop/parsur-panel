@extends('portal.layout')
@section('title', 'Acceso')
@section('content')
<div class="card" style="max-width:440px;margin:30px auto">
  <div style="text-align:center"><div style="background:#242424;border-radius:12px;padding:16px 14px;margin-bottom:14px"><img src="/img/parsur-flota.png" alt="Parsur Flota: tu maquinaria, bajo control" style="max-width:100%;height:auto;width:300px;display:block;margin:0 auto"></div><h1 style="margin:0 0 4px">Tu área de cliente</h1><p class="muted small" style="margin:0 0 14px">Tus máquinas, lo que llevan, tus gastos y tus pedidos.</p></div>
  @if(!empty($bad))<div class="alert err">Este enlace no es válido o ya no está activo.</div>@endif
  @if(!empty($error))<div class="alert err">{{ $error }}</div>@endif
  <form method="post" action="/portal/entrar" autocomplete="off">@csrf
    <label>Teléfono</label><input type="tel" name="telefono" inputmode="tel" autocomplete="off" value="{{ $telefono ?? '' }}" placeholder="El que tenemos en tu ficha" required style="width:100%;font-size:20px;padding:12px 14px">
    <label>PIN</label><input type="password" name="pin" inputmode="numeric" pattern="[0-9]*" autocomplete="new-password" maxlength="8" required style="width:100%;font-size:22px;letter-spacing:6px;padding:12px 14px">
    <button class="btn y" type="submit" style="width:100%;margin-top:8px;font-size:18px;padding:13px">Entrar</button>
  </form>
  <p class="small muted" style="margin:14px 0 0;text-align:center">¿No tienes PIN o no entra? <a href="https://wa.me/34624172538?text={{ rawurlencode('Hola, quiero acceso al área de clientes de Parsur') }}" target="_blank" rel="noopener">Pídenoslo por WhatsApp</a> · 957 287 109</p>
</div>
@endsection
