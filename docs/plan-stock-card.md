# Plan: Stock Card + Stock In/Out Diskrit

Branch: `feature/stock-documents-v2` (dari `main` 4c141ae). Implementasi lama ada di `stash@{0}` ("stock-documents v1") hanya sebagai referensi.

## 1. Tujuan

Saat ini stok project **digenerate penuh otomatis** ketika memilih pabrik di "Ready to Deliver" (`ProjectController::delivery()` membuat baris `inventories` dengan `quantity = products.quantity`). Tujuan:

1. Stok masuk dicatat **diskrit** seperti Delivery: dokumen Stock In dengan item, qty rencana, qty aktual, dan konfirmasi **parsial**.
2. Mendukung stok **manual** (tanpa project, barang bukan dari project) dengan Stock In dan Stock Out yang mekanismenya sama.
3. Menu utama **Stock Card**: saldo + riwayat mutasi per barang.

Di luar cakupan: mengubah halaman Inventory lama, mengubah data/perilaku project legacy, Stock Out untuk project (sudah ditangani Delivery).

## 2. Keputusan yang sudah diambil

| Topik | Keputusan |
|---|---|
| Struktur | Satu mekanisme dokumen (header + item) untuk Stock In project, Stock In manual, Stock Out manual |
| Penerimaan | Parsial boleh; total aktual per produk ≤ qty produk di project |
| Menu | "Stock Card" menu utama; tombol Stock In/Stock Out + tab Dokumen di dalamnya; pintasan Stock In di detail project |
| Inventory lama | Tidak diubah; tetap tampilan saldo project |
| Project lama | Flag `stock_in_required` diisi saat Ready to Deliver: `true` hanya jika project belum punya inventory maupun delivery. Project legacy **tidak** punya Stock Card (hanya saldo di Inventory lama) |
| Pabrik | "Pilih pabrik" tetap di Ready to Deliver; di Stock In menjadi asal/referensi. Gudang penerima dipilih per dokumen (gudang `storage = true`) |
| Stok manual | Disimpan terpisah (`manual_inventories`) agar `inventories` (project/product wajib) tidak berubah |

Asumsi yang belum dikonfirmasi eksplisit: Stock Out manual ditolak jika saldo kurang; Stock In hanya boleh ke gudang `storage = true`.

## 3. Model data

Migrasi baru (tanpa menyentuh tabel lama kecuali satu kolom di `projects`):

- `projects.stock_in_required` boolean default `false`.
- `manual_items`: `id, code (unique), name, unit, active, timestamps`.
- `stock_ins` (header): `id, direction ('in'|'out'), project (nullable FK), do_number, document_date, origin (nullable FK warehouses; pabrik, referensi), warehouse (FK warehouses; gudang penerima/asal-keluar), files (json), status ('ready'|'partial'|'received'|'cancel'), notes, created_by, timestamps, softDeletes`.
- `stock_in_items`: `id, stock_in (FK), product (nullable FK products), manual_item (nullable FK), quantity, actual_quantity, received_at (nullable), timestamps, softDeletes`. Constraint di aplikasi: tepat salah satu dari `product`/`manual_item`; `product` wajib milik `project` header; `manual_item` hanya jika `project` null.
- `manual_inventories`: `id, manual_item, warehouse, quantity, unique(manual_item, warehouse)`.

Aturan: `direction = out` hanya boleh tanpa project dan dengan manual item.

## 4. Backend

- `Src\Models\StockIn`, `StockInItem`, `ManualItem`, `ManualInventory`.
- `Src\Services\StockInService` (transaksi DB, `lockForUpdate`):
  - `save()` validasi (qty integer 1..1e9, tanpa item duplikat, aktual ≤ rencana, total per produk ≤ qty project, gudang storage, tujuan ≠ asal).
  - `confirm(item)` / `unconfirm(item)`: menambah/mengurangi saldo.
    - project + in → `InventoryService::adjust(+actual)` di gudang header (jadikan `adjust` public).
    - manual + in → `manual_inventories` +actual.
    - manual + out → `manual_inventories` −actual, ditolak jika saldo < actual.
  - `unconfirm` ditolak jika membuat saldo negatif (mis. stok sudah dikirim lewat delivery).
  - `destroy` hanya jika tidak ada item terkonfirmasi (atau unconfirm dulu).
  - Hitung status header dari item (ready/partial/received).
- `StockInController` + rute:
  - `GET /stock-ins` (filter: direction, status, project, search, paginate), `GET /stock-ins/{id}`, `POST`, `PUT /{id}`, `DELETE /{id}`.
  - `GET /manual-items`, `POST`, `PUT /{id}`.
  - `GET /stock-card` (daftar barang + saldo, filter: scope, gudang, project, search, paginate) dan `GET /stock-card/{type}/{id}?warehouse_id&from&to` (mutasi).
