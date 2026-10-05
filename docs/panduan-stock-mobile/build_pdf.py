"""Bangun docs/panduan-stock-mobile.pdf dari screenshot di screens/.
Jalankan: python build_pdf.py (butuh reportlab + pillow)."""
from pathlib import Path
from PIL import Image
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import cm
from reportlab.lib.colors import HexColor
from reportlab.lib.utils import ImageReader, simpleSplit
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas

HERE = Path(__file__).parent
SCR = HERE / "screens"
OUT = HERE.parent / "panduan-stock-mobile.pdf"
FONT = "/usr/share/fonts/TTF/DejaVuSans.ttf"
BOLD = "/usr/share/fonts/TTF/DejaVuSans-Bold.ttf"
pdfmetrics.registerFont(TTFont("F", FONT))
pdfmetrics.registerFont(TTFont("FB", BOLD))
RED, GRAY, DARK = HexColor("#e11d1d"), HexColor("#6b7280"), HexColor("#111827")
W, H = A4
M = 2 * cm

# crop daftar delivery: hanya kartu demo (tanpa data klien lain)
Image.open(SCR / "01-delivery-list-raw.png").crop((0, 262, 390, 434)).save(SCR / "01-delivery-list.png")

c = canvas.Canvas(str(OUT), pagesize=A4)
c.setTitle("Panduan Stock - Aplikasi Mobile")
page = 0
y = 0


def footer():
    c.setFont("F", 8); c.setFillColor(GRAY)
    c.drawString(M, 1.1 * cm, "Panduan Stock - Pelangi Production Inventory (Mobile)")
    c.drawRightString(W - M, 1.1 * cm, f"Hal. {page}")


def new_page():
    global page, y
    if page:
        footer(); c.showPage()
    page += 1
    y = H - M


def para(text, size=10, font="F", color=DARK, width=None, lead=None, x=None):
    global y
    width = width or (W - 2 * M); lead = lead or size * 1.45; x = M if x is None else x
    c.setFont(font, size); c.setFillColor(color)
    for line in simpleSplit(text, font, size, width):
        c.drawString(x, y, line); y -= lead
    y -= 3


def h1(text):
    global y
    c.setFont("FB", 15); c.setFillColor(RED); c.drawString(M, y, text); y -= 8
    c.setStrokeColor(RED); c.setLineWidth(1.2); c.line(M, y, W - M, y); y -= 18


def step(num, title, text, img, note=None):
    """Teks di kiri, screenshot di kanan. Satu blok ~ 9 cm."""
    global y
    im = Image.open(SCR / img)
    iw = 3.7 * cm; ih = iw * im.height / im.width
    need = max(ih, 3 * cm) + 0.4 * cm
    if y - need < 2 * cm:
        new_page()
    top = y
    c.setFillColor(RED); c.circle(M + 0.4 * cm, top - 0.3 * cm, 0.4 * cm, fill=1, stroke=0)
    c.setFillColor(HexColor("#ffffff")); c.setFont("FB", 10)
    c.drawCentredString(M + 0.4 * cm, top - 0.3 * cm - 3.5, str(num))
    tx = M + 1.2 * cm; tw = W - M - iw - 0.6 * cm - tx
    y = top - 0.15 * cm
    para(title, 11, "FB", x=tx, width=tw)
    para(text, 9.5, width=tw, x=tx)
    if note:
        para("Catatan: " + note, 8.5, color=GRAY, width=tw, x=tx)
    bottom_text = y
    ix = W - M - iw
    c.drawImage(ImageReader(str(SCR / img)), ix, top - ih, iw, ih)
    c.setStrokeColor(HexColor("#d1d5db")); c.setLineWidth(0.6); c.rect(ix, top - ih, iw, ih)
    y = min(bottom_text, top - ih) - 0.5 * cm


# ---------------- Cover ----------------
new_page()
y = H - 6 * cm
c.setFont("FB", 26); c.setFillColor(RED); c.drawString(M, y, "Panduan Stock"); y -= 1.1 * cm
c.setFont("F", 14); c.setFillColor(DARK); c.drawString(M, y, "Aplikasi Mobile - Production Inventory"); y -= 0.9 * cm
c.setFont("F", 10); c.setFillColor(GRAY)
c.drawString(M, y, "Stock In, Stock Card, stok manual, dan pengiriman (delivery)"); y -= 3 * cm
for t in ["Isi panduan:", "1. Konsep dasar", "2. Stock Card (saldo & riwayat)", "3. Stock In untuk project",
          "4. Stock manual (tanpa project)", "5. Pengiriman (delivery) & transfer antar gudang",
          "6. Daftar dokumen & aturan penting"]:
    c.setFont("FB" if t.endswith(":") else "F", 11); c.setFillColor(DARK); c.drawString(M, y, t); y -= 0.7 * cm
y = 4 * cm
c.setFont("F", 8.5); c.setFillColor(GRAY)
c.drawString(M, y, "Screenshot memakai data contoh (project demo). Tampilan dan angka di aplikasi Anda akan berbeda.")

# ---------------- 1. Konsep ----------------
new_page()
h1("1. Konsep dasar")
para("Stok dicatat saat barang datang. Begitu dokumen Stock In disimpan, stok di gudang langsung bertambah "
     "sebesar qty yang Anda isi. Tidak ada rencana dan tidak ada langkah konfirmasi lagi.")
y -= 4
para("Istilah pada halaman detail project:", 10.5, "FB")
para("- Belum Masuk: sisa jumlah order yang belum dicatat masuk gudang (jumlah order dikurangi total yang sudah dicatat).")
para("- Stored: jumlah yang sudah dicatat masuk dan ada di gudang.")
para("- Delivered: jumlah yang sudah dikirim ke klien / gudang tujuan.")
y -= 4
para("Alur singkat untuk barang project:", 10.5, "FB")
para("Ready To Deliver  >  barang datang, catat Stock In (isi qty yang datang)  >  stok bertambah  >  "
     "buat Delivery  >  tandai Delivered  >  stok keluar dari gudang asal.")
y -= 4
para("Penting:", 10.5, "FB")
para("- Catat Stock In hanya saat barang benar-benar sudah ada di tangan.")
para("- Barang datang bertahap? Buat satu dokumen Stock In untuk setiap kedatangan. Total yang dicatat tidak boleh melebihi jumlah order project.")
para("- Pengiriman tidak bisa dibuat/disimpan jika stok di gudang asal tidak cukup. Catat Stock In terlebih dahulu.")
para("- Salah catat? Buka dokumen lalu Ubah atau Hapus; stok disesuaikan otomatis (tidak bisa jika stoknya sudah terkirim).")
para("- Barang tanpa project (misalnya kardus, bahan pembantu) memakai Stock In/Out manual.")
y -= 0.5 * cm
step(1, "Menu Stock Card", "Buka menu (ikon tiga garis kiri atas) lalu pilih Stock Card. Ini menu utama untuk melihat saldo "
     "stok, membuat Stock In/Out, dan membuka dokumen.", "19-menu.png")

# ---------------- 2. Stock Card ----------------
new_page()
h1("2. Stock Card")
step(1, "Saldo per barang & gudang", "Daftar menampilkan saldo tiap barang di tiap gudang. Gunakan kolom pencarian "
     "(nama barang / nomor JOB) dan ikon filter untuk menyaring. Tombol Stock In dan Stock Out di bawah dipakai untuk "
     "membuat dokumen. Ikon di kanan pencarian membuka Dokumen dan katalog Barang Manual.", "10-stockcard.png")
step(2, "Riwayat (ledger) barang", "Ketuk salah satu baris untuk melihat riwayat: tanggal, dokumen, jumlah masuk, "
     "keluar, dan saldo. Isi Dari/Sampai lalu ketuk ikon cari untuk membatasi periode; saldo awal dan akhir periode "
     "ikut dihitung.", "11-ledger.png")

# ---------------- 3. Stock In project ----------------
new_page()
h1("3. Stock In untuk project")
step(1, "Cari project", "Di menu Delivery, cari project. Project yang sudah Ready namun belum punya Stock In "
     "diberi label Belum Stock In.", "01-delivery-list.png",
     "Label ini hanya muncul untuk project yang diset Ready To Deliver dengan aturan Stock In.")
step(2, "Buka detail project", "Di detail project ada kolom Belum Masuk, Stored, dan Delivered. Ketuk tombol "
     "Stock In di bagian bawah untuk membuat dokumen penerimaan barang.", "02-project-detail.png")
step(3, "Isi form Stock In", "Pilih Produk project dan project-nya, isi Nomor DO / surat jalan, tanggal barang datang, "
     "asal (opsional, lihat catatan), gudang penerima, lalu barang dan qty yang datang. Tambah baris dengan Tambah Barang bila "
     "ada beberapa barang. Ketuk Simpan.", "03-stockin-form-atas.png",
     "Tanpa Asal: total qty yang datang tidak boleh melebihi qty order project. Dengan Asal (gudang penyimpanan): dokumen menjadi transfer, stok asal berkurang, stok gudang penerima bertambah, dan qty maksimal = stok barang itu di gudang asal.")
step(4, "Stok langsung masuk", "Setelah Simpan, dokumen tercatat dan stok gudang penerima langsung bertambah. "
     "Contoh: datang 3 dari 10 order.", "08-received.png",
     "Tombol Ubah dan Hapus tersedia bila ada salah catat; stok disesuaikan otomatis.")
step(5, "Belum Masuk dan Stored diperbarui", "Kembali ke detail project: Stored bertambah sesuai yang sudah dicatat, "
     "dan Belum Masuk menunjukkan sisa yang masih ditunggu (7 dari 10).", "09-project-after.png",
     "Saat sisa barang datang, buat Stock In baru untuk sisa tersebut.")

# ---------------- 4. Manual ----------------
new_page()
h1("4. Stock manual (tanpa project)")
para("Untuk barang yang bukan bagian dari project (kardus, bahan pembantu, dsb.), daftarkan dulu barangnya di katalog "
     "Barang Manual, lalu buat Stock In/Out biasa.")
