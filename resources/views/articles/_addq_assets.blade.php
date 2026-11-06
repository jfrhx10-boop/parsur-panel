<datalist id="addq-customers">@foreach($customersList as $id => $name)<option value="{{ $name }}" data-id="{{ $id }}"></option>@endforeach</datalist>
<script>
(function(){
  const map = {}; document.querySelectorAll('#addq-customers option').forEach(o => { map[o.value] = o.dataset.id; });
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
