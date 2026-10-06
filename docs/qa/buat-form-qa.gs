/**
 * Membuat Google Form "Laporan QA Kabelota" + Google Sheets rekap dalam satu kali jalan.
 *
 * Cara pakai:
 * 1. Buka https://script.google.com → Proyek baru.
 * 2. Hapus isi editor, tempel seluruh file ini, simpan.
 * 3. Pilih fungsi buatFormQa → Jalankan → izinkan akses ke Google Forms dan Sheets.
 * 4. Buka menu Eksekusi/Log: link form, link edit, dan link sheet ada di sana.
 * 5. Pertanyaan unggah screenshot belum bisa dibuat lewat skrip: tambahkan manual di form
 *    (Tambah pertanyaan → Upload file, maks. 5 file, 10 MB).
 */
function buatFormQa() {
  var form = FormApp.create('Laporan QA Kabelota');
  form.setDescription(
    'Satu form untuk satu temuan. Kalau menemukan 3 masalah, isi 3 kali. ' +
    'Sebelum mengisi, ulangi langkahnya sekali lagi supaya yakin masalahnya bisa terjadi lagi.'
  );
  form.setCollectEmail(true);
  form.setProgressBar(true);
  form.setConfirmationMessage('Terima kasih. Temuanmu masuk ke rekap QA dan akan ditinjau.');

  // Bagian 1: penguji
  form.addSectionHeaderItem().setTitle('Penguji');
  form.addTextItem().setTitle('Nama').setRequired(true);
  pilihan(form, 'Kamu menguji sebagai', ['Pengunjung (belum masuk)', 'Talenta alumni', 'Talenta mahasiswa', 'Perusahaan (HRD)', 'Admin']);
  pilihan(form, 'Perangkat', ['HP Android', 'iPhone', 'Laptop/PC', 'Tablet']);
  pilihan(form, 'Browser', ['Chrome', 'Safari', 'Firefox', 'Brave', 'Lainnya']);
  pilihan(form, 'Tema saat menguji', ['Terang', 'Gelap']);

  // Bagian 2: temuan
  form.addPageBreakItem().setTitle('Temuan');
  pilihan(form, 'Jenis temuan', ['Error atau tidak bisa lanjut', 'Tampilan berantakan', 'Teks salah atau membingungkan', 'Lambat', 'Saran fitur']);
  form.addListItem().setTitle('Halaman atau fitur').setRequired(true).setChoiceValues([
    'Beranda', 'Cari Talenta', 'Profil talenta', 'Buat/Ubah Profil', 'Tawaran', 'Lamaran', 'Lowongan',
    'Pasang Lowongan', 'Profil Perusahaan', 'Pelamar', 'Daftar/Masuk', 'Lupa kata sandi', 'Verifikasi email',
    'Pengaturan akun', 'Tentang Kami/Kontak', 'Panel Admin', 'Loader/animasi', 'Lainnya',
  ]);
  form.addTextItem().setTitle('Link halaman').setHelpText('Salin dari address bar browser.');
  form.addTextItem().setTitle('Judul singkat masalah').setRequired(true)
    .setHelpText('Contoh: Tombol Simpan tidak merespons di langkah 3');
  form.addParagraphTextItem().setTitle('Langkah yang kamu lakukan').setRequired(true)
    .setHelpText('Tulis berurutan. 1. Buka ... 2. Klik ... 3. Isi ...');
  form.addParagraphTextItem().setTitle('Yang kamu harapkan terjadi').setRequired(true);
  form.addParagraphTextItem().setTitle('Yang benar-benar terjadi').setRequired(true)
    .setHelpText('Tulis juga pesan error yang muncul, kalau ada.');
  pilihan(form, 'Seberapa sering terjadi', ['Selalu', 'Kadang-kadang', 'Sekali saja']);
  pilihan(form, 'Tingkat keparahan', [
    'Kritis: data orang lain terlihat, atau sama sekali tidak bisa lanjut',
    'Tinggi: fitur utama gagal, tapi ada cara lain',
    'Sedang: mengganggu, tapi fitur tetap bisa dipakai',
    'Rendah: kosmetik (salah ketik, warna, jarak, ikon)',
  ]);

  // Rekap di Google Sheets + kolom untuk QA lead.
  var ss = SpreadsheetApp.create('Rekap QA Kabelota');
  form.setDestination(FormApp.DestinationType.SPREADSHEET, ss.getId());
  SpreadsheetApp.flush();
  var sheet = ss.getSheets()[0];
  var last = sheet.getLastColumn();
  var extra = ['ID', 'Status', 'PIC', 'Rilis QA', 'Catatan perbaikan / commit'];
  sheet.getRange(1, last + 1, 1, extra.length).setValues([extra]).setFontWeight('bold').setBackground('#F5B800');
  sheet.setFrozenRows(1);
  var status = SpreadsheetApp.newDataValidation()
    .requireValueInList(['Baru', 'Dikerjakan', 'Selesai', 'Bukan bug', 'Ditunda'], true).build();
  sheet.getRange(2, last + 2, 999, 1).setDataValidation(status);

  Logger.log('Link untuk anggota : ' + form.getPublishedUrl());
  Logger.log('Edit form          : ' + form.getEditUrl());
  Logger.log('Rekap (Sheets)     : ' + ss.getUrl());
}

function pilihan(form, judul, opsi) {
  form.addMultipleChoiceItem().setTitle(judul).setRequired(true).setChoiceValues(opsi);
}
