@php
  // Botón "+ Presupuesto" para un artículo: lo añade a un presupuesto abierto o crea uno nuevo para un cliente.
  // El panel se rellena al abrirlo (plantilla común en _addq_assets) para no repetirlo en cada fila.
@endphp
<details class="addq" data-art="{{ $codart }}"><summary class="btn ghost sm" title="Añadir este artículo a un presupuesto">+ Presup.</summary></details>
