<?php
session_start();
require_once 'config/database.php';

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $koneksi->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Login Sukses
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role'] = $user['role'];
                
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Username atau Password salah!";
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem.";
        }
    } else {
        $error = "Silakan isi semua kolom.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PTPN I Regional 3</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="bg-image">
        <div class="bg-overlay">
            
            <!-- Decorative Shapes -->
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>

            <div class="login-card">
                <!-- Logo Section -->
                <div class="logo-area">
                    <img src="assets/img/log.png" alt="Logo PTPN" class="logo-img">
                    <div class="company-name">PT Perkebunan Nusantara I</div>
                    <div class="company-name">PTPN I Regional 3</div>
                    <div class="unit-name">KEBUN SILUWOK</div>
                </div>

                <!-- Login Form -->
                <?php if($error): ?>
                    <div class="alert alert-danger py-2 mb-3" role="alert" style="font-size: 0.9rem;">
                        <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">
                    
                    <!-- Username Input Group -->
                    <div class="input-group mb-3">
                        <span class="input-group-text input-group-text-custom">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" class="form-control form-control-custom" placeholder="Username / NIK" name="username" required autocomplete="off">
                    </div>

                    <!-- Password Input Group -->
                    <div class="input-group mb-4">
                        <span class="input-group-text input-group-text-custom">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control form-control-custom" placeholder="Password" name="password" id="passwordInput" required>
                        <!-- Show Password Toggle (Optional, using simple inline JS for now) -->
                        <span class="input-group-text input-group-text-custom" style="border-left: none; border-radius: 0 50px 50px 0; border-right: 1px solid rgba(255,255,255,0.2); cursor: pointer;" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>

                    <!-- Remember Me -->
                    <div class="form-check form-check-custom">
                        <input class="form-check-input" type="checkbox" value="" id="ingatSaya">
                        <label class="form-check-label text-white" for="ingatSaya">
                            Ingat saya di perangkat ini
                        </label>
                    </div>

                    <!-- Button -->
                    <button type="submit" class="btn-login">
                        Masuk <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                    
                  
                    <div class="footer-copy">
                        &copy; 2024 IT Kebun Siluwok. All Rights Reserved.
                    </div>

                </form>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Simple Password Toggle Script -->
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
