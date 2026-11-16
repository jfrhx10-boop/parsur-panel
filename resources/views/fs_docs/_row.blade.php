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
      @if($d->type === 'albaran' && (int) $d->paid > 0)<span class="badge ok">Cobrado</span>@endif
    </span>
  </summary>
  @include('customers._doclines', ['d' => $d])
  <div class="doc-actions"><a class="btn ghost sm" href="/documentos/{{ $d->type }}/{{ $d->year }}/{{ $d->code }}/pdf" target="_blank" rel="noopener">📄 PDF / Imprimir</a>
    @if(in_array($d->type, ['entrada', 'facturarec'], true) && auth()->user()?->canSeeSales())
      <form method="post" action="/documentos/{{ $d->type }}/{{ $d->year }}/{{ $d->code }}/su-numero" class="row" style="display:inline-flex;gap:6px;align-items:center">@csrf
        <label class="small" for="sn{{ $d->id }}" style="margin:0">{{ $d->type === 'facturarec' ? 'Nº de su factura' : 'Nº de su albarán' }}</label>
        <input id="sn{{ $d->id }}" name="numero" value="{{ $d->ext_ref }}" maxlength="20" style="width:150px;padding:6px 8px">
        <button class="btn ghost sm" type="submit">Guardar</button>
      </form>
    @endif
    @if($d->type === 'albaran' && (int) $d->status === 0 && auth()->user()?->canSeeSales())
      <form method="post" action="/albaranes/{{ $d->year }}/{{ $d->code }}/cobro" style="display:inline" onsubmit="return confirm('{{ (int) $d->paid > 0 ? '¿Quitar la marca de cobrado de este albarán en FactuSOL?' : '¿Marcar este albarán como cobrado en FactuSOL?' }}')">@csrf
        <input type="hidden" name="cobrado" value="{{ (int) $d->paid > 0 ? 0 : 1 }}">
        <button class="btn sm {{ (int) $d->paid > 0 ? 'ghost' : '' }}" type="submit">{{ (int) $d->paid > 0 ? 'Quitar cobrado' : 'Marcar cobrado' }}</button>
      </form>
    @endif
  </div>
</details>
