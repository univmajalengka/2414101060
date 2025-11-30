<?php
$servername = "localhost";
$username = "username";
$password = "password";
$dbname = "database_name";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

function hitungDiskon($totalBelanja) {
    $nominalDiskon = 0;

    // Logika Diskon (If-Elseif-Else)
    if ($totalBelanja >= 100000) {
        $nominalDiskon = $totalBelanja * 0.10;
        
    } elseif ($totalBelanja >= 50000) {
        $nominalDiskon = $totalBelanja * 0.05;
        
    } else {
        $nominalDiskon = 0;
    }

    return $nominalDiskon;
}

$totalBelanja = 120000;

$diskon = hitungDiskon($totalBelanja);

$totalBayar = $totalBelanja - $diskon;

echo "<h3>Rincian Pembayaran</h3>";
echo "Total Belanja : Rp. " . number_format($totalBelanja, 0, ',', '.') . "<br>";
echo "Diskon        : Rp. " . number_format($diskon, 0, ',', '.') . "<br>";
echo "<hr style='width: 250px; text-align:left; margin-left:0'>";
echo "<b>Total Bayar   : Rp. " . number_format($totalBayar, 0, ',', '.') . "</b>";

?>