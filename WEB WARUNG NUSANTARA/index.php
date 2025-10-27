<?php
require_once 'config/config.php';

// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Proses aksi keranjang (tambah, hapus, proses pesanan) dan update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case 'add_to_cart':
            $menu_id = $_POST['menu_id'];
            $rasa = $_POST['rasa'] ?? null;
            $item_key = $menu_id . '_' . ($rasa ?? 'null');

            // Ambil data menu dari DB
            $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
            $stmt->execute([$menu_id]);
            $menu = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($menu) {
                if (isset($_SESSION['cart'][$item_key])) {
                    $_SESSION['cart'][$item_key]['kuantitas']++;
                } else {
                    $_SESSION['cart'][$item_key] = [
                        'menu_id' => $menu['id'],
                        'nama' => $menu['nama'],
                        'harga' => $menu['harga'],
                        'kuantitas' => 1,
                        'rasa' => $rasa
                    ];
                }
            }
            // Kembali ke halaman utama untuk mencegah resubmission
            header('Location: index.php');
            exit;

        case 'remove_from_cart':
            $item_id = $_POST['item_id'];
            if (isset($_SESSION['cart'][$item_id])) {
                unset($_SESSION['cart'][$item_id]);
            }
            // Kembali ke halaman utama
            header('Location: index.php');
            exit;

        case 'process_order':
            // Logika dari process_order.php
            if (!empty($_SESSION['cart'])) {
                $nama_pelanggan = $_POST['nama_pelanggan'];
                $cart_items = $_SESSION['cart'];
                $total_harga = 0;

                foreach ($cart_items as $item) {
                    $total_harga += $item['harga'] * $item['kuantitas'];
                }

                try {
                    $pdo->beginTransaction();

                    // 1. Insert ke tabel orders
                    $stmt = $pdo->prepare("INSERT INTO orders (nama_pelanggan, total_harga, status) VALUES (?, ?, 'masuk')");
                    $stmt->execute([$nama_pelanggan, $total_harga]);
                    $order_id = $pdo->lastInsertId();

                    // 2. Insert ke tabel order_items
                    $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, menu_id, kuantitas, rasa_pilihan, harga_saat_pesanan) VALUES (?, ?, ?, ?, ?)");
                    foreach ($cart_items as $item) {
                        $stmt_item->execute([
                            $order_id,
                            $item['menu_id'],
                            $item['kuantitas'],
                            $item['rasa'],
                            $item['harga']
                        ]);
                    }

                    $pdo->commit();

                    // Kosongkan keranjang
                    unset($_SESSION['cart']);

                    // Redirect ke halaman utama dengan status sukses
                    header('Location: index.php?status=success');
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    // Dalam aplikasi nyata, mungkin redirect dengan pesan error
                    die("Gagal memproses pesanan: " . $e->getMessage());
                }
            }
            // Jika keranjang kosong, kembali ke index
            header('Location: index.php');
            exit;
        
        // Logika dari process_order_status.php
        case 'update_status':
            // CATATAN: Logika ini seharusnya dilindungi dan hanya dapat diakses oleh admin.
            // Dalam aplikasi nyata, tambahkan pengecekan session/login admin di sini.
            // if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) { ... redirect ... }
            
            $order_id = $_POST['order_id'];
            $new_status = $_POST['new_status'];

            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);

            // Redirect kembali ke halaman sebelumnya (biasanya halaman admin)
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
    }
}

// Ambil semua menu dari database
 $stmt = $pdo->query("SELECT * FROM menus ORDER BY kategori, nama");
 $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data keranjang dari session
 $cart = $_SESSION['cart'] ?? [];
 $total_harga = 0;
foreach ($cart as $item) {
    $total_harga += $item['harga'] * $item['kuantitas'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warung Nusantara - Nikmati Kelezatan Masakan Indonesia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <header class="glass shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <h1 class="text-2xl md:text-3xl font-bold flex items-center">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center mr-3 shadow-md">
                    <i class="fas fa-utensils text-white"></i>
                </div>
                <span class="text-gradient">Warung Nusantara</span>
            </h1>
            <a href="login.php" class="bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white px-5 py-2.5 rounded-full font-medium transition-all duration-300 transform hover:scale-105 shadow-md">
                <i class="fas fa-user-shield mr-2"></i>Login Admin
            </a>
        </div>
    </header>

    <!-- Hero Banner -->
    <section class="hero-banner h-screen flex items-center justify-center text-white relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-r from-blue-900/70 to-indigo-800/70"></div>
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="max-w-4xl mx-auto">
                <h2 class="text-4xl md:text-6xl font-bold mb-6 fade-in-up">Nikmati Kelezatan Masakan Nusantara</h2>
                <p class="text-xl md:text-2xl mb-10 max-w-3xl mx-auto leading-relaxed fade-in-up" style="animation-delay: 0.2s">
                    Temukan cita rasa autentik Indonesia dalam setiap hidangan kami. Dari makanan tradisional hingga minuman segar, semua disajikan dengan cinta.
                </p>
                <div class="flex flex-col sm:flex-row gap-5 justify-center fade-in-up" style="animation-delay: 0.4s">
                    <a href="#menu" class="btn-primary text-white font-semibold py-4 px-8 rounded-full shadow-lg">
                        <i class="fas fa-book-open mr-2"></i>Lihat Menu
                    </a>
                    <a href="#about" class="bg-transparent border-2 border-white hover:bg-white hover:text-blue-600 text-white font-semibold py-4 px-8 rounded-full transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-info-circle mr-2"></i>Tentang Kami
                    </a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                <path fill="#f0f9ff" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,133.3C960,128,1056,96,1152,90.7C1248,85,1344,107,1392,117.3L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            </svg>
        </div>
    </section>

    <!-- Featured Section -->
    <section class="py-20 relative">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h3 class="text-4xl font-bold text-gray-800 mb-4">Mengapa Memilih Warung Nusantara?</h3>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">Kami berkomitmen untuk memberikan pengalaman kuliner terbaik dengan sentuhan kehangatan khas Indonesia</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                <div class="text-center group">
                    <div class="bg-gradient-to-br from-blue-100 to-indigo-100 w-28 h-28 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:shadow-xl transition-all duration-300 float-animation">
                        <i class="fas fa-leaf text-blue-600 text-4xl"></i>
                    </div>
                    <h4 class="text-2xl font-semibold mb-3 text-gray-800">Bahan Segar</h4>
                    <p class="text-gray-600 leading-relaxed">Kami menggunakan bahan-bahan segar pilihan untuk memastikan kualitas terbaik dalam setiap hidangan</p>
                </div>
                <div class="text-center group">
                    <div class="bg-gradient-to-br from-blue-100 to-indigo-100 w-28 h-28 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:shadow-xl transition-all duration-300 float-animation" style="animation-delay: 0.2s">
                        <i class="fas fa-award text-blue-600 text-4xl"></i>
                    </div>
                    <h4 class="text-2xl font-semibold mb-3 text-gray-800">Resep Autentik</h4>
                    <p class="text-gray-600 leading-relaxed">Resep turun temurun yang dipertahankan keasliannya untuk memberikan cita rasa yang otentik</p>
                </div>
                <div class="text-center group">
                    <div class="bg-gradient-to-br from-blue-100 to-indigo-100 w-28 h-28 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:shadow-xl transition-all duration-300 float-animation" style="animation-delay: 0.4s">
                        <i class="fas fa-heart text-blue-600 text-4xl"></i>
                    </div>
                    <h4 class="text-2xl font-semibold mb-3 text-gray-800">Dibuat dengan Cinta</h4>
                    <p class="text-gray-600 leading-relaxed">Setiap hidangan disiapkan dengan penuh perhatian dan kehangatan untuk pelanggan kami</p>
                </div>
            </div>
        </div>
    </section>

    <div class="container mx-auto px-4 py-16 flex flex-col lg:flex-row gap-10">
        <!-- Bagian Menu -->
        <main class="flex-1" id="menu">
            <div class="flex justify-center mb-10">
                <div class="glass rounded-full shadow-lg p-1 inline-flex">
                    <button class="tab-btn px-8 py-3 rounded-full font-semibold transition-all duration-300 bg-gradient-to-r from-blue-500 to-indigo-600 text-white" onclick="window.location.href='index.php?kategori=makanan'">
                        <i class="fas fa-hamburger mr-2"></i>Makanan
                    </button>
                    <button class="tab-btn px-8 py-3 rounded-full font-semibold transition-all duration-300 text-gray-600 hover:bg-gray-100" onclick="window.location.href='index.php?kategori=minuman'">
                        <i class="fas fa-coffee mr-2"></i>Minuman
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php
                $filter_kategori = $_GET['kategori'] ?? 'makanan';
                foreach ($menus as $menu) {
                    if ($menu['kategori'] === $filter_kategori) {
                        $gambar_path = $menu['gambar'] ? 'uploads/' . htmlspecialchars($menu['gambar']) : 'https://picsum.photos/seed/' . $menu['id'] . '/400/300.jpg';
                ?>
                        <div class="glass rounded-2xl shadow-xl overflow-hidden card-hover">
                            <div class="relative overflow-hidden h-56">
                                <img src="<?= $gambar_path ?>" alt="<?= htmlspecialchars($menu['nama']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                <div class="absolute top-4 right-4 bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-3 py-1 rounded-full text-sm font-semibold shadow-lg">
                                    <?= ucfirst($menu['kategori']) ?>
                                </div>
                            </div>
                            <div class="p-6">
                                <h3 class="font-bold text-xl mb-2 text-gray-800"><?= htmlspecialchars($menu['nama']) ?></h3>
                                <p class="text-gray-600 mb-4 line-clamp-2"><?= htmlspecialchars($menu['deskripsi']) ?></p>
                                <div class="flex items-center justify-between mb-4">
                                    <p class="text-2xl font-bold text-gradient">Rp <?= number_format($menu['harga'], 0, ',', '.') ?></p>
                                    <div class="flex items-center text-yellow-500">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star-half-alt"></i>
                                    </div>
                                </div>
                                
                                <form action="index.php" method="POST" class="space-y-4">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="menu_id" value="<?= $menu['id'] ?>">
                                    
                                    <?php if ($menu['kategori'] === 'makanan'): ?>
                                    <select name="rasa" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                        <option value="normal">Normal</option>
                                        <option value="pedas">Pedas</option>
                                        <option value="extra-pedas">Extra Pedas</option>
                                    </select>
                                    <?php endif; ?>

                                    <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg font-medium">
                                        <i class="fas fa-cart-plus mr-2"></i>Tambah ke Keranjang
                                    </button>
                                </form>
                            </div>
                        </div>
                <?php
                    }
                }
                ?>
            </div>
        </main>

        <!-- Bagian Keranjang -->
        <aside class="lg:w-96">
            <div class="glass rounded-2xl shadow-xl p-6 sticky top-24">
                <h2 class="text-2xl font-bold mb-6 flex items-center">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center mr-3">
                        <i class="fas fa-shopping-cart text-white"></i>
                    </div>
                    Keranjang Belanja
                </h2>
                
                <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span>Pesanan berhasil dibuat! Terima kasih telah berbelanja.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($cart)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-shopping-basket text-7xl text-gray-300 mb-6"></i>
                        <p class="text-gray-500 text-lg">Keranjang masih kosong.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4 mb-6 max-h-80 overflow-y-auto pr-2">
                        <?php foreach ($cart as $id => $item): ?>
                            <div class="bg-gray-50 p-4 rounded-xl hover:bg-gray-100 transition-colors duration-200">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($item['nama']) ?></h4>
                                        <p class="text-sm text-gray-600 mt-1"><?= $item['kuantitas'] ?>x <?= $item['rasa'] ? "({$item['rasa']})" : '' ?> - Rp <?= number_format($item['harga'], 0, ',', '.') ?></p>
                                    </div>
                                    <form action="index.php" method="POST" class="ml-2">
                                        <input type="hidden" name="action" value="remove_from_cart">
                                        <input type="hidden" name="item_id" value="<?= $id ?>">
                                        <button type="submit" class="text-red-500 hover:text-red-700 transition-colors duration-200 p-2">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="border-t pt-6">
                        <div class="flex justify-between items-center mb-6">
                            <span class="text-lg font-semibold text-gray-700">Total:</span>
                            <span class="text-3xl font-bold text-gradient">Rp <?= number_format($total_harga, 0, ',', '.') ?></span>
                        </div>
                        
                        <!-- Form untuk memproses pesanan -->
                        <form action="index.php" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="process_order">
                            <div>
                                <label for="customer-name" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-user mr-1 text-blue-500"></i>Nama Pelanggan
                                </label>
                                <input type="text" id="customer-name" name="nama_pelanggan" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan nama Anda">
                            </div>
                            <button type="submit" class="w-full btn-primary text-white py-3 rounded-lg font-medium">
                                <i class="fas fa-check-circle mr-2"></i>Pesan Sekarang
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <!-- About Section -->
    <section id="about" class="py-20 mt-16">
        <div class="container mx-auto px-4">
            <div class="flex flex-col lg:flex-row items-center gap-12">
                <div class="lg:w-1/2">
                    <div class="relative">
                        <img src="img/profile.jpg" alt="Warung Nusantara" class="rounded-2xl shadow-2xl">
                        <div class="absolute -bottom-6 -right-6 bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-6 rounded-2xl shadow-xl">
                            <p class="text-3xl font-bold">15+ Tahun</p>
                            <p class="text-lg">Pengalaman</p>
                        </div>
                    </div>
                </div>
                <div class="lg:w-1/2">
                    <h3 class="text-4xl font-bold text-gray-800 mb-6">Tentang Warung Nusantara</h3>
                    <p class="text-lg text-gray-600 mb-6 leading-relaxed">Warung Nusantara didirikan pada tahun 2010 dengan misi untuk melestarikan dan memperkenalkan kekayaan kuliner Indonesia kepada generasi muda. Kami percaya bahwa makanan adalah bagian penting dari budaya, dan melalui setiap hidangan, kami menceritakan kisah Indonesia.</p>
                    <p class="text-lg text-gray-600 mb-8 leading-relaxed">Dengan bahan-bahan lokal berkualitas dan resep tradisional yang diwariskan dari generasi ke generasi, kami berkomitmen untuk memberikan pengalaman kuliner yang autentik dan tak terlupakan.</p>
                    <div class="grid grid-cols-3 gap-6">
                        <div class="text-center bg-white p-6 rounded-xl shadow-lg">
                            <p class="text-4xl font-bold text-gradient mb-2">50+</p>
                            <p class="text-gray-600">Menu Variasi</p>
                        </div>
                        <div class="text-center bg-white p-6 rounded-xl shadow-lg">
                            <p class="text-4xl font-bold text-gradient mb-2">1000+</p>
                            <p class="text-gray-600">Pelanggan Puas</p>
                        </div>
                        <div class="text-center bg-white p-6 rounded-xl shadow-lg">
                            <p class="text-4xl font-bold text-gradient mb-2">5★</p>
                            <p class="text-gray-600">Rating</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonial Section -->
    <section class="py-20 bg-gradient-to-br from-blue-50 to-indigo-100">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h3 class="text-4xl font-bold text-gray-800 mb-4">Apa Kata Pelanggan Kami</h3>
                <p class="text-xl text-gray-600">Kepuasan pelanggan adalah prioritas utama kami</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white p-8 rounded-2xl shadow-xl card-hover">
                    <div class="flex items-center mb-6">
                        <img src="https://picsum.photos/seed/person1/100/100.jpg" alt="Pelanggan" class="w-16 h-16 rounded-full mr-4 border-4 border-blue-100">
                        <div>
                            <h4 class="font-semibold text-lg">Andi Pratama</h4>
                            <div class="flex text-yellow-500">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-600 italic text-lg">"Makanannya sangat enak dan autentik! Pelayanan juga ramah. Recommended!"</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-xl card-hover">
                    <div class="flex items-center mb-6">
                        <img src="https://picsum.photos/seed/person2/100/100.jpg" alt="Pelanggan" class="w-16 h-16 rounded-full mr-4 border-4 border-blue-100">
                        <div>
                            <h4 class="font-semibold text-lg">Siti Nurhaliza</h4>
                            <div class="flex text-yellow-500">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-600 italic text-lg">"Saya sudah menjadi pelanggan setia selama 3 tahun. Kualitas selalu konsisten!"</p>
                </div>
                <div class="bg-white p-8 rounded-2xl shadow-xl card-hover">
                    <div class="flex items-center mb-6">
                        <img src="img/profile.jpg" alt="Pelanggan" class="w-16 h-16 rounded-full mr-4 border-4 border-blue-100">
                        <div>
                            <h4 class="font-semibold text-lg">Angga Nugraha</h4>
                            <div class="flex text-yellow-500">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-600 italic text-lg">"Tempat yang nyaman untuk makan bersama keluarga. Harga juga terjangkau."</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between">
                <div class="mb-8 md:mb-0">
                    <h3 class="text-2xl font-bold mb-4 flex items-center">
                        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-utensils text-white"></i>
                        </div>
                        Warung Nusantara
                    </h3>
                    <p class="text-blue-100 text-lg">Nikmati kelezatan masakan Indonesia autentik</p>
                </div>
                <div class="mb-8 md:mb-0">
                    <h4 class="text-xl font-semibold mb-4">Kontak Kami</h4>
                    <p class="text-blue-100 mb-3 text-lg"><i class="fas fa-map-marker-alt mr-2"></i>Jl. Nusantara No. 123, Jakarta</p>
                    <p class="text-blue-100 mb-3 text-lg"><i class="fas fa-phone mr-2"></i>+62 812-3456-7890</p>
                    <p class="text-blue-100 text-lg"><i class="fas fa-envelope mr-2"></i>info@warungnusantara.com</p>
                </div>
                <div>
                    <h4 class="text-xl font-semibold mb-4">Ikuti Kami</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="bg-white/20 hover:bg-white/30 text-white w-12 h-12 rounded-full flex items-center justify-center transition-all duration-300 transform hover:scale-110">
                            <i class="fab fa-facebook-f text-xl"></i>
                        </a>
                        <a href="#" class="bg-white/20 hover:bg-white/30 text-white w-12 h-12 rounded-full flex items-center justify-center transition-all duration-300 transform hover:scale-110">
                            <i class="fab fa-instagram text-xl"></i>
                        </a>
                        <a href="#" class="bg-white/20 hover:bg-white/30 text-white w-12 h-12 rounded-full flex items-center justify-center transition-all duration-300 transform hover:scale-110">
                            <i class="fab fa-twitter text-xl"></i>
                        </a>
                        <a href="#" class="bg-white/20 hover:bg-white/30 text-white w-12 h-12 rounded-full flex items-center justify-center transition-all duration-300 transform hover:scale-110">
                            <i class="fab fa-whatsapp text-xl"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="border-t border-blue-400 mt-8 pt-8 text-center">
                <p class="text-blue-100 text-lg">&copy; 2023 Warung Nusantara. Hak Cipta Dilindungi.</p>
            </div>
        </div>
    </footer>
</body>
</html>