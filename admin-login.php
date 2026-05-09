<?php
/**
 * DesiVastra Admin Login — /admin-login
 * Clean standalone login page with redirect to admin/dashboard
 */
require_once __DIR__ . '/includes/core/app.php';

if (isAdminLoggedIn()) {
    header('Location: /admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['_token'] ?? '')) {
        $error = 'Security token mismatch. Please refresh and try again.';
    } else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$email || !$password) {
            $error = 'Please enter both email and password.';
        } else {
            $result = adminLogin($email, $password);
            if ($result['success']) {
                header('Location: /admin/index.php');
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}
$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — DesiVastra</title>
<meta name="robots" content="noindex,nofollow">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:radial-gradient(ellipse at 60% 0%,#1a1040 0%,#0a0a0f 60%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.wrap{width:100%;max-width:420px}
.brand{text-align:center;margin-bottom:28px}
.brand-icon{width:56px;height:56px;background:linear-gradient(135deg,#d4a853,#f0d78c);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:800;color:#0a0a0f;margin:0 auto 12px}
.brand h1{font-size:22px;font-weight:700;background:linear-gradient(135deg,#d4a853,#f0d78c);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.brand p{color:#9a9ab0;font-size:13px;margin-top:4px}
.card{background:rgba(26,26,46,.95);border:1px solid rgba(212,168,83,.15);border-radius:20px;padding:36px;backdrop-filter:blur(20px);box-shadow:0 20px 60px rgba(0,0,0,.5)}
.err{background:rgba(231,76,60,.12);border:1px solid rgba(231,76,60,.3);border-radius:10px;padding:12px 16px;font-size:13px;color:#e74c3c;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.fg{margin-bottom:18px}
label{display:block;font-size:12px;font-weight:600;color:#9a9ab0;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
.input-wrap{position:relative}
input[type=email],input[type=password],input[type=text]{width:100%;padding:12px 14px;background:#16162b;border:1.5px solid #2a2a4a;border-radius:10px;color:#f0f0f5;font-size:14px;font-family:inherit;transition:.25s}
input:focus{outline:none;border-color:#d4a853;background:#1e1e3a;box-shadow:0 0 0 3px rgba(212,168,83,.1)}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#6b6b85;cursor:pointer;font-size:14px;padding:4px}
.pw-toggle:hover{color:#d4a853}
.btn{width:100%;padding:14px;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;background:linear-gradient(135deg,#b8922e,#d4a853,#f0d78c,#d4a853);color:#0a0a0f;letter-spacing:.3px;transition:.3s;margin-top:6px;display:flex;align-items:center;justify-content:center;gap:8px}
.btn:hover{box-shadow:0 6px 24px rgba(212,168,83,.35);transform:translateY(-1px)}
.btn:active{transform:translateY(0)}
.meta{text-align:center;margin-top:20px;font-size:13px;color:#6b6b85}
.meta a{color:#d4a853;text-decoration:none}
.meta a:hover{text-decoration:underline}
.divider{display:flex;align-items:center;gap:12px;margin:20px 0}
.divider span{flex:1;height:1px;background:#2a2a4a}
.divider em{font-size:11px;color:#6b6b85;font-style:normal}
.footer-info{text-align:center;margin-top:24px;font-size:11px;color:#3a3a5a}
/* Loading state */
.btn.loading{opacity:.7;pointer-events:none}
.spin{animation:spin .8s linear infinite;display:inline-block}
@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <div class="brand-icon">DV</div>
        <h1>DesiVastra Admin</h1>
        <p>Sign in to manage your store</p>
    </div>
    <div class="card">
        <?php if ($error): ?>
        <div class="err"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" id="loginForm" onsubmit="setLoading()">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="fg">
                <label>Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    placeholder="admin@desivastra.in" required autofocus autocomplete="username">
            </div>
            <div class="fg">
                <label>Password</label>
                <div class="input-wrap">
                    <input type="password" name="password" id="pwField"
                        placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Toggle password">
                        <i class="fas fa-eye" id="pwIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn" id="submitBtn">
                <i class="fas fa-sign-in-alt"></i> Sign In to Admin Panel
            </button>
        </form>
        <div class="divider"><span></span><em>quick access</em><span></span></div>
        <div class="meta">
            <a href="/install">Setup Wizard</a> &nbsp;|&nbsp;
            <a href="/">← View Website</a>
        </div>
    </div>
    <div class="footer-info">DesiVastra Admin Panel &nbsp;·&nbsp; <?= date('Y') ?></div>
</div>
<script>
function togglePw() {
    const f = document.getElementById('pwField');
    const i = document.getElementById('pwIcon');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
}
function setLoading() {
    const btn = document.getElementById('submitBtn');
    btn.innerHTML = '<i class="fas fa-circle-notch spin"></i> Signing In...';
    btn.classList.add('loading');
}
</script>
</body>
</html>
