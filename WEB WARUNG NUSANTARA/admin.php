<?php
require_once 'config/config.php';

// Cek apakah user sudah login dan adalah admin
if (!is_admin_logged_in()) {
    header('Location: login.php');
    exit;
}

// Proses perubahan status pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];

    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// Proses tambah menu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_menu') {
    require_once 'process_add_menu.php';
}

// Proses edit menu (integrated)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_menu') {
    // Ambil data dari form
    $menu_id = $_POST['menu_id'] ?? '';
    $nama = $_POST['nama'] ?? '';
    $kategori = $_POST['kategori'] ?? '';
    $harga = $_POST['harga'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';

    // Validasi input
    if (empty($menu_id) || empty($nama) || empty($kategori) || empty($harga)) {
        $_SESSION['error'] = 'Semua field wajib diisi kecuali deskripsi dan gambar!';
        header('Location: admin.php?page=edit-menu&edit_id=' . $menu_id);
        exit;
    }

    // Validasi harga harus angka
    if (!is_numeric($harga) || $harga <= 0) {
        $_SESSION['error'] = 'Harga harus berupa angka positif!';
        header('Location: admin.php?page=edit-menu&edit_id=' . $menu_id);
        exit;
    }

    try {
        // Ambil data menu lama untuk mendapatkan nama gambar
        $stmt = $pdo->prepare("SELECT gambar FROM menus WHERE id = ?");
        $stmt->execute([$menu_id]);
        $menu_lama = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$menu_lama) {
            $_SESSION['error'] = 'Menu tidak ditemukan!';
            header('Location: admin.php?page=kelola-menu');
            exit;
        }
        
        $gambar_baru = null;
        $upload_error = '';
        
        // Proses upload gambar jika ada
        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['gambar'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_error = $file['error'];
            
            // Validasi file
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_extensions)) {
                $upload_error = 'Hanya file gambar (JPG, JPEG, PNG, GIF, WEBP) yang diperbolehkan!';
            } elseif ($file_size > 2 * 1024 * 1024) { // Max 2MB
                $upload_error = 'Ukuran file maksimal 2MB!';
            } else {
                // Generate nama file unik
                $new_file_name = 'menu_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = 'uploads/' . $new_file_name;
                
                // Buat folder uploads jika belum ada
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }
                
                // Upload file
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $gambar_baru = $new_file_name;
                    
                    // Hapus gambar lama jika ada
                    if ($menu_lama['gambar'] && file_exists('uploads/' . $menu_lama['gambar'])) {
                        unlink('uploads/' . $menu_lama['gambar']);
                    }
                } else {
                    $upload_error = 'Gagal mengupload gambar!';
                }
            }
        }
        
        // Jika ada error upload, set pesan error dan redirect
        if (!empty($upload_error)) {
            $_SESSION['error'] = $upload_error;
            header('Location: admin.php?page=edit-menu&edit_id=' . $menu_id);
            exit;
        }
        
        // Update menu di database
        if ($gambar_baru) {
            // Update dengan gambar baru
            $stmt = $pdo->prepare("
                UPDATE menus 
                SET nama = ?, kategori = ?, harga = ?, deskripsi = ?, gambar = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$nama, $kategori, $harga, $deskripsi, $gambar_baru, $menu_id]);
        } else {
            // Update tanpa mengubah gambar
            $stmt = $pdo->prepare("
                UPDATE menus 
                SET nama = ?, kategori = ?, harga = ?, deskripsi = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$nama, $kategori, $harga, $deskripsi, $menu_id]);
        }
        
        // Set pesan sukses
        $_SESSION['success'] = 'Menu berhasil diperbarui!';
        
        // Redirect ke halaman kelola menu
        header('Location: admin.php?page=kelola-menu');
        exit;
        
    } catch (PDOException $e) {
        // Jika terjadi error database
        $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
        header('Location: admin.php?page=edit-menu&edit_id=' . $menu_id);
        exit;
    } catch (Exception $e) {
        // Jika terjadi error lain
        $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
        header('Location: admin.php?page=edit-menu&edit_id=' . $menu_id);
        exit;
    }
}

