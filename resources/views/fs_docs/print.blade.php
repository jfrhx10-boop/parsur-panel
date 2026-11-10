<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{{ $d->typeLabel() }} {{ $d->number() }} · Recambios Parsur</title>
<meta name="robots" content="noindex">
<style>
  @page{size:A4;margin:14mm 14mm 16mm}
  *{box-sizing:border-box}
  body{font:12.5px/1.45 "Segoe UI",Roboto,Arial,sans-serif;color:#222;margin:0;background:#fff}
  .sheet{width:210mm;min-height:297mm;margin:16px auto;padding:14mm 14mm 16mm;background:#fff;box-shadow:0 10px 40px rgba(0,0,0,.15)}
  body{background:#e9e9e6}
  @media(max-width:860px){.sheet{width:auto;min-height:0;margin:0;padding:16px;box-shadow:none}}
  .top{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;border-bottom:4px solid #FAB001;padding-bottom:14px}
  .top img{width:210px;display:block}
  .co{font-size:11.5px;color:#555;margin-top:8px;line-height:1.4}
  .doc{text-align:right}
  .doc h1{margin:0;font-size:22px;letter-spacing:-.2px}
  .doc .n{font-size:15px;font-weight:700}
  .doc .d{color:#555}
  .st{display:inline-block;margin-top:6px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;background:#eee;color:#444}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:18px 0}
  .box{border:1px solid #e3e3e0;border-radius:10px;padding:12px 14px}
  .box h3{margin:0 0 6px;font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:#999997}
  .box .name{font-weight:800;font-size:14px}
  table{width:100%;border-collapse:collapse;margin-top:6px}
  th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:#999997;padding:8px 8px;border-bottom:2px solid #e3e3e0}
  td{padding:8px;border-bottom:1px solid #eeeeec;vertical-align:top}
  .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
  .art{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:11.5px;font-weight:700}
  .tot{margin-top:14px;margin-left:auto;width:280px}
  .tot div{display:flex;justify-content:space-between;padding:5px 8px}
  .tot .g{border-top:2px solid #222;font-weight:800;font-size:15px;margin-top:4px;padding-top:8px}
  .notes{margin-top:16px;font-size:12px;color:#444;white-space:pre-line}
  .foot{margin-top:28px;padding-top:10px;border-top:1px solid #e3e3e0;font-size:10.5px;color:#777;display:flex;justify-content:space-between}
  .bar{position:sticky;top:0;background:#1F1F1F;color:#fff;padding:10px 16px;display:flex;gap:10px;align-items:center;font-size:13px}
  .bar button,.bar a{background:#FAB001;color:#1F1F1F;border:0;border-radius:8px;padding:8px 14px;font-weight:800;cursor:pointer;text-decoration:none;font-size:13px}
  .bar a.g{background:#444;color:#fff}
  @media print{.bar{display:none}.sheet{padding:0;margin:0;width:auto;min-height:0;box-shadow:none}body{background:#fff}}
  .bar{flex-wrap:wrap}
  .bar .wa{background:#25D366;color:#fff}.bar .ol{background:#0F6CBD;color:#fff}
  .bar .hint{opacity:.7;font-size:12px;flex:1 1 100%}
  .busy{opacity:.5;pointer-events:none}
</style>
</head>
<body>
@php
  $fname = $d->typeLabel().'_'.$d->year.'-'.$d->code.'_'.\Illuminate\Support\Str::slug(\Illuminate\Support\Str::limit($party?->displayName() ?? $d->customer_name, 30, ''), '_').'.pdf';
  $subject = $d->typeLabel().' '.$d->number().' · Recambios Parsur';
  $bodyTxt = 'Hola,'."\n\n".'te adjuntamos el '.mb_strtolower($d->typeLabel()).' '.$d->number().($d->ref ? ' ('.$d->ref.')' : '').' por un total de '.eur($d->total).'.'."\n\n".'Un saludo,'."\n".'Recambios Parsur · 957 287 109';
  $tel = $party && method_exists($party, 'bestPhone') ? preg_replace('/\D+/', '', (string) $party->bestPhone()) : ($d->phoneInName() ?? '');
  $email = $party?->email ?? '';
@endphp
<div class="bar" id="bar">
  <button type="button" onclick="savePdf()">⬇ Guardar PDF</button>
  <button type="button" class="wa" onclick="sharePdf()">WhatsApp</button>
  <button type="button" class="ol" onclick="outlook()">Enviar por Outlook</button>
  <button type="button" class="g" onclick="window.print()" style="background:#444;color:#fff">🖨 Imprimir</button>
  <a class="g" href="javascript:history.back()">← Volver</a>
  <span class="hint" id="hint">PDF en A4 con el logo. En el móvil, «WhatsApp» abre el menú de compartir con el PDF ya adjunto; en el ordenador se descarga y se abre el chat para adjuntarlo.</span>
</div>
<script src="/js/html2pdf.bundle.min.js?v=1"></script>
<script>
const FNAME = @json($fname), SUBJECT = @json($subject), BODY = @json($bodyTxt), TEL = @json($tel), EMAIL = @json($email);
function pdfOpts(){ return {margin:[10,10,12,10], filename:FNAME, image:{type:'jpeg',quality:.96}, html2canvas:{scale:2,useCORS:true,backgroundColor:'#fff'}, jsPDF:{unit:'mm',format:'a4',orientation:'portrait'}, pagebreak:{mode:['css','legacy']}}; }
function sheetEl(){ const s=document.querySelector('.sheet'); return s; }
function busy(on){ document.getElementById('bar').classList.toggle('busy',on); }
async function savePdf(){ busy(true); try{ await html2pdf().set(pdfOpts()).from(sheetEl()).save(); } finally{ busy(false);} }
async function sharePdf(){
  busy(true);
  try{
    const blob = await html2pdf().set(pdfOpts()).from(sheetEl()).outputPdf('blob');
    const file = new File([blob], FNAME, {type:'application/pdf'});
    if (navigator.canShare && navigator.canShare({files:[file]})) { await navigator.share({files:[file], title:SUBJECT, text:BODY}); return; }
    // Escritorio: descargar y abrir el chat para adjuntarlo
    const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=FNAME; a.click();
    const url = TEL ? 'https://wa.me/'+(TEL.startsWith('34')?TEL:'34'+TEL)+'?text='+encodeURIComponent(BODY) : 'https://web.whatsapp.com/';
    window.open(url,'_blank');
    document.getElementById('hint').textContent='PDF descargado. En el chat, adjunta el archivo '+FNAME+' (botón del clip).';
  } finally{ busy(false); }
}
async function outlook(){
  busy(true);
  try{
    await html2pdf().set(pdfOpts()).from(sheetEl()).save();
    window.location.href = 'mailto:'+encodeURIComponent(EMAIL)+'?subject='+encodeURIComponent(SUBJECT)+'&body='+encodeURIComponent(BODY);
    document.getElementById('hint').textContent='Se abre Outlook con el asunto y el texto. Adjunta el PDF descargado: '+FNAME+'.';
  } finally{ busy(false); }
}
</script>
<div class="sheet">
  <div class="top">
    <div>
      <img src="/img/logo.png" alt="Recambios Parsur">
      <div class="co"><strong>{{ $emp['name'] ?? 'Recambios Parsur S.L.' }}</strong>@if(!empty($emp['nif'])) · NIF {{ $emp['nif'] }}@endif<br>{{ $emp['address'] ?? 'C/ Andrés Barrera, parc. 106, nº 8' }} · {{ $emp['postcode'] ?? '14014' }} {{ $emp['city'] ?? 'Córdoba' }}<br>Tel. {{ $emp['phone'] ?? '957 287 109' }} · WhatsApp 624 172 538 · {{ $emp['web'] ?? 'recambiosparsur.com' }}@if(!empty($emp['email'])) · {{ $emp['email'] }}@endif</div>
    </div>
    <div class="doc">
      <h1>{{ $d->typeLabel() }}</h1>
      <div class="n">Nº {{ $d->number() }}</div>
      <div class="d">{{ $d->date?->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}</div>
      <span class="st">{{ $d->statusLabel() }}</span>
    </div>
  </div>

  <div class="grid">
    <div class="box">
      <h3>{{ in_array($type, ['entrada', 'facturarec', 'pedidoprov'], true) ? 'Proveedor' : 'Cliente' }}</h3>
      <div class="name">{{ $party?->displayName() ?? $d->customer_name }}</div>
      @if($party)
        @if($party->name !== $party->displayName())<div>{{ $party->name }}</div>@endif
        @if($party->nif)<div>NIF {{ $party->nif }}</div>@endif
        @if($party->address || $party->city)<div>{{ $party->address }}@if($party->postcode || $party->city)<br>{{ $party->postcode }} {{ $party->city }}@if($party->province) ({{ $party->province }})@endif @endif</div>@endif
        @if($party->phone)<div>Tel. {{ $party->phone }}</div>@endif
      @endif
    </div>
    <div class="box">
      <h3>Datos del documento</h3>
      @if($d->ref)<div><strong>Referencia / máquina:</strong> {{ $d->ref }}</div>@endif
      @if($d->ext_ref)<div><strong>Albarán del proveedor:</strong> {{ $d->ext_ref }}</div>@endif
      <div><strong>Líneas:</strong> {{ $d->lines->count() }}</div>
      @if(! in_array($type, ['entrada', 'facturarec', 'pedidoprov'], true) && $party && method_exists($party, 'paymentTerms') && $party->paymentTerms())<div><strong>Forma de pago:</strong> {{ $party->paymentTerms() }}</div>@endif
      @if(! in_array($type, ['entrada', 'facturarec', 'pedidoprov'], true) && $party && $party->tariff)<div><strong>Tarifa:</strong> {{ $party->tariff }}</div>@endif
    </div>
  </div>

  <table>
    <thead><tr><th style="width:120px">Referencia</th><th>Descripción</th><th class="num">Cant.</th><th class="num">Precio</th><th class="num">Dto.</th><th class="num">Importe</th></tr></thead>
    <tbody>
    @forelse($d->lines as $l)
      <tr><td class="art">{{ $l->art }}</td><td>{{ $l->description }}</td><td class="num">{{ rtrim(rtrim(number_format($l->qty, 2, ',', '.'), '0'), ',') }}</td><td class="num">{{ eur($l->price) }}</td><td class="num">{{ $l->discount > 0 ? rtrim(rtrim(number_format($l->discount, 2, ',', '.'), '0'), ',').' %' : '' }}</td><td class="num">{{ eur($l->total) }}</td></tr>
    @empty
      <tr><td colspan="6" style="color:#777">Sin líneas sincronizadas.</td></tr>
    @endforelse
    </tbody>
  </table>

  <div class="tot">
    <div><span>Base imponible</span><span>{{ eur($base) }}</span></div>
    <div><span>IVA {{ $ivaPct }} %</span><span>{{ eur($iva) }}</span></div>
    <div class="g"><span>Total</span><span>{{ eur($d->total) }}</span></div>
  </div>

  @if($d->notes)<div class="notes"><strong>Observaciones:</strong> {{ $d->notes }}</div>@endif

  @if($type === 'presupuesto')<div class="notes"><strong>Validez del presupuesto:</strong> 15 días desde la fecha indicada, salvo variación de precios del fabricante. Precios sin IVA salvo indicación. Plazo de entrega según disponibilidad.</div>@endif
  <div class="foot"><span>{{ $emp['name'] ?? 'Recambios Parsur S.L.' }}@if(!empty($emp['registry'])) · {{ $emp['registry'] }}@endif</span><span>Documento generado desde el panel el {{ now('Europe/Madrid')->format('d/m/Y H:i') }}</span></div>
</div>
</body>
</html>
