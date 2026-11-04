@extends('layout')
@section('title', 'Usuarios')
@section('content')
<div class="head"><div><h1>Usuarios</h1><div class="muted small">Quién puede entrar al panel y qué ve cada uno</div></div></div>

@if(session('invite'))
  <div class="card" style="border-color:#F3D48A;background:#fffaf0;margin-bottom:14px">
    <h2>Enlace para {{ session('invite')['name'] }}</h2>
    <p class="muted" style="margin:0">Mándaselo por WhatsApp o email. Con él pone su propia contraseña. Caduca en 48 horas y solo sirve para esta persona.</p>
    <div class="copy"><input id="inv" type="text" readonly value="{{ session('invite')['url'] }}"><button class="btn sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('inv').value);this.textContent='Copiado'">Copiar</button></div>
  </div>
@endif

<div class="card"><div class="scroll">
  <table class="tbl">
    <thead><tr><th>Nombre</th><th>Rol</th><th>Estado</th><th class="hide-m">Último acceso</th><th></th></tr></thead>
    <tbody>
    @foreach($users as $u)
      <tr>
        <td><strong>{{ $u->name }}</strong><div class="muted small">{{ $u->email }}</div></td>
        <td colspan="2">
          <form method="post" action="/usuarios/{{ $u->id }}" class="row" style="gap:8px">
            @csrf
            <select name="role" style="flex:1 1 160px">@foreach(\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected($u->role === $k)>{{ $v }}</option>@endforeach</select>
            <label class="check" style="margin:0;flex:0 0 auto"><input type="checkbox" name="active" value="1" @checked($u->active)> Activo</label>
            <button class="btn ghost sm" type="submit">Guardar</button>
          </form>
        </td>
        <td class="small muted hide-m">{{ $u->last_login_at ? $u->last_login_at->timezone('Europe/Madrid')->format('d/m/Y H:i') : 'Nunca' }}</td>
        <td class="r"><form method="post" action="/usuarios/{{ $u->id }}/enlace">@csrf<button class="btn ghost sm" type="submit" title="Genera un enlace para que ponga (o cambie) su contraseña">Enlace de acceso</button></form></td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div></div>

<div class="card mt">
  <h2>Añadir usuario</h2>
  <form method="post" action="/usuarios" class="row">
    @csrf
    <div><label for="n">Nombre</label><input id="n" type="text" name="name" required></div>
    <div><label for="e">Email</label><input id="e" type="email" name="email" required></div>
    <div><label for="r">Rol</label><select id="r" name="role">@foreach(\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected($k === 'mostrador')>{{ $v }}</option>@endforeach</select></div>
    <button class="btn y" type="submit">Crear y sacar enlace</button>
  </form>
  <p class="small muted">Administrador: todo. Oficina: ve ventas e importes. Mostrador: pedidos, productos y stock, sin importes del resumen.</p>
</div>
@endsection
