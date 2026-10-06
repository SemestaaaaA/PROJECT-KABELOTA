@extends('errors.shell')
@section('code', '503')
@section('heading', 'Sedang perbaikan')
@section('message', 'Kabelota sedang diperbarui dan akan kembali dalam beberapa menit.')
@section('actions')
    <button class="btn btn-accent" type="button" onclick="location.reload()">Muat ulang</button>
@endsection
