# Cheatsheet Pemain — Monopoly Bank

Satu halaman. Detail: [aturan-pemain-monopoly.md](aturan-pemain-monopoly.md). Aturan uang: [aturan-banker-monopoly.md](aturan-banker-monopoly.md). Sewa tidak cukup: [aturan-bangkrut-sewa.md](aturan-bangkrut-sewa.md).

## Dual-mode

- **HP (`file://`):** hanya banker. Tidak ada join. Tidak ada salin tautan.
- **Laragon (`http://`):** modal **Banker** atau **Pemain**. Salin URL di Setelan banker.

File UI: `bank_monopoly_v1.html` saja. Data server: `save/game.json`.

## Gabung (mode server)

1. Minta banker daftarkan nama Anda.
2. Buka URL yang disalin banker (Wi-Fi yang sama, IP PC — bukan `file://`).
3. Pilih **Pemain**, isi nama (**huruf besar/kecil tidak peduli**).
4. Nama belum ada → tidak masuk. Minta banker menambah pemain.

## Layar pemain

Header: **badge nama** (warna pion) + **saldo** di pojok kanan, semua tab.

- **Beranda** — request GO / bayar / terima / transfer; daftar pemain
- **Aset** — milik Anda saja
- **Papan** — lihat semua; **Tagih sewa** jika aset Anda (pilih pemain yang mendarat); request beli jika kosong
- **Antrian** — permintaan Anda + **riwayat semua transaksi** meja
- **Setelan** — warna pion, senyap suara, keluar

Saldo berubah **hanya setelah banker setuju**. Tagih sewa juga menunggu banker. Jika saldo tidak cukup tapi aset masih bisa dihipotekkan, Anda **wajib hipotek** sampai cukup. Jika likuiditas tetap kurang: **bangkrut** (uang sisa ke penagih, properti ke bank). Detail: [aturan-bangkrut-sewa.md](aturan-bangkrut-sewa.md).

Suara: kecewa jika uang berkurang, bahagia jika bertambah. Banker mendengar ding saat ada permintaan baru.

## Tidak boleh

Tambah pemain, reset, ubah setelan nominal, setujui request orang lain.