- Hak akses: tulis hanya `admin`/`delivery`; baca `admin`/`delivery`/`marketing`/`finance`.
- Perubahan di kode yang ada:
  - `ProjectController::delivery()`: jika project belum punya inventory/delivery → set `stock_in_required = true`, **tidak** membuat baris inventory (tetap set status/manufacture + notifikasi). Selain itu perilaku lama.
  - `InventoryService::recalculateForProject()`: untuk `stock_in_required` hitung dari Stock In terkonfirmasi (bukan `products.quantity`).
  - `DeliveryController`: untuk project `stock_in_required`, saat store/update, jika stok asal kurang → error jelas + tautan ke Stock In. Logika transfer tidak berubah.
  - `ProjectController::update` (edit produk): tolak menghapus/mengurangi produk di bawah total Stock In terkonfirmasi.
- Kartu stok: ledger dibangun dari `stock_in_items` terkonfirmasi (+/−) dan `delivery_items` delivered (project baru saja), diurut `received_at`/`delivered_at`, saldo berjalan dihitung server-side. Project legacy dikecualikan.

## 5. Frontend

Mobile (`src/client/src/pages`):
- `StockCard.vue` (daftar barang+saldo, filter, tombol Stock In/Out, tab Dokumen) dan `StockCardDetail.vue` (mutasi).
- `StockIns.vue` (daftar dokumen), `StockInInput.vue`, `StockInDetail.vue` (konfirmasi parsial memakai pola `BulkDelivery`).
- `ManualItems.vue` (katalog) — fase 2.
- Menu "Stock Card" di `Layout.vue`; tombol "Stock In" di `Detail.vue` project (hanya jika `stock_in_required`).

Desktop (`src/client/src/desktop`): `MktStockCard.vue`, `MktStockCardDetail.vue`, `MktStockIns.vue`, `MktStockInInput.vue`, `MktStockInDetail.vue`, `MktManualItems.vue`; menu di `MktLayout.vue`. Komponen bersama dari v1 yang layak dipakai ulang: `MktFilterPopover`, `MktPager` (ambil dari stash).

Rute: `router.js` (mobile + `desktopRoutes`), API helper di `apilist.js`/`service.js`.

## 6. Fase dan urutan kerja

**Fase 0 — Persiapan**
1. Rollback skema lokal dari v1 (`stock_flow`, `stock_documents`, `stock_document_items`, `inventory_products`; kembalikan 549 project ke kondisi awal).

**Fase 1 — Stock In project + Stock Card project**
2. Migrasi (`stock_in_required`, `stock_ins`, `stock_in_items`), model, `StockInService`, controller, rute.
3. Tes PHPUnit: simpan, validasi, konfirmasi parsial, batas total ≤ qty project, unconfirm, hapus, status header, hak akses, kartu stok.
4. Ubah `ProjectController::delivery()`, `InventoryService::recalculateForProject()`, cek stok di `DeliveryController`; tes regresi legacy (project dengan inventory tetap seperti semula).
5. UI mobile lalu desktop.
6. Uji end-to-end di browser: project baru → Ready to Deliver → Stock In parsial 2x → DO → delivered → saldo Inventory lama dan Stock Card cocok.

**Fase 2 — Barang manual**
7. Migrasi `manual_items`, `manual_inventories`; `StockInService` untuk manual in/out; katalog; tes.
8. UI form manual (project kosong), tab Manual di Stock Card.
9. E2E: manual in parsial, manual out, saldo kurang ditolak.

## 7. Risiko dan hal yang dicek

- `InventoryService::recalculateForProject()` dipakai saat edit produk project; salah hitung bisa menghapus stok. Wajib ada tes.
- Unique `(warehouse, product)` di `inventories` (dipakai `adjust` dengan fallback "adopt row") — jangan sampai dua project berbagi produk yang sama.
- Pasangan project deposit/aktual (bundle mode) bisa beda alur karena flag per project.
- `.env` lokal menunjuk database `inventory_prod`; migrasi/rollback hanya dijalankan setelah konfirmasi.
- Dev server Vite di `/mnt/D`: kelas Windi untuk file baru perlu `touch`.
- Ikon Remixicon dimuat dari CDN; masalah ikon hilang bukan dari fitur ini.

## 8. Definisi selesai

- Semua tes PHPUnit baru dan lama lulus; `vite build` berhasil.
- Alur e2e fase 1 dan fase 2 terverifikasi di browser (mobile + desktop), tanpa error console.
- Project legacy tidak berubah perilaku (diverifikasi dengan tes dan satu project nyata).
- Data demo dibersihkan; laporan singkat + screenshot diperbarui.
