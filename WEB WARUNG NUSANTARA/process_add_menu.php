<?php
// process_add_menu.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_menu') {
    $nama = $_POST['nama'];
    $kategori = $_POST['kategori'];
    $harga = $_POST['harga'];
    $deskripsi = $_POST['deskripsi'];
    $gambar_name = null;

    // Proses upload gambar
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['gambar']['tmp_name'];
        $file_name = basename($_FILES['gambar']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Buat nama file unik untuk menghindari konflik
        $new_file_name = uniqid('menu_', true) . '.' . $file_ext;
        $upload_dir = 'uploads/';
        $dest_path = $upload_dir . $new_file_name;

        if (move_uploaded_file($file_tmp_path, $dest_path)) {
            $gambar_name = $new_file_name;
        }
    }

    // Insert ke database
    $stmt = $pdo->prepare("INSERT INTO menus (nama, kategori, harga, deskripsi, gambar) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$nama, $kategori, $harga, $deskripsi, $gambar_name]);
}

header('Location: admin.php?page=tambah-menu');
exit;