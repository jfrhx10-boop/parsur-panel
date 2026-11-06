@php
  // Botón "+ Presupuesto" para un artículo: lo añade a un presupuesto abierto o crea uno nuevo para un cliente.
  // $codart: referencia · $openQuotes: colección de Quote abiertos (con customer) · $customersList: [id => nombre]
@endphp
<details class="addq">
  <summary class="btn ghost sm" title="Añadir este artículo a un presupuesto">+ Presup.</summary>
  <div class="addq-p">
    <div class="small muted" style="margin-bottom:6px">Cantidad</div>
    <input type="text" class="addq-qty" value="1" inputmode="decimal" form="">
    @if($openQuotes->isNotEmpty())
    <div class="small muted" style="margin:10px 0 4px">Añadir a un presupuesto abierto</div>
    <div class="addq-list">
      @foreach($openQuotes as $oq)
      <form method="post" action="/presupuestos/{{ $oq->id }}/lineas" class="addq-f">@csrf<input type="hidden" name="art" value="{{ $codart }}"><input type="hidden" name="qty" value="1"><button type="submit" class="linkbtn"><b>{{ $oq->number }}</b> · {{ $oq->customer?->displayName() }} <span class="muted">· {{ $oq->statusLabel() }} · {{ eur($oq->total) }}</span></button></form>
      @endforeach
    </div>
    @endif
    <div class="small muted" style="margin:10px 0 4px">O nuevo presupuesto para…</div>
    <form method="post" action="/presupuestos" class="addq-new">@csrf
      <input type="hidden" name="refs[]" value="{{ $codart }}|{{ $codart }}">
      <input type="hidden" name="customer_id" value="">
      <input type="text" list="addq-customers" placeholder="Escribe el cliente…" autocomplete="off" class="addq-c">
      <button type="submit" class="btn sm y" disabled>Crear</button>
    </form>
  </div>
</details>
