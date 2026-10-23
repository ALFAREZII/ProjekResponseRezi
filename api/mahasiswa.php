<?php

require_once "../config.php";
require_once "../helpers/response.php";

// cors
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // GET by id wow
        if (isset($_GET['id'])) {
            $id = (int) ($_GET['id'] ?? 0);

            $stmt = mysqli_prepare($koneksi, 
                "SELECT m.id, m.nama, m.nim, j.nama_jurusan AS jurusan 
                 FROM mahasiswa m 
                 LEFT JOIN jurusan j ON m.jurusan_id = j.id 
                 WHERE m.id = ?"
            );

            if (!$stmt) {
                error_log(mysqli_error($koneksi));
                sendResponse(false, 'terjadi kesalahan pada server', null, 500);
            }

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);

            if (!$r) {
                error_log(mysqli_error($koneksi));
                sendResponse(false, 'terjadi kesalahan pada server', null, 500);
            }

            $data = mysqli_fetch_assoc($r);
            if (!$data) {
                sendResponse(false, 'mahasiswa tidak ditemukan', null, 404);
            }

            sendResponse(true, "berhasil", $data, 200);
        }

        // GET searchhhhh
        if (isset($_GET['search'])) {
            $search = trim($_GET['search'] ?? '');
            $term = "%" . $search . "%";

            $stmt = mysqli_prepare($koneksi,
                "SELECT m.id, m.nama, m.nim, j.nama_jurusan AS jurusan
                 FROM mahasiswa m
                 LEFT JOIN jurusan j ON m.jurusan_id = j.id
                 WHERE m.nama LIKE ? OR m.nim LIKE ?
                 ORDER BY m.id DESC"
            );

            if (!$stmt) {
                error_log(mysqli_error($koneksi));
                sendResponse(false, 'terjadi kesalahan pada server', null, 500);
            }

            mysqli_stmt_bind_param($stmt, "ss", $term, $term);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);

            if (!$r) {
                error_log(mysqli_error($koneksi));
                sendResponse(false, 'terjadi kesalahan pada server', null, 500);
            }

            $data = [];
            while ($row = mysqli_fetch_assoc($r)) {
                $data[] = $row;
            }

            sendResponse(true, "berhasil", $data, 200);
        }

        // GET pagination / get all????????
        $page  = (int) ($_GET['page'] ?? 1);
        $limit = (int) ($_GET['limit'] ?? 10);

        if ($page < 1)  $page = 1;
        if ($limit < 1) $limit = 1;

        $offset = ($page - 1) * $limit;

        $stmt = mysqli_prepare($koneksi,
            "SELECT m.id, m.nama, m.nim, j.nama_jurusan AS jurusan
             FROM mahasiswa m
             LEFT JOIN jurusan j ON m.jurusan_id = j.id
             ORDER BY m.id DESC
             LIMIT ? OFFSET ?"
        );

        if (!$stmt) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }

        mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);

        if (!$r) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }

        $data = [];
        while ($row = mysqli_fetch_assoc($r)) {
            $data[] = $row;
        }

        sendResponse(true, "berhasil", $data, 200);
        break;

    case 'POST':
        $raw   = file_get_contents('php://input');
        $input = json_decode($raw, true);

        if (
            !is_array($input) ||
            empty(trim($input['nama'] ?? '')) ||
            empty(trim($input['nim'] ?? '')) ||
            !isset($input['jurusan_id']) ||
            $input['jurusan_id'] === '' ||
            $input['jurusan_id'] === null
        ) {
            sendResponse(false, 'field nama, nim, jurusan_id wajib', null, 400);
        }

        $nama       = trim($input['nama']);
        $nim        = trim($input['nim']);
        $jurusan_id = (int) $input['jurusan_id'];

        // Cek jurusan ade dak
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM jurusan WHERE id = ?");
        if (!$stmt) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }
        mysqli_stmt_bind_param($stmt, "i", $jurusan_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'jurusan tidak ditemukan', null, 404);
        }

        // Cek NIM yg lom pakai
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM mahasiswa WHERE nim = ?");
        if (!$stmt) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }
        mysqli_stmt_bind_param($stmt, "s", $nim);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) > 0) {
            sendResponse(false, 'NIM sudah terdaftar', null, 409);
        }

        // INSERTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTTT (pls jgn eror agik)
        $stmt = mysqli_prepare($koneksi, 
            "INSERT INTO mahasiswa (nama, nim, jurusan_id, created_at) VALUES (?, ?, ?, CURDATE())"
        );
        if (!$stmt) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }
        mysqli_stmt_bind_param($stmt, "ssi", $nama, $nim, $jurusan_id);

        if (!mysqli_stmt_execute($stmt)) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }

        $newId = mysqli_insert_id($koneksi);
        sendResponse(true, 'data tersimpan', ['id' => $newId], 201);
        break;

    case 'PUT':
        if (!isset($_GET['id']) || $_GET['id'] === '') {
            sendResponse(false, 'parameter id wajib', null, 400);
        }
        $id = (int) $_GET['id'];

        $raw   = file_get_contents('php://input');
        $input = json_decode($raw, true);

        if (
            !is_array($input) ||
            empty(trim($input['nama'] ?? '')) ||
            empty(trim($input['nim'] ?? '')) ||
            !isset($input['jurusan_id']) ||
            $input['jurusan_id'] === '' ||
            $input['jurusan_id'] === null
        ) {
            sendResponse(false, 'field nama, nim, jurusan_id wajib', null, 400);
        }

        $nama       = trim($input['nama']);
        $nim        = trim($input['nim']);
        $jurusan_id = (int) $input['jurusan_id'];

        // Cek jurusan ade dak?
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM jurusan WHERE id = ?");
        if (!$stmt) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }
        mysqli_stmt_bind_param($stmt, "i", $jurusan_id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'jurusan tidak ditemukan', null, 404);
        }

        // Cek mahasiswaa ngawi
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM mahasiswa WHERE id = ?");
        if (!$stmt) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'mahasiswa tidak ditemukan', null, 404);
        }

        // updateeeeeeeee
        $stmt = mysqli_prepare($koneksi, 
            "UPDATE mahasiswa SET nama = ?, nim = ?, jurusan_id = ? WHERE id = ?"
        );
        if (!$stmt) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }
        mysqli_stmt_bind_param($stmt, "ssii", $nama, $nim, $jurusan_id, $id);

        if (!mysqli_stmt_execute($stmt)) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }

        $affected = mysqli_stmt_affected_rows($stmt);

        if ($affected > 0) {
            sendResponse(true, 'data berhasil diubah', ['id' => $id], 200);
        } else {
            sendResponse(true, 'tidak ada perubahan data', ['id' => $id], 200);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['id']) || $_GET['id'] === '') {
            sendResponse(false, 'parameter id wajib', null, 400);
        }
        $id = (int) $_GET['id'];

        // Cek ada dulu
        $stmt = mysqli_prepare($koneksi, "SELECT id FROM mahasiswa WHERE id = ?");
        if (!$stmt) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'mahasiswa tidak ditemukan', null, 404);
        }

        // deleteeeeeeeee
        $stmt = mysqli_prepare($koneksi, "DELETE FROM mahasiswa WHERE id = ?");
        if (!$stmt) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (!mysqli_stmt_execute($stmt)) {
            error_log(mysqli_error($koneksi));
            sendResponse(false, 'terjadi kesalahan pada server', null, 500);
        }

        sendResponse(true, 'data berhasil dihapus', ['id' => $id], 200);
        break;

    default:
        sendResponse(false, 'method tidak didukung', null, 405);
        break;
}