@php $sz = $m->size(); $sp = $spend[$m->id] ?? null; $ss = $m->serviceStatus(); $da = $m->dateAlerts(); @endphp
<a class="pm" href="/portal/maquinas/{{ $m->id }}">
  <span class="pm-ic">{!! \App\Services\Icons::machine($m->type, $m->model) !!}</span>
  <span class="pm-b">
    <span class="pm-n">@if($m->brand){!! \App\Services\Icons::brand($m->brand, 'sm') !!}@endif<b>{{ $m->model }}</b>@if($sz)<span class="sz sz-{{ strtolower($sz) }}">{{ $sz }}</span>@endif</span>
    <span class="pm-m">{{ $m->type ?: 'Máquina' }}@if($m->serial) · nº serie {{ $m->serial }}@endif @if($m->year) · {{ $m->year }}@endif @if($m->operator) · 👷 {{ $m->operator }}@endif @if($m->hours !== null) · {{ number_format($m->hours, 0, ',', '.') }} h @endif</span>
    @if($m->whereLabel())<span class="pm-m">📍 {{ $m->whereLabel() }}</span>@endif
    @if($ss && $ss['state'] !== 'ok')<span class="small sv-{{ $ss['state'] }}">{{ $ss['state'] === 'vencido' ? '⚠ Mantenimiento vencido' : '⏱ Mantenimiento próximo' }} ({{ $ss['left'] > 0 ? 'faltan '.$ss['left'].' h' : 'hace '.(-$ss['left']).' h' }})</span>@endif
    @foreach($da as $a)<span class="small sv-{{ $a['state'] }}">{{ $a['label'] }} {{ $a['days'] < 0 ? 'vencido' : 'vence en '.$a['days'].' días' }}</span>@endforeach
    @if($sp && $sp['n'])<span class="pm-s">{{ $sp['n'] }} {{ $sp['n'] === 1 ? 'compra' : 'compras' }} · {{ eur($sp['total']) }} en total · {{ eur($sp['year']) }} este año</span>@else<span class="pm-s muted">Sin compras registradas para esta máquina</span>@endif
  </span>
  <span class="pm-go">›</span>
</a>
