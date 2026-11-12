@extends('portal.layout')
@section('title', 'Pedir piezas')
@section('content')
@php
  $mq = $machine ? 'maquina='.$machine->id : '';
  $step = ! $machine ? 1 : ($tipo === '' ? 2 : 3);
@endphp
<a class="small" href="{{ $machine ? '/portal/maquinas/'.$machine->id : '/portal/inicio' }}">← Volver</a>
<h1 style="margin-top:6px">Pedir piezas</h1>
<div class="steps"><span class="{{ $step >= 1 ? 'on' : '' }}">1 Máquina</span><span class="{{ $step >= 2 ? 'on' : '' }}">2 ¿Qué necesitas?</span><span class="{{ $step >= 3 ? 'on' : '' }}">3 Piezas y enviar</span></div>

{{-- Paso 1: máquina --}}
@if($step === 1)
<div class="card">
  <h2>¿Para qué máquina es?</h2>
  <div class="pick">
    @foreach($machines as $m)<a class="pick-o" href="/portal/pedidos/nuevo?maquina={{ $m->id }}">{!! \App\Services\Icons::machine($m->type, $m->model) !!}<span><b>{{ trim(($m->brand ?? '').' '.$m->model) }}</b><small>{{ $m->type ?: 'Máquina' }}@if($m->serial) · {{ $m->serial }}@endif</small></span></a>@endforeach
    <a class="pick-o" href="/portal/pedidos/nuevo?tipo=otro"><i class="ic ic-help"></i><span><b>Para ninguna en concreto</b><small>o una que no está en la lista</small></span></a>
  </div>
</div>

{{-- Paso 2: consulta --}}
@elseif($step === 2)
<div class="card">
  <h2>{{ trim(($machine->brand ?? '').' '.$machine->model) }} · ¿Qué necesitas?</h2>
  <div class="pick">
    <a class="pick-o {{ $hasFilters ? '' : 'dim' }}" href="/portal/pedidos/nuevo?{{ $mq }}&tipo=mantenimiento"><i class="ic ic-drop"></i><span><b>Mantenimiento</b><small>{{ $hasFilters ? 'los filtros de esta máquina, listos para marcar' : 'filtros (aún no los tenemos apuntados: nos lo cuentas)' }}</small></span></a>
    <a class="pick-o {{ $hasWear ? '' : 'dim' }}" href="/portal/pedidos/nuevo?{{ $mq }}&tipo=desgaste"><i class="ic ic-wrench"></i><span><b>Desgaste y recambios</b><small>{{ $hasWear ? 'cuchillas, puntas, tornillos… de esta máquina' : 'cuchillas, puntas, cantoneras, dientes…' }}</small></span></a>
    <a class="pick-o" href="/portal/pedidos/nuevo?{{ $mq }}&tipo=repetir"><i class="ic ic-refresh"></i><span><b>Lo mismo que la última vez</b><small>repetir una compra anterior</small></span></a>
    <a class="pick-o" href="/portal/pedidos/nuevo?{{ $mq }}&tipo=otro"><i class="ic ic-edit"></i><span><b>Otra cosa / no lo sé</b><small>nos lo cuentas con tus palabras y lo buscamos</small></span></a>
  </div>
</div>