// Proses hapus menu (integrated)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_menu') {
    // Ambil ID menu dari form
    $menu_id = $_POST['menu_id'] ?? '';

    // Validasi ID
    if (empty($menu_id) || !is_numeric($menu_id)) {
        $_SESSION['error'] = 'ID menu tidak valid!';
        header('Location: admin.php?page=kelola-menu');
        exit;
    }

    try {
        // Mulai transaction
        $pdo->beginTransaction();
        
        // Cek apakah menu ada
        $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
        $stmt->execute([$menu_id]);
        $menu = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$menu) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Menu tidak ditemukan!';
            header('Location: admin.php?page=kelola-menu');
            exit;
        }
        
        // Cek apakah menu ada dalam pesanan yang masih aktif
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM order_items oi 
            JOIN orders o ON oi.menu_id = ? 
            WHERE o.status = 'masuk'
        ");
        $stmt->execute([$menu_id]);
        $pesanan_aktif = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($pesanan_aktif > 0) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Tidak dapat menghapus menu yang ada dalam pesanan aktif!';
            header('Location: admin.php?page=kelola-menu');
            exit;
        }
        
        // Hapus dari order_items (pesanan yang sudah selesai)
        $stmt = $pdo->prepare("DELETE FROM order_items WHERE menu_id = ?");
        $stmt->execute([$menu_id]);
        
        // Hapus menu dari database
        $stmt = $pdo->prepare("DELETE FROM menus WHERE id = ?");
        $stmt->execute([$menu_id]);
        
        // Hapus file gambar jika ada
        if ($menu['gambar'] && file_exists('uploads/' . $menu['gambar'])) {
            unlink('uploads/' . $menu['gambar']);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Set pesan sukses
        $_SESSION['success'] = 'Menu "' . htmlspecialchars($menu['nama']) . '" berhasil dihapus!';
        
        // Redirect ke halaman kelola menu
        header('Location: admin.php?page=kelola-menu');
        exit;
        
    } catch (PDOException $e) {
        // Rollback transaction jika terjadi error
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $_SESSION['error'] = 'Terjadi kesalahan database: ' . $e->getMessage();
        header('Location: admin.php?page=kelola-menu');
        exit;
    } catch (Exception $e) {
        // Rollback transaction jika terjadi error
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
        header('Location: admin.php?page=kelola-menu');
        exit;
    }
}

