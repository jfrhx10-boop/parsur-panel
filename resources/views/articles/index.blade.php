@extends('layout')
@section('title', 'Artículos')
@section('content')
@php $sales = auth()->user()->canSeeSales(); @endphp
<div class="head">
  <div><h1>Artículos</h1><div class="muted small">{{ number_format($stats['total'], 0, ',', '.') }} artículos de FactuSOL · {{ number_format($stats['conStock'], 0, ',', '.') }} con stock @if($sales)· valor del almacén a coste {{ eur($stats['valor']) }}@endif</div></div>
</div>

<nav class="tabs" style="margin-bottom:12px">
  <a href="/articulos?{{ http_build_query(array_filter(['q' => $q, 'stock' => $stock, 'tarifa' => $tariff !== 1 ? $tariff : null, 'orden' => $sort !== 'codart' ? $sort : null])) }}" class="{{ $cat === '' ? 'on' : '' }}">Todos <span class="n">{{ number_format($stats['total'], 0, ',', '.') }}</span></a>
  @foreach(\App\Services\ArticleCategories::LABELS as $k => $v)
  <a href="/articulos?{{ http_build_query(array_filter(['cat' => $k, 'q' => $q, 'stock' => $stock, 'tarifa' => $tariff !== 1 ? $tariff : null, 'orden' => $sort !== 'codart' ? $sort : null])) }}" class="{{ $cat === $k ? 'on' : '' }}">{{ $v }} <span class="n">{{ number_format($cats[$k] ?? 0, 0, ',', '.') }}</span></a>
  @endforeach
</nav>
<form class="card row" method="get" action="/articulos" style="margin-bottom:14px">
  @if($cat !== '')<input type="hidden" name="cat" value="{{ $cat }}">@endif
  <div style="flex:3 1 260px"><label for="q">Buscar</label><input id="q" type="search" name="q" value="{{ $q }}" placeholder="Referencia, descripción, EAN o equivalencia (sin tildes, varias palabras)" autofocus></div>
  <div style="flex:1 1 160px"><label for="fam">Familia</label><select id="fam" name="fam"><option value="">Todas</option>@foreach($families as $f)<option value="{{ $f->family_code }}" {{ $fam === (string) $f->family_code ? 'selected' : '' }}>{{ $f->family }}</option>@endforeach</select></div>
  <div style="flex:1 1 140px"><label for="stock">Stock</label><select id="stock" name="stock"><option value="">Todos</option><option value="con" {{ $stock === 'con' ? 'selected' : '' }}>Con stock</option><option value="sin" {{ $stock === 'sin' ? 'selected' : '' }}>Sin stock</option><option value="bajo" {{ $stock === 'bajo' ? 'selected' : '' }}>Bajo mínimo</option></select></div>
  <div style="flex:1 1 150px"><label for="tarifa">Precio de</label><select id="tarifa" name="tarifa">@foreach($tariffs as $t)<option value="{{ $t->code }}" {{ $tariff === (int) $t->code ? 'selected' : '' }}>Tarifa {{ $t->code }} · {{ $t->margin }} %</option>@endforeach</select></div>
  <div style="flex:1 1 140px"><label for="orden">Orden</label><select id="orden" name="orden">@foreach(['codart' => 'Referencia', 'description' => 'Descripción', 'stock' => 'Stock', 'cost' => 'Coste', 'price' => 'PVP'] as $k => $v)<option value="{{ $k }}" {{ $sort === $k ? 'selected' : '' }}>{{ $v }}</option>@endforeach</select></div>
  <button class="btn" type="submit">Buscar</button>
</form>

