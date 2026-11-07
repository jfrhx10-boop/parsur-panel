@extends('portal.layout')
@section('title', 'Nuevo pedido')
@section('content')
<a class="small" href="/portal/inicio">← Volver</a>
<h1 style="margin-top:6px">Hacer un pedido</h1>
<form method="post" action="/portal/pedidos/nuevo" class="card" style="max-width:820px" id="pt-order">
  @csrf
  <label>Máquina</label>
  <select name="machine_id" onchange="location.href='/portal/pedidos/nuevo?maquina='+this.value{{ $repeat ? "+'&repetir=".$repeat->year.'/'.$repeat->code."'" : '' }}">
    <option value="">— sin máquina concreta —</option>
    @foreach($machines as $m)<option value="{{ $m->id }}" {{ $machine && $machine->id === $m->id ? 'selected' : '' }}>{{ trim(($m->brand ?? '').' '.$m->model) }}@if($m->serial) · {{ $m->serial }}@endif</option>@endforeach
  </select>

  @if($repeat)
    <h2 style="margin-top:14px">Repetir el albarán del {{ $repeat->date?->format('d/m/Y') }}</h2>
    <p class="small muted">Marca lo que quieras volver a pedir y ajusta cantidades.</p>
    <div class="ol">
    @foreach($repeat->lines as $i => $l)
      <label class="ol-r"><input type="checkbox" name="lines[r{{ $i }}][on]" value="1" checked><input type="hidden" name="lines[r{{ $i }}][label]" value="{{ \App\Services\Portal::clean($l->description) ?: 'Artículo' }}"><input type="hidden" name="lines[r{{ $i }}][ref]" value="{{ $l->art }}"><span class="ol-l">{{ \App\Services\Portal::clean($l->description) ?: 'Artículo' }}</span><input type="text" name="lines[r{{ $i }}][qty]" value="{{ rtrim(rtrim(number_format((float) $l->qty, 2, ',', ''), '0'), ',') }}" inputmode="decimal" class="ol-q"></label>
    @endforeach
    </div>
  @elseif($machine && $items)
    <h2 style="margin-top:14px">Lo que lleva {{ trim(($machine->brand ?? '').' '.$machine->model) }}</h2>
    <p class="small muted">Marca lo que necesitas y pon la cantidad.</p>
    @foreach(['filtro' => 'Filtros', 'desgaste' => 'Desgaste', 'recambio' => 'Recambios'] as $k => $t)
      @php $g = array_filter($items, fn ($i) => $i['kind'] === $k); @endphp
      @if($g)
      <div class="pi-t" style="margin-top:8px">{{ $t }}</div>
      <div class="ol">
      @foreach($g as $i => $it)
        <label class="ol-r"><input type="checkbox" name="lines[m{{ $i }}][on]" value="1"><input type="hidden" name="lines[m{{ $i }}][label]" value="{{ $it['label'] }}"><input type="hidden" name="lines[m{{ $i }}][ref]" value="{{ $it['ref'] }}"><span class="ol-l">{!! \App\Services\Icons::part($it['label'], $k === 'filtro') !!}{{ $it['label'] }}</span><input type="text" name="lines[m{{ $i }}][qty]" value="{{ $it['qty'] ?: 1 }}" inputmode="decimal" class="ol-q"></label>
      @endforeach
      </div>
      @endif
    @endforeach
  @elseif($machine)
    <p class="muted" style="margin-top:12px">De esta máquina aún no tenemos apuntado lo que lleva: escribe abajo lo que necesitas.</p>
  @endif

  <label style="margin-top:14px">Otras cosas que necesitas <span class="muted small">(una por línea)</span></label>
  <textarea name="extra" rows="3" placeholder="Ej.: 2 filtros de aire para la retro&#10;aceite hidráulico 20 L&#10;cuchilla pala">{{ old('extra') }}</textarea>
  <div class="row">
    <div style="flex:1 1 200px"><label>Quién lo recoge / contacto</label><input type="text" name="contact_name" value="{{ old('contact_name') }}" list="pt-contacts" placeholder="Nombre"><datalist id="pt-contacts">@foreach($contacts as $ct)<option value="{{ $ct->name }}">@endforeach</datalist></div>
    <div style="flex:1 1 160px"><label>Teléfono</label><input type="text" name="contact_phone" value="{{ old('contact_phone', $c->bestPhone()) }}" inputmode="tel"></div>
  </div>
  <label>Observaciones</label><textarea name="notes" rows="2" placeholder="Urgencia, horario de entrega, dirección de obra…">{{ old('notes') }}</textarea>
  <div class="row" style="margin-top:12px;align-items:center;gap:10px"><button class="btn y" type="submit">Enviar pedido</button><span class="small muted">Te confirmamos disponibilidad y precio antes de servirlo.</span></div>
</form>
<script>
document.querySelectorAll('.ol-q').forEach(q=>q.addEventListener('input',()=>{const cb=q.closest('.ol-r').querySelector('input[type=checkbox]'); if(q.value.trim()!=='') cb.checked=true;}));
</script>
@endsection
