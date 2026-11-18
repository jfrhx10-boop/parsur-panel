@extends('layout')
@section('title', 'Presupuestos')
@section('content')
@if($drafts->isNotEmpty())<div class="alert ok"><b>Sin terminar:</b> @foreach($drafts as $d)<a href="/presupuestos/{{ $d->id }}">{{ $d->kind === 'pedido' ? 'Pedido' : 'Albarán' }} de {{ $d->customer?->displayName() }}</a>@if(! $loop->last) · @endif @endforeach</div>@endif
<div class="head">
  <div><h1>Presupuestos</h1><div class="muted small">Un solo sitio: lo que haces aquí queda guardado en FactuSOL y lo que haces en FactuSOL aparece aquí.</div></div>
</div>
@include('partials_newbtns')
@if($importing)<div class="alert ok">Estoy trayendo los presupuestos antiguos de FactuSOL (primero los más recientes). <a href="/presupuestos">Recarga</a> para ver más; en unas pocas recargas están todos.</div>@endif
<div class="tabs">
  <a href="/presupuestos" class="{{ $st === '' && ! $reclamar ? 'on' : '' }}">Todos</a>
  @foreach(\App\Models\Quote::STATUSES as $k => $v)<a href="/presupuestos?estado={{ $k }}" class="{{ $st === $k ? 'on' : '' }}">{{ $v }} ({{ $counts[$k] ?? 0 }})</a>@endforeach
  <a href="/presupuestos?reclamar=1" class="{{ $reclamar ? 'on' : '' }}" title="Presupuestos sin respuesta desde hace {{ \App\Models\Quote::FOLLOW_AFTER }} días o más">Por reclamar ({{ $nFollow }})</a>
</div>
@if($reclamar)<div class="alert ok">Presupuestos sin respuesta desde hace {{ \App\Models\Quote::FOLLOW_AFTER }} días o más (y de menos de 45). Pulsa <b>Reclamar</b>: se abre WhatsApp con el mensaje escrito y queda apuntado. Si no contesta, vuelve a salir a los {{ \App\Models\Quote::FOLLOW_GAP }} días, una vez más como mucho.</div>@endif
<form method="get" action="/presupuestos" class="row" style="margin:10px 0">
  @if($st !== '')<input type="hidden" name="estado" value="{{ $st }}">@endif
  @if($reclamar)<input type="hidden" name="reclamar" value="1">@endif
  <input type="search" name="q" value="{{ $term }}" placeholder="Buscar por cliente, teléfono, número o referencia…" style="flex:1;min-width:200px">
  <input type="date" name="desde" value="{{ $from }}" title="Desde" aria-label="Desde" style="flex:0 1 150px">
  <input type="date" name="hasta" value="{{ $to }}" title="Hasta" aria-label="Hasta" style="flex:0 1 150px">
  <label class="small" style="display:flex;align-items:center;gap:6px;white-space:nowrap"><input type="checkbox" name="sinficha" value="1" @checked($sinficha) onchange="this.form.submit()"> Solo sin ficha de cliente</label>
  <button class="btn" type="submit">Buscar</button>
  @include('partials_dates', ['base' => '/presupuestos'])
