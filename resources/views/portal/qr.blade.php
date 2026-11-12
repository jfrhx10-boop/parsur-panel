<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>QR · {{ trim(($machine->brand ?? '').' '.$machine->model) }} · Parsur Flota</title>
<style>
  @page{size:A4;margin:12mm}
  *{box-sizing:border-box}
  body{font:15px/1.45 "Segoe UI",Roboto,Arial,sans-serif;color:#222;margin:0;background:#ecebe7}
  .bar{position:sticky;top:0;background:#1f1f1f;color:#fff;padding:10px 16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .bar button,.bar a{background:#fab001;color:#1f1f1f;border:0;border-radius:8px;padding:9px 16px;font-weight:800;cursor:pointer;text-decoration:none;font-size:14px}
  .bar a{background:#444;color:#fff}
  .bar span{font-size:13px;opacity:.8;flex:1 1 220px}
  .lab{width:100mm;margin:22px auto;background:#fff;border-radius:6mm;border:1.2mm solid #2a2a2a;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.15)}
  .lab-h{background:#2a2a2a;padding:4mm 5mm;display:flex;align-items:center;justify-content:center}
  .lab-h img{height:13mm;display:block}
  .lab-b{padding:5mm 6mm 6mm;text-align:center}
  .lab-m{font-size:20px;font-weight:800;line-height:1.15}
  .lab-s{font-size:12.5px;color:#666;margin-top:1mm}
  .lab-q{margin:4mm auto 3mm;width:58mm;height:58mm;padding:2mm;border:1mm solid #fab001;border-radius:4mm}
  .lab-q img{width:100%;height:100%;image-rendering:pixelated;display:block}
  .lab-t{font-size:14px;font-weight:800}
  .lab-u{font-size:11.5px;color:#666;margin-top:1mm}
  .hint{max-width:100mm;margin:0 auto 30px;font-size:13px;color:#555;padding:0 4px}
  @media print{.bar,.hint{display:none}body{background:#fff}.lab{margin:0 auto;box-shadow:none}}
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()">Imprimir etiqueta</button><a href="/portal/maquinas/{{ $machine->id }}">Volver a la máquina</a><span>Imprímela, plastifícala y pégala en la cabina.</span></div>
<div class="lab">
  <div class="lab-h"><img src="/img/parsur-flota.png" alt="Parsur Flota"></div>
  <div class="lab-b">
    <div class="lab-m">{{ trim(($machine->brand ?? '').' '.$machine->model) }}</div>
    <div class="lab-s">{{ $machine->type ?: 'Máquina' }}@if($machine->serial) · nº serie {{ $machine->serial }}@endif</div>
    <div class="lab-q"><img id="qr" alt="Código QR de la máquina"></div>
    <div class="lab-t">Escanea para apuntar horas, hacer la revisión o pedir piezas</div>
    <div class="lab-u">Recambios Parsur · 669 925 472 · WhatsApp 624 172 538</div>
  </div>
</div>
<p class="hint">Al escanearlo se abre la ficha de esta máquina. Quien lo escanee tiene que haber entrado antes con el enlace de la empresa o con el teléfono y el PIN: el código por sí solo no da acceso.</p>
<script src="/js/qrcode.js?v=1"></script>
<script>
(function(){try{var q=qrcode(0,'M');q.addData(@json($url));q.make();document.getElementById('qr').src=q.createDataURL(8,2);}catch(e){document.querySelector('.lab-q').innerHTML='<p style="font-size:12px;padding:8mm 2mm">No se ha podido dibujar el código. Recarga la página.</p>';}})();
</script>
</body>
</html>
