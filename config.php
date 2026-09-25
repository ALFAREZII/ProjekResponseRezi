<?php
require_once __DIR__ . "/helpers/response.php";

mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$user = "root";
$pass = "";
$db = "ayam2511500046";

$koneksi = @mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    sendResponse(
        false,
        "koneksi database gagal",
        null,
        500
    );
}