<div class="card"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Referencia</th><th>Descripción</th><th class="hide-m">Familia</th><th class="num">Stock</th>@if($sales)<th class="num">Coste</th>@endif<th class="num">PVP T{{ $tariff }}</th>@if($sales)<th class="num hide-m">Margen</th>@endif<th class="num">Vendidos 12 m</th><th class="hide-m">Ubic.</th></tr></thead>
    <tbody>
    @forelse($articles as $a)
      <tr>
        <td><a href="/articulos/{{ rawurlencode($a->codart) }}"><code class="art">{{ $a->codart }}</code></a></td>
        <td class="small">{{ $a->description }}@if($a->equivalent)<div class="muted">≈ {{ $a->equivalent }}</div>@endif</td>
        <td class="small muted hide-m">{{ $a->family }}</td>
        <td class="num">@if($a->stock > 0)<strong>{{ rtrim(rtrim(number_format($a->stock, 2, ',', '.'), '0'), ',') }}</strong>@else<span class="muted">0</span>@endif @if($a->stock_min > 0 && $a->stock < $a->stock_min)<span class="badge warn">bajo</span>@endif</td>
        @if($sales)<td class="num"><span class="qe" data-art="{{ $a->codart }}" data-field="coste" data-val="{{ number_format((float) $a->cost, 2, ',', '') }}" title="Clic para cambiar el coste (se guarda en FactuSOL)">{{ eur($a->cost) }}</span></td>@endif
        @php $pv = $tariff === 1 ? (float) $a->price : (float) ($tprices[$a->codart] ?? 0); $mg = ($pv > 0 && $a->cost > 0) ? round(($pv - $a->cost) / $pv * 100, 1) : null; @endphp
        <td class="num">@if($sales)<span class="qe" data-art="{{ $a->codart }}" data-field="precio" data-tariff="{{ $tariff }}" data-val="{{ number_format($pv, 2, ',', '') }}" title="Clic para cambiar el PVP de la tarifa {{ $tariff }} (se guarda en FactuSOL)">{{ eur($pv) }}</span>@else{{ eur($pv) }}@endif @if($sales && $pv > 0 && $pv < $a->cost) <span class="badge bad" title="Por debajo del coste">bajo coste</span>@endif</td>
        @if($sales)<td class="num hide-m small muted">@if($mg !== null){{ $mg }} %@endif</td>@endif
        <td class="num">{{ isset($sold[$a->codart]) ? rtrim(rtrim(number_format($sold[$a->codart], 2, ',', '.'), '0'), ',') : '—' }}</td>
        <td class="small muted hide-m">{{ $a->location }}</td>
      </tr>
    @empty
      <tr><td colspan="9" class="muted">Nada con ese filtro. Si es la primera vez, sincroniza artículos desde Ajustes.</td></tr>
    @endforelse
    </tbody>
  </table>
</div></div>
@if($articles->hasPages())
  <div class="pager">
    @if(!$articles->onFirstPage())<a class="btn ghost sm" href="{{ $articles->previousPageUrl() }}">← Anteriores</a>@endif
    <span class="muted small" style="align-self:center">Página {{ $articles->currentPage() }} de {{ $articles->lastPage() }}</span>
    @if($articles->hasMorePages())<a class="btn ghost sm" href="{{ $articles->nextPageUrl() }}">Siguientes →</a>@endif
  </div>
@endif
@endsection
@if($sales)
<script>
(function(){
  const csrf = document.querySelector('meta[name=csrf-token]')?.content || document.querySelector('input[name=_token]')?.value;
  function toast(msg, bad){ const t=document.createElement('div'); t.className='alert '+(bad?'err':'ok'); t.style.cssText='position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:999;box-shadow:0 8px 30px rgba(0,0,0,.18);max-width:90vw'; t.textContent=msg; document.body.appendChild(t); setTimeout(()=>t.remove(), bad?6000:3000); }
  document.addEventListener('click', function(e){
    const el = e.target.closest('.qe'); if(!el || el.querySelector('input')) return;
    const old = el.textContent; const inp = document.createElement('input'); inp.type='text'; inp.inputMode='decimal'; inp.value=el.dataset.val; inp.className='qe-in';
    el.textContent=''; el.appendChild(inp); inp.focus(); inp.select();
    let done=false;
    const cancel=()=>{ if(done) return; done=true; el.textContent=old; };
    const save=async()=>{ if(done) return; done=true; const v=inp.value.trim(); if(v===''||v===el.dataset.val){ el.textContent=old; return; }
      el.textContent='…';
      const body=new URLSearchParams({_token:csrf}); if(el.dataset.field==='coste'){ body.set('coste',v); body.set('recalcular','1'); } else { body.set('tarifa',el.dataset.tariff); body.set('precio',v); }
      try{ const r=await fetch('/articulos/'+encodeURIComponent(el.dataset.art)+'/precios',{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf,'Content-Type':'application/x-www-form-urlencoded'},body}); const j=await r.json();
        if(!j.ok){ el.textContent=old; toast(j.error||'Error', true); return; }
        const row=el.closest('tr');
        row.querySelectorAll('.qe').forEach(q=>{ if(q.dataset.field==='coste'){ q.textContent=j.cost_fmt; q.dataset.val=(j.cost).toFixed(2).replace('.',','); } else { const p=j.prices[q.dataset.tariff]; if(p!==undefined){ q.textContent=j.prices_fmt[q.dataset.tariff]; q.dataset.val=Number(p).toFixed(2).replace('.',','); } else if(el===q){ q.textContent=old; } } });
        toast(j.message);
      }catch(err){ el.textContent=old; toast('No se ha podido guardar: '+err, true); }
    };
    inp.addEventListener('keydown', ev=>{ if(ev.key==='Enter'){ ev.preventDefault(); save(); } if(ev.key==='Escape'){ cancel(); } });
    inp.addEventListener('blur', ()=>setTimeout(save, 80));
  });
})();
</script>
@endif
