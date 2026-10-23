<?php

require_once "../config.php";
require_once "../helpers/response.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
       
        if (isset($_GET['id'])) {
            $id = $_GET['id'];

            $q = "SELECT 
                m.id,
                m.nama, 
                m.nim, 
                j.nama_jurusan AS jurusan 
                FROM mahasiswa m 
                LEFT JOIN jurusan j 
                ON m.jurusan_id = j.id
                WHERE m.id = $id";

            $r = mysqli_query($koneksi, $q);

            if (!$r) {
                sendResponse(false, 'query gagal: ' . mysqli_error($koneksi), null, 500);
            }

            $data = mysqli_fetch_assoc($r); 

            if (!$data) {
                sendResponse(false, 'mahasiswa tidak ditemukan', null, 404);
            }

            sendResponse(true, "berhasil", $data, 200);
        }

        // api/mahasiswa.php?search=...
        if (isset($_GET['search'])) {
            $search = mysqli_real_escape_string($koneksi, $_GET['search']);

            $q = "SELECT
                    m.id,
                    m.nama,
                    m.nim,
                    j.nama_jurusan AS jurusan
                FROM mahasiswa m
                LEFT JOIN jurusan j ON m.jurusan_id = j.id
                WHERE m.nama LIKE '%$search%'
                   OR m.nim LIKE '%$search%'
                ORDER BY m.id DESC";

            $r = mysqli_query($koneksi, $q);

            if (!$r) {
                sendResponse(false, 'query gagal: ' . mysqli_error($koneksi), null, 500);
            }

            $data = [];
            while ($row = mysqli_fetch_assoc($r)) {
                $data[] = $row;
            }

            sendResponse(true, "berhasil", $data, 200);
        }

        // api/mahasiswa.php?page=&limit=  (atau tanpa parameter = get all)
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 1;

        $offset = ($page - 1) * $limit;

        $q = "SELECT
                m.id,
                m.nama,
                m.nim,
                j.nama_jurusan AS jurusan
            FROM mahasiswa m
            LEFT JOIN jurusan j ON m.jurusan_id = j.id
            ORDER BY m.id DESC
            LIMIT $limit OFFSET $offset";

        $r = mysqli_query($koneksi, $q);

        if (!$r) {
            sendResponse(false, 'query gagal: ' . mysqli_error($koneksi), null, 500);
        }

        $data = [];
        while ($row = mysqli_fetch_assoc($r)) {
            $data[] = $row;
        }

        sendResponse(true, "berhasil", $data, 200);
        break;

    case 'POST':
        
        $raw = file_get_contents('php://input');
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

        $nama = mysqli_real_escape_string($koneksi, trim($input['nama']));
        $nim = mysqli_real_escape_string($koneksi, trim($input['nim']));
        $jurusan_id = (int) $input['jurusan_id'];

       
        $q = "SELECT id FROM jurusan WHERE id = $jurusan_id";
        $r = mysqli_query($koneksi, $q);
        if (!$r) {
            sendResponse(false, 'query gagal: ' . mysqli_error($koneksi), null, 500);
        }
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'jurusan tidak ditemukan', null, 404);
        }

        
        $q = "SELECT id FROM mahasiswa WHERE nim = '$nim'";
        $r = mysqli_query($koneksi, $q);
        if (!$r) {
            sendResponse(false, 'query gagal: ' . mysqli_error($koneksi), null, 500);
        }
        if (mysqli_num_rows($r) > 0) {
            sendResponse(false, 'NIM sudah terdaftar', null, 409);
        }

        $q = "INSERT INTO mahasiswa (nama, nim, jurusan_id, created_at) 
              VALUES ('$nama', '$nim', $jurusan_id, CURDATE())";

        $r = mysqli_query($koneksi, $q);
        if (!$r) {
            sendResponse(false, 'query gagal: ' . mysqli_error($koneksi), null, 500);
        }

        $newId = mysqli_insert_id($koneksi);
        sendResponse(true, 'data tersimpan', ['id' => $newId], 201);
        break;

    case 'PUT':
      
        if (!isset($_GET['id']) || $_GET['id'] === '') {
            sendResponse(false, 'parameter id wajib', null, 400);
        }
        $id = (int) $_GET['id'];

        
        $raw = file_get_contents('php://input');
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

        $nama = mysqli_real_escape_string($koneksi, trim($input['nama']));
        $nim = mysqli_real_escape_string($koneksi, trim($input['nim']));
        $jurusan_id = (int) $input['jurusan_id'];

      
        $q = "SELECT id FROM jurusan WHERE id = $jurusan_id";
        $r = mysqli_query($koneksi, $q);
        if (!$r) {
            sendResponse(false, 'query gagal: ' . mysqli_error($koneksi), null, 500);
        }
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'jurusan tidak ditemukan', null, 404);
        }

       
        $q = "SELECT id FROM mahasiswa WHERE id = $id";
        $r = mysqli_query($koneksi, $q);
        if (!$r) {
            sendResponse(false, 'query gagal: ' . mysqli_error($koneksi), null, 500);
        }
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'mahasiswa tidak ditemukan', null, 404);
        }

        
        $q = "UPDATE mahasiswa 
              SET nama = '$nama', nim = '$nim', jurusan_id = $jurusan_id 
              WHERE id = $id";

        $r = mysqli_query($koneksi, $q);
        if (!$r) {
            sendResponse(false, 'query gagal: ' . mysqli_error($koneksi), null, 500);
        }

      
        $affected = mysqli_affected_rows($koneksi);

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

        // rekord
        $q = "SELECT id FROM mahasiswa WHERE id = $id";
        $r = mysqli_query($koneksi, $q);
        if (!$r) {
            sendResponse(false, 'query gagal: ' . mysqli_error($koneksi), null, 500);
        }
        if (mysqli_num_rows($r) === 0) {
            sendResponse(false, 'mahasiswa tidak ditemukan', null, 404);
        }

        // delete
        $q = "DELETE FROM mahasiswa WHERE id = $id";
        $r = mysqli_query($koneksi, $q);
        if (!$r) {
            sendResponse(false, 'query gagal: ' . mysqli_error($koneksi), null, 500);
        }

        // hasol
        sendResponse(true, 'data berhasil dihapus', ['id' => $id], 200);
        break;

    default:
        sendResponse(false, 'method tidak didukung', null, 405);
        break;
}