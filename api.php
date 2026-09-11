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

// ============================================================
// KONFIGURASI DATABASE MYSQL / XAMPP phpMyAdmin (MURNI SQL)
// ============================================================
$db_host = '127.0.0.1'; // Host MySQL XAMPP (localhost / 127.0.0.1)
$db_user = 'root';      // Username phpMyAdmin (default XAMPP: root)
$db_pass = '';          // Password phpMyAdmin (default XAMPP: kosong)
$db_name = 'db_cbt';    // Nama Database MySQL
$db_port = 3306;        // Port MySQL (default: 3306)

$pdo = null;

// Koneksi ke MySQL Server (XAMPP / phpMyAdmin)
try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 2
    ]);

    // Otomatis buat database db_cbt jika belum ada di phpMyAdmin
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db_name`");

    // Inisialisasi Tabel MySQL jika belum dibuat
    initMysqlTables($pdo);
} catch (Exception $e) {
    // KETENTUAN KHUSUS: TANPA FALLBACK KE FILE JSON
    // Jika server MySQL belum aktif / mati, kirim respons db_error secara tegas
    echo json_encode([
        "status" => "db_error",
        "error_code" => "MYSQL_OFFLINE",
        "pesan" => "Server Database MySQL (phpMyAdmin/XAMPP) belum aktif atau belum dinyalakan! Silakan buka XAMPP Control Panel lalu klik 'Start' pada modul MySQL.",
        "detail" => $e->getMessage()
    ]);
    exit();
}

// Fungsi Inisialisasi Struktur Tabel MySQL
function initMysqlTables($pdo) {
    // 1. Settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `schoolName` VARCHAR(255) NOT NULL DEFAULT 'MI Terpadu Harapan Bangsa',
        `yayasan` VARCHAR(255) DEFAULT '',
        `akreditasi` VARCHAR(50) DEFAULT 'A',
        `alamat` TEXT,
        `kelurahan` VARCHAR(100) DEFAULT '',
        `kecamatan` VARCHAR(100) DEFAULT '',
        `kabupaten` VARCHAR(100) DEFAULT '',
        `provinsi` VARCHAR(100) DEFAULT '',
        `kodepos` VARCHAR(20) DEFAULT '',
        `email` VARCHAR(100) DEFAULT '',
        `website` VARCHAR(100) DEFAULT '',
        `ujian` VARCHAR(255) DEFAULT 'Asesmen Sumatif Akhir Tahun (ASAT)',
        `tp` VARCHAR(50) DEFAULT '2025/2026',
        `kepala` VARCHAR(150) DEFAULT '',
        `nip` VARCHAR(50) DEFAULT '',
        `pengawas` VARCHAR(150) DEFAULT '',
        `nipPengawas` VARCHAR(50) DEFAULT '',
        `proktor` VARCHAR(150) DEFAULT '',
        `nipProktor` VARCHAR(50) DEFAULT '',
        `logo` LONGTEXT,
        `adminUser` VARCHAR(100) NOT NULL DEFAULT 'admin',
        `adminPass` VARCHAR(255) NOT NULL DEFAULT 'admin123',
        `loginMethod` VARCHAR(50) DEFAULT 'dropdown',
        `scriptUrl` TEXT,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Groups / Kelas
    $pdo->exec("CREATE TABLE IF NOT EXISTS `groups` (
        `id` VARCHAR(50) PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `level` VARCHAR(50) DEFAULT '',
        `studentCount` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Students / Siswa
    $pdo->exec("CREATE TABLE IF NOT EXISTS `students` (
        `id` VARCHAR(50) PRIMARY KEY,
        `nis` VARCHAR(50) DEFAULT '',
        `nisn` VARCHAR(50) DEFAULT '',
        `name` VARCHAR(150) NOT NULL,
        `groupId` VARCHAR(50) NOT NULL,
        `groupName` VARCHAR(100) DEFAULT '',
        `username` VARCHAR(100) NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `gender` VARCHAR(20) DEFAULT 'L',
        `room` VARCHAR(50) DEFAULT 'Ruang 01',
        `session` VARCHAR(50) DEFAULT 'Sesi 1',
        `status` VARCHAR(50) DEFAULT 'Aktif',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Subjects / Mata Pelajaran
    $pdo->exec("CREATE TABLE IF NOT EXISTS `subjects` (
        `id` VARCHAR(50) PRIMARY KEY,
        `code` VARCHAR(50) DEFAULT '',
        `name` VARCHAR(150) NOT NULL,
        `questionCount` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. Tests / Bank Soal
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tests` (
        `id` VARCHAR(50) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `subjectId` VARCHAR(50) DEFAULT '',
        `subjectName` VARCHAR(150) DEFAULT '',
        `groupId` VARCHAR(50) DEFAULT '',
        `groupName` VARCHAR(100) DEFAULT '',
        `date` VARCHAR(50) DEFAULT '',
        `startTime` VARCHAR(20) DEFAULT '',
        `duration` INT DEFAULT 60,
        `totalQuestions` INT DEFAULT 0,
        `token` VARCHAR(20) DEFAULT '',
        `status` VARCHAR(50) DEFAULT 'Non-Aktif',
        `questions` LONGTEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. Results / Hasil Ujian
    $pdo->exec("CREATE TABLE IF NOT EXISTS `results` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `studentId` VARCHAR(50) NOT NULL,
        `studentName` VARCHAR(150) DEFAULT '',
        `testId` VARCHAR(50) NOT NULL,
        `testName` VARCHAR(255) DEFAULT '',
        `subjectName` VARCHAR(150) DEFAULT '',
        `groupName` VARCHAR(100) DEFAULT '',
        `score` FLOAT DEFAULT 0,
        `answers` LONGTEXT,
        `submittedAt` VARCHAR(100) DEFAULT '',
        `status` VARCHAR(50) DEFAULT 'Selesai',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_student_test` (`studentId`, `testId`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 7. CBT Storage (Penyimpanan State Utama SQL)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cbt_storage` (
        `key_name` VARCHAR(100) PRIMARY KEY,
        `data_content` LONGTEXT NOT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action == 'ambil_data') {
    try {
        $stmt = $pdo->prepare("SELECT `data_content` FROM `cbt_storage` WHERE `key_name` = 'full_db'");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row && !empty($row['data_content'])) {
            $db_data = json_decode($row['data_content'], true);
            echo json_encode([
                "status" => "sukses",
                "driver" => "mysql",
                "pesan" => "Data berhasil dimuat dari MySQL (phpMyAdmin)",
                "data" => $db_data,
                "server_time" => round(microtime(true) * 1000)
            ]);
            exit();
        } else {
            // Inisialisasi struktur awal di MySQL jika cbt_storage masih kosong
            $initial_db = [
                "settings" => [
                    "schoolName" => "MI Terpadu Harapan Bangsa",
                    "yayasan" => "Yayasan Pendidikan Islam",
                    "akreditasi" => "A",
                    "alamat" => "Jl. Pendidikan No. 1 RT. 01 / RW. 01",
                    "kelurahan" => "Karangkandri",
                    "kecamatan" => "Kesugihan",
                    "kabupaten" => "Cilacap",
                    "provinsi" => "Jawa Tengah",
                    "kodepos" => "53274",
                    "email" => "miharapanbangsa@info.com",
                    "website" => "miharapanbangsa.sch.id",
                    "ujian" => "Asesmen Sumatif Akhir Tahun (ASAT)",
                    "tp" => "2025/2026",
                    "kepala" => "Ahmad Dahlan, S.Pd.I",
                    "nip" => "198001012005011003",
                    "adminUser" => "admin",
                    "adminPass" => "admin123",
                    "loginMethod" => "dropdown"
                ],
                "groups" => [],
                "students" => [],
                "subjects" => [],
                "tests" => [],
                "results" => []
            ];
            
            $json_init = json_encode($initial_db, JSON_UNESCAPED_UNICODE);
            $stmt_init = $pdo->prepare("INSERT INTO `cbt_storage` (`key_name`, `data_content`) VALUES ('full_db', :content) ON DUPLICATE KEY UPDATE `data_content` = :content2");
            $stmt_init->execute(['content' => $json_init, 'content2' => $json_init]);

            echo json_encode([
                "status" => "sukses",
                "driver" => "mysql",
                "pesan" => "Database MySQL baru berhasil diinisialisasi",
                "data" => $initial_db,
                "server_time" => round(microtime(true) * 1000)
            ]);
            exit();
        }
    } catch (Exception $e) {
        echo json_encode([
            "status" => "db_error",
            "pesan" => "Gagal membaca database MySQL: " . $e->getMessage()
        ]);
        exit();
    }
} 
elseif ($action == 'server_time') {
    echo json_encode([
        "status" => "sukses",
        "server_time" => round(microtime(true) * 1000)
    ]);
    exit();
}
elseif ($action == 'simpan_data') {
    $input = file_get_contents('php://input');
    $data_terima = json_decode($input, true);
    
    if (!$data_terima) {
        echo json_encode(["status" => "error", "pesan" => "Data kosong atau format JSON tidak valid"]);
        exit();
    }

    try {
        // 1. Simpan ke cbt_storage MySQL
        $stmt = $pdo->prepare("INSERT INTO `cbt_storage` (`key_name`, `data_content`) VALUES ('full_db', :content) ON DUPLICATE KEY UPDATE `data_content` = :content2");
        $json_str = json_encode($data_terima, JSON_UNESCAPED_UNICODE);
        $stmt->execute(['content' => $json_str, 'content2' => $json_str]);

        // 2. Sinkronkan ke tabel-tabel relasional MySQL
        syncRelationalTables($pdo, $data_terima);

        echo json_encode([
            "status" => "sukses",
            "driver" => "mysql",
            "pesan" => "Database berhasil disimpan ke Server MySQL (phpMyAdmin)",
            "server_time" => round(microtime(true) * 1000)
        ]);
        exit();
    } catch (Exception $e) {
        echo json_encode(["status" => "db_error", "pesan" => "Gagal menyimpan ke MySQL: " . $e->getMessage()]);
        exit();
    }
}
elseif ($action == 'reset_database' || $action == 'hapus_semua') {
    try {
        $pdo->exec("TRUNCATE TABLE `cbt_storage`");
        $pdo->exec("TRUNCATE TABLE `groups`");
        $pdo->exec("TRUNCATE TABLE `students`");
        $pdo->exec("TRUNCATE TABLE `subjects`");
        $pdo->exec("TRUNCATE TABLE `tests`");
        $pdo->exec("TRUNCATE TABLE `results`");
        $pdo->exec("TRUNCATE TABLE `settings`");

        echo json_encode([
            "status" => "sukses",
            "driver" => "mysql",
            "pesan" => "Seluruh data di Server MySQL (phpMyAdmin) telah berhasil dihapus bersih!"
        ]);
        exit();
    } catch (Exception $e) {
        echo json_encode([
            "status" => "db_error",
            "pesan" => "Gagal menghapus data di MySQL: " . $e->getMessage()
        ]);
        exit();
    }
}
elseif ($action == 'simpan_hasil_siswa') {
    $input = file_get_contents('php://input');
    $hasilUjian = json_decode($input, true);

    if (!$hasilUjian) {
        echo json_encode(["status" => "error", "pesan" => "Data hasil ujian tidak valid"]);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT `data_content` FROM `cbt_storage` WHERE `key_name` = 'full_db'");
        $stmt->execute();
        $row = $stmt->fetch();
        $current_db = $row ? json_decode($row['data_content'], true) : [];
        if (!isset($current_db['results'])) $current_db['results'] = [];
        
        $stId = isset($hasilUjian['studentNoPeserta']) && $hasilUjian['studentNoPeserta'] !== '' 
            ? strval($hasilUjian['studentNoPeserta']) 
            : (isset($hasilUjian['studentId']) ? strval($hasilUjian['studentId']) : '');
        $testId = isset($hasilUjian['testId']) ? strval($hasilUjian['testId']) : '';

        // Pastikan kedua properti ada pada objek yang disimpan
        if ($stId !== '') {
            $hasilUjian['studentId'] = $stId;
            $hasilUjian['studentNoPeserta'] = $stId;
        }

        $ditemukan = false;
        foreach ($current_db['results'] as $index => $res) {
            $existingStId = isset($res['studentNoPeserta']) && $res['studentNoPeserta'] !== '' 
                ? strval($res['studentNoPeserta']) 
                : (isset($res['studentId']) ? strval($res['studentId']) : '');
            $existingTestId = isset($res['testId']) ? strval($res['testId']) : '';

            if ($stId !== '' && $testId !== '' && $existingStId === $stId && $existingTestId === $testId) {
                $isAdminAction = (isset($hasilUjian['adminAction']) && in_array($hasilUjian['adminAction'], ['reopen', 'reset', 'add_time']))
                    || !empty($hasilUjian['reopened'])
                    || !empty($hasilUjian['resetByAdmin'])
                    || !empty($hasilUjian['timeExtensionId']);

                // Keamanan ekstra: Jika hasil di server sudah 'finished' dan data masuk masih 'progress' dari polling siswa biasa (bukan aksi admin), pertahankan status 'finished'
                if (!$isAdminAction && isset($res['status']) && $res['status'] === 'finished' && isset($hasilUjian['status']) && $hasilUjian['status'] === 'progress') {
                    $hasilUjian['status'] = 'finished';
                    $hasilUjian['remainingSeconds'] = 0;
                    if (isset($res['score'])) $hasilUjian['score'] = $res['score'];
                    if (isset($res['endTime'])) $hasilUjian['endTime'] = $res['endTime'];
                }
                $current_db['results'][$index] = $hasilUjian;
                $ditemukan = true;
                break;
            }
        }
        if (!$ditemukan) $current_db['results'][] = $hasilUjian;
        
        $stmt_save = $pdo->prepare("INSERT INTO `cbt_storage` (`key_name`, `data_content`) VALUES ('full_db', :content) ON DUPLICATE KEY UPDATE `data_content` = :content2");
        $json_str = json_encode($current_db, JSON_UNESCAPED_UNICODE);
        $stmt_save->execute(['content' => $json_str, 'content2' => $json_str]);

        // Lookups if empty
        $stId = isset($hasilUjian['studentId']) ? strval($hasilUjian['studentId']) : (isset($hasilUjian['studentNoPeserta']) ? strval($hasilUjian['studentNoPeserta']) : '');
        $testId = isset($hasilUjian['testId']) ? strval($hasilUjian['testId']) : '';

        $testName = isset($hasilUjian['testName']) ? $hasilUjian['testName'] : '';
        $subjectName = isset($hasilUjian['subjectName']) ? $hasilUjian['subjectName'] : '';
        $groupName = isset($hasilUjian['groupName']) ? $hasilUjian['groupName'] : '';
        $studentName = isset($hasilUjian['studentName']) ? $hasilUjian['studentName'] : '';

        if (($testName === '' || $subjectName === '' || $groupName === '') && isset($current_db['tests']) && is_array($current_db['tests'])) {
            foreach ($current_db['tests'] as $t) {
                if (isset($t['id']) && strval($t['id']) === $testId) {
                    if ($testName === '') $testName = isset($t['name']) ? $t['name'] : '';
                    if ($subjectName === '') $subjectName = isset($t['subjectName']) ? $t['subjectName'] : '';
                    if ($groupName === '') $groupName = isset($t['groupName']) ? $t['groupName'] : '';
                    break;
                }
            }
        }

        if (($studentName === '' || $groupName === '') && isset($current_db['groups']) && is_array($current_db['groups'])) {
            foreach ($current_db['groups'] as $g) {
                if (isset($g['students']) && is_array($g['students'])) {
                    foreach ($g['students'] as $st) {
                        $stKey = isset($st['id']) ? strval($st['id']) : (isset($st['noPeserta']) ? strval($st['noPeserta']) : '');
                        if ($stKey === $stId || (isset($st['noPeserta']) && strval($st['noPeserta']) === $stId)) {
                            if ($studentName === '') $studentName = isset($st['name']) ? $st['name'] : '';
                            if ($groupName === '') $groupName = isset($g['name']) ? $g['name'] : '';
                            break 2;
                        }
                    }
                }
            }
        }

        // Simpan ke tabel results relasional
        $stmt_res = $pdo->prepare("INSERT INTO `results` (`studentId`, `studentName`, `testId`, `testName`, `subjectName`, `groupName`, `score`, `answers`, `submittedAt`, `status`) 
            VALUES (:studentId, :studentName, :testId, :testName, :subjectName, :groupName, :score, :answers, :submittedAt, :status)
            ON DUPLICATE KEY UPDATE `studentName` = :studentName2, `testName` = :testName2, `subjectName` = :subjectName2, `groupName` = :groupName2, `score` = :score2, `answers` = :answers2, `submittedAt` = :submittedAt2, `status` = :status2");
        
        $answers_json = json_encode(isset($hasilUjian['answers']) ? $hasilUjian['answers'] : [], JSON_UNESCAPED_UNICODE);
        $subAt = isset($hasilUjian['submittedAt']) ? $hasilUjian['submittedAt'] : (isset($hasilUjian['submitted']) ? $hasilUjian['submitted'] : date('Y-m-d H:i:s'));

        $stmt_res->execute([
            'studentId' => $stId,
            'studentName' => $studentName,
            'studentName2' => $studentName,
            'testId' => $testId,
            'testName' => $testName,
            'testName2' => $testName,
            'subjectName' => $subjectName,
            'subjectName2' => $subjectName,
            'groupName' => $groupName,
            'groupName2' => $groupName,
            'score' => isset($hasilUjian['score']) ? floatval($hasilUjian['score']) : 0,
            'score2' => isset($hasilUjian['score']) ? floatval($hasilUjian['score']) : 0,
            'answers' => $answers_json,
            'answers2' => $answers_json,
            'submittedAt' => $subAt,
            'submittedAt2' => $subAt,
            'status' => isset($hasilUjian['status']) ? $hasilUjian['status'] : 'Selesai',
            'status2' => isset($hasilUjian['status']) ? $hasilUjian['status'] : 'Selesai'
        ]);

        echo json_encode([
            "status" => "sukses", 
            "driver" => "mysql", 
            "pesan" => "Hasil ujian siswa berhasil dikirim ke MySQL phpMyAdmin",
            "server_time" => round(microtime(true) * 1000)
        ]);
        exit();
    } catch (Exception $e) {
        echo json_encode(["status" => "db_error", "pesan" => "Gagal menyimpan hasil ujian ke MySQL: " . $e->getMessage()]);
        exit();
    }
}
else {
    echo json_encode(["status" => "error", "pesan" => "Action tidak ditemukan"]);
}

// Fungsi bantu sinkronisasi tabel relasional di phpMyAdmin
function syncRelationalTables($pdo, $data) {
    // 1. Settings
    if (isset($data['settings']) && is_array($data['settings'])) {
        $s = $data['settings'];
        $stmt = $pdo->prepare("INSERT INTO `settings` (`id`, `schoolName`, `yayasan`, `akreditasi`, `alamat`, `kelurahan`, `kecamatan`, `kabupaten`, `provinsi`, `kodepos`, `email`, `website`, `ujian`, `tp`, `kepala`, `nip`, `pengawas`, `nipPengawas`, `proktor`, `nipProktor`, `logo`, `adminUser`, `adminPass`, `loginMethod`, `scriptUrl`) 
            VALUES (1, :schoolName, :yayasan, :akreditasi, :alamat, :kelurahan, :kecamatan, :kabupaten, :provinsi, :kodepos, :email, :website, :ujian, :tp, :kepala, :nip, :pengawas, :nipPengawas, :proktor, :nipProktor, :logo, :adminUser, :adminPass, :loginMethod, :scriptUrl)
            ON DUPLICATE KEY UPDATE `schoolName`=:schoolName2, `yayasan`=:yayasan2, `akreditasi`=:akreditasi2, `alamat`=:alamat2, `kelurahan`=:kelurahan2, `kecamatan`=:kecamatan2, `kabupaten`=:kabupaten2, `provinsi`=:provinsi2, `kodepos`=:kodepos2, `email`=:email2, `website`=:website2, `ujian`=:ujian2, `tp`=:tp2, `kepala`=:kepala2, `nip`=:nip2, `pengawas`=:pengawas2, `nipPengawas`=:nipPengawas2, `proktor`=:proktor2, `nipProktor`=:nipProktor2, `logo`=:logo2, `adminUser`=:adminUser2, `adminPass`=:adminPass2, `loginMethod`=:loginMethod2, `scriptUrl`=:scriptUrl2");
        
        $stmt->execute([
            'schoolName' => isset($s['schoolName']) ? $s['schoolName'] : '', 'schoolName2' => isset($s['schoolName']) ? $s['schoolName'] : '',
            'yayasan' => isset($s['yayasan']) ? $s['yayasan'] : '', 'yayasan2' => isset($s['yayasan']) ? $s['yayasan'] : '',
            'akreditasi' => isset($s['akreditasi']) ? $s['akreditasi'] : 'A', 'akreditasi2' => isset($s['akreditasi']) ? $s['akreditasi'] : 'A',
            'alamat' => isset($s['alamat']) ? $s['alamat'] : '', 'alamat2' => isset($s['alamat']) ? $s['alamat'] : '',
            'kelurahan' => isset($s['kelurahan']) ? $s['kelurahan'] : '', 'kelurahan2' => isset($s['kelurahan']) ? $s['kelurahan'] : '',
            'kecamatan' => isset($s['kecamatan']) ? $s['kecamatan'] : '', 'kecamatan2' => isset($s['kecamatan']) ? $s['kecamatan'] : '',
            'kabupaten' => isset($s['kabupaten']) ? $s['kabupaten'] : '', 'kabupaten2' => isset($s['kabupaten']) ? $s['kabupaten'] : '',
            'provinsi' => isset($s['provinsi']) ? $s['provinsi'] : '', 'provinsi2' => isset($s['provinsi']) ? $s['provinsi'] : '',
            'kodepos' => isset($s['kodepos']) ? $s['kodepos'] : '', 'kodepos2' => isset($s['kodepos']) ? $s['kodepos'] : '',
            'email' => isset($s['email']) ? $s['email'] : '', 'email2' => isset($s['email']) ? $s['email'] : '',
            'website' => isset($s['website']) ? $s['website'] : '', 'website2' => isset($s['website']) ? $s['website'] : '',
            'ujian' => isset($s['ujian']) ? $s['ujian'] : '', 'ujian2' => isset($s['ujian']) ? $s['ujian'] : '',
            'tp' => isset($s['tp']) ? $s['tp'] : '', 'tp2' => isset($s['tp']) ? $s['tp'] : '',
            'kepala' => isset($s['kepala']) ? $s['kepala'] : '', 'kepala2' => isset($s['kepala']) ? $s['kepala'] : '',
            'nip' => isset($s['nip']) ? $s['nip'] : '', 'nip2' => isset($s['nip']) ? $s['nip'] : '',
            'pengawas' => isset($s['pengawas']) ? $s['pengawas'] : '', 'pengawas2' => isset($s['pengawas']) ? $s['pengawas'] : '',
            'nipPengawas' => isset($s['nipPengawas']) ? $s['nipPengawas'] : '', 'nipPengawas2' => isset($s['nipPengawas']) ? $s['nipPengawas'] : '',
            'proktor' => isset($s['proktor']) ? $s['proktor'] : '', 'proktor2' => isset($s['proktor']) ? $s['proktor'] : '',
            'nipProktor' => isset($s['nipProktor']) ? $s['nipProktor'] : '', 'nipProktor2' => isset($s['nipProktor']) ? $s['nipProktor'] : '',
            'logo' => isset($s['logo']) ? $s['logo'] : '', 'logo2' => isset($s['logo']) ? $s['logo'] : '',
            'adminUser' => isset($s['adminUser']) ? $s['adminUser'] : 'admin', 'adminUser2' => isset($s['adminUser']) ? $s['adminUser'] : 'admin',
            'adminPass' => isset($s['adminPass']) ? $s['adminPass'] : 'admin123', 'adminPass2' => isset($s['adminPass']) ? $s['adminPass'] : 'admin123',
            'loginMethod' => isset($s['loginMethod']) ? $s['loginMethod'] : 'dropdown', 'loginMethod2' => isset($s['loginMethod']) ? $s['loginMethod'] : 'dropdown',
            'scriptUrl' => isset($s['scriptUrl']) ? $s['scriptUrl'] : '', 'scriptUrl2' => isset($s['scriptUrl']) ? $s['scriptUrl'] : ''
        ]);
    }

    // 2. Groups
    $groupMap = [];
    if (isset($data['groups']) && is_array($data['groups'])) {
        $pdo->exec("TRUNCATE TABLE `groups`");
        $stmt_g = $pdo->prepare("INSERT INTO `groups` (`id`, `name`, `level`, `studentCount`) VALUES (:id, :name, :level, :studentCount)");
        foreach ($data['groups'] as $g) {
            $gId = isset($g['id']) ? strval($g['id']) : uniqid();
            $gName = isset($g['name']) ? $g['name'] : '';
            $groupMap[$gId] = $gName;
            
            $cnt = 0;
            if (isset($g['students']) && is_array($g['students'])) {
                $cnt = count($g['students']);
            } else if (isset($g['studentCount'])) {
                $cnt = intval($g['studentCount']);
            }

            $stmt_g->execute([
                'id' => $gId,
                'name' => $gName,
                'level' => isset($g['level']) ? $g['level'] : '',
                'studentCount' => $cnt
            ]);
        }
    }

    // 3. Students (Ekstrak dari data.students DAN data.groups[].students)
    $allStudents = [];
    if (isset($data['students']) && is_array($data['students'])) {
        foreach ($data['students'] as $st) {
            $stId = isset($st['id']) ? strval($st['id']) : (isset($st['noPeserta']) ? strval($st['noPeserta']) : uniqid());
            $allStudents[$stId] = $st;
        }
    }
    if (isset($data['groups']) && is_array($data['groups'])) {
        foreach ($data['groups'] as $g) {
            $gId = isset($g['id']) ? strval($g['id']) : '';
            $gName = isset($g['name']) ? $g['name'] : '';
            if (isset($g['students']) && is_array($g['students'])) {
                foreach ($g['students'] as $st) {
                    $stId = isset($st['id']) ? strval($st['id']) : (isset($st['noPeserta']) ? strval($st['noPeserta']) : uniqid());
                    if (!isset($st['groupId']) || $st['groupId'] === '') $st['groupId'] = $gId;
                    if (!isset($st['groupName']) || $st['groupName'] === '') $st['groupName'] = $gName;
                    $allStudents[$stId] = $st;
                }
            }
        }
    }

    $studentMap = [];
    if (!empty($allStudents)) {
        $pdo->exec("TRUNCATE TABLE `students`");
        $stmt_st = $pdo->prepare("INSERT INTO `students` (`id`, `nis`, `nisn`, `name`, `groupId`, `groupName`, `username`, `password`, `gender`, `room`, `session`, `status`) 
            VALUES (:id, :nis, :nisn, :name, :groupId, :groupName, :username, :password, :gender, :room, :session, :status)");
        foreach ($allStudents as $stId => $st) {
            $stName = isset($st['name']) ? $st['name'] : '';
            $nis = isset($st['nis']) && $st['nis'] !== '' ? $st['nis'] : (isset($st['noPeserta']) ? $st['noPeserta'] : '');
            $gId = isset($st['groupId']) ? strval($st['groupId']) : '';
            $gName = isset($st['groupName']) && $st['groupName'] !== '' ? $st['groupName'] : (isset($groupMap[$gId]) ? $groupMap[$gId] : (isset($st['group']) ? $st['group'] : ''));

            $studentMap[$stId] = ['name' => $stName, 'groupName' => $gName];

            $stmt_st->execute([
                'id' => $stId,
                'nis' => $nis,
                'nisn' => isset($st['nisn']) ? $st['nisn'] : '',
                'name' => $stName,
                'groupId' => $gId,
                'groupName' => $gName,
                'username' => isset($st['username']) && $st['username'] !== '' ? $st['username'] : (isset($st['noPeserta']) ? $st['noPeserta'] : $nis),
                'password' => isset($st['password']) && $st['password'] !== '' ? $st['password'] : '123456',
                'gender' => isset($st['gender']) ? $st['gender'] : 'L',
                'room' => isset($st['room']) ? $st['room'] : 'Ruang 01',
                'session' => isset($st['session']) ? $st['session'] : 'Sesi 1',
                'status' => isset($st['status']) ? $st['status'] : 'Aktif'
            ]);
        }
    }

    // 4. Subjects
    $subjectMap = [];
    if (isset($data['subjects']) && is_array($data['subjects'])) {
        $pdo->exec("TRUNCATE TABLE `subjects`");
        $stmt_sub = $pdo->prepare("INSERT INTO `subjects` (`id`, `code`, `name`, `questionCount`) VALUES (:id, :code, :name, :questionCount)");
        foreach ($data['subjects'] as $sub) {
            $sId = isset($sub['id']) ? strval($sub['id']) : uniqid();
            $sName = isset($sub['name']) ? $sub['name'] : '';
            $subjectMap[$sId] = $sName;

            $sCode = isset($sub['code']) && $sub['code'] !== '' ? $sub['code'] : (isset($sub['kktp']) ? 'KKTP ' . $sub['kktp'] : (isset($sub['kode']) ? $sub['kode'] : $sId));
            $qCount = 0;
            if (isset($sub['questions']) && is_array($sub['questions'])) {
                $qCount = count($sub['questions']);
            } else if (isset($sub['questionCount'])) {
                $qCount = intval($sub['questionCount']);
            }

            $stmt_sub->execute([
                'id' => $sId,
                'code' => $sCode,
                'name' => $sName,
                'questionCount' => $qCount
            ]);
        }
    }

    // 5. Tests
    $testMap = [];
    if (isset($data['tests']) && is_array($data['tests'])) {
        $pdo->exec("TRUNCATE TABLE `tests`");
        $stmt_t = $pdo->prepare("INSERT INTO `tests` (`id`, `name`, `subjectId`, `subjectName`, `groupId`, `groupName`, `date`, `startTime`, `duration`, `totalQuestions`, `token`, `status`, `questions`) 
            VALUES (:id, :name, :subjectId, :subjectName, :groupId, :groupName, :date, :startTime, :duration, :totalQuestions, :token, :status, :questions)");
        foreach ($data['tests'] as $t) {
            $tId = isset($t['id']) ? strval($t['id']) : uniqid();
            $tName = isset($t['name']) ? $t['name'] : '';
            $sId = isset($t['subjectId']) ? strval($t['subjectId']) : '';
            $sName = isset($t['subjectName']) && $t['subjectName'] !== '' ? $t['subjectName'] : (isset($subjectMap[$sId]) ? $subjectMap[$sId] : '');
            
            $gId = isset($t['groupId']) ? strval($t['groupId']) : (isset($t['targetGroup']) ? strval($t['targetGroup']) : '');
            $gName = isset($t['groupName']) && $t['groupName'] !== '' ? $t['groupName'] : (isset($groupMap[$gId]) ? $groupMap[$gId] : '');

            $testMap[$tId] = ['name' => $tName, 'subjectName' => $sName, 'groupName' => $gName];

            $startTime = isset($t['startTime']) && $t['startTime'] !== '' ? $t['startTime'] : (isset($t['timeStart']) ? $t['timeStart'] : (isset($t['waktuMulai']) ? $t['waktuMulai'] : '08:00'));
            $totQ = 0;
            if (isset($t['questions']) && is_array($t['questions'])) {
                $totQ = count($t['questions']);
            } else if (isset($t['totalQuestions'])) {
                $totQ = intval($t['totalQuestions']);
            }

            $q_json = json_encode(isset($t['questions']) ? $t['questions'] : [], JSON_UNESCAPED_UNICODE);
            $stmt_t->execute([
                'id' => $tId,
                'name' => $tName,
                'subjectId' => $sId,
                'subjectName' => $sName,
                'groupId' => $gId,
                'groupName' => $gName,
                'date' => isset($t['date']) ? $t['date'] : (isset($t['testDate']) ? $t['testDate'] : ''),
                'startTime' => $startTime,
                'duration' => isset($t['duration']) ? intval($t['duration']) : (isset($t['time']) ? intval($t['time']) : 60),
                'totalQuestions' => $totQ,
                'token' => isset($t['token']) ? $t['token'] : '',
                'status' => isset($t['status']) ? $t['status'] : 'Aktif',
                'questions' => $q_json
            ]);
        }
    }

    // 6. Results
    if (isset($data['results']) && is_array($data['results'])) {
        $pdo->exec("TRUNCATE TABLE `results`");
        $stmt_r = $pdo->prepare("INSERT INTO `results` (`studentId`, `studentName`, `testId`, `testName`, `subjectName`, `groupName`, `score`, `answers`, `submittedAt`, `status`) 
            VALUES (:studentId, :studentName, :testId, :testName, :subjectName, :groupName, :score, :answers, :submittedAt, :status)");
        foreach ($data['results'] as $r) {
            $stId = isset($r['studentId']) ? strval($r['studentId']) : (isset($r['studentNoPeserta']) ? strval($r['studentNoPeserta']) : '');
            $stName = isset($r['studentName']) && $r['studentName'] !== '' ? $r['studentName'] : (isset($studentMap[$stId]['name']) ? $studentMap[$stId]['name'] : $stId);

            $tId = isset($r['testId']) ? strval($r['testId']) : '';
            $tName = isset($r['testName']) && $r['testName'] !== '' ? $r['testName'] : (isset($testMap[$tId]['name']) ? $testMap[$tId]['name'] : '');
            $subjName = isset($r['subjectName']) && $r['subjectName'] !== '' ? $r['subjectName'] : (isset($testMap[$tId]['subjectName']) ? $testMap[$tId]['subjectName'] : '');
            $grpName = isset($r['groupName']) && $r['groupName'] !== '' ? $r['groupName'] : (isset($studentMap[$stId]['groupName']) ? $studentMap[$stId]['groupName'] : (isset($testMap[$tId]['groupName']) ? $testMap[$tId]['groupName'] : ''));

            $ans_json = json_encode(isset($r['answers']) ? $r['answers'] : [], JSON_UNESCAPED_UNICODE);
            $stmt_r->execute([
                'studentId' => $stId,
                'studentName' => $stName,
                'testId' => $tId,
                'testName' => $tName,
                'subjectName' => $subjName,
                'groupName' => $grpName,
                'score' => isset($r['score']) ? floatval($r['score']) : 0,
                'answers' => $ans_json,
                'submittedAt' => isset($r['submittedAt']) ? $r['submittedAt'] : (isset($r['submitted']) ? $r['submitted'] : date('Y-m-d H:i:s')),
                'status' => isset($r['status']) ? $r['status'] : 'Selesai'
            ]);
        }
    }
}
?>