{{-- Paso 3: piezas y enviar --}}
@else
<form method="post" action="/portal/pedidos/nuevo" class="card" id="pt-order" enctype="multipart/form-data">
  @csrf
  <input type="hidden" name="machine_id" value="{{ $machine?->id }}">
  <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:6px">
    <h2 style="margin:0">@if($machine){{ trim(($machine->brand ?? '').' '.$machine->model) }} · @endif{{ ['mantenimiento' => 'Mantenimiento', 'desgaste' => 'Desgaste y recambios', 'repetir' => 'Repetir una compra', 'otro' => 'Cuéntanos qué necesitas', 'todo' => 'Todo lo que lleva'][$tipo] ?? 'Piezas' }}</h2>
    @if($machine)<a class="small" href="/portal/pedidos/nuevo?{{ $mq }}">cambiar</a>@endif
  </div>

  @if($tipo === 'repetir')
    @if($repeat)
      <p class="small muted">Compra del {{ $repeat->date?->format('d/m/Y') }}. Desmarca lo que no quieras y ajusta cantidades.</p>
      <div class="ol">
      @foreach($repeat->lines as $i => $l)
        <label class="ol-r"><input type="checkbox" name="lines[r{{ $i }}][on]" value="1" checked><input type="hidden" name="lines[r{{ $i }}][label]" value="{{ \App\Services\Portal::clean($l->description) ?: 'Artículo' }}"><input type="hidden" name="lines[r{{ $i }}][ref]" value="{{ $l->art }}"><span class="ol-l">{{ \App\Services\Portal::clean($l->description) ?: 'Artículo' }}</span><input type="text" name="lines[r{{ $i }}][qty]" value="{{ rtrim(rtrim(number_format((float) $l->qty, 2, ',', ''), '0'), ',') }}" inputmode="decimal" class="ol-q"></label>
      @endforeach
      </div>
    @elseif($lastDocs->isNotEmpty())
      <p class="small muted">¿Cuál quieres repetir?</p>
      <div class="pick">
      @foreach($lastDocs as $d)<a class="pick-o" href="/portal/pedidos/nuevo?{{ $mq }}&repetir={{ $d->year }}/{{ $d->code }}"><i class="ic ic-file"></i><span><b>{{ $d->date?->format('d/m/Y') }} · {{ eur($d->base) }}</b><small>{{ $d->lines->take(3)->map(fn ($l) => \App\Services\Portal::clean($l->description))->filter()->implode(' · ') }}{{ $d->lines->count() > 3 ? ' …' : '' }}</small></span></a>@endforeach
      </div>
    @else
      <p class="muted">No tenemos compras anteriores{{ $machine ? ' de esta máquina' : '' }}. Cuéntanos abajo qué necesitas.</p>
    @endif
  @elseif(in_array($tipo, ['mantenimiento', 'desgaste', 'todo'], true) && $items)
    @php $groups = $tipo === 'mantenimiento' ? ['filtro' => 'Filtros'] : ($tipo === 'desgaste' ? ['desgaste' => 'Desgaste', 'recambio' => 'Recambios'] : ['filtro' => 'Filtros', 'desgaste' => 'Desgaste', 'recambio' => 'Recambios']); $any = false; @endphp
    @if($tipo === 'mantenimiento')<p class="small muted">Marca los filtros que quieras. <button type="button" class="linkbtn" onclick="document.querySelectorAll('#pt-order .ol-r input[type=checkbox]').forEach(c=>c.checked=true)">Marcar todos (mantenimiento completo)</button></p>@else<p class="small muted">Marca lo que necesitas y pon la cantidad.</p>@endif
    @foreach($groups as $k => $t)
      @php $g = array_filter($items, fn ($i) => $i['kind'] === $k); @endphp
      @if($g)@php $any = true; @endphp
      <div class="pi-t" style="margin-top:8px">{{ $t }}</div>
      <div class="ol">
      @foreach($g as $i => $it)
        <label class="ol-r"><input type="checkbox" name="lines[m{{ $i }}][on]" value="1"><input type="hidden" name="lines[m{{ $i }}][label]" value="{{ $it['label'] }}"><input type="hidden" name="lines[m{{ $i }}][ref]" value="{{ $it['ref'] }}"><span class="ol-l">{!! \App\Services\Icons::part($it['label'], $k === 'filtro') !!}{{ $it['label'] }}</span><input type="text" name="lines[m{{ $i }}][qty]" value="{{ $it['qty'] ?: 1 }}" inputmode="decimal" class="ol-q"></label>
      @endforeach
      </div>
      @endif
    @endforeach
    @if(! $any)<p class="muted">De esta máquina no tenemos apuntado nada de ese tipo. Escríbelo abajo.</p>@endif
  @elseif($machine && ! $items && $tipo !== 'otro')
    <p class="muted">De esta máquina aún no tenemos apuntado lo que lleva: escribe abajo lo que necesitas (si sabes la referencia, mejor).</p>
  @endif

  <label style="margin-top:14px">{{ $tipo === 'otro' ? '¿Qué necesitas?' : 'Algo más que no esté en la lista' }} <span class="muted small">(una cosa por línea)</span></label>
  <textarea name="extra" rows="{{ $tipo === 'otro' ? 5 : 3 }}" placeholder="Ej.: 2 filtros de aire&#10;aceite hidráulico 20 L&#10;cuchilla de la pala" {{ $tipo === 'otro' ? 'autofocus' : '' }}>{{ old('extra') }}</textarea>
  <label><i class="ic ic-camera"></i> Foto de la pieza o de la placa <span class="muted small">(opcional, ayuda mucho)</span></label>
  <input type="file" name="photo" accept="image/*,.pdf" capture="environment">
  <div class="row">
    <div style="flex:1 1 200px"><label>Quién lo recoge / contacto</label><input type="text" name="contact_name" value="{{ old('contact_name') }}" list="pt-contacts" placeholder="Nombre"><datalist id="pt-contacts">@foreach($contacts as $ct)<option value="{{ $ct->name }}">@endforeach</datalist></div>
    <div style="flex:1 1 160px"><label>Teléfono</label><input type="text" name="contact_phone" value="{{ old('contact_phone', $c->bestPhone()) }}" inputmode="tel"></div>
  </div>
  <label>Observaciones <span class="muted small">(opcional)</span></label><textarea name="notes" rows="2" placeholder="Urgencia, horario, dirección de obra…">{{ old('notes') }}</textarea>
  <div class="row" style="margin-top:12px;align-items:center;gap:10px"><button class="btn y" type="submit" style="font-size:17px;padding:12px 22px">Enviar pedido</button><span class="small muted">Te confirmamos disponibilidad y precio antes de servirlo.</span></div>
</form>
<script>
document.querySelectorAll('.ol-q').forEach(q=>q.addEventListener('input',()=>{const cb=q.closest('.ol-r').querySelector('input[type=checkbox]'); if(q.value.trim()!=='') cb.checked=true;}));
</script>
@endif
@endsection
