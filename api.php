<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Mengatasi preflight request dari fetch API
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$file_db = 'data/database.json';

// Cek apakah file database ada, jika tidak, buat struktur kosong
if (!file_exists($file_db)) {
    // Struktur JSON awal jika file belum ada
    $initial_data = [
        "settings" => [
            "schoolName" => "Computer Based Test",
            "token" => "",
            "tokenRequired" => false,
            "loginMethod" => "dropdown",
            "adminUser" => "admin",
            "adminPass" => "admin123"
        ],
        "groups" => [],
        "students" => [],
        "subjects" => [],
        "tests" => [],
        "questions" => [],
        "examSessions" => [],
        "results" => []
    ];
    file_put_contents($file_db, json_encode($initial_data, JSON_PRETTY_PRINT));
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'ambil_data') {
    // Membaca seluruh data JSON
    $json_data = file_get_contents($file_db);
    echo json_encode([
        "status" => "sukses",
        "data" => json_decode($json_data, true)
    ]);
} 
elseif ($action == 'simpan_data') {
    // Menerima data utuh dari frontend
    $input = file_get_contents('php://input');
    $data_terima = json_decode($input, true);
    
    if ($data_terima) {
        // Tulis (timpa) seluruh database.json
        if (file_put_contents($file_db, json_encode($data_terima, JSON_PRETTY_PRINT))) {
            echo json_encode(["status" => "sukses", "pesan" => "Database berhasil diperbarui di server"]);
        } else {
            echo json_encode(["status" => "error", "pesan" => "Gagal menulis ke database.json, periksa permission file"]);
        }
    } else {
        echo json_encode(["status" => "error", "pesan" => "Data yang dikirim kosong atau format JSON salah"]);
    }
}
elseif ($action == 'simpan_hasil_siswa') {
    // Digunakan khusus untuk siswa mensubmit hasil ujian agar tidak bentrok dengan perubahan Admin
    $input = file_get_contents('php://input');
    $hasilUjian = json_decode($input, true);

    if ($hasilUjian) {
        $db = json_decode(file_get_contents($file_db), true);
        
        // Pastikan array results ada
        if (!isset($db['results'])) {
            $db['results'] = [];
        }

        // Cek apakah siswa sudah submit hasil ujian ini sebelumnya (replace/update)
        $ditemukan = false;
        foreach ($db['results'] as $index => $res) {
            if ($res['studentId'] == $hasilUjian['studentId'] && $res['testId'] == $hasilUjian['testId']) {
                $db['results'][$index] = $hasilUjian; // timpa hasil lama
                $ditemukan = true;
                break;
            }
        }
        
        if (!$ditemukan) {
            $db['results'][] = $hasilUjian;
        }

        if (file_put_contents($file_db, json_encode($db, JSON_PRETTY_PRINT))) {
            echo json_encode(["status" => "sukses", "pesan" => "Hasil ujian siswa berhasil diamankan"]);
        } else {
            echo json_encode(["status" => "error", "pesan" => "Gagal menyimpan hasil ujian"]);
        }
    } else {
        echo json_encode(["status" => "error", "pesan" => "Data hasil ujian tidak valid"]);
    }
}
else {
    echo json_encode(["status" => "error", "pesan" => "Action tidak ditemukan"]);
}
?>
