@extends('portal.layout')
@section('title', 'Contactos')
@section('content')
<h1>Contactos</h1>
<p class="muted small">Maquinistas, proveedores y talleres con los que trabajas. Solo los ves tú y Recambios Parsur.</p>
<div class="ptg">
  <div class="ptg-l">
    @foreach(\App\Models\PortalContact::KINDS as $k => $t)
      @php $g = $contacts->get($k, collect()); @endphp
      @if($g->isNotEmpty())
      <div class="card" style="margin-bottom:12px"><h2>{{ $t }}s</h2>
        @foreach($g as $ct)<div class="pd"><div><b>{{ $ct->name }}</b>@if($ct->phone) · <a href="tel:{{ preg_replace('/\D+/', '', $ct->phone) }}">{{ $ct->phone }}</a>@endif @if($ct->notes)<div class="small muted">{{ $ct->notes }}</div>@endif</div><form method="post" action="/portal/contactos/{{ $ct->id }}/borrar" onsubmit="return confirm('¿Eliminar este contacto?')">@csrf<button class="linkbtn small" type="submit">eliminar</button></form></div>@endforeach
      </div>
      @endif
    @endforeach
    @if($contacts->isEmpty())<div class="card"><p class="muted">Aún no hay contactos.</p></div>@endif
  </div>
  <div class="ptg-r">
    <form method="post" action="/portal/contactos" class="card">@csrf
      <h2>Añadir contacto</h2>
      <label>Tipo</label><select name="kind">@foreach(\App\Models\PortalContact::KINDS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select>
      <label>Nombre *</label><input type="text" name="name" required>
      <label>Teléfono</label><input type="text" name="phone" inputmode="tel">
      <label>Notas</label><input type="text" name="notes" placeholder="Qué máquina lleva, qué suministra…">
      <div style="margin-top:12px"><button class="btn y" type="submit">Guardar</button></div>
    </form>
  </div>
</div>
@endsection
