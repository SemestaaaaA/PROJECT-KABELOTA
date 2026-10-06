@extends('errors.shell')
@section('code', '419')
@section('heading', 'Sesi sudah habis')
@section('message', 'Halaman terlalu lama terbuka sehingga formulir tidak bisa dikirim. Kembali, muat ulang halaman, lalu coba lagi.')
@section('actions')
    <button class="btn btn-accent" type="button" onclick="history.back()">Kembali</button>
    <a class="btn btn-line" href="/">Ke beranda</a>
@endsection
