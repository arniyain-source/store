<?php
/**
 * Admin Login Page
 */
require_once __DIR__ . '/../includes/core/app.php';

// If already logged in, redirect to dashboard
if (isAdminLoggedIn()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $result = adminLogin($email, $password);
        if ($result['success']) {
            redirect('index.php');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - DesiVastra</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-card">
            <div class="brand-logo">
                <div class="logo-icon">DV</div>
                <h2>DesiVastra Admin</h2>
                <p>Sign in to your admin panel</p>
            </div>
            
            <?php if ($error): ?>
                <div class="flash-message flash-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo clean($error); ?>
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@desivastra.in" value="<?php echo clean($_POST['email'] ?? ''); ?>" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div style="position:relative">
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                        <button type="button" onclick="togglePassword()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:14px">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group" style="display:flex;align-items:center;justify-content:space-between">
                    <label class="form-check">
                        <input type="checkbox" name="remember">
                        <label>Remember me</label>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
            
            <div style="text-align:center;margin-top:24px;font-size:12px;color:var(--text-muted)">
                <!-- credentials removed for security -->
                <p style="margin-top:8px"><a href="<?php echo SITE_URL; ?>"><i class="fas fa-arrow-left"></i> Back to Website</a></p>
            </div>
        </div>
    </div>
    
    <script>
    function togglePassword() {
        const pw = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (pw.type === 'password') {
            pw.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            pw.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    </script>
</body>
</html>
