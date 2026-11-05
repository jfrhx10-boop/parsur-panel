@php
  $items = array_values(array_filter($items, fn($i) => $i[1] > 0));
  $sum = array_sum(array_map(fn($i) => $i[1], $items));
  $cols = ['#e0a800','#2b2b2b','#6b8e23','#3d7ea6','#b5523b','#8a6fb0','#d98b5f','#4aa3a0','#a3a33a','#c25b8a','#999999'];
  $R = 80; $r = 50; $cx = 100; $cy = 100; $a0 = -M_PI / 2;
@endphp
<div class="pie" data-pie>
  <h3>{{ $title }}</h3>
  @if($sum <= 0)<div class="muted small">Sin datos con este filtro.</div>@else
  <div class="pie-wrap">
    <svg viewBox="0 0 200 200" role="img" aria-label="{{ $title }}">
      @foreach($items as $i => [$label, $val])
        @php
          $frac = $val / $sum; $a1 = $a0 + $frac * 2 * M_PI; $pct = round($frac * 100, 1);
          $col = $cols[min($i, count($cols) - 1)];
          if ($frac >= 0.9999) {
            $d = null;
          } else {
            $large = $frac > 0.5 ? 1 : 0;
            $x0 = $cx + $R * cos($a0); $y0 = $cy + $R * sin($a0); $x1 = $cx + $R * cos($a1); $y1 = $cy + $R * sin($a1);
            $x2 = $cx + $r * cos($a1); $y2 = $cy + $r * sin($a1); $x3 = $cx + $r * cos($a0); $y3 = $cy + $r * sin($a0);
            $d = sprintf('M%.2f %.2f A%d %d 0 %d 1 %.2f %.2f L%.2f %.2f A%d %d 0 %d 0 %.2f %.2f Z', $x0, $y0, $R, $R, $large, $x1, $y1, $x2, $y2, $r, $r, $large, $x3, $y3);
          }
          $a0 = $a1;
          $txt = $label.' · '.$fmt($val).' · '.str_replace('.', ',', $pct).' %';
        @endphp
        @if($d)<path class="sl" d="{{ $d }}" fill="{{ $col }}" data-l="{{ $label }}" data-v="{{ $fmt($val) }}" data-p="{{ str_replace('.', ',', $pct) }} %"><title>{{ $txt }}</title></path>
        @else<circle class="sl" cx="100" cy="100" r="65" fill="none" stroke="{{ $col }}" stroke-width="30" data-l="{{ $label }}" data-v="{{ $fmt($val) }}" data-p="100 %"><title>{{ $txt }}</title></circle>@endif
      @endforeach
      <text class="pc-l" x="100" y="94" text-anchor="middle">Total</text>
      <text class="pc-v" x="100" y="112" text-anchor="middle" data-def="{{ $fmt($sum) }}">{{ $fmt($sum) }}</text>
    </svg>
    <ul class="pie-leg">
      @foreach($items as $i => [$label, $val])
        <li><b style="background:{{ $cols[min($i, count($cols) - 1)] }}"></b><span>{{ $label }}</span><em>{{ str_replace('.', ',', round($val / $sum * 100, 1)) }} %</em></li>
      @endforeach
    </ul>
  </div>
  @endif
</div>
