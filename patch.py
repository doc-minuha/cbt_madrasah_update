import re

with open("index.html", "r", encoding="utf-8") as f:
    content = f.read()

# We want to replace the `function saveDB()` through `// FUNGSI TAMBAHAN`
# Let's find the start index of `function saveDB() {`
start_idx = content.find("function saveDB() {")
# Find the end index right before `// FUNGSI TAMBAHAN UNTUK MEMASTIKAN DROPDOWN`
end_idx = content.find("// FUNGSI TAMBAHAN UNTUK MEMASTIKAN DROPDOWN GRUP")

if start_idx == -1 or end_idx == -1:
    print("Cannot find indices")
else:
    new_code = """function saveDB() {
    // Simpan ke local sebagai backup (untuk fail-safe offline sebentar)
    localStorage.setItem(DB_KEY, JSON.stringify(db));
    
    // Simpan ke server XAMPP/PHP
    clearTimeout(syncTimeout);
    syncTimeout = setTimeout(() => {
        syncToCloud();
    }, 1000); 
}

function syncToCloud() {
    const url = 'api.php?action=simpan_data';
    
    fetch(url, {
        method: 'POST',
        body: JSON.stringify(db),
        headers: { 'Content-Type': 'application/json' }
    })
    .then(res => res.json())
    .then(data => console.log('Sinkronisasi XAMPP:', data.pesan))
    .catch(err => {
        console.warn("Sinkronisasi server XAMPP tertunda, data aman di lokal:", err && err.message ? err.message : err);
    });
}

// FUNGSI: Khusus untuk siswa mengirim hasil ujian (Aman dari tabrakan data)
function syncHasilSiswaToCloud(dataHasilSiswa) {
    const url = 'api.php?action=simpan_hasil_siswa';
    
    fetch(url, {
        method: 'POST',
        body: JSON.stringify(dataHasilSiswa),
        headers: { 'Content-Type': 'application/json' }
    })
    .then(res => res.json())
    .then(res => {
        console.log("Hasil ujian berhasil dikirim ke Server:", res);
    })
    .catch(err => {
        console.warn("Kirim hasil ujian ke Server tertunda, data aman di lokal:", err && err.message ? err.message : err);
    });
}

// FUNGSI LOAD DATABASE (Offline-first / Semi-online XAMPP)
async function loadDB() {
    // LANGKAH 1: Ambil data dari localStorage SECARA SINKRONUS (INSTAN) untuk load cepat
    const localData = localStorage.getItem(DB_KEY);
    if (localData) {
        try {
            let parsed = JSON.parse(localData);
            db = { ...db, ...parsed };
        } catch(e) {
            console.warn("Gagal membaca cache lokal:", e);
        }
    }
    
    // Tampilkan UI awal menggunakan data lokal terlebih dahulu
    updateGatewayInfo();
    updateLoginMethodUI();
    refreshLoginDropdowns();

    // LANGKAH 2: Ambil data terbaru dari file database.json di XAMPP via api.php
    const url = 'api.php?action=ambil_data';
    
    const isFirstTime = !localData || (Array.isArray(db.groups) && db.groups.length === 0);
    
    if (isFirstTime) {
        Swal.fire({
            title: 'Menghubungkan Server...',
            text: 'Mengunduh data ujian dari XAMPP...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
    }

    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);
        
        const response = await fetch(url, { signal: controller.signal });
        clearTimeout(timeoutId);
        
        if (response.ok) {
            const result = await response.json();
            if (result.status === 'sukses' && result.data && typeof result.data === 'object' && result.data.settings) {
                db = { ...db, ...result.data };
                // Simpan ke cache lokal
                localStorage.setItem(DB_KEY, JSON.stringify(db));
                
                updateGatewayInfo();
                updateLoginMethodUI();
                refreshLoginDropdowns();
            }
        }
        if (isFirstTime) Swal.close();
    } catch (error) {
        console.warn("Sinkronisasi data dari Server tidak dapat diakses, beralih ke penyimpanan lokal:", error && error.message ? error.message : error);
        if (isFirstTime) {
            Swal.fire('Error Koneksi', 'Gagal menghubungi server XAMPP. Pastikan IP dan Jaringan terhubung.', 'error');
        }
    }
}

"""
    new_content = content[:start_idx] + new_code + content[end_idx:]
    with open("index.html", "w", encoding="utf-8") as f:
        f.write(new_content)
    print("Successfully patched index.html")
