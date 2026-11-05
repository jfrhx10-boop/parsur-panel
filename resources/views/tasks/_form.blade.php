<form method="post" action="/pendientes" class="row">
  @csrf
  @if(!empty($customerId))<input type="hidden" name="customer_id" value="{{ $customerId }}">@endif
  <div style="flex:4 1 260px"><label for="tt">Qué hay que hacer</label><input id="tt" type="text" name="title" required maxlength="300" placeholder="{{ !empty($customerId) ? 'Llamar por las cuchillas de la L150H' : 'Pedir cuchillas a HIFI para Áridos…' }}"></div>
  <div style="flex:0 1 150px"><label for="td">Para cuándo</label><input id="td" type="date" name="due_date" value="{{ now('Europe/Madrid')->toDateString() }}"></div>
  <div style="flex:0 1 170px"><label for="ta">Quién</label><select id="ta" name="assigned_to"><option value="">Cualquiera</option>@foreach($users as $u)<option value="{{ $u->id }}" {{ $u->id === auth()->id() ? 'selected' : '' }}>{{ $u->name }}</option>@endforeach</select></div>
  <button class="btn y" type="submit">Apuntar</button>
</form>
