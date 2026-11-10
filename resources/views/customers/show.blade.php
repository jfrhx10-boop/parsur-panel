@extends('layout')
@section('title', $customer->displayName())
@section('content')
@php $c = $customer; $tel = preg_replace('/\D+/', '', (string) $c->bestPhone()); @endphp
<div class="head">
  <div>
    <a class="small" href="/clientes">← Clientes</a>
    <h1>{{ $c->displayName() }}</h1>
    <div class="muted small">@if($c->tradeName())Comercial: {{ $c->tradeName() }} · @endif @if($c->nif){{ $c->nif }} · @endif @if($c->codcli)Código FactuSOL {{ $c->codcli }}@else Cliente solo del panel @endif @if($c->synced_at)· sincronizado {{ $c->synced_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }}@endif
      @if($c->tariff)@php $tf = \App\Models\Tariff::all_()[$c->tariff] ?? null; @endphp · <a href="/tarifas" class="badge" style="text-decoration:none" title="{{ $tf?->name }}">Tarifa {{ $c->tariff }}@if($tf) · {{ $tf->detail() }} · margen {{ rtrim(rtrim(number_format($tf->margin, 2, ',', '.'), '0'), ',') }} %@endif</a>@endif</div>
  </div>
</div>

@if($c->alerts->isNotEmpty())
<div class="alerts">
  @foreach($c->alerts as $al)
    <div class="al al-{{ $al->kind }}">
      <span class="al-k">{{ $al->kindLabel() }}</span>
      <span class="al-t">{{ $al->text }}@if($al->kind === 'contacto' && $al->phone()) <a class="wa-mini" target="_blank" rel="noopener" href="https://wa.me/{{ str_starts_with($al->phone(), '34') ? $al->phone() : '34'.$al->phone() }}">WhatsApp</a>@endif</span>
      <form method="post" action="/clientes/{{ $c->id }}/avisos/{{ $al->id }}/quitar" onsubmit="return confirm('¿Quitar este aviso?')">@csrf<button class="linkbtn" type="submit" title="Quitar aviso">×</button></form>
    </div>
  @endforeach
</div>
@endif

<nav class="tabs ficha-tabs" id="fichaTabs" style="margin:12px 0 4px;flex-wrap:wrap">
  <a href="#resumen" data-t="resumen" class="on">Resumen</a>
  <a href="#flota" data-t="flota">Flota ({{ $c->machines->count() }})</a>
  <a href="#ventas" data-t="ventas">Ventas y pedidos</a>
  <a href="#notas" data-t="notas">Pendientes y notas</a>
