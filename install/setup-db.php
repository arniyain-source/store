<?php
/**
 * DesiVastra - Installation Wizard
 * Step 2: Database Configuration & Migration
 */
require_once __DIR__ . '/../includes/functions.php';

$step = 2;
$error = '';
$success = false;

// Handle AJAX connection test
if (isset($_POST['action']) && $_POST['action'] === 'test_connection') {
    header('Content-Type: application/json');
    $host = $_POST['db_host'] ?? '';
    $name = $_POST['db_name'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $pass = $_POST['db_pass'] ?? '';
    $sock = $_POST['db_socket'] ?? '';

    try {
        $dsn = "mysql:host=$host;charset=utf8mb4";
        if (!empty($sock)) {
            $dsn = "mysql:unix_socket=$sock;charset=utf8mb4";
        }
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo json_encode(['success' => true, 'message' => 'Connection to server successful!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Connection Failed: ' . $e->getMessage()]);
    }
    exit;
}

// Handle Database Schema Installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install_database'])) {
    $host = $_POST['db_host'] ?? '';
    $name = $_POST['db_name'] ?? '';
    $user = $_POST['db_user'] ?? '';
    $pass = $_POST['db_pass'] ?? '';
    $sock = $_POST['db_socket'] ?? '';

    try {
        $dsn = "mysql:host=$host;charset=utf8mb4";
        if (!empty($sock)) {
            $dsn = "mysql:unix_socket=$sock;charset=utf8mb4";
        }
        
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        
        // Create DB
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");

        // Run Schema
        $sqlFile = __DIR__ . '/../config/setup.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("Setup SQL file not found at " . $sqlFile);
        }
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);

        // Save to session for next step
        $_SESSION['install_db_config'] = [
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
            'sock' => $sock
        ];

        // Update database.php config
        $configContent = "<?php\n";
        $configContent .= "/**\n * Auto-generated Database Configuration\n */\n\n";
        $configContent .= "define('DB_HOST', '$host');\n";
        $configContent .= "define('DB_NAME', '$name');\n";
        $configContent .= "define('DB_USER', '$user');\n";
        $configContent .= "define('DB_PASS', '$pass');\n";
        if (!empty($sock)) {
            $configContent .= "define('DB_SOCKET', '$sock');\n";
        }
        $configContent .= "define('SITE_URL', '" . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . "');\n";
        
        file_put_contents(__DIR__ . '/../config/database.php', $configContent);
        
        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - DesiVastra Installer</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --gold-primary: #b8892a; --bg: #0f0f11; --card: #1a1a1e; --text: #f0f0f5; --muted: #9a9ab0; --success: #4CAF50; --danger: #cf6679; }
        body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .setup-container { width: 100%; max-width: 550px; padding: 20px; }
        .setup-card { background: var(--card); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 40px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        .logo { text-align: center; font-size: 28px; font-weight: 700; margin-bottom: 30px; }
        .logo span { color: var(--gold-primary); }
        .progress-steps { display: flex; justify-content: space-between; margin-bottom: 40px; position: relative; }
        .progress-steps::before { content: ''; position: absolute; top: 15px; left: 0; width: 100%; height: 2px; background: rgba(255,255,255,0.1); z-index: 1; }
        .step { width: 32px; height: 32px; border-radius: 50%; background: var(--card); border: 2px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; z-index: 2; position: relative; }
        .step.active { border-color: var(--gold-primary); color: var(--gold-primary); box-shadow: 0 0 15px rgba(184,137,42,0.3); }
        .step.completed { background: var(--gold-primary); border-color: var(--gold-primary); color: #000; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 8px; font-weight: 600; }
        input { width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; font-family: inherit; transition: 0.3s; }
        input:focus { outline: none; border-color: var(--gold-primary); background: rgba(255,255,255,0.08); }
        .status-bar { padding: 10px 15px; border-radius: 8px; font-size: 12px; margin-bottom: 20px; background: rgba(255,255,255,0.03); display: flex; align-items: center; gap: 10px; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--muted); }
        .status-dot.connecting { background: var(--gold-primary); animation: pulse 1s infinite; }
        .status-dot.success { background: var(--success); }
        .status-dot.error { background: var(--danger); }
        .btn { width: 100%; padding: 14px; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.3s; font-family: inherit; margin-bottom: 12px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-primary { background: linear-gradient(135deg, #e5c35a, #b8892a); color: #000; }
        .btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(184,137,42,0.4); }
        .btn-secondary { background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); }
        .btn-secondary:hover:not(:disabled) { background: rgba(255,255,255,0.1); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .alert { padding: 15px; border-radius: 10px; font-size: 13px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; }
        .alert-error { background: rgba(207,102,121,0.1); border: 1px solid var(--danger); color: var(--danger); }
        .alert-success { background: rgba(76,175,80,0.1); border: 1px solid var(--success); color: var(--success); }
        @keyframes pulse { 0% { opacity: 0.4; } 50% { opacity: 1; } 100% { opacity: 0.4; } }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="logo">Arniya<span>Hub</span></div>
            
            <div class="progress-steps">
                <div class="step completed" title="Requirements"><i class="fas fa-check"></i></div>
                <div class="step active" title="Database">2</div>
                <div class="step" title="Admin">3</div>
                <div class="step" title="Finalize">4</div>
            </div>

            <h2 style="text-align:center; margin-bottom:5px; font-weight: 600;">Database Setup</h2>
            <p style="text-align:center; color:var(--muted); font-size:14px; margin-bottom:30px;">Connect and initialize your store database</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    <span>Database initialized and schema installed successfully!</span>
                </div>
            <?php endif; ?>

            <form method="POST" id="dbForm">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" id="db_host" value="localhost" placeholder="e.g. localhost or 127.0.0.1" required>
                </div>
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" id="db_name" placeholder="e.g. desivastra_db" required>
                </div>
                <div class="form-group">
                    <label>DB Username</label>
                    <input type="text" name="db_user" id="db_user" placeholder="e.g. root" required>
                </div>
                <div class="form-group">
                    <label>DB Password</label>
                    <input type="password" name="db_pass" id="db_pass" placeholder="Your DB password">
                </div>
                <div class="form-group">
                    <label>Unix Socket (Optional)</label>
                    <input type="text" name="db_socket" id="db_socket" placeholder="e.g. /run/mysqld/mysqld.sock">
                </div>

                <div class="status-bar" id="connStatusBar">
                    <div class="status-dot" id="statusDot"></div>
                    <span id="statusText">Not Tested</span>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary" id="testBtn" style="flex: 1;">
                        <i class="fas fa-plug"></i> Test Connection
                    </button>
                    <button type="submit" name="install_database" id="installBtn" class="btn btn-primary" style="flex: 1.5;" <?php echo $success ? 'disabled' : 'disabled'; ?>>
                        <i class="fas fa-database"></i> Install Schema
                    </button>
                </div>
            </form>

            <div id="nextStepArea" style="<?php echo $success ? 'display: block;' : 'display: none;'; ?> margin-top: 10px;">
                <a href="setup-admin.php" style="text-decoration:none;">
                    <button class="btn btn-primary">
                        Next: Admin Account Setup <i class="fas fa-arrow-right"></i>
                    </button>
                </a>
            </div>
        </div>
    </div>

    <script>
        const testBtn = document.getElementById('testBtn');
        const installBtn = document.getElementById('installBtn');
        const statusText = document.getElementById('statusText');
        const statusDot = document.getElementById('statusDot');

        testBtn.addEventListener('click', function() {
            testBtn.disabled = true;
            statusText.innerText = 'Connecting...';
            statusDot.className = 'status-dot connecting';

            const formData = new FormData();
            formData.append('action', 'test_connection');
            formData.append('db_host', document.getElementById('db_host').value);
            formData.append('db_name', document.getElementById('db_name').value);
            formData.append('db_user', document.getElementById('db_user').value);
            formData.append('db_pass', document.getElementById('db_pass').value);
            formData.append('db_socket', document.getElementById('db_socket').value);

            fetch('setup-db.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                testBtn.disabled = false;
                if (data.success) {
                    statusText.innerText = 'Success: ' + data.message;
                    statusDot.className = 'status-dot success';
                    installBtn.disabled = false;
                } else {
                    statusText.innerText = data.message;
                    statusDot.className = 'status-dot error';
                    installBtn.disabled = true;
                }
            })
            .catch(err => {
                testBtn.disabled = false;
                statusText.innerText = 'Network error occurred.';
                statusDot.className = 'status-dot error';
            });
        });
    </script>
</body>
</html>