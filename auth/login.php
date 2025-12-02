<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    try {
        $db = db();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND status = 'active'");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['user_role'] = $user['role'];
            
            header('Location: ../index.php');
            exit();
        } else {
            $error = "Invalid username or password";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
try {
    $db2 = db();
    $settings = $db2->query("SELECT hotel_name, hotel_email FROM system_settings ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $settings = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo $settings['hotel_name'] ?? 'Hotel Booking System'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root { --gold: #d9b86c; --gold-d: #b99746; --ink: #1f2937; }
        .login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f4f6f8; padding: 2rem; }
        .login-card { background: #fff; border-radius: 14px; box-shadow: 0 18px 36px rgba(0,0,0,.12); width: 100%; max-width: 420px; padding: 2rem; }
        .brand { font-family: 'Playfair Display', serif; color: var(--gold); }
        .btn-gold { background-image: linear-gradient(135deg, var(--gold), var(--gold-d)); color: var(--ink); border: none; }
        .btn-gold:hover { filter: brightness(1.05); }
        .input-group-text { background-color: #f3f4f6; border-color: #e5e7eb; }
        .form-control { border-color: #e5e7eb; padding-top: .7rem; padding-bottom: .7rem; }
        .form-control:focus { border-color: var(--gold-d); box-shadow: 0 0 0 .2rem rgba(217,184,108,.25); }
        .helper { font-size: .85rem; }
        .action-links a { text-decoration: none; }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="text-center mb-4">
                <i class="bi bi-gem" style="font-size:2rem;color:#d9b86c"></i>
                <h4 class="brand mt-2"><?php echo htmlspecialchars($settings['hotel_name'] ?? 'Hotel Booking System'); ?></h4>
                <p class="text-muted mb-0">Sign in to continue</p>
            </div>
            <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required autofocus autocomplete="username">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye"></i></button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <div class="action-links">
                        <a href="../landing.php" class="text-decoration-none">Back to Landing</a>
                    </div>
                </div>
                <button type="submit" class="btn btn-gold w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
                <div class="text-center">
                    <small class="text-muted helper">Default admin: admin / password</small>
                </div>
                <div class="mt-3 text-center">
                    <small class="text-muted helper"><?php echo htmlspecialchars($settings['hotel_email'] ?? ''); ?></small>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('togglePassword').addEventListener('click', function(){
            var p = document.getElementById('password');
            var is = p.getAttribute('type') === 'password';
            p.setAttribute('type', is ? 'text' : 'password');
            this.innerHTML = is ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        });
    </script>
</body>
</html>
