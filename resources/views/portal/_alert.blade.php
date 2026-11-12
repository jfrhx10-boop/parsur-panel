<div class="av av-{{ $a['state'] }}">
  <span class="av-ic"><i class="dot dot-{{ $a['state'] }}"></i></span>
  <span class="av-t"><b>{{ $a['text'] }}</b>@if(!empty($showMachine)) · {{ trim(($m->brand ?? '').' '.$m->model) }}@endif<small>{{ $a['when'] }}</small></span>
  @if($a['kind'] === 'mantenimiento')<a class="btn sm y" href="/portal/pedidos/nuevo?maquina={{ $m->id }}&tipo=mantenimiento">Pedir filtros</a>
  @elseif($a['reminder'])<form method="post" action="/portal/avisos/{{ $a['reminder']->id }}/hecho">@csrf<button class="btn sm ghost" type="submit"><i class="ic ic-check"></i> Hecho</button></form>@endif
</div>
