{{-- Vista compacta / amplia y documentos por página (se recuerdan en este navegador) --}}
@php $pp = \App\Support\PerPage::get(request()); @endphp
<span class="dens no-print">
  <button type="button" class="btn ghost sm" onclick="psDense()" title="Cambiar entre vista compacta (más documentos a la vista) y amplia"><span class="dens-c">▦ Vista amplia</span><span class="dens-a">☰ Vista compacta</span></button>
  <select class="dens-n" aria-label="Por página" title="Documentos por página" onchange="document.cookie='pp='+this.value+';path=/;max-age=31536000';var u=new URL(location.href);u.searchParams.delete('page');u.searchParams.delete('n');location.href=u.toString()">@foreach(\App\Support\PerPage::OPTIONS as $o)<option value="{{ $o }}" {{ $pp === $o ? 'selected' : '' }}>{{ $o }} por página</option>@endforeach</select>
</span>
