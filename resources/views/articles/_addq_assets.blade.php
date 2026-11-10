<template id="addq-tpl">
  <div class="addq-p">
    <div class="small muted" style="margin-bottom:6px">Cantidad</div>
    <input type="text" class="addq-qty" value="1" inputmode="decimal" form="">
    @if($openQuotes->isNotEmpty())
    <div class="small muted" style="margin:10px 0 4px">Añadir a un presupuesto abierto</div>
    <div class="addq-list">
      @foreach($openQuotes as $oq)
      <form method="post" action="/presupuestos/{{ $oq->id }}/lineas" class="addq-f">@csrf<input type="hidden" name="art" value=""><input type="hidden" name="qty" value="1"><button type="submit" class="linkbtn"><b>{{ $oq->number }}</b> · {{ $oq->customer?->displayName() }} <span class="muted">· {{ $oq->statusLabel() }} · {{ eur($oq->total) }}</span></button></form>
      @endforeach
    </div>
    @endif
    <div class="small muted" style="margin:10px 0 4px">O nuevo presupuesto para…</div>
    <form method="post" action="/presupuestos" class="addq-new">@csrf
      <input type="hidden" name="refs[]" value="">
      <input type="hidden" name="customer_id" value="">
      <input type="text" list="addq-customers" placeholder="Escribe el cliente…" autocomplete="off" class="addq-c">
      <button type="submit" class="btn sm y" disabled>Crear</button>
    </form>
  </div>
</template>
<datalist id="addq-customers">@foreach($customersList as $id => $name)<option value="{{ $name }}" data-id="{{ $id }}"></option>@endforeach</datalist>
<script>
(function(){
  const map = {}; document.querySelectorAll('#addq-customers option').forEach(o => { map[o.value] = o.dataset.id; });
  const tpl = document.getElementById('addq-tpl');
  // El panel de cada artículo se crea al abrirlo
  document.addEventListener('toggle', e => {
    const d = e.target; if (!d.classList || !d.classList.contains('addq') || !d.open || d.querySelector('.addq-p')) return;
    d.appendChild(tpl.content.cloneNode(true));
    const art = d.dataset.art;
    d.querySelectorAll('[name=art]').forEach(i => i.value = art);
    d.querySelectorAll('[name="refs[]"]').forEach(i => i.value = art + '|' + art);
    const q = d.querySelector('.addq-qty'); if (q) { q.focus(); q.select(); }
  }, true);
  document.addEventListener('input', e => {
    const c = e.target.closest('.addq-c'); if (!c) return;
    const f = c.closest('form'); const id = map[c.value] || ''; f.querySelector('[name=customer_id]').value = id; f.querySelector('button').disabled = !id;
  });
  document.addEventListener('input', e => {
    const q = e.target.closest('.addq-qty'); if (!q) return;
    q.closest('.addq-p').querySelectorAll('.addq-f [name=qty]').forEach(i => i.value = q.value.replace(',', '.'));
  });
  document.addEventListener('click', e => { document.querySelectorAll('details.addq[open]').forEach(d => { if (!d.contains(e.target)) d.removeAttribute('open'); }); });
})();
</script>
