@php
  // $d: FsDoc · $party: Customer|FsSupplier|null · $partyUrl: string|null · $open: bool · $hideParty: bool
  $open = $open ?? false; $hideParty = $hideParty ?? false;
@endphp
<details class="doc t-{{ $d->type }}"{{ $open ? ' open' : '' }}>
  <summary>
    <span class="doc-ic">{!! \App\Services\Icons::svg('doc-'.$d->type) !!}</span>
    <span class="doc-main">
      <span class="doc-n">{{ $d->typeLabel() }} <b>{{ $d->number() }}</b> <span class="doc-d">{{ $d->date?->format('d/m/Y') }}</span></span>
      @if(!$hideParty)<span class="doc-p">@if($party)<a href="{{ $partyUrl }}" onclick="event.stopPropagation()">{{ $party->displayName() }}</a>@else{{ $d->customer_name }}@endif</span>@endif
      <span class="doc-m">{{ $d->lines_count }} {{ $d->lines_count === 1 ? 'línea' : 'líneas' }}@if($d->ref) · <b>{{ $d->ref }}</b>@endif @if($d->ext_ref) · su albarán {{ $d->ext_ref }}@endif</span>
    </span>
    <span class="doc-r">
      <span class="doc-t">{{ eur($d->total) }}</span>
      <span class="badge {{ $d->statusClass() }}">{{ $d->statusLabel() }}</span>
    </span>
  </summary>
  @include('customers._doclines', ['d' => $d])
</details>
