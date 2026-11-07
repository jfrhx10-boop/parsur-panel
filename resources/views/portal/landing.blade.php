@extends('portal.layout')
@section('title', 'Acceso')
@section('content')
<div class="pt-center">
  <div class="card" style="max-width:460px;margin:30px auto;text-align:center">
    <img src="/img/logo.png" alt="Recambios Parsur" style="height:44px;margin-bottom:14px">
    <h1 style="margin:0 0 8px">Área de clientes</h1>
    @if(!empty($bad))<div class="alert err">Este enlace no es válido o ya no está activo.</div>@endif
    <p class="muted">Para entrar usa el <b>enlace privado</b> que te hemos enviado por WhatsApp o correo. Si no lo tienes, pídenoslo:</p>
    <p><a class="btn y" href="https://wa.me/34624172538?text={{ rawurlencode('Hola, quiero mi enlace del área de clientes de Parsur') }}" target="_blank" rel="noopener">Pedir mi enlace por WhatsApp</a></p>
    <p class="small muted">957 287 109 · info@recambiosparsur.com</p>
  </div>
</div>
@endsection