</form>
<div class="row" style="justify-content:space-between;align-items:center;margin:-2px 0 10px;gap:8px"><div class="small muted">@if($sum){{ number_format($sum['n'], 0, ',', '.') }} presupuestos · {{ eur($sum['total']) }} con IVA @else{{ number_format($quotes->total(), 0, ',', '.') }} presupuestos @endif</div>@include('partials_density')</div>
<div class="card"><div class="scroll">
  <table class="tbl qlist">
    <thead><tr><th>Número</th><th>Fecha</th><th>Cliente</th><th>Máquina / ref.</th><th>Estado</th><th class="num">Total</th><th></th></tr></thead>
    <tbody>
    @forelse($quotes as $q)
      <tr data-print="/presupuestos/{{ $q->id }}/pdf" data-href="/presupuestos/{{ $q->id }}" class="qrow">
        <td><a href="/presupuestos/{{ $q->id }}"><strong>{{ $q->number }}</strong></a>
          @if($q->fs_order_code)<div class="small"><span class="badge ok">Pedido {{ $q->fs_order_code }}</span></div>@elseif(! $q->fs_code)<div class="small"><span class="badge warn" title="Todavía no está en FactuSOL">Sin enviar a FactuSOL</span></div>@endif @if($q->fs_error)<div class="small"><span class="badge bad" title="{{ $q->fs_error }}">⚠ Cambio sin guardar en FactuSOL</span></div>@endif</td>
        <td class="small">{{ $q->date?->format('d/m/Y') }}@if($q->valid_until)<div class="muted">válido hasta {{ $q->valid_until->format('d/m/Y') }}</div>@endif</td>
        <td>@if($q->noClient())<a href="/presupuestos/{{ $q->id }}">{{ $q->customer_name }}</a> <span class="badge warn" title="En FactuSOL este presupuesto no tiene cliente: solo un nombre o un teléfono">Sin ficha</span>@if($q->customer?->mobile) <a class="wa-mini" target="_blank" rel="noopener" href="https://wa.me/{{ str_starts_with($q->customer->mobile, '34') && strlen($q->customer->mobile) > 9 ? $q->customer->mobile : '34'.$q->customer->mobile }}">WhatsApp</a>@endif
          @else<a href="/presupuestos/{{ $q->id }}">{{ $q->customer?->displayName() }}</a>@if($q->customer?->codcli) <span class="small muted">#{{ $q->customer->codcli }}</span>@endif @endif</td>
        <td class="small">{{ $q->ref }}</td>
        <td><span class="badge {{ $q->statusClass() }}">{{ $q->statusLabel() }}</span>@if($q->follow_n > 0)<div class="small muted" title="Último recordatorio: {{ $q->followed_at ? \Illuminate\Support\Carbon::parse($q->followed_at)->format('d/m/Y') : '' }}">Reclamado {{ $q->follow_n }} {{ $q->follow_n == 1 ? 'vez' : 'veces' }}</div>@endif</td>
        <td class="num"><strong>{{ eur($q->total) }}</strong></td>
        <td class="num" style="white-space:nowrap">
          @if($reclamar)@php $wa = $q->waPhone(); @endphp
            @if($wa)<a class="btn sm q-recl" style="background:#25D366;color:#fff" target="_blank" rel="noopener" data-id="{{ $q->id }}" href="https://wa.me/{{ $wa }}?text={{ rawurlencode($q->followText(explode(' ', trim((string) auth()->user()->name))[0] ?: null)) }}">Reclamar</a>
            @else<span class="badge warn" title="La ficha del cliente no tiene un móvil válido">Sin móvil</span> <button class="btn ghost sm q-recl-m" type="button" data-id="{{ $q->id }}" title="Apuntar que ya se ha reclamado por teléfono o correo">Ya reclamado</button>@endif
            <button class="btn ghost sm q-norecl" type="button" data-id="{{ $q->id }}" title="No volver a proponer este presupuesto">No insistir</button>
          @endif
          <a class="btn ghost sm" href="/presupuestos/{{ $q->id }}/pdf" target="_blank">PDF</a>
          @if(auth()->user()->canSeeSales() && ! $q->fs_order_code)<form method="post" action="/presupuestos/{{ $q->id }}/borrar" style="display:inline" onsubmit="return confirm('{{ $q->fs_code ? 'Se borra el presupuesto '.$q->number.' del panel y también de FactuSOL. No se puede deshacer. ¿Seguro?' : '¿Borrar este presupuesto?' }}')">@csrf<button class="btn ghost sm" type="submit" title="Borrar" style="color:var(--bad)">🗑 Borrar</button></form>@endif
        </td>
      </tr>
    @empty<tr><td colspan="7" class="muted">No hay presupuestos que coincidan.</td></tr>@endforelse
    </tbody>
  </table>
</div></div>
<style>.qlist tr.qrow{cursor:pointer}.qlist tr.qrow:hover td{background:rgba(250,176,1,.07)}</style>
<script>
// Pinches donde pinches en la fila, se abre el presupuesto (salvo en botones, enlaces y casillas).
(function(){
  var tok=(document.querySelector('meta[name=csrf-token]')||{}).content;
  function post(u,cb){fetch(u,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-TOKEN':tok,'Accept':'application/json'}}).then(function(r){if(r.ok&&cb){cb();}}).catch(function(){});}
  function fuera(el){var tr=el.closest('tr');if(tr){tr.style.opacity='.35';}}
  document.querySelectorAll('.q-recl').forEach(function(a){a.addEventListener('click',function(){post('/presupuestos/'+a.dataset.id+'/reclamado',function(){fuera(a);});});});
  document.querySelectorAll('.q-recl-m').forEach(function(b){b.addEventListener('click',function(){post('/presupuestos/'+b.dataset.id+'/reclamado',function(){fuera(b);});});});
  document.querySelectorAll('.q-norecl').forEach(function(b){b.addEventListener('click',function(){post('/presupuestos/'+b.dataset.id+'/no-reclamar',function(){fuera(b);});});});
})();
document.querySelectorAll('.qlist tr.qrow').forEach(function(tr){tr.addEventListener('click',function(e){if(e.target.closest('a,button,form,input,label,select'))return;var u=tr.getAttribute('data-href');if(e.ctrlKey||e.metaKey){window.open(u,'_blank');}else{location.href=u;}});});
</script>
@if($quotes->hasPages())<div class="pager">@if(!$quotes->onFirstPage())<a class="btn ghost sm" href="{{ $quotes->previousPageUrl() }}">← Anteriores</a>@endif @if($quotes->hasMorePages())<a class="btn ghost sm" href="{{ $quotes->nextPageUrl() }}">Siguientes →</a>@endif</div>@endif
@endsection
