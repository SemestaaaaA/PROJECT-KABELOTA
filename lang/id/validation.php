<?php

// Subset used by Kabelota forms; other rules fall back to English.
return [
    'required' => 'Kolom :attribute wajib diisi.',
    'email' => 'Format :attribute belum benar, contoh: nama@perusahaan.co.id.',
    'string' => 'Kolom :attribute harus berupa teks.',
    'integer' => 'Kolom :attribute harus berupa angka.',
    'date' => 'Kolom :attribute harus berupa tanggal.',
    'after_or_equal' => 'Kolom :attribute tidak boleh sebelum hari ini.',
    'in' => 'Pilihan :attribute tidak tersedia.',
    'array' => 'Pilihan :attribute tidak valid.',
    'enum' => 'Pilihan :attribute tidak tersedia.',
    'min' => ['string' => 'Kolom :attribute minimal :min karakter.', 'numeric' => 'Kolom :attribute minimal :min.'],
    'max' => ['string' => 'Kolom :attribute maksimal :max karakter.', 'numeric' => 'Kolom :attribute maksimal :max.'],
];
