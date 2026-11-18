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
<div class="dm" id="{{ $uid }}">
  <span class="dm-l">🚜 Máquina:</span>
  @if($has)
    <b class="dm-n">{{ $name ?: 'Sin modelo' }}</b>
    @php $bits = array_filter([$doc->machine_type, $doc->machine_serial ? 'nº serie '.$doc->machine_serial : null, $doc->machine_year ? 'año '.$doc->machine_year : null, $doc->machine_hours ? number_format($doc->machine_hours, 0, ',', '.').' h' : null]); @endphp
    @if($bits)<span class="dm-x">{{ implode(' · ', $bits) }}</span>@endif
    @if($inFleet)
      <a class="dm-ok" href="/clientes/{{ $customer->id }}#maquina-{{ $doc->machine_id }}" title="Ver en Parsur Flota">✓ en flota</a>
    @elseif($customer)
      <form method="post" action="{{ $action }}" class="dm-inl" title="La registra en la flota del cliente con las piezas de este documento">@csrf<input type="hidden" name="accion" value="flota"><input type="hidden" name="piezas" value="1">@foreach(['machine_type', 'machine_brand', 'machine_model', 'machine_serial', 'machine_year', 'machine_hours'] as $f)<input type="hidden" name="{{ $f }}" value="{{ $doc->$f }}">@endforeach<button class="dm-add" type="submit">＋ a la flota</button></form>
    @else
      <span class="dm-no">sin flota</span>
    @endif
  @endif
  <details class="dm-ed">
    <summary class="dm-btn">{{ $has ? '✏️ cambiar' : '＋ poner máquina' }}</summary>
    <div class="dm-pop">
      @if($fleet->isNotEmpty())
        <form method="post" action="{{ $action }}" class="dm-row">@csrf
          <input type="hidden" name="accion" value="elegir">
          <select name="machine_id" required style="flex:1 1 220px"><option value="">De su flota ({{ $fleet->count() }})…</option>@foreach($fleet as $m)<option value="{{ $m->id }}" {{ (int) $doc->machine_id === (int) $m->id ? 'selected' : '' }}>{{ trim($m->brand.' '.$m->model) ?: 'Máquina '.$m->id }}{{ $m->serial ? ' · '.$m->serial : '' }}{{ $m->year ? ' · '.$m->year : '' }}</option>@endforeach</select>
          <button class="btn sm" type="submit">Usar</button>
        </form>
        <div class="dm-or">o una nueva:</div>
      @endif
      <form method="post" action="{{ $action }}">@csrf
        <div class="dm-g">
          <input type="text" name="machine_brand" value="{{ $inFleet ? '' : $doc->machine_brand }}" placeholder="Marca" style="text-transform:uppercase">
          <input type="text" name="machine_model" value="{{ $inFleet ? '' : $doc->machine_model }}" placeholder="Modelo" style="text-transform:uppercase">
          <input type="text" name="machine_type" value="{{ $inFleet ? '' : $doc->machine_type }}" list="dm-types" placeholder="Tipo">
          <input type="text" name="machine_serial" value="{{ $inFleet ? '' : $doc->machine_serial }}" placeholder="Nº serie / bastidor" style="text-transform:uppercase">
          <input type="number" name="machine_year" value="{{ $inFleet ? '' : $doc->machine_year }}" min="1950" max="2100" placeholder="Año">
          <input type="number" name="machine_hours" value="{{ $inFleet ? '' : $doc->machine_hours }}" min="0" placeholder="Horas">
        </div>
        <div class="dm-row" style="margin-top:8px">
          <button class="btn sm" type="submit" name="accion" value="guardar">Guardar</button>
          @if($customer)<button class="btn y sm" type="submit" name="accion" value="flota">Guardar y añadir a la flota</button><label class="small"><input type="checkbox" name="piezas" value="1" checked> con las piezas</label>
          @else<span class="small muted">Sin ficha de cliente: no se puede añadir a una flota.</span>@endif
        </div>
      </form>
      @if($has)<form method="post" action="{{ $action }}" style="margin-top:8px" onsubmit="return confirm('¿Quitar la máquina de este documento? (No se borra de la flota)')">@csrf<input type="hidden" name="accion" value="quitar"><button class="dm-btn" type="submit" style="color:#a32020">Quitar la máquina del documento</button></form>@endif
    </div>
  </details>
</div>
@if(empty($GLOBALS['__dm_css']))@php $GLOBALS['__dm_css'] = 1; @endphp<datalist id="dm-types"><option value="Miniexcavadora"><option value="Excavadora"><option value="Retrocargadora"><option value="Pala cargadora"><option value="Dumper"><option value="Minicargadora"><option value="Bulldozer"><option value="Manipulador telescópico"><option value="Carretilla"><option value="Tractor"><option value="Motoniveladora"><option value="Rodillo"></datalist>
<style>
.dm{display:flex;flex-wrap:wrap;align-items:center;gap:4px 8px;font-size:13px;margin-top:4px;position:relative}
.dm .dm-l{color:var(--mut,#6b6b6b)}
.dm .dm-n{font-size:13px}
.dm .dm-x{color:var(--mut,#6b6b6b)}
.dm .dm-ok{font-size:11px;font-weight:700;color:#1c7a43;background:#e6f5ec;border-radius:999px;padding:1px 8px;text-decoration:none}
.dm .dm-no{font-size:11px;color:#8a5a00;background:#fff3d6;border-radius:999px;padding:1px 8px}
.dm .dm-inl{display:inline;margin:0}
.dm .dm-add,.dm .dm-btn{background:none;border:0;padding:0;font:inherit;font-size:12px;font-weight:700;color:#b07a00;cursor:pointer;text-decoration:underline;list-style:none}
.dm .dm-ed{display:inline-block}
.dm .dm-ed>summary::-webkit-details-marker{display:none}
/* dentro de un pedido de la lista, las reglas de «.doc summary» (rejilla, flecha) no deben aplicarse aquí */
.dm .dm-ed>summary,html.dense .doc .dm .dm-ed>summary{display:inline;padding:0;margin:0;min-height:0;grid-template-columns:none;gap:0;background:none!important;white-space:nowrap;list-style:none}
.dm .dm-ed>summary::after{content:none!important;display:none!important}
.dm .dm-n,.dm .dm-l{white-space:nowrap}
.dm .dm-pop{position:absolute;z-index:45;left:0;top:24px;width:min(560px,92vw);background:#fff;border:1px solid #e3ded2;border-radius:12px;box-shadow:0 14px 34px rgba(0,0,0,.18);padding:12px}
.dm .dm-g{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px}
.dm .dm-g input,.dm .dm-pop select{height:34px;padding:0 9px;font-size:13px;border:1px solid #d8d1c0;border-radius:8px;min-width:0;width:100%}
.dm .dm-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.dm .dm-or{font-size:12px;color:#888;margin:8px 0 6px}
@media(max-width:600px){.dm .dm-g{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
@endif