// Ambil data pesanan
 $incoming_orders_stmt = $pdo->query("
    SELECT o.*, oi.menu_id, oi.kuantitas, oi.rasa_pilihan, m.nama as nama_menu 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN menus m ON oi.menu_id = m.id
    WHERE o.status = 'masuk'
    ORDER BY o.timestamp DESC
");
 $incoming_orders_raw = $incoming_orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Kelompokkan pesanan berdasarkan order_id
 $incoming_orders = [];
foreach ($incoming_orders_raw as $item) {
    if (!isset($incoming_orders[$item['id']])) {
        $incoming_orders[$item['id']] = [
            'id' => $item['id'],
            'nama_pelanggan' => $item['nama_pelanggan'],
            'total_harga' => $item['total_harga'],
            'timestamp' => $item['timestamp'],
            'items' => []
        ];
    }
    $incoming_orders[$item['id']]['items'][] = $item;
}

// Ambil riwayat pesanan
 $history_orders_stmt = $pdo->query("
    SELECT o.*, oi.menu_id, oi.kuantitas, oi.rasa_pilihan, m.nama as nama_menu 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN menus m ON oi.menu_id = m.id
    WHERE o.status = 'selesai'
    ORDER BY o.timestamp DESC
");
 $history_orders_raw = $history_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
 $history_orders = [];
foreach ($history_orders_raw as $item) {
    if (!isset($history_orders[$item['id']])) {
        $history_orders[$item['id']] = [
            'id' => $item['id'],
            'nama_pelanggan' => $item['nama_pelanggan'],
            'total_harga' => $item['total_harga'],
            'timestamp' => $item['timestamp'],
            'items' => []
        ];
    }
    $history_orders[$item['id']]['items'][] = $item;
}

// Ambil semua menu untuk halaman kelola menu
 $menus_for_management = $pdo->query("SELECT * FROM menus ORDER BY kategori, nama")->fetchAll(PDO::FETCH_ASSOC);

// Ambil data menu untuk diedit
 $menu_to_edit = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $menu_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

 $page = $_GET['page'] ?? 'tambah-menu';
?>
<!-- Tampilkan pesan sukses atau error -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="mx-6 mt-4 mb-0">
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg animate-slide-in" role="alert">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?= $_SESSION['success'] ?></span>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="mx-6 mt-4 mb-0">
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg animate-slide-in" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span><?= $_SESSION['error'] ?></span>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Warung Nusantara</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-gradient-to-b from-blue-600 to-blue-800 text-white shadow-xl z-10">
            <div class="p-6 text-2xl font-bold border-b border-blue-500 flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-utensils text-white"></i>
                </div>
                <span>Warung Nusantara</span>
            </div>
            <nav class="mt-6">
                <a href="?page=tambah-menu" class="admin-nav flex items-center py-3 px-6 hover:bg-blue-700 transition-all duration-200 <?= $page == 'tambah-menu' ? 'bg-blue-700 border-l-4 border-white' : '' ?>">
                    <i class="fas fa-plus-circle mr-3 text-blue-200"></i>
                    <span>Tambah Menu</span>
                </a>
                <a href="?page=kelola-menu" class="admin-nav flex items-center py-3 px-6 hover:bg-blue-700 transition-all duration-200 <?= $page == 'kelola-menu' ? 'bg-blue-700 border-l-4 border-white' : '' ?>">
                    <i class="fas fa-edit mr-3 text-blue-200"></i>
                    <span>Kelola Menu</span>
                </a>
                <a href="?page=pesanan-masuk" class="admin-nav flex items-center py-3 px-6 hover:bg-blue-700 transition-all duration-200 <?= $page == 'pesanan-masuk' ? 'bg-blue-700 border-l-4 border-white' : '' ?>">
                    <i class="fas fa-inbox mr-3 text-blue-200"></i>
                    <span>Pesanan Masuk</span>
                    <?php if (!empty($incoming_orders)): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-1"><?= count($incoming_orders) ?></span>
                    <?php endif; ?>
                </a>
                <a href="?page=riwayat-pesanan" class="admin-nav flex items-center py-3 px-6 hover:bg-blue-700 transition-all duration-200 <?= $page == 'riwayat-pesanan' ? 'bg-blue-700 border-l-4 border-white' : '' ?>">
                    <i class="fas fa-history mr-3 text-blue-200"></i>
                    <span>Riwayat Pesanan</span>
                </a>
                <a href="logout.php" class="flex items-center py-3 px-6 hover:bg-red-600 transition-all duration-200 mt-8 border-t border-blue-500">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="glass shadow-sm border-b border-gray-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-semibold text-gray-800">
                        <?php 
                        if ($page == 'tambah-menu') echo 'Tambah Menu Baru';
                        elseif ($page == 'kelola-menu') echo 'Kelola Menu';
                        elseif ($page == 'edit-menu') echo 'Edit Menu';
                        elseif ($page == 'pesanan-masuk') echo 'Pesanan Masuk';
                        elseif ($page == 'riwayat-pesanan') echo 'Riwayat Pesanan';
                        ?>
                    </h1>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">
                            <i class="far fa-clock mr-1"></i>
                            <?= date('d M Y, H:i') ?>
                        </span>
                        <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-md">
                            <i class="fas fa-user text-sm"></i>
                        </div>
                    </div>
                </div>
            </header>

            <div class="p-6">
                <!-- Halaman Tambah Menu -->
                <?php if ($page == 'tambah-menu'): ?>
                    <div class="max-w-2xl mx-auto">
                        <div class="glass rounded-xl shadow-lg overflow-hidden animate-slide-in">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4">
                                <h2 class="text-xl font-semibold text-white flex items-center">
                                    <i class="fas fa-plus-circle mr-2"></i>
                                    Form Tambah Menu
                                </h2>
                            </div>
                            <div class="p-6">
                                <form action="admin.php?page=tambah-menu" method="POST" enctype="multipart/form-data" class="space-y-5">
                                    <input type="hidden" name="action" value="add_menu">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div>
                                            <label for="menu-nama" class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="fas fa-tag mr-1 text-blue-600"></i>Nama Menu
                                            </label>
                                            <input type="text" id="menu-nama" name="nama" required 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div>
                                            <label for="menu-kategori" class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="fas fa-list mr-1 text-blue-600"></i>Kategori
                                            </label>
                                            <select id="menu-kategori" name="kategori" required 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                                <option value="makanan">Makanan</option>
                                                <option value="minuman">Minuman</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="menu-harga" class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-money-bill-wave mr-1 text-blue-600"></i>Harga
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-2.5 text-gray-500">Rp</span>
                                            <input type="number" id="menu-harga" name="harga" required 
                                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="menu-deskripsi" class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-align-left mr-1 text-blue-600"></i>Deskripsi
                                        </label>
                                        <textarea id="menu-deskripsi" name="deskripsi" rows="3" 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"></textarea>
                                    </div>
                                    
                                    <div>
                                        <label for="menu-gambar" class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-image mr-1 text-blue-600"></i>Gambar
                                        </label>
                                        <div class="relative">
                                            <input type="file" id="menu-gambar" name="gambar" accept="image/*" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        </div>
                                    </div>
                                    
                                    <div class="pt-4">
                                        <button type="submit" class="w-full btn-primary text-white py-3 px-4 rounded-lg font-semibold">
                                            <i class="fas fa-save mr-2"></i>Simpan Menu
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Halaman Kelola Menu -->
                <?php if ($page == 'kelola-menu'): ?>
                    <div class="glass rounded-xl shadow-lg p-6 animate-slide-in">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-edit mr-2 text-blue-600"></i>
                                Daftar Menu
                            </h2>
                            <a href="?page=tambah-menu" class="btn-primary text-white px-4 py-2 rounded-lg font-medium">
                                <i class="fas fa-plus mr-2"></i>Tambah Menu Baru
                            </a>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white rounded-lg overflow-hidden table-hover">
                                <thead class="bg-gradient-to-r from-blue-500 to-blue-600 text-white">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Gambar</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Nama</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Kategori</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Harga</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($menus_for_management as $menu): ?>
                                        <tr class="animate-fade-in">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <img src="<?= $menu['gambar'] ? 'uploads/' . htmlspecialchars($menu['gambar']) : 'https://picsum.photos/seed/' . $menu['id'] . '/100/100.jpg' ?>" 
                                                     alt="<?= htmlspecialchars($menu['nama']) ?>" 
                                                     class="h-12 w-12 rounded-lg object-cover">
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($menu['nama']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    <?= $menu['kategori'] === 'makanan' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                                                    <?= ucfirst($menu['kategori']) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">Rp <?= number_format($menu['harga'], 0, ',', '.') ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="?page=edit-menu&edit_id=<?= $menu['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <button onclick="confirmDelete(<?= $menu['id'] ?>, '<?= htmlspecialchars($menu['nama']) ?>')" 
                                                        class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Halaman Edit Menu -->
                <?php if ($page == 'edit-menu' && $menu_to_edit): ?>
                    <div class="max-w-2xl mx-auto">
                        <div class="glass rounded-xl shadow-lg overflow-hidden animate-slide-in">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4">
                                <h2 class="text-xl font-semibold text-white flex items-center">
                                    <i class="fas fa-edit mr-2"></i>
                                    Edit Menu: <?= htmlspecialchars($menu_to_edit['nama']) ?>
                                </h2>
                            </div>
                            <div class="p-6">
                                <form action="admin.php?page=edit-menu" method="POST" enctype="multipart/form-data" class="space-y-5">
                                    <input type="hidden" name="action" value="edit_menu">
                                    <input type="hidden" name="menu_id" value="<?= $menu_to_edit['id'] ?>">
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div>
                                            <label for="menu-nama" class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="fas fa-tag mr-1 text-blue-600"></i>Nama Menu
                                            </label>
                                            <input type="text" id="menu-nama" name="nama" required 
                                                value="<?= htmlspecialchars($menu_to_edit['nama']) ?>"
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                        
                                        <div>
                                            <label for="menu-kategori" class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="fas fa-list mr-1 text-blue-600"></i>Kategori
                                            </label>
                                            <select id="menu-kategori" name="kategori" required 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                                <option value="makanan" <?= $menu_to_edit['kategori'] === 'makanan' ? 'selected' : '' ?>>Makanan</option>
                                                <option value="minuman" <?= $menu_to_edit['kategori'] === 'minuman' ? 'selected' : '' ?>>Minuman</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="menu-harga" class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-money-bill-wave mr-1 text-blue-600"></i>Harga
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-2.5 text-gray-500">Rp</span>
                                            <input type="number" id="menu-harga" name="harga" required 
                                                value="<?= $menu_to_edit['harga'] ?>"
                                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="menu-deskripsi" class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-align-left mr-1 text-blue-600"></i>Deskripsi
                                        </label>
                                        <textarea id="menu-deskripsi" name="deskripsi" rows="3" 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"><?= htmlspecialchars($menu_to_edit['deskripsi']) ?></textarea>
                                    </div>
                                    
                                    <div>
                                        <label for="menu-gambar" class="block text-sm font-medium text-gray-700 mb-2">
                                            <i class="fas fa-image mr-1 text-blue-600"></i>Gambar
                                        </label>
                                        <div class="mb-3">
                                            <img src="<?= $menu_to_edit['gambar'] ? 'uploads/' . htmlspecialchars($menu_to_edit['gambar']) : 'https://picsum.photos/seed/' . $menu_to_edit['id'] . '/300/200.jpg' ?>" 
                                                 alt="<?= htmlspecialchars($menu_to_edit['nama']) ?>" 
                                                 class="h-32 w-full object-cover rounded-lg">
                                        </div>
                                        <div class="relative">
                                            <input type="file" id="menu-gambar" name="gambar" accept="image/*" 
                                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                            <p class="text-xs text-gray-500 mt-1">Kosongkan jika tidak ingin mengubah gambar</p>
                                        </div>
                                    </div>
                                    
                                    <div class="pt-4 flex gap-3">
                                        <button type="submit" class="flex-1 btn-primary text-white py-3 px-4 rounded-lg font-semibold">
                                            <i class="fas fa-save mr-2"></i>Simpan Perubahan
                                        </button>
                                        <a href="?page=kelola-menu" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 py-3 px-4 rounded-lg font-semibold text-center transition-all duration-200">
                                            <i class="fas fa-times mr-2"></i>Batal
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Halaman Pesanan Masuk -->
                <?php if ($page == 'pesanan-masuk'): ?>
                    <div class="space-y-4">
                        <?php if (empty($incoming_orders)): ?>
                            <div class="glass rounded-xl shadow-md p-8 text-center animate-slide-in">
                                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 text-lg">Tidak ada pesanan masuk.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($incoming_orders as $order): ?>
                                <div class="glass rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-all duration-300 animate-slide-in">
                                    <div class="bg-gradient-to-r from-yellow-500 to-orange-500 p-4">
                                        <div class="flex justify-between items-center">
                                            <div class="text-white">
                                                <h3 class="text-xl font-bold flex items-center">
                                                    <i class="fas fa-user mr-2"></i>
                                                    <?= htmlspecialchars($order['nama_pelanggan']) ?>
                                                </h3>
                                                <p class="text-sm opacity-90 mt-1">
                                                    <i class="far fa-clock mr-1"></i>
                                                    <?= date('d M Y, H:i', strtotime($order['timestamp'])) ?>
                                                </p>
                                            </div>
                                            <span class="bg-white text-orange-600 text-sm font-bold px-3 py-1 rounded-full">
                                                <i class="fas fa-hourglass-half mr-1"></i>Menunggu
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-6">
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
                                                <i class="fas fa-receipt mr-2 text-blue-600"></i>
                                                Detail Pesanan:
                                            </h4>
                                            <div class="bg-gray-50 rounded-lg p-4">
                                                <ul class="space-y-2">
                                                    <?php foreach ($order['items'] as $item): ?>
                                                        <li class="flex justify-between items-center">
                                                            <span class="text-gray-700">
                                                                <span class="font-medium"><?= $item['kuantitas'] ?>x</span> 
                                                                <?= htmlspecialchars($item['nama_menu']) ?>
                                                                <?php if ($item['rasa_pilihan']): ?>
                                                                    <span class="text-sm text-gray-500">({$item['rasa_pilihan']})</span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                                            <div>
                                                <span class="text-gray-600">Total:</span>
                                                <span class="text-2xl font-bold text-gradient ml-2">
                                                    Rp <?= number_format($order['total_harga'], 0, ',', '.') ?>
                                                </span>
                                            </div>
                                            <form action="admin.php?page=pesanan-masuk" method="POST">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <input type="hidden" name="new_status" value="selesai">
                                                <button type="submit" class="btn-primary text-white px-6 py-2 rounded-lg font-medium">
                                                    <i class="fas fa-check-circle mr-2"></i>Selesaikan
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Halaman Riwayat Pesanan -->
                <?php if ($page == 'riwayat-pesanan'): ?>
                    <div class="space-y-4">
                        <?php if (empty($history_orders)): ?>
                            <div class="glass rounded-xl shadow-md p-8 text-center animate-slide-in">
                                <i class="fas fa-history text-6xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500 text-lg">Belum ada riwayat pesanan.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($history_orders as $order): ?>
                                <div class="glass rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-all duration-300 animate-slide-in">
                                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-4">
                                        <div class="flex justify-between items-center">
                                            <div class="text-white">
                                                <h3 class="text-xl font-bold flex items-center">
                                                    <i class="fas fa-user mr-2"></i>
                                                    <?= htmlspecialchars($order['nama_pelanggan']) ?>
                                                </h3>
                                                <p class="text-sm opacity-90 mt-1">
                                                    <i class="far fa-clock mr-1"></i>
                                                    <?= date('d M Y, H:i', strtotime($order['timestamp'])) ?>
                                                </p>
                                            </div>
                                            <span class="bg-white text-blue-600 text-sm font-bold px-3 py-1 rounded-full">
                                                <i class="fas fa-check-circle mr-1"></i>Selesai
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-6">
                                        <div class="mb-4">
                                            <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
                                                <i class="fas fa-receipt mr-2 text-blue-600"></i>
                                                Detail Pesanan:
                                            </h4>
                                            <div class="bg-gray-50 rounded-lg p-4">
                                                <ul class="space-y-2">
                                                    <?php foreach ($order['items'] as $item): ?>
                                                        <li class="flex justify-between items-center">
                                                            <span class="text-gray-700">
                                                                <span class="font-medium"><?= $item['kuantitas'] ?>x</span> 
                                                                <?= htmlspecialchars($item['nama_menu']) ?>
                                                                <?php if ($item['rasa_pilihan']): ?>
                                                                    <span class="text-sm text-gray-500">({$item['rasa_pilihan']})</span>
                                                                <?php endif; ?>
                                                            </span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="flex justify-end pt-4 border-t border-gray-200">
                                            <div>
                                                <span class="text-gray-600">Total:</span>
                                                <span class="text-2xl font-bold text-gradient ml-2">
                                                    Rp <?= number_format($order['total_harga'], 0, ',', '.') ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="bg-gradient-to-r from-red-500 to-red-600 p-4 rounded-t-lg">
                <h3 class="text-xl font-semibold text-white flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Konfirmasi Hapus
                </h3>
            </div>
            <div class="p-6">
                <p class="text-gray-700 mb-6">Apakah Anda yakin ingin menghapus menu "<span id="menuName" class="font-semibold"></span>"? Tindakan ini tidak dapat dibatalkan.</p>
                <form id="deleteForm" method="POST" action="admin.php?page=kelola-menu">
                    <input type="hidden" name="action" value="delete_menu">
                    <input type="hidden" id="menuId" name="menu_id">
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 btn-danger text-white py-2 px-4 rounded-lg font-medium">
                            <i class="fas fa-trash mr-2"></i>Hapus
                        </button>
                        <button type="button" onclick="closeDeleteModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 py-2 px-4 rounded-lg font-medium transition-all duration-200">
                            <i class="fas fa-times mr-2"></i>Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/admin.js"></script>

</body>
</html>