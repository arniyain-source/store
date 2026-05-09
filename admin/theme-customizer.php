<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }

/**
 * Theme Customizer - DesiVastra Admin
 */

$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Customizer - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .coming-soon-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 60vh;
            text-align: center;
            padding: 40px;
        }
        .coming-soon-icon {
            font-size: 64px;
            color: var(--gold-primary);
            margin-bottom: 24px;
            opacity: 0.8;
        }
        .coming-soon-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text-primary);
        }
        .coming-soon-text {
            font-size: 16px;
            color: var(--text-secondary);
            max-width: 500px;
            line-height: 1.6;
            margin-bottom: 32px;
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php require_once __DIR__ . '/includes/layout.php'; ?>

    <div class="page-content">
        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <a href="index.php"><i class="fas fa-home"></i></a>
                    <span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <a href="#">System</a>
                    <span class="separator"><i class="fas fa-chevron-right"></i></span>
                    <span>Theme Customizer</span>
                </div>
                <h1>Theme Customizer</h1>
                <p class="subtitle">Customize the look and feel of your storefront.</p>
            </div>
        </div>

        <div class="card">
            <div class="coming-soon-container">
                <i class="fas fa-paint-roller coming-soon-icon"></i>
                <h2 class="coming-soon-title">Theme Customizer Coming Soon</h2>
                <p class="coming-soon-text">
                    We're building a powerful visual editor that will allow you to customize colors, typography, homepage layouts, and banner placements without touching a single line of code.
                </p>
                <a href="settings.php" class="btn btn-primary">
                    <i class="fas fa-cog"></i> Go to Settings
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
