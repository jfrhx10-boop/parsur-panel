<table class="tbl doc-lines">
  <thead><tr><th>Artículo</th><th>Descripción</th><th class="num">Cant.</th><th class="num">Precio</th><th class="num">Dto.</th><th class="num">Importe</th></tr></thead>
  <tbody>
  @forelse($d->lines as $l)
    <tr>
      <td><code class="art">{{ $l->art }}</code></td>
      <td class="small">{{ $l->description }}</td>
      <td class="num">{{ rtrim(rtrim(number_format($l->qty, 2, ',', '.'), '0'), ',') }}</td>
      <td class="num">{{ eur($l->price) }}</td>
      <td class="num">@if($l->discount > 0){{ rtrim(rtrim(number_format($l->discount, 2, ',', '.'), '0'), ',') }} %@endif</td>
      <td class="num">{{ eur($l->total) }}</td>
    </tr>
  @empty
    <tr><td colspan="6" class="muted small">Sin líneas (todavía no sincronizadas).</td></tr>
  @endforelse
  </tbody>
  @if($d->notes)<tfoot><tr><td colspan="6" class="small muted">{{ $d->notes }}</td></tr></tfoot>@endif
</table>
