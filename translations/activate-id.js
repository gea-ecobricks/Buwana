/*-----------------------------------
TEXT TRANSLATION SNIPPETS FOR GOBRIK.com
-----------------------------------*/

// Ampersand (&): Should be escaped as &amp; because it starts HTML character references.
// Less-than (<): Should be escaped as &lt; because it starts an HTML tag.
// Greater-than (>): Should be escaped as &gt; because it ends an HTML tag.
// Double quote ("): Should be escaped as &quot; when inside attribute values.
// Single quote/apostrophe ('): Should be escaped as &#39; or &apos; when inside attribute values.
// Backslash (\): Should be escaped as \\ in JavaScript strings to prevent ending the string prematurely.
// Forward slash (/): Should be escaped as \/ in </script> tags to prevent prematurely closing a script.

const id_Page_Translations = {

    // Activate
    "0001-activate-notice": "Sejak terakhir kali Anda masuk, kami telah melakukan peningkatan besar pada GoBrik.",
    "0002-activate-explantion-1": "Versi lama GoBrik kami berjalan di server dan kode perusahaan. Kami telah membiarkannya berlalu.",
    "0002-activate-explantion-2": "Sebagai gantinya, kami telah memigrasikan semua data kami ke server mandiri yang kami kelola sendiri. GoBrik 3.0 baru kami sekarang sepenuhnya sumber terbuka dan berfokus sepenuhnya pada akuntabilitas ekologis. Sebagai alternatif dari login dengan Google, Apple, atau Facebook, kami telah mengembangkan sistem login kami sendiri (yang kami sebut akun Buwana). Untuk bergabung dengan GoBrik yang telah diperbarui, luangkan waktu sebentar untuk memperbarui ",
"0002-activate-explantion-3": "akun lama Anda ke sistem baru kami.",


    // Confirm email
    "0003-activate-button": '<input type="submit" id="submit-button" value="🍃 Tingkatkan Akun!" class="submit-button activate">',
    "0004-buwana-accounts": "Akun Buwana dirancang dengan mempertimbangkan ekologi, keamanan, dan privasi. Segera, Anda dapat masuk ke aplikasi regeneratif hebat lainnya dengan cara yang sama seperti Anda masuk ke GoBrik!",
    "0005-new-terms": "Syarat dan Ketentuan Baru GoBrik",
    "0005-regen-blog": "Mengapa? Baca blog kami &#39;Regenerasi Besar GoBrik&#39;.",
    "0006-github-code": "Repositori Kode Sumber Github Baru",
    "0007-not-interested": "Jika Anda tidak tertarik dan ingin ",
    "0009-that-too": " akun lama Anda sepenuhnya dihapus, Anda juga dapat melakukannya.",
    "0010-delete-button": "Hapus Akun Saya",
    "0011-warning": "PERINGATAN: Ini tidak dapat dibatalkan.",
    "001-alright": "Baiklah",
    "002-lets-confirm": "mari konfirmasi email Anda.",
    "003-to-create": "Untuk membuat akun Buwana GoBrik Anda, kami perlu mengonfirmasi kredensial yang Anda pilih. Ini adalah cara kami akan tetap berhubungan dan menjaga keamanan akun Anda. Klik tombol kirim dan kami akan mengirimkan kode aktivasi akun ke:",
    "004-send-email-button": '<input type="submit" name="send_email" id="send_email" value="📨 Kirim Kode" class="submit-button activate">',
    "006-enter-code": "Silakan masukkan kode Anda:",
    "007-check-email": "Periksa email Anda",
    "008-for-your-code": "untuk kode konfirmasi akun Anda. Masukkan di sini:",
    "009-no-code": "Tidak menerima kode Anda? Anda dapat meminta pengiriman ulang kode dalam",
"010-email-no-longer": "Apakah Anda tidak lagi menggunakan alamat email ini?<br>Jika tidak, Anda perlu <a href=\"signup.php\">membuat akun baru</a> atau hubungi tim kami di support@gobrik.com.",
    "011-change-email": "Ingin mengganti email Anda?",
    "012-go-back-new-email": "Kembali untuk memasukkan alamat email yang berbeda.",

 // Activate 2
    "001-set-your-pass": "Atur Kata Sandi Baru Anda",
    "002-to-get-going": " Untuk memulai dengan akun yang ditingkatkan, silakan atur kata sandi baru...",
    "007-set-your-pass": "Atur kata sandi Anda:",
    "008-password-advice": "🔑 Kata sandi Anda harus memiliki setidaknya 6 karakter.",
    "009-confirm-pass": "Konfirmasi Kata Sandi Anda:",
    "010-pass-error-no-match": "👉 Kata sandi tidak cocok.",
    "013-by-registering": "Dengan mendaftar hari ini, saya setuju dengan <a href=\"#\" onclick=\"showModalInfo('terms')\" class=\"underline-link\">Ketentuan Layanan GoBrik</a>",
"014-i-agree-newsletter": "Tolong kirimkan saya <a href=\"#\" onclick=\"showModalInfo('earthen', 'id')\" class=\"underline-link\">newsletter Earthen</a> untuk pembaruan aplikasi, ecobrick, dan earthen",
  "015-confirm-pass-button": '<input type="submit" id="submit-button" value="Konfirmasi Kata Sandi" class="submit-button disabled">',

    // Activate-3.php

 "001-password-set": "kata sandi Anda telah diatur!",
  "012-status-heading2": "Sekarang mari kita melokalkan Anda.",
  "013-sub-ecozone": "GoBrik berfokus pada aksi ekologis. Tolong bantu kami menentukan zona ekologis Anda: daerah aliran sungai atau lembah sungai tempat Anda tinggal.",
  "011-your-local-area": "Apa nama daerah lokal Anda?",
  "011-location-full-caption": "Mulailah mengetik nama daerah lokal Anda, dan kami akan melengkapi sisanya menggunakan API OpenStreetMap yang bersumber terbuka dan non-korporat.",
  "000-field-required-error": "Kolom ini wajib diisi.",
   '011-watershed-select': 'Apa daerah aliran sungai (DAS) Anda? Ke sungai/air mana air lokal Anda mengalir?',
  '011b-select-river': '👉 Pilih sungai/aliran air...',
  '011c-unknown': 'Saya tidak tahu',
  '011d-unseen': 'Saya tidak melihat sungai/aliran air lokal saya',
  '011e-no-watershed': 'Tidak ada daerah aliran sungai',
  '012-river-basics': 'ℹ️ <a href="#" onclick="showModalInfo(\'watershed\', \'<?php echo $lang; ?>\')" class="underline-link">Daerah Aliran Sungai</a> memberikan cara ekologis yang bagus untuk melokalkan pengguna berdasarkan wilayah. Peta menunjukkan sungai dan aliran air di sekitar Anda. Pilih yang menuju aliran air Anda.',

   "012-community-name": "Pilih dan konfirmasi komunitas GoBrik Anda:",
  "012-community-caption": "Mulailah mengetik untuk melihat dan memilih komunitas. Saat ini hanya GoBrik 2.0 yang tersedia. Segera Anda dapat menambahkan komunitas baru!",
  "016-next-button": "<input type=\"submit\" id=\"submit-button\" value=\"Berikutnya ➡️\" class=\"submit-button enabled\">"



};

