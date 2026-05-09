<?php
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RTO Tracker - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="admin-layout">
<?php require_once __DIR__ . '/includes/layout.php'; ?>
<div class="page-content">

    <div class="page-header">
        <div>
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator">/</span>
                <span>RTO Tracker</span>
            </div>
            <h1>RTO Tracker</h1>
            <p class="subtitle">Track Return to Origin shipments and manage RTO cases.</p>
        </div>
    </div>

    <div class="card" style="text-align:center; padding: 80px 40px;">
        <i class="fas fa-truck-loading" style="font-size:64px; color:var(--gold-primary); opacity:0.6; display:block; margin-bottom:24px;"></i>
        <h2 style="font-size:24px; font-weight:700; color:var(--text-primary); margin-bottom:12px;">RTO Tracker</h2>
        <p style="color:var(--text-muted); font-size:14px; max-width:400px; margin:0 auto 28px;">Track Return to Origin shipments and manage RTO cases.</p>
        <span style="display:inline-block; padding:6px 18px; background:rgba(212,168,83,0.1); border:1px solid rgba(212,168,83,0.3); border-radius:20px; font-size:12px; font-weight:700; color:var(--gold-primary); text-transform:uppercase; letter-spacing:1px;">
            <i class="fas fa-clock" style="margin-right:6px;"></i>Coming Soon
        </span>
        <div style="margin-top:32px;">
            <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

</div>
</main>
</div>
</body>
</html>