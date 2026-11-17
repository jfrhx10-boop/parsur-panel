@php
  // $doc: Quote o FsDoc · $action: URL del POST · $customer: Customer|null
  $customer = $customer ?? null;
  // Pedido que viene de un presupuesto del panel: hereda su máquina la primera vez.
  if ($doc instanceof \App\Models\FsDoc && ! $doc->machine_id && ! $doc->machine_model && ! $doc->machine_brand) {
      $src = \App\Models\Quote::where('fs_year', $doc->year)->where('fs_order_code', $doc->code)->where(fn ($q) => $q->whereNotNull('machine_id')->orWhereNotNull('machine_model')->orWhereNotNull('machine_brand'))->first();
      if ($src) {
          $doc->update($src->only(['machine_id', 'machine_type', 'machine_brand', 'machine_model', 'machine_serial', 'machine_year', 'machine_hours']));
      }
  }
  $fleet = $customer ? \App\Models\Machine::where('customer_id', $customer->id)->orderBy('brand')->orderBy('model')->get() : collect();
  if ($doc->machine_id && ! $doc->machine_model && ! $doc->machine_brand && ($mm = \App\Models\Machine::find($doc->machine_id))) {
      foreach (['type' => 'machine_type', 'brand' => 'machine_brand', 'model' => 'machine_model', 'serial' => 'machine_serial', 'year' => 'machine_year', 'hours' => 'machine_hours'] as $from => $to) {
          $doc->$to = $mm->$from; // solo para mostrarla (no se guarda)
      }
  }
  $has = $doc->machine_id || $doc->machine_brand || $doc->machine_model;
  $inFleet = $doc->machine_id && $fleet->firstWhere('id', $doc->machine_id);
  $name = trim(($doc->machine_brand ?? '').' '.($doc->machine_model ?? ''));
  $uid = 'dm'.substr(md5($action), 0, 8);
