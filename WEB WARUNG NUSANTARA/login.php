<?php
require_once 'config/config.php';

 $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Di produksi, gunakan password_verify() untuk password yang di-hash
    if ($user && $password === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Username atau password salah!';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Warung Nusantara</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body class="bg-pattern min-h-screen flex items-center justify-center">
    <div class="w-full max-w-md px-4">
        <!-- Logo/Brand -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full shadow-lg mb-4 float-animation">
                <i class="fas fa-utensils text-white text-3xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800">Warung Nusantara</h1>
            <p class="text-gray-600 mt-1">Portal Administrator</p>
        </div>
        
        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 p-6">
                <h2 class="text-2xl font-bold text-white text-center flex items-center justify-center">
                    <i class="fas fa-user-shield mr-2"></i>
                    Login Admin
                </h2>
            </div>
            
            <div class="p-8">
                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <span><?= $error ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST" class="space-y-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user mr-1 text-blue-500"></i>
                            Username
                        </label>
                        <div class="relative">
                            <input type="text" id="username" name="username" required 
                                class="block w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" 
                                placeholder="Masukkan username">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock mr-1 text-blue-500"></i>
                            Password
                        </label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required 
                                class="block w-full px-4 py-3 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" 
                                placeholder="Masukkan password">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-700">
                                Ingat saya
                            </label>
                        </div>
                        <div class="text-sm">
                            <a href="#" class="font-medium text-blue-600 hover:text-blue-500">
                                Lupa password?
                            </a>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 px-4 rounded-lg font-medium hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:-translate-y-0.5">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Masuk
                        </button>
                    </div>
                </form>
                
                <div class="mt-6">
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-center text-sm">
                            <span class="px-2 bg-white text-gray-500">Informasi Login</span>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <p class="text-xs text-gray-500">
                            Gunakan: <span class="font-semibold">admin</span> / <span class="font-semibold">admin123</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-8 text-center">
            <a href="index.php" class="text-blue-600 hover:text-blue-500 font-medium flex items-center justify-center">
                <i class="fas fa-arrow-left mr-2"></i>
                Kembali ke Halaman Utama
            </a>
        </div>
    </div>
</body>
</html>