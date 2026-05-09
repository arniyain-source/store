<?php
require_once __DIR__ . '/includes/core/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In a real application, you would have a users table and hashed passwords
    if ($_POST['username'] === 'admin' && $_POST['password'] === 'password') { 
        $_SESSION['is_admin'] = true;
        header('Location: /admin/index.php');
        exit;
    } else {
        $error = "Invalid credentials.";
    }
}

$pageTitle = "Admin Login";
require __DIR__ . '/includes/header.php';
?>

<div class="login-container">
    <form method="POST" class="login-form">
        <h1>Admin Login</h1>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <button type="submit">Login</button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