y -= 6
step(1, "Tambah barang manual", "Buka Stock Card > ikon Barang Manual > Tambah Barang Manual. Isi kode, nama, dan "
     "satuan, lalu Simpan. Barang manual tidak disinkronkan ke accounting/eproc.", "20-manual-item.png")
step(2, "Buat Stock In / Stock Out manual", "Dari Stock Card ketuk Stock In atau Stock Out. Pilih Jenis, lalu "
     "Barang = Manual / tanpa project. Isi nomor DO, gudang, barang, dan qty, lalu Simpan.", "21-manual-form.png")
step(3, "Hasil", "Setelah Simpan, stok langsung berubah: bertambah untuk Stock In, berkurang untuk Stock Out. "
     "Stock Out ditolak bila saldo tidak cukup.", "22-manual-detail.png")

# ---------------- 5. Delivery ----------------
new_page()
h1("5. Pengiriman (delivery)")
step(1, "Buat delivery baru", "Dari detail project pilih Update Delivery Status > tambah delivery. Isi tanggal, nomor "
     "surat jalan, Default Origin (gudang asal), dan Destination (klien atau gudang lain). Tombol tambah item baru "
     "muncul setelah isian ini lengkap.", "14-delivery-form.png")
step(2, "Tambah item & cek stok", "Pada form item, angka di kanan qty (/ 3) adalah stok tersedia di gudang asal. "
     "Qty tidak boleh melebihi stok.", "13-item-modal.png",
     "Jika stok tidak cukup, aplikasi menolak dengan pesan: buat Stock In terlebih dahulu.")
step(3, "Simpan delivery", "Ketuk Save. Delivery tersimpan dengan status Ready dan belum mengurangi stok.",
     "15-delivery-saved.png")
step(4, "Detail delivery", "Buka delivery dari daftar. Anda bisa mengubah item (ikon pensil) atau langsung "
     "menandai terkirim dengan Bulk Delivery.", "16-delivery-detail.png")
step(5, "Bulk Delivery", "Isi qty yang benar-benar terkirim per item (isi 0 untuk mengecualikan), ketuk Save di dialog, "
     "lalu ketuk Save di halaman utama. Perubahan baru tersimpan setelah Save di halaman utama.", "17-bulk.png",
     "Jangan lupa Save kedua - dialog hanya menyimpan sementara di layar.")
step(6, "Delivered", "Status berubah Delivered. Stok keluar dari gudang asal dan masuk ke tujuan. Jika tujuannya "
     "gudang lain (bukan Client), maka ini adalah transfer antar gudang: stok gudang asal berkurang, gudang "
     "tujuan bertambah.", "18-delivered.png")
step(7, "Saldo setelah pengiriman", "Di Stock Card, saldo gudang asal turun sesuai jumlah yang dikirim (contoh: 3 menjadi 0).",
     "10b-stockcard-after.png")

# ---------------- 6. Dokumen & aturan ----------------
new_page()
h1("6. Daftar dokumen & aturan penting")
step(1, "Daftar dokumen Stock In/Out", "Dari Stock Card buka ikon Dokumen. Semua dokumen tampil dengan status "
     "dan jumlah item. Cari dengan nomor DO atau JOB, atau saring dengan ikon filter. Ketuk dokumen untuk "
     "melihat, mengubah, atau menghapusnya.", "12-documents.png")
y -= 0.3 * cm
para("Pertanyaan umum", 11, "FB", color=RED)
for q, a in [
    ("Kapan stok bertambah?", "Saat dokumen Stock In disimpan. Karena itu catat hanya ketika barang sudah datang."),
    ("Barang datang sebagian, bagaimana?",
     "Catat qty yang datang sekarang. Saat sisanya datang, buat Stock In baru. Angka Belum Masuk menunjukkan sisanya."),
    ("Salah catat qty?", "Buka dokumen > Ubah, perbaiki qty lalu Simpan. Atau Hapus dokumen; stok kembali otomatis."),
    ("Ubah/Hapus ditolak karena stok sudah dikirim?",
     "Barang dari dokumen itu sudah terpakai pengiriman. Batalkan pengiriman terkait dulu, atau catat koreksi lewat dokumen baru."),
    ("Delivery ditolak karena stok kurang?",
     "Catat Stock In ke gudang asal terlebih dahulu, atau kurangi qty / ganti gudang asal."),
    ("Kirim ke gudang lain?", "Pilih gudang tujuan (bukan Client). Efeknya transfer stok antar gudang."),
    ("Project lama yang sudah punya stok / delivery?",
     "Tetap memakai alur lama; aturan Stock In hanya berlaku untuk project yang diset Ready To Deliver tanpa "
     "stok/delivery sebelumnya."),
]:
    para("Q: " + q, 9.5, "FB"); para("A: " + a, 9.5); y -= 2

footer()
c.save()
print("OK", OUT, page, "halaman")