@endphp
<div class="dm card mt" id="{{ $uid }}">
  <div class="dm-h">
    <span class="dm-ic">🚜</span>
    <div class="dm-t">
      <b>Máquina</b>
      @if($has)
        <span class="dm-n">{{ $name ?: 'Sin modelo' }}</span>
        <span class="small muted">@if($doc->machine_type){{ $doc->machine_type }} · @endif @if($doc->machine_serial)nº serie {{ $doc->machine_serial }} · @endif @if($doc->machine_year)año {{ $doc->machine_year }} · @endif @if($doc->machine_hours){{ number_format($doc->machine_hours, 0, ',', '.') }} h @endif</span>
        @if($inFleet)
          <span class="dm-ok">✓ En la flota del cliente</span> @if($customer)<a class="small" href="/clientes/{{ $customer->id }}#maquina-{{ $doc->machine_id }}">Ver en Parsur Flota</a>@endif
        @else
          <span class="dm-no">No está en la flota</span>
        @endif
      @else
        <span class="small muted">Apunta para qué máquina es: así queda en el documento y se puede registrar en la flota del cliente.</span>
      @endif
    </div>
    @if($has)<form method="post" action="{{ $action }}" onsubmit="return confirm('¿Quitar la máquina de este documento? (No se borra de la flota)')">@csrf<input type="hidden" name="accion" value="quitar"><button class="btn ghost sm" type="submit">Quitar</button></form>@endif
  </div>

  @if($has && ! $inFleet && $customer)
    <form method="post" action="{{ $action }}" class="dm-fl">@csrf
      <input type="hidden" name="accion" value="flota">
      @foreach(['machine_type', 'machine_brand', 'machine_model', 'machine_serial', 'machine_year', 'machine_hours'] as $f)<input type="hidden" name="{{ $f }}" value="{{ $doc->$f }}">@endforeach
      <label class="small"><input type="checkbox" name="piezas" value="1" checked> Apuntar en la máquina las piezas de este documento</label>
      <button class="btn y sm" type="submit">＋ Añadir a la flota de {{ $customer->displayName() }}</button>
    </form>
  @endif

  <details class="dm-ed"{{ $has ? '' : ' open' }}>
    <summary class="btn ghost sm">{{ $has ? '✏️ Cambiar máquina' : '＋ Poner máquina' }}</summary>
    @if($fleet->isNotEmpty())
      <form method="post" action="{{ $action }}" class="dm-row">@csrf
        <input type="hidden" name="accion" value="elegir">
        <div style="flex:1 1 280px"><label>De su flota ({{ $fleet->count() }})</label>
          <select name="machine_id" required><option value="">Elige una máquina…</option>@foreach($fleet as $m)<option value="{{ $m->id }}" {{ (int) $doc->machine_id === (int) $m->id ? 'selected' : '' }}>{{ trim($m->brand.' '.$m->model) ?: 'Máquina '.$m->id }}{{ $m->serial ? ' · '.$m->serial : '' }}{{ $m->year ? ' · '.$m->year : '' }}</option>@endforeach</select></div>
        <div style="align-self:flex-end"><button class="btn sm" type="submit">Usar esta</button></div>
      </form>
      <div class="small muted" style="margin:8px 0 2px">…o apunta una máquina nueva:</div>
    @endif
    <form method="post" action="{{ $action }}">@csrf
      <div class="dm-row">
        <div style="flex:1 1 140px"><label>Tipo</label><input type="text" name="machine_type" value="{{ $inFleet ? '' : $doc->machine_type }}" list="dm-types" placeholder="Miniexcavadora"></div>
        <div style="flex:1 1 140px"><label>Marca</label><input type="text" name="machine_brand" value="{{ $inFleet ? '' : $doc->machine_brand }}" placeholder="Kubota" style="text-transform:uppercase"></div>
        <div style="flex:1 1 140px"><label>Modelo</label><input type="text" name="machine_model" value="{{ $inFleet ? '' : $doc->machine_model }}" placeholder="KX41-3" style="text-transform:uppercase"></div>
        <div style="flex:1 1 150px"><label>Nº de serie / bastidor</label><input type="text" name="machine_serial" value="{{ $inFleet ? '' : $doc->machine_serial }}" style="text-transform:uppercase"></div>
        <div style="flex:0 1 90px"><label>Año</label><input type="number" name="machine_year" value="{{ $inFleet ? '' : $doc->machine_year }}" min="1950" max="2100"></div>
        <div style="flex:0 1 100px"><label>Horas</label><input type="number" name="machine_hours" value="{{ $inFleet ? '' : $doc->machine_hours }}" min="0"></div>
      </div>
      <div class="dm-row" style="margin-top:8px;align-items:center">
        <button class="btn sm" type="submit" name="accion" value="guardar">Guardar en el documento</button>
        @if($customer)
          <button class="btn y sm" type="submit" name="accion" value="flota">Guardar y añadir a la flota</button>
          <label class="small"><input type="checkbox" name="piezas" value="1" checked> con las piezas de este documento</label>
        @else
          <span class="small muted">Sin ficha de cliente: se guarda en el documento; para la flota hace falta el cliente.</span>
        @endif
      </div>
    </form>
  </details>
</div>
<datalist id="dm-types"><option value="Miniexcavadora"><option value="Excavadora"><option value="Retrocargadora"><option value="Pala cargadora"><option value="Dumper"><option value="Minicargadora"><option value="Bulldozer"><option value="Manipulador telescópico"><option value="Carretilla"><option value="Tractor"><option value="Motoniveladora"><option value="Rodillo"></datalist>
<style>
.dm .dm-h{display:flex;gap:12px;align-items:flex-start}
.dm .dm-ic{font-size:24px;line-height:1}
.dm .dm-t{flex:1;display:flex;flex-wrap:wrap;gap:4px 10px;align-items:baseline}
.dm .dm-n{font-weight:800;font-size:16px}
.dm .dm-ok{font-size:12px;font-weight:700;color:#1c7a43;background:#e6f5ec;border-radius:999px;padding:2px 9px}
.dm .dm-no{font-size:12px;font-weight:700;color:#8a5a00;background:#fff3d6;border-radius:999px;padding:2px 9px}
.dm .dm-fl{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px;padding:10px;background:#fffaf0;border:1px dashed #f0c76a;border-radius:10px}
.dm .dm-ed{margin-top:10px}
.dm .dm-ed>summary{list-style:none;display:inline-block;margin-bottom:8px}
.dm .dm-row{display:flex;gap:10px;flex-wrap:wrap}
</style>
