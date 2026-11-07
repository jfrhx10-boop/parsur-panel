@extends('portal.layout')
@section('title', $machine ? 'Editar máquina' : 'Nueva máquina')
@section('content')
<a class="small" href="{{ $machine ? '/portal/maquinas/'.$machine->id : '/portal/inicio' }}">← Volver</a>
<h1 style="margin-top:6px">{{ $machine ? 'Editar máquina' : 'Añadir una máquina' }}</h1>
<form method="post" action="{{ $machine ? '/portal/maquinas/'.$machine->id.'/editar' : '/portal/maquinas/nueva' }}" class="card" style="max-width:720px">
  @csrf
  <div class="row">
    <div style="flex:1 1 180px"><label>Tipo</label><select name="type"><option value="">— elegir —</option>@foreach($types as $t)<option value="{{ $t }}" {{ old('type', $machine?->type) === $t ? 'selected' : '' }}>{{ $t }}</option>@endforeach</select></div>
    <div style="flex:1 1 160px"><label>Marca</label><input type="text" name="brand" value="{{ old('brand', $machine?->brand) }}" placeholder="CAT, Volvo, JCB…" list="pt-brands"></div>
    <div style="flex:1 1 160px"><label>Modelo *</label><input type="text" name="model" value="{{ old('model', $machine?->model) }}" placeholder="320D, L90F, 3CX…" required></div>
  </div>
  <div class="row">
    <div style="flex:1 1 200px"><label>Nº de serie</label><input type="text" name="serial" value="{{ old('serial', $machine?->serial) }}" placeholder="Está en la placa de la máquina"></div>
    <div style="flex:0 1 110px"><label>Año</label><input type="number" name="year" value="{{ old('year', $machine?->year) }}" min="1950" max="2100"></div>
  </div>
  <div class="row">
    <div style="flex:1 1 200px"><label>Maquinista</label><input type="text" name="operator" value="{{ old('operator', $machine?->operator) }}" placeholder="Quién la lleva"></div>
    <div style="flex:1 1 160px"><label>Teléfono del maquinista</label><input type="text" name="operator_phone" value="{{ old('operator_phone', $machine?->operator_phone) }}" inputmode="tel"></div>
  </div>
  <label>Notas</label><textarea name="portal_notes" rows="3" placeholder="Dónde trabaja, horas, lo que quieras apuntar">{{ old('portal_notes', $machine?->portal_notes) }}</textarea>
  <div class="row" style="margin-top:12px;align-items:center;gap:10px"><button class="btn y" type="submit">{{ $machine ? 'Guardar cambios' : 'Añadir máquina' }}</button><a class="btn ghost" href="{{ $machine ? '/portal/maquinas/'.$machine->id : '/portal/inicio' }}">Cancelar</a></div>
  <datalist id="pt-brands">@foreach(['CAT','Volvo','JCB','Komatsu','Hitachi','Hyundai','Doosan','Case','New Holland','Kubota','Takeuchi','Bobcat','Liebherr','John Deere','Manitou','Bomag','Yanmar','Kobelco','Terex','Ausa'] as $b)<option value="{{ $b }}">@endforeach</datalist>
</form>
@endsection
