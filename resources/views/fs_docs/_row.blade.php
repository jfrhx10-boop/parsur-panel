@php
  // $d: FsDoc · $party: Customer|FsSupplier|null · $partyUrl: string|null · $open: bool · $hideParty: bool
  $open = $open ?? false; $hideParty = $hideParty ?? false;
@endphp
<details class="doc t-{{ $d->type }} st-{{ $d->statusClass() }}"{{ $open ? ' open' : '' }} data-print="/documentos/{{ $d->type }}/{{ $d->year }}/{{ $d->code }}/pdf">
  <summary>
    <span class="doc-ic">{!! \App\Services\Icons::svg('doc-'.$d->type) !!}</span>
    <span class="doc-main">
      <span class="doc-n">{{ $d->typeLabel() }} <b>{{ $d->number() }}</b> <span class="doc-d">{{ $d->date?->format('d/m/Y') }}</span></span>
      @if(!$hideParty)<span class="doc-p">@if($party)<a href="{{ $partyUrl }}" onclick="event.stopPropagation()">{{ $party->displayName() }}</a>@if($d->codcli && !str_contains($partyUrl ?? '', 'proveedores')) <span class="small muted">#{{ $d->codcli }}</span>@endif @else{{ $d->customer_name }}@if($d->phoneInName()) <a class="wa-mini" onclick="event.stopPropagation()" target="_blank" rel="noopener" href="https://wa.me/{{ str_starts_with($d->phoneInName(), '34') ? $d->phoneInName() : '34'.$d->phoneInName() }}" title="WhatsApp">WhatsApp</a>@endif @endif</span>@endif
      <span class="doc-m">{{ $d->lines_count }} {{ $d->lines_count === 1 ? 'línea' : 'líneas' }}@if($d->ref) <span class="doc-chip" title="Máquina / referencia">{{ $d->ref }}</span>@endif @if($d->ext_ref) · {{ $d->type === 'facturarec' ? 'su factura' : 'su albarán' }} {{ $d->ext_ref }}@endif</span>
    </span>
    <span class="doc-r">
      <span class="doc-t">{{ eur($d->total) }}</span>
      <span class="badge {{ $d->statusClass() }}">{{ $d->statusLabel() }}</span>
    </span>
  </summary>
  @include('customers._doclines', ['d' => $d])
  <div class="doc-actions"><a class="btn ghost sm" href="/documentos/{{ $d->type }}/{{ $d->year }}/{{ $d->code }}/pdf" target="_blank" rel="noopener">📄 PDF / Imprimir</a></div>
</details>
