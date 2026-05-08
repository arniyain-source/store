<?php
/**
 * DesiVastra - Installation Wizard
 * Step 1: Server Requirements Check
 */

// Basic requirements
$minPhpVersion = '8.1.0';
$requiredExtensions = ['pdo', 'pdo_mysql', 'mysqli', 'gd', 'json', 'mbstring', 'curl', 'fileinfo'];
$writableDirectories = ['../config', '../uploads', '../backups', '../api/payments'];

// Results array
$results = [
    'php' => ['status' => version_compare(PHP_VERSION, $minPhpVersion, '>='), 'current' => PHP_VERSION],
    'extensions' => [],
    'permissions' => []
];

// Check Extensions
foreach ($requiredExtensions as $ext) {
    $results['extensions'][$ext] = extension_loaded($ext);
}

// Check Permissions
foreach ($writableDirectories as $dir) {
    // Attempt to create directory if not exists
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $results['permissions'][$dir] = is_writable($dir);
}

// Check if all passed
$allPassed = $results['php']['status'];
foreach ($results['extensions'] as $status) if (!$status) $allPassed = false;
foreach ($results['permissions'] as $status) if (!$status) $allPassed = false;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Wizard - DesiVastra</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: #0f0f11;
            --card-bg: #16161a;
            --gold-primary: #b8892a;
            --gold-light: #e5c35a;
            --text-primary: #ffffff;
            --text-secondary: #9ca3af;
            --success: #2ecc71;
            --danger: #cf6679;
            --border: rgba(255, 255, 255, 0.08);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .installer-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            width: 100%;
            max-width: 600px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .header { text-align: center; margin-bottom: 30px; }
        .logo { font-size: 32px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 10px; }
        .gold { color: var(--gold-light); }

        .progress-container { display: flex; justify-content: space-between; margin-bottom: 40px; position: relative; }
        .progress-container::before {
            content: ''; position: absolute; top: 15px; left: 0; right: 0;
            height: 2px; background: var(--border); z-index: 1;
        }
        .step { position: relative; z-index: 2; text-align: center; flex: 1; }
        .step-circle {
            width: 32px; height: 32px; border-radius: 50%; background: var(--bg-dark);
            border: 2px solid var(--border); display: flex; align-items: center;
            justify-content: center; margin: 0 auto 8px; font-size: 14px; font-weight: 700;
        }
        .step.active .step-circle { border-color: var(--gold-primary); background: var(--gold-primary); color: #000; }
        .step.done .step-circle { border-color: var(--gold-primary); color: var(--gold-primary); }
        .step-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); font-weight: 600; }
        .step.active .step-label { color: var(--gold-light); }

        h2 { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
        .subtitle { font-size: 14px; color: var(--text-secondary); margin-bottom: 25px; }

        .table-wrap { margin-bottom: 30px; border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
        .req-table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.02); }
        .req-table th { text-align: left; padding: 12px 16px; background: rgba(255,255,255,0.04); font-size: 11px; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.5px; }
        .req-table td { padding: 14px 16px; border-top: 1px solid var(--border); font-size: 14px; }
        .status-icon { font-size: 16px; }
        .pass { color: var(--success); }
        .fail { color: var(--danger); }

        .error-alert {
            background: rgba(207, 102, 121, 0.1); border: 1px solid rgba(207, 102, 121, 0.2);
            padding: 15px; border-radius: 10px; color: var(--danger); font-size: 13px;
            margin-bottom: 25px; display: flex; align-items: center; gap: 12px;
        }

        .btn {
            display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; padding: 15px; border-radius: 12px; border: none;
            font-size: 16px; font-weight: 700; cursor: pointer; text-decoration: none;
            transition: all 0.3s ease;
        }
        .btn-gold { background: linear-gradient(135deg, var(--gold-light), var(--gold-primary)); color: #000; }
        .btn-gold:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(184, 137, 42, 0.3); }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
    </style>
</head>
<body>

<div class="installer-card">
    <div class="header">
        <div class="logo">Arniya<span class="gold">Hub</span></div>
    </div>

    <div class="progress-container">
        <div class="step active">
            <div class="step-circle">1</div>
            <div class="step-label">Requirements</div>
        </div>
        <div class="step">
            <div class="step-circle">2</div>
            <div class="step-label">Database</div>
        </div>
        <div class="step">
            <div class="step-circle">3</div>
            <div class="step-label">Admin</div>
        </div>
        <div class="step">
            <div class="step-circle">4</div>
            <div class="step-label">Finalize</div>
        </div>
    </div>

    <h2>System Check</h2>
    <p class="subtitle">Checking server configuration and permissions.</p>

    <?php if (!$allPassed): ?>
    <div class="error-alert">
        <i class="fas fa-exclamation-triangle"></i>
        <span>Some requirements are not met. Please resolve the issues below to proceed.</span>
    </div>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="req-table">
            <thead>
                <tr>
                    <th>Requirement</th>
                    <th>Required</th>
                    <th style="text-align: right;">Current / Status</th>
                </tr>
            </thead>
            <tbody>
                <!-- PHP -->
                <tr>
                    <td>PHP Version</td>
                    <td><?php echo $minPhpVersion; ?>+</td>
                    <td style="text-align: right;">
                        <?php echo $results['php']['current']; ?> 
                        <i class="fas <?php echo $results['php']['status'] ? 'fa-check-circle pass' : 'fa-times-circle fail'; ?> status-icon"></i>
                    </td>
                </tr>
                <!-- Extensions -->
                <?php foreach ($results['extensions'] as $ext => $loaded): ?>
                <tr>
                    <td>Extension: <strong><?php echo $ext; ?></strong></td>
                    <td>Enabled</td>
                    <td style="text-align: right;">
                        <i class="fas <?php echo $loaded ? 'fa-check-circle pass' : 'fa-times-circle fail'; ?> status-icon"></i>
                    </td>
                </tr>
                <?php endforeach; ?>
                <!-- Permissions -->
                <?php foreach ($results['permissions'] as $path => $writable): ?>
                <tr>
                    <td>Path: <strong><?php echo str_replace('../', '', $path); ?></strong></td>
                    <td>Writable</td>
                    <td style="text-align: right;">
                        <i class="fas <?php echo $writable ? 'fa-check-circle pass' : 'fa-times-circle fail'; ?> status-icon"></i>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <a href="setup-db.php" class="btn btn-gold" <?php echo !$allPassed ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
        Next: Database Setup <i class="fas fa-chevron-right"></i>
    </a>
</div>

</body>
</html>