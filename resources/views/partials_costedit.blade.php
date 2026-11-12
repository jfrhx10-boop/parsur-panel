@php
  // Coste del artículo editable desde la línea (escribe en FactuSOL). Solo ventas.
  $ceArt = ($art ?? null) ? \App\Models\Article::where('codart', $art)->first(['codart', 'cost']) : null;
  $ceCost = (float) ($ceArt->cost ?? 0);
  $cePrice = (float) ($price ?? 0);
@endphp
@if($ceArt && auth()->user()->canSeeSales())
  <div class="ce small muted" data-art="{{ $ceArt->codart }}" data-price="{{ $cePrice }}" @if(!empty($ceTariff)) data-tariff="{{ (int) $ceTariff }}" data-input="{{ $ceInput ?? '' }}" @endif>
    <button type="button" class="linkbtn ce-b" title="Cambiar el coste del artículo (se escribe en FactuSOL)">coste <b class="ce-v">{{ eur($ceCost) }}</b></button>
    <span class="ce-m">@if($ceCost > 0 && $cePrice > 0)· margen {{ round(($cePrice - $ceCost) / $cePrice * 100, 1) }} %@endif</span>
  </div>
@endif