</nav>
<div data-pane="resumen">
<div class="grid g2">
  <div class="card">
    <h2>Contacto</h2>
    <div class="ct">
      @if($c->bestPhone())
      <div class="ct-r"><span class="ct-i">{!! \App\Services\Icons::svg('phone') !!}</span><div class="ct-v"><a href="tel:{{ $tel }}">{{ $c->bestPhone() }}</a>@if($c->phone && $c->mobile && $c->phone !== $c->mobile)<span class="ct-sec">· <a href="tel:{{ preg_replace('/\D+/', '', $c->phone) }}">{{ $c->phone }}</a></span>@endif</div><a class="ct-a ct-wa" href="https://wa.me/{{ str_starts_with($tel, '34') ? $tel : '34'.$tel }}" target="_blank" rel="noopener">{!! \App\Services\Icons::svg('whatsapp') !!}WhatsApp</a></div>
      @endif
      @if($c->email)
      <div class="ct-r"><span class="ct-i">{!! \App\Services\Icons::svg('mail') !!}</span><div class="ct-v"><a href="mailto:{{ $c->email }}">{{ $c->email }}</a></div><a class="ct-a" href="mailto:{{ $c->email }}">Escribir</a></div>
      @endif
      @if($c->address || $c->city)
      <div class="ct-r"><span class="ct-i">{!! \App\Services\Icons::svg('pin') !!}</span><div class="ct-v ct-addr">{{ $c->address }}@if($c->postcode || $c->city)<br><span>{{ $c->postcode }} {{ $c->city }}@if($c->province) ({{ $c->province }})@endif</span>@endif</div><a class="ct-a" href="https://www.google.com/maps/search/?api=1&query={{ urlencode(trim($c->address.' '.$c->postcode.' '.$c->city.' '.$c->province)) }}" target="_blank" rel="noopener">Mapa</a></div>
      @endif
      @if($c->name !== $c->displayName())
      <div class="ct-r"><span class="ct-i">{!! \App\Services\Icons::svg('building') !!}</span><div class="ct-v ct-muted">{{ $c->name }}</div></div>
      @endif
      @if($c->paymentTerms())
      <div class="ct-r"><span class="ct-i">{!! \App\Services\Icons::svg('card') !!}</span><div class="ct-v"><span class="badge {{ str_contains(mb_strtolower($c->paymentTerms()), 'contado') ? 'warn' : '' }}">{{ $c->paymentTerms() }}</span></div></div>
      @endif
    </div>
    <details style="margin-top:10px"><summary class="small" style="cursor:pointer;font-weight:600">+ Añadir aviso destacado</summary>
      <form method="post" action="/clientes/{{ $c->id }}/avisos" class="row" style="margin-top:6px">
        @csrf
        <div style="flex:0 1 170px"><label for="ak">Tipo</label><select id="ak" name="kind">@foreach(\App\Models\CustomerAlert::KINDS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div style="flex:3 1 220px"><label for="at">Aviso</label><input id="at" type="text" name="text" required maxlength="500" placeholder="DESCUENTO 160 € PENDIENTE DE APLICAR"></div>
        <button class="btn y sm" type="submit">Guardar</button>
      </form>
    </details>
    <details class="pt-admin" style="margin-top:10px" {{ $c->portal_enabled ? 'open' : '' }}>
      <summary class="small" style="cursor:pointer;font-weight:600">🔗 Área de cliente (portal) {!! $c->portal_enabled ? '<span class="badge ok">activa</span>' : '<span class="badge">sin activar</span>' !!}</summary>
      <div style="margin-top:8px">
        @if($c->portal_enabled && $c->portal_token)
          <div class="small" style="margin-bottom:6px">El cliente entra en <b>panel.recambiosparsur.com/portal</b> con su <b>teléfono</b> ({{ $c->bestPhone() ?: 'sin teléfono en la ficha' }}) y su <b>PIN</b>{{ $c->portal_pin ? '' : ' (aún sin PIN: ponle uno abajo)' }}. También vale este enlace privado:</div>
          <div class="row" style="gap:6px;align-items:center;margin-bottom:8px">
            <input type="text" readonly value="{{ $c->portalUrl() }}" id="pt-url" onclick="this.select()" style="flex:1 1 260px;font-size:12px">
            <button type="button" class="btn ghost sm" onclick="navigator.clipboard.writeText(document.getElementById('pt-url').value).then(()=>this.textContent='Copiado')">Copiar</button>
            @if($c->bestPhone())<a class="btn ghost sm" target="_blank" rel="noopener" href="https://wa.me/{{ preg_replace('/\D+/', '', '34'.ltrim($c->bestPhone(), '+34')) }}?text={{ rawurlencode("Hola, ya tienes acceso al área de clientes de Recambios Parsur: entra en https://panel.recambiosparsur.com/portal con tu teléfono y tu PIN (te lo decimos aparte). Ahí puedes ver tus máquinas, lo que llevas gastado y hacernos pedidos.") }}">Enviar por WhatsApp</a>@endif
          </div>
          <div class="row" style="gap:8px;align-items:center;flex-wrap:wrap">
            <form method="post" action="/clientes/{{ $c->id }}/portal/pin" class="row" style="gap:6px;align-items:center;margin:0">@csrf<input type="text" name="pin" inputmode="numeric" maxlength="8" placeholder="PIN (opcional)" style="width:130px" title="3–8 cifras; en blanco quita el PIN"><button class="btn ghost sm" type="submit">{{ $c->portal_pin ? 'Cambiar PIN' : 'Poner PIN' }}</button>@if($c->codcli)<button class="btn ghost sm" type="submit" name="codigo" value="1" title="PIN = código de cliente en FactuSOL ({{ $c->codcli }})">Usar código de cliente ({{ $c->codcli }})</button>@endif @if($c->portal_pin)<span class="small muted">PIN activo</span>@endif</form>
            <form method="post" action="/clientes/{{ $c->id }}/portal/regenerar" style="margin:0" onsubmit="return confirm('El enlace actual dejará de funcionar. ¿Generar uno nuevo?')">@csrf<button class="btn ghost sm" type="submit">Nuevo enlace</button></form>
            <form method="post" action="/clientes/{{ $c->id }}/portal/desactivar" style="margin:0" onsubmit="return confirm('¿Desactivar el acceso del cliente?')">@csrf<button class="btn ghost sm" type="submit">Desactivar</button></form>
            @if($c->portal_last_at)<span class="small muted">última entrada {{ $c->portal_last_at->format('d/m/Y H:i') }}</span>@endif
            <a class="btn sm" href="/mensajes/{{ $c->id }}">💬 Mensajes con el cliente</a>
          </div>
          @php $logins = \App\Models\PortalLogin::where('customer_id', $c->id)->orderByDesc('id')->limit(8)->get(); @endphp
          @if($logins->isNotEmpty())<details style="margin-top:8px"><summary class="small muted" style="cursor:pointer">Accesos recientes ({{ $logins->count() }})</summary><div class="small" style="margin-top:4px">@foreach($logins as $lg)<div>{{ $lg->created_at->format('d/m/Y H:i') }} · {!! $lg->ok ? 'entrada' : '<b style="color:var(--bad)">PIN fallido</b>' !!} · IP {{ $lg->ip }}</div>@endforeach</div></details>@endif
        @else
          <p class="small muted" style="margin:0 0 8px">El cliente podrá ver sus máquinas, añadir otras, poner maquinistas y contactos, ver lo que lleva gastado y hacer pedidos (o repetirlos) sin ver ninguna referencia. Los pedidos os llegan como pendientes.</p>
          <form method="post" action="/clientes/{{ $c->id }}/portal/activar" style="margin:0">@csrf<button class="btn y sm" type="submit">Activar y generar enlace</button></form>
        @endif
      </div>
    </details>
  </div>
  <div class="card">
    <h2>Observaciones de FactuSOL <span class="badge">solo lectura</span></h2>
    @if(!$c->factusol_notes)<p class="muted" style="margin:0">Sin observaciones en FactuSOL.</p>
    @elseif($c->fleet_imported_at)
      <p class="small muted" style="margin:0 0 8px">Las máquinas, piezas y filtros de estas observaciones ya están en la flota de abajo; el resto, en los avisos. Si cambias las observaciones en FactuSOL, la flota se actualiza sola en la siguiente sincronización (cada 15 min) o ahora mismo con el botón.</p>
      <div class="row" style="align-items:center;gap:10px;margin-bottom:6px">
        <form method="post" action="/clientes/{{ $c->id }}/flota/actualizar" style="margin:0">@csrf<button class="btn ghost sm" type="submit" title="Relee las observaciones de FactuSOL y rehace la flota sin perder lo editado a mano">↻ Actualizar desde FactuSOL</button></form>
        <span class="small muted">flota importada {{ $c->fleet_imported_at?->format('d/m/Y H:i') }}</span>
      </div>
      <details><summary class="small" style="cursor:pointer;font-weight:600">Ver el texto original</summary><p class="small" style="margin:8px 0 0;white-space:pre-line">{{ $c->factusol_notes }}</p></details>
    @else<p style="margin:0;white-space:pre-line">{{ $c->factusol_notes }}</p>@endif
  </div>
</div>
</div>

<div data-pane="flota" hidden>
<div class="card mt">
  @php $pend = $c->machines->where('review', true)->count(); @endphp
  <div class="head" style="margin-bottom:10px;align-items:center">
    <h2 style="margin:0">Flota <span class="muted small">({{ $c->machines->count() }})</span></h2>
    <div class="small muted">
      @if($refCount){{ $refCount }} referencias apuntadas @endif
      @if($pend) · <span class="badge warn">{{ $pend }} por revisar</span>@endif
    </div>
  </div>
  <p class="small muted" style="margin:0 0 10px">Marca las piezas que quieras en una máquina y pulsa <strong>Presupuesto</strong>: se crea con los precios de la tarifa del cliente, listo para retocar y enviar en PDF.</p>
  @if($pend)<p class="small muted" style="margin:0 0 10px">Las máquinas «por revisar» vienen de las observaciones de FactuSOL y les falta la marca o el nº de serie. Si está bien, pulsa <strong>Confirmar</strong>; si no, corrígela a mano o quítala.</p>@endif
  @if($c->machines->isNotEmpty())
  <div class="fleet">
    @foreach($c->machines as $m)
      @php $list = $items[$m->id] ?? []; $groups = ['filtro' => 'Filtros', 'desgaste' => 'Desgaste', 'recambio' => 'Recambios']; @endphp
      <article class="mc{{ $m->review ? ' rev' : '' }}" id="maquina-{{ $m->id }}">
        <form method="post" action="/presupuestos" class="mc-sel" id="sel-{{ $m->id }}">@csrf<input type="hidden" name="customer_id" value="{{ $c->id }}"><input type="hidden" name="machine_id" value="{{ $m->id }}"></form>
        <header class="mc-h">
          <span class="fl-ico">{!! \App\Services\Icons::machine($m->type, $m->model) !!}</span>
          <div class="mc-t">
            <div class="mc-n">@if($m->brand){!! \App\Services\Icons::brand($m->brand, 'sm') !!}@endif<a class="mc-link" href="/clientes/{{ $c->id }}/maquinas/{{ $m->id }}" title="Ficha de la máquina: ventas, pedidos y presupuestos"><strong>{{ $m->model }}</strong></a>@if($m->review)<span class="badge warn">por revisar</span>@endif @if($sz = $m->size())<span class="sz sz-{{ strtolower($sz) }}" title="Gama por peso operativo{{ $m->tons === null ? ' (estimado por el modelo; edítalo con el lápiz)' : '' }}">{{ $sz }}<i>{{ $m->tons === null ? '~' : '' }}{{ \App\Services\MachineSize::fmt($m->tonsEstimate()) }} t</i></span>@endif</div>
            <div class="mc-meta"><span>{{ $m->type ?: 'Máquina' }}</span>@if($m->year)<span>Año {{ $m->year }}</span>@endif @if(!empty($sales[$m->id]))<a href="/clientes/{{ $c->id }}/maquinas/{{ $m->id }}">{!! \App\Services\Icons::svg('doc-albaran') !!}{{ $sales[$m->id]['n'] }} {{ $sales[$m->id]['n'] === 1 ? 'albarán' : 'albaranes' }}@if(auth()->user()->canSeeSales()) · {{ eur($sales[$m->id]['total']) }}@endif · último {{ $sales[$m->id]['last'] }}</a>@endif</div>
          </div>
          <div class="mc-sn">
            <span class="small muted">Nº serie</span>
            @if($m->serial)<code>{{ $m->serial }}</code>@else<span class="mc-nosn">—</span>@endif
          </div>
          <div class="mc-act">
            @if($m->review)<form method="post" action="/clientes/{{ $c->id }}/maquinas/{{ $m->id }}/confirmar">@csrf<button class="btn sm" type="submit">Confirmar</button></form>@endif
            <a class="btn ghost sm" href="/clientes/{{ $c->id }}/maquinas/{{ $m->id }}" title="Ficha de la máquina">Ficha</a>
            <button class="btn ghost sm" type="button" onclick="document.getElementById('edit-{{ $m->id }}').toggleAttribute('hidden')" title="Editar máquina">✎</button>
            <button class="btn y sm" type="submit" form="sel-{{ $m->id }}" title="Presupuesto con las piezas marcadas (o vacío)">Presupuesto</button>
          </div>
        </header>
        <div class="mc-edit" id="edit-{{ $m->id }}" hidden>
          <form method="post" action="/clientes/{{ $c->id }}/maquinas/{{ $m->id }}/editar" class="row">
            @csrf
            <div style="flex:1 1 140px"><label>Tipo</label><input type="text" name="type" value="{{ $m->type }}" list="tipos"></div>
            <div style="flex:1 1 120px"><label>Marca</label><input type="text" name="brand" value="{{ $m->brand }}"></div>
            <div style="flex:1 1 140px"><label>Modelo</label><input type="text" name="model" value="{{ $m->model }}" required></div>
            <div style="flex:0 1 100px"><label>Año</label><input type="number" name="year" value="{{ $m->year }}" min="1950" max="2100"></div>
            <div style="flex:0 1 110px"><label>Toneladas</label><input type="text" name="tons" value="{{ $m->tons !== null ? \App\Services\MachineSize::fmt((float) $m->tons) : '' }}" placeholder="{{ $m->tonsEstimate() !== null ? '~'.\App\Services\MachineSize::fmt($m->tonsEstimate()) : '' }}" inputmode="decimal" title="Peso operativo aproximado; en blanco se estima por el modelo"></div>
            <div style="flex:1 1 160px"><label>Nº serie</label><input type="text" name="serial" value="{{ $m->serial }}"></div>
            <div style="flex:1 1 100%"><label>Piezas y filtros (tal como se apuntan: «F ACEITE SO115 F AIRE SA17118 CUCHILLAS 4T6381-1»)</label><textarea name="parts" rows="2" style="width:100%;padding:10px 12px;border:1px solid #d8d1c0;border-radius:10px;font:inherit">{{ $m->parts }}</textarea></div>
            <div style="flex:1 1 100%"><label>Notas</label><input type="text" name="notes" value="{{ $m->notes }}"></div>
            <button class="btn sm" type="submit">Guardar cambios</button>
            <button class="btn ghost sm" type="button" onclick="document.getElementById('edit-{{ $m->id }}').setAttribute('hidden','')">Cancelar</button>
          </form>
          <form method="post" action="/clientes/{{ $c->id }}/maquinas/{{ $m->id }}/borrar" onsubmit="return confirm('¿Quitar esta máquina de la flota?')" style="margin-top:6px">@csrf<button class="linkbtn" type="submit" style="color:var(--bad)">Quitar esta máquina de la flota</button></form>
        </div>
        @if($list || $m->notes)
        <div class="mc-b">
          @foreach($groups as $gk => $gname)
            @php $gi = array_values(array_filter($list, fn ($x) => ($x['kind'] ?? 'recambio') === $gk)); @endphp
            @if($gi)
            <div class="pg">
              <span class="pg-l">{{ $gname }}</span>
              <div class="pg-items">
              @foreach($gi as $it)
                @php $k = $known[$it['ref']] ?? null; @endphp
                <label class="pt {{ $gk }} sel"><input type="checkbox" name="refs[]" value="{{ $it['label'] }}|{{ $it['ref'] }}" form="sel-{{ $m->id }}">{!! \App\Services\Icons::part($it['label'], $it['filter']) !!}<em>{{ $it['label'] }}</em>@if($it['ref']) @if($k)<a href="/proveedores/{{ $k->supplier_id }}/referencias/{{ $k->id }}" title="Ver consumo de esta referencia" onclick="event.stopPropagation()">{{ $it['ref'] }}</a>@else<b>{{ $it['ref'] }}</b>@endif @endif @if($it['qty'])<small>×{{ $it['qty'] }}</small>@endif</label>
              @endforeach
              </div>
            </div>
            @endif
          @endforeach
          @if($m->notes)<div class="small" style="white-space:pre-line;margin-top:4px">{{ $m->notes }}</div>@endif
        </div>
        @else<div class="mc-b"><span class="small muted">Sin piezas apuntadas</span></div>@endif
      </article>
    @endforeach
  </div>
  @else<p class="muted" style="margin:0 0 10px">Todavía no hay máquinas. Añade las que tiene este cliente.</p>@endif
  <h3 class="small muted" style="margin:16px 0 0;text-transform:uppercase;letter-spacing:.4px">Añadir máquina</h3>
  <form method="post" action="/clientes/{{ $c->id }}/maquinas" class="row" style="margin-top:8px">
    @csrf
    <div><label for="mt">Tipo</label><input id="mt" type="text" name="type" placeholder="Miniexcavadora, dumper…" list="tipos"></div>
    <div><label for="mb">Marca</label><input id="mb" type="text" name="brand" placeholder="Kubota"></div>
    <div><label for="mm">Modelo</label><input id="mm" type="text" name="model" required placeholder="KX016-4"></div>
    <div style="flex:0 1 110px"><label for="my">Año</label><input id="my" type="number" name="year" min="1950" max="2100" placeholder="2015"></div>
    <div><label for="ms">Nº serie</label><input id="ms" type="text" name="serial"></div>
    <div style="flex:2 1 220px"><label for="mp">Piezas / medidas</label><input id="mp" type="text" name="parts" placeholder="F ACEITE SO115 F AIRE SA17118 PUNTAS TSJ450P"></div>
    <div style="flex:3 1 300px"><label for="mn">Notas</label><input id="mn" type="text" name="notes"></div>
    <button class="btn y" type="submit">Añadir máquina</button>
  </form>
  <datalist id="tipos"><option>Miniexcavadora</option><option>Excavadora</option><option>Dumper</option><option>Minicargadora</option><option>Pala cargadora</option><option>Bulldozer</option><option>Motoniveladora</option><option>Compactador</option><option>Tractor</option></datalist>
</div>
</div>

<div data-pane="notas" hidden>
<div class="card mt">
  <div class="head" style="margin-bottom:8px;align-items:center"><h2 style="margin:0">Pendientes con este cliente <span class="muted small">({{ $tasks->count() }})</span></h2><a class="small" href="/pendientes">Ver todos →</a></div>
  @include('tasks._form', ['customerId' => $c->id])
  <div style="margin-top:10px">@include('tasks._list', ['hideCustomer' => true, 'emptyText' => 'Nada pendiente con este cliente.'])</div>
  @if($doneTasks->isNotEmpty())<details style="margin-top:8px"><summary class="small muted" style="cursor:pointer">Últimos hechos ({{ $doneTasks->count() }})</summary><div style="margin-top:6px">@include('tasks._list', ['tasks' => $doneTasks, 'hideCustomer' => true])</div></details>@endif
</div>

<div class="card mt">
  <h2>Notas del panel <span class="badge">solo en el panel</span></h2>
  <p class="small muted" style="margin:-4px 0 8px">No se guardan en FactuSOL. Las observaciones de FactuSOL están en «Resumen» y se editan allí.</p>
  <form method="post" action="/clientes/{{ $c->id }}/notas" class="row" style="margin-bottom:10px">
    @csrf
    <div style="flex:5 1 320px"><label for="nb" class="muted small">Nueva nota</label><input id="nb" type="text" name="body" required maxlength="5000" placeholder="Qué ha pasado, qué necesita, qué se le prometió…"></div>
    <button class="btn y" type="submit">Guardar</button>
  </form>
  @forelse($c->notes as $n)
    <div style="border-top:1px solid var(--line);padding:10px 0">
      <div class="small muted">{{ $n->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i') }} · {{ $n->user?->name ?? '—' }}
        @if(auth()->user()->isAdmin() || $n->user_id === auth()->id())
          · <form method="post" action="/clientes/{{ $c->id }}/notas/{{ $n->id }}/borrar" style="display:inline" onsubmit="return confirm('¿Borrar la nota?')">@csrf<button class="linkbtn" type="submit">borrar</button></form>
        @endif</div>
      <div style="white-space:pre-line">{{ $n->body }}</div>
    </div>
  @empty
    <p class="muted" style="margin:0">Aún no hay notas.</p>
  @endforelse
</div>
</div>

<div data-pane="ventas" hidden>
@if($repurchase->isNotEmpty())
<div class="card mt" style="border-left:5px solid {{ $repurchase->first()->late > 0 ? 'var(--yel)' : 'var(--line)' }}">
  <div class="head" style="margin-bottom:8px;align-items:center"><h2 style="margin:0">Le toca cambiar <span class="muted small">(filtros y aceite que compra con regularidad)</span></h2><a class="small" href="/recompra">Ver recompra →</a></div>
  <div class="scroll"><table class="tbl"><thead><tr><th>Artículo</th><th>Máquina</th><th>Cada</th><th>Última</th><th>Le toca</th></tr></thead><tbody>
    @foreach($repurchase->take(8) as $r)
      <tr><td><a href="/articulos/{{ rawurlencode($r->art) }}"><code class="art">{{ $r->art }}</code></a> <span class="small muted">{{ \Illuminate\Support\Str::limit($r->description, 36) }}</span></td><td class="small">{{ $r->ref }}</td><td class="small">{{ $r->interval }} días</td><td class="small">{{ $r->last->format('d/m/Y') }}</td><td>@if($r->late > 0)<span class="badge {{ $r->late > 60 ? 'bad' : 'warn' }}">hace {{ $r->late }} días</span>@else<span class="small muted">en {{ abs($r->late) }} días</span>@endif</td></tr>
    @endforeach
  </tbody></table></div>
</div>
@endif

@if($c->codcli)
<div class="card mt">
  <div class="head" style="margin-bottom:10px;align-items:center">
    <h2 style="margin:0">Ventas en FactuSOL</h2>
    <div class="small muted">
      @if($albCount){{ $albCount }} albaranes @if(auth()->user()->canSeeSales())· {{ date('Y') }}: <strong>{{ eur($yearTotal) }}</strong> @if($prevTotal)· {{ date('Y') - 1 }}: {{ eur($prevTotal) }}@endif <span class="muted">(sin IVA)</span>@endif @else Sin albaranes sincronizados @endif
    </div>
  </div>

  @if($panelQuotes->isNotEmpty())
    <h3 class="small muted" style="margin:0 0 6px;text-transform:uppercase;letter-spacing:.4px">Presupuestos hechos en el panel ({{ $panelQuotes->count() }})</h3>
    <div class="scroll"><table class="tbl" style="margin-bottom:14px"><tbody>
      @foreach($panelQuotes as $pq)<tr><td><a href="/presupuestos/{{ $pq->id }}"><strong>{{ $pq->number }}</strong></a></td><td class="small">{{ $pq->date->format('d/m/Y') }}</td><td class="small">{{ $pq->ref }}</td><td><span class="badge {{ $pq->statusClass() }}">{{ $pq->statusLabel() }}</span></td><td class="num">{{ eur($pq->total) }}</td><td class="num"><a class="btn ghost sm" href="/presupuestos/{{ $pq->id }}/pdf" target="_blank">PDF</a></td></tr>@endforeach
    </tbody></table></div>
  @endif
  @if($quotes->isNotEmpty())
    <h3 class="small muted" style="margin:0 0 6px;text-transform:uppercase;letter-spacing:.4px">Presupuestos FactuSOL sin respuesta ({{ $quotes->count() }})</h3>
    <div class="docs">
      @foreach($quotes as $d)
        @include('fs_docs._row', ['d' => $d, 'party' => null, 'partyUrl' => null, 'hideParty' => true])
      @endforeach
    </div>
  @endif

  @if($portalOrders->isNotEmpty())
    <h3 class="small muted" style="margin:{{ $quotes->isNotEmpty() ? '16px' : '0' }} 0 6px;text-transform:uppercase;letter-spacing:.4px">Pedidos desde el portal ({{ $portalOrders->count() }}) <a class="small" style="text-transform:none;letter-spacing:0;font-weight:400" href="/pedidos-portal">todos los clientes →</a></h3>
    @foreach($portalOrders as $o)@include('portal_orders._order', ['o' => $o])@endforeach
  @endif

  @if($pending->isNotEmpty())
    <h3 class="small muted" style="margin:{{ $quotes->isNotEmpty() || $portalOrders->isNotEmpty() ? '16px' : '0' }} 0 6px;text-transform:uppercase;letter-spacing:.4px">Pedidos en curso ({{ $pending->count() }}) <span style="text-transform:none;letter-spacing:0;font-weight:400">· verde servido · ámbar en almacén · rojo por llegar</span></h3>
    <div class="docs">
      @foreach($pending as $d)
        @include('fs_docs._order', ['d' => $d, 'p' => $progress[$d->id], 'open' => $pending->count() <= 2])
      @endforeach
    </div>
  @endif

  <h3 class="small muted" style="margin:{{ $pending->isNotEmpty() || $quotes->isNotEmpty() ? '16px' : '0' }} 0 6px;text-transform:uppercase;letter-spacing:.4px">Últimos albaranes</h3>
  @if($albaranes->isEmpty())
    <p class="muted" style="margin:0">Todavía no hay albaranes de este cliente. Sincroniza los documentos en Ajustes.</p>
  @else
    <div class="docs">
      @foreach($albaranes as $d)
        @include('fs_docs._row', ['d' => $d, 'party' => null, 'partyUrl' => null, 'hideParty' => true, 'open' => $loop->first])
      @endforeach
    </div>
  @endif
</div>
@endif

<div class="card mt">
  <h2>Pedidos de la web</h2>
  @if($ordersError)<div class="alert err">{{ $ordersError }}</div>
  @elseif(!$c->email && !$c->bestPhone())<p class="muted" style="margin:0">Sin email ni teléfono no se pueden buscar sus pedidos.</p>
  @elseif(count($orders) === 0)<p class="muted" style="margin:0">No se han encontrado pedidos de la web para este cliente.</p>
  @else
  <div class="scroll"><table class="tbl">
    <thead><tr><th>Pedido</th><th>Fecha</th><th>Productos</th><th>Estado</th><th class="r">Total</th></tr></thead>
    <tbody>
    @foreach($orders as $o)
      <tr>
        <td><a href="/pedidos/{{ $o['id'] }}">#{{ $o['number'] }}</a></td>
        <td class="small">{{ fecha($o['date_created']) }}</td>
        <td class="small">@foreach($o['line_items'] as $li){{ $li['quantity'] }}× {{ \Illuminate\Support\Str::limit($li['name'], 50) }}<br>@endforeach</td>
        <td><span class="badge b-{{ $o['status'] }}">{{ estado_pedido($o['status']) }}</span></td>
        <td class="r">{{ eur($o['total']) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  @endif
</div>
</div>
<script>
(function(){
  var tabs=document.querySelectorAll('#fichaTabs a'),panes=document.querySelectorAll('[data-pane]');
  function show(t){ if(!document.querySelector('[data-pane="'+t+'"]')) t='resumen';
    panes.forEach(function(p){p.hidden=p.dataset.pane!==t}); tabs.forEach(function(a){a.classList.toggle('on',a.dataset.t===t)});
    try{history.replaceState(null,'','#'+t)}catch(e){} }
  tabs.forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();show(a.dataset.t)})});
  show((location.hash||'#resumen').slice(1));
})();
</script>
@if(auth()->user()->isAdmin() && !$c->codcli)
<form method="post" action="/clientes/{{ $c->id }}/borrar" class="mt" onsubmit="return confirm('¿Borrar este cliente con su flota y sus notas?')">@csrf<button class="btn ghost sm" type="submit">Borrar cliente</button></form>
@endif
@endsection
