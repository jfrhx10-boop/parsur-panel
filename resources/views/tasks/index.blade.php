@extends('layout')
@section('title', 'Pendientes')
@section('content')
<div class="head">
  <div><h1>Pendientes</h1><div class="muted small">Lo que hay que hacer, compartido entre todos: {{ $c['pending'] }} pendientes @if($c['overdue'])· <span class="bad">{{ $c['overdue'] }} vencidos</span>@endif @if($c['today'])· {{ $c['today'] }} para hoy@endif</div></div>
</div>
<div class="card" style="margin-bottom:14px">@include('tasks._form')</div>
<div class="tabs">
  <a href="/pendientes" class="{{ $filter === 'pendientes' ? 'on' : '' }}">Todos</a>
  <a href="/pendientes?ver=mias" class="{{ $filter === 'mias' ? 'on' : '' }}">Míos</a>
  <a href="/pendientes?ver=hechas" class="{{ $filter === 'hechas' ? 'on' : '' }}">Hechos</a>
</div>
<div class="card">@include('tasks._list', ['emptyText' => $filter === 'hechas' ? 'Todavía no hay nada hecho.' : 'Nada pendiente. 🎉'])</div>
@endsection
