<?php
// ── Auth guard: MUST run before any HTML output ──
require_once __DIR__ . '/../includes/functions.php';
if (!isAdminLoggedIn()) { header('Location: /admin-login'); exit; }
?>
<?php
/**
 * Profile Page - DesiVastra Admin
 * View and edit admin profile, change password
 */

// Handle POST before layout output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/functions.php';
    requireAdminLogin();

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please try again.');
        header('Location: profile.php');
        exit;
    }

    $formAction = sanitize($_POST['form_action'] ?? '');

    try {
        $db = getDB();
        $adminId = $_SESSION['admin_id'];

        // ========================================
        // UPDATE PROFILE
        // ========================================
        if ($formAction === 'update_profile') {
            $name  = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');

            // Validation
            $errors = [];
            if (empty($name) || strlen($name) < 2) {
                $errors[] = 'Name must be at least 2 characters.';
            }
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }

            // Check email uniqueness (excluding current admin)
            $stmt = $db->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
            $stmt->execute([$email, $adminId]);
            if ($stmt->fetch()) {
                $errors[] = 'This email is already used by another admin.';
            }

            // Handle avatar upload
            $avatarPath = null;
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['avatar'], 'avatars', ALLOWED_IMAGE_TYPES);
                if ($uploadResult['success']) {
                    $avatarPath = $uploadResult['path'];

                    // Delete old avatar
                    $stmt = $db->prepare("SELECT avatar FROM admins WHERE id = ?");
                    $stmt->execute([$adminId]);
                    $oldAdmin = $stmt->fetch();
                    if ($oldAdmin && $oldAdmin['avatar']) {
                        deleteUploadedFile($oldAdmin['avatar']);
                    }
                } else {
                    $errors[] = $uploadResult['message'];
                }
            }

            if (!empty($errors)) {
                setFlash('error', implode(' ', $errors));
            } else {
                // Build update query
                if ($avatarPath) {
                    $stmt = $db->prepare("UPDATE admins SET name = ?, email = ?, avatar = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $email, $avatarPath, $adminId]);
                } else {
                    $stmt = $db->prepare("UPDATE admins SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $email, $adminId]);
                }

                // Update session
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_email'] = $email;

                logActivity('update', 'admin', $adminId, ['fields' => 'profile info']);

                setFlash('success', 'Profile updated successfully.');
            }

            header('Location: profile.php');
            exit;
        }

        // ========================================
        // CHANGE PASSWORD
        // ========================================
        if ($formAction === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword     = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $errors = [];

            // Verify current password
            $stmt = $db->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->execute([$adminId]);
            $admin = $stmt->fetch();

            if (!$admin || !password_verify($currentPassword, $admin['password'])) {
                $errors[] = 'Current password is incorrect.';
            }

            // Validate new password
            if (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters long.';
            }

            if (!preg_match('/[A-Z]/', $newPassword)) {
                $errors[] = 'New password must contain at least one uppercase letter.';
            }

            if (!preg_match('/[a-z]/', $newPassword)) {
                $errors[] = 'New password must contain at least one lowercase letter.';
            }

            if (!preg_match('/[0-9]/', $newPassword)) {
                $errors[] = 'New password must contain at least one number.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'New password and confirm password do not match.';
            }

            if ($currentPassword === $newPassword) {
                $errors[] = 'New password must be different from the current password.';
            }

            if (!empty($errors)) {
                setFlash('error', implode(' ', $errors));
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashedPassword, $adminId]);

                logActivity('update', 'admin', $adminId, ['fields' => 'password change']);

                setFlash('success', 'Password changed successfully. Please use the new password on your next login.');
            }

            header('Location: profile.php');
            exit;
        }

        // Unknown form action
        setFlash('error', 'Invalid form action.');
        header('Location: profile.php');
        exit;

    } catch (Exception $e) {
        setFlash('error', 'An error occurred: ' . $e->getMessage());
        header('Location: profile.php');
        exit;
    }
}

// Normal page load
require_once __DIR__ . '/includes/layout.php';

// Get fresh admin data
$admin = getCurrentAdmin();

// Get admin's recent activity
$recentActivity = [];
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT action, entity_type, details, created_at
        FROM activity_log
        WHERE admin_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$admin['id']]);
    $recentActivity = $stmt->fetchAll();
} catch (Exception $e) {
    // Activity log may be empty
}

// Flash message
$flash = getFlash();
$csrf = generateCSRF();

// Helper to get avatar URL
$avatarUrl = null;
if (!empty($admin['avatar'])) {
    // Check if it's a full URL or a path
    if (filter_var($admin['avatar'], FILTER_VALIDATE_URL)) {
        $avatarUrl = $admin['avatar'];
    } else {
        $avatarUrl = SITE_URL . '/' . $admin['avatar'];
    }
}

// Role display name
$roleLabels = [
    'super_admin' => 'Super Admin',
    'admin'       => 'Admin',
    'editor'      => 'Editor',
];
$roleLabel = $roleLabels[$admin['role'] ?? 'admin'] ?? ucfirst($admin['role'] ?? 'Admin');

// Role badge class
$roleBadgeClass = [
    'super_admin' => 'badge-danger',
    'admin'       => 'badge-primary',
    'editor'      => 'badge-info',
];
$roleBadge = $roleBadgeClass[$admin['role'] ?? 'admin'] ?? 'badge-dark';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - DesiVastra Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        /* Profile page-specific styles */
        .profile-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 24px;
            align-items: start;
        }

        /* Profile Card */
        .profile-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .profile-card-banner {
            height: 100px;
            background: linear-gradient(135deg, rgba(212,168,83,0.3), rgba(18,18,26,0.9)), url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 100"><rect fill="%231a1a2e" width="400" height="100"/><circle cx="50" cy="50" r="80" fill="rgba(212,168,83,0.08)"/><circle cx="350" cy="30" r="60" fill="rgba(212,168,83,0.05)"/></svg>');
            background-size: cover;
            position: relative;
        }

        .profile-card-banner::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40px;
            background: linear-gradient(transparent, var(--bg-card));
        }

        .profile-avatar-wrapper {
            display: flex;
            justify-content: center;
            margin-top: -44px;
            position: relative;
            z-index: 1;
        }

        .profile-avatar {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: var(--gold-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 800;
            color: #0a0a0f;
            border: 4px solid var(--bg-card);
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-card-body {
            padding: 16px 24px 24px;
            text-align: center;
        }

        .profile-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .profile-email {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 12px;
            word-break: break-all;
        }

        .profile-role-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 16px;
        }

        .profile-details {
            text-align: left;
            border-top: 1px solid var(--border-color);
            padding-top: 16px;
            margin-top: 4px;
        }

        .profile-detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            font-size: 13px;
        }

        .profile-detail-item i {
            width: 18px;
            text-align: center;
            color: var(--gold-primary);
            font-size: 12px;
        }

        .profile-detail-item .detail-label {
            color: var(--text-muted);
            min-width: 80px;
        }

        .profile-detail-item .detail-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        /* Recent Activity in Profile Card */
        .profile-activity {
            border-top: 1px solid var(--border-color);
            padding-top: 16px;
            margin-top: 8px;
        }

        .profile-activity-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .profile-activity-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 0;
            font-size: 12px;
        }

        .profile-activity-item .pa-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
        }

        .profile-activity-item .pa-icon.create { background: var(--success-bg); color: var(--success); }
        .profile-activity-item .pa-icon.update { background: var(--info-bg); color: var(--info); }
        .profile-activity-item .pa-icon.delete { background: var(--danger-bg); color: var(--danger); }
        .profile-activity-item .pa-icon.login  { background: var(--purple-bg); color: var(--purple); }

        .profile-activity-item .pa-text {
            color: var(--text-secondary);
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profile-activity-item .pa-time {
            color: var(--text-muted);
            font-size: 10px;
            flex-shrink: 0;
        }

        .profile-activity-more {
            display: block;
            text-align: center;
            font-size: 12px;
            color: var(--gold-primary);
            margin-top: 8px;
            font-weight: 500;
        }

        .profile-activity-more:hover {
            color: var(--gold-light);
        }

        /* Right side forms */
        .profile-forms {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Avatar upload in form */
        .avatar-upload-area {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .avatar-preview {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--gold-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            color: #0a0a0f;
            overflow: hidden;
            border: 2px solid var(--border-color);
            flex-shrink: 0;
        }

        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-upload-controls {
            flex: 1;
        }

        .avatar-upload-controls .upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .avatar-upload-controls .upload-btn:hover {
            border-color: var(--gold-dark);
            color: var(--gold-primary);
        }

        .avatar-upload-controls .upload-btn i {
            color: var(--gold-primary);
        }

        .avatar-upload-controls .upload-hint {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        .avatar-upload-controls input[type="file"] {
            display: none;
        }

        /* Password strength meter */
        .password-strength {
            margin-top: 6px;
        }

        .strength-bar {
            display: flex;
            gap: 4px;
            margin-bottom: 4px;
        }

        .strength-segment {
            flex: 1;
            height: 4px;
            border-radius: 2px;
            background: var(--border-color);
            transition: var(--transition);
        }

        .strength-segment.active.weak    { background: var(--danger); }
        .strength-segment.active.fair     { background: var(--warning); }
        .strength-segment.active.good     { background: var(--info); }
        .strength-segment.active.strong   { background: var(--success); }

        .strength-text {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Password requirements */
        .password-requirements {
            margin-top: 8px;
            padding: 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
        }

        .password-requirements .req-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .password-requirements .req-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-muted);
            padding: 2px 0;
        }

        .password-requirements .req-item i {
            font-size: 10px;
            width: 14px;
            text-align: center;
        }

        .password-requirements .req-item.met {
            color: var(--success);
        }

        .password-requirements .req-item.met i {
            color: var(--success);
        }

        @media (max-width: 768px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }

            .profile-card-body {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">

    <!-- Sidebar + Header already included via layout.php -->

    
        <div class="page-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i></a>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <span>My Profile</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-user-circle" style="color:var(--gold-primary);margin-right:8px"></i>My Profile</h1>
                    <p class="subtitle">Manage your account information and security settings</p>
                </div>
            </div>

            <!-- Flash Message -->
            <?php if ($flash): ?>
                <div class="flash-message flash-<?php echo $flash['type']; ?>">
                    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : ($flash['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle')); ?>"></i>
                    <?php echo clean($flash['message']); ?>
                    <button class="flash-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Profile Layout -->
            <div class="profile-layout">

                <!-- ======================================== -->
                <!-- LEFT: Profile Card                       -->
                <!-- ======================================== -->
                <div class="profile-card">
                    <div class="profile-card-banner"></div>
                    <div class="profile-avatar-wrapper">
                        <div class="profile-avatar">
                            <?php if ($avatarUrl): ?>
                                <img src="<?php echo clean($avatarUrl); ?>" alt="<?php echo clean($admin['name'] ?? 'Admin'); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($admin['name'] ?? 'A', 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="profile-card-body">
                        <div class="profile-name"><?php echo clean($admin['name'] ?? 'Admin'); ?></div>
                        <div class="profile-email"><?php echo clean($admin['email'] ?? ''); ?></div>
                        <div class="profile-role-badge">
                            <span class="badge <?php echo $roleBadge; ?>">
                                <i class="fas fa-shield-alt"></i>
                                <?php echo $roleLabel; ?>
                            </span>
                        </div>

                        <div class="profile-details">
                            <div class="profile-detail-item">
                                <i class="fas fa-envelope"></i>
                                <span class="detail-label">Email</span>
                                <span class="detail-value"><?php echo clean($admin['email'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="profile-detail-item">
                                <i class="fas fa-user-tag"></i>
                                <span class="detail-label">Role</span>
                                <span class="detail-value"><?php echo $roleLabel; ?></span>
                            </div>
                            <div class="profile-detail-item">
                                <i class="fas fa-clock"></i>
                                <span class="detail-label">Last Login</span>
                                <span class="detail-value">
                                    <?php echo $admin['last_login'] ? date('M j, Y g:i A', strtotime($admin['last_login'])) : 'Never'; ?>
                                </span>
                            </div>
                            <div class="profile-detail-item">
                                <i class="fas fa-calendar-plus"></i>
                                <span class="detail-label">Joined</span>
                                <span class="detail-value">
                                    <?php
                                        try {
                                            $db = getDB();
                                            $stmt = $db->prepare("SELECT created_at FROM admins WHERE id = ?");
                                            $stmt->execute([$admin['id']]);
                                            $created = $stmt->fetch();
                                            echo $created ? date('M j, Y', strtotime($created['created_at'])) : 'N/A';
                                        } catch (Exception $e) {
                                            echo 'N/A';
                                        }
                                    ?>
                                </span>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <?php if (!empty($recentActivity)): ?>
                            <div class="profile-activity">
                                <div class="profile-activity-title">Recent Activity</div>
                                <?php foreach ($recentActivity as $ra):
                                    $iconInfo = getActivityIconForProfile($ra['action']);
                                ?>
                                    <div class="profile-activity-item">
                                        <div class="pa-icon <?php echo $iconInfo['class']; ?>">
                                            <i class="fas <?php echo $iconInfo['icon']; ?>"></i>
                                        </div>
                                        <span class="pa-text"><?php echo formatActionForProfile($ra['action'], $ra['entity_type']); ?></span>
                                        <span class="pa-time"><?php echo timeAgo($ra['created_at']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <a href="activity.php" class="profile-activity-more">
                                    View All Activity <i class="fas fa-arrow-right" style="font-size:10px;margin-left:4px"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ======================================== -->
                <!-- RIGHT: Edit Forms                        -->
                <!-- ======================================== -->
                <div class="profile-forms">

                    <!-- Edit Profile Form -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-user-edit" style="color:var(--gold-primary);margin-right:8px"></i> Edit Profile</h3>
                        </div>
                        <form method="POST" action="profile.php" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="form_action" value="update_profile">

                            <div class="card-body">
                                <!-- Avatar Upload -->
                                <div class="avatar-upload-area">
                                    <div class="avatar-preview" id="avatarPreview">
                                        <?php if ($avatarUrl): ?>
                                            <img src="<?php echo clean($avatarUrl); ?>" alt="Avatar" id="avatarPreviewImg">
                                        <?php else: ?>
                                            <span id="avatarPreviewLetter"><?php echo strtoupper(substr($admin['name'] ?? 'A', 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="avatar-upload-controls">
                                        <label class="upload-btn" for="avatarInput">
                                            <i class="fas fa-camera"></i> Change Avatar
                                        </label>
                                        <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewAvatar(event)">
                                        <p class="upload-hint">JPG, PNG, GIF or WebP. Max 5MB.</p>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" name="name" class="form-control" value="<?php echo clean($admin['name'] ?? ''); ?>" placeholder="Enter your full name" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo clean($admin['email'] ?? ''); ?>" placeholder="Enter your email" required>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer" style="display:flex;justify-content:flex-end;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password Form -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-lock" style="color:var(--danger);margin-right:8px"></i> Change Password</h3>
                        </div>
                        <form method="POST" action="profile.php" id="changePasswordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="form_action" value="change_password">

                            <div class="card-body">
                                <div class="form-group">
                                    <label class="form-label">Current Password</label>
                                    <div style="position:relative">
                                        <input type="password" name="current_password" id="currentPassword" class="form-control" placeholder="Enter your current password" required style="padding-right:40px">
                                        <button type="button" class="btn-icon" onclick="togglePasswordVisibility('currentPassword', this)" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;width:auto;height:auto;padding:4px;">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">New Password</label>
                                    <div style="position:relative">
                                        <input type="password" name="new_password" id="newPassword" class="form-control" placeholder="Enter a new password" required onkeyup="checkPasswordStrength(this.value)" style="padding-right:40px">
                                        <button type="button" class="btn-icon" onclick="togglePasswordVisibility('newPassword', this)" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;width:auto;height:auto;padding:4px;">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <!-- Password strength meter -->
                                    <div class="password-strength" id="strengthMeter" style="display:none">
                                        <div class="strength-bar">
                                            <div class="strength-segment" id="seg1"></div>
                                            <div class="strength-segment" id="seg2"></div>
                                            <div class="strength-segment" id="seg3"></div>
                                            <div class="strength-segment" id="seg4"></div>
                                        </div>
                                        <span class="strength-text" id="strengthText"></span>
                                    </div>
                                    <!-- Password requirements -->
                                    <div class="password-requirements" id="passwordReqs" style="display:none">
                                        <div class="req-title">Password Requirements</div>
                                        <div class="req-item" id="req-length">
                                            <i class="fas fa-circle"></i> At least 8 characters
                                        </div>
                                        <div class="req-item" id="req-upper">
                                            <i class="fas fa-circle"></i> One uppercase letter
                                        </div>
                                        <div class="req-item" id="req-lower">
                                            <i class="fas fa-circle"></i> One lowercase letter
                                        </div>
                                        <div class="req-item" id="req-number">
                                            <i class="fas fa-circle"></i> One number
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Confirm New Password</label>
                                    <div style="position:relative">
                                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Re-enter the new password" required onkeyup="checkPasswordMatch()" style="padding-right:40px">
                                        <button type="button" class="btn-icon" onclick="togglePasswordVisibility('confirmPassword', this)" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;width:auto;height:auto;padding:4px;">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <p class="form-hint" id="matchHint" style="display:none"></p>
                                </div>
                            </div>

                            <div class="card-footer" style="display:flex;justify-content:flex-end;">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </main>
</div>

<?php
// Helper functions for profile page (scoped to avoid conflicts)
function getActivityIconForProfile($action) {
    if (strpos($action, 'login') !== false)  return ['class' => 'login',  'icon' => 'fa-sign-in-alt'];
    if (strpos($action, 'logout') !== false) return ['class' => 'login',  'icon' => 'fa-sign-out-alt'];
    if (strpos($action, 'create') !== false) return ['class' => 'create', 'icon' => 'fa-plus'];
    if (strpos($action, 'update') !== false) return ['class' => 'update', 'icon' => 'fa-pen'];
    if (strpos($action, 'delete') !== false) return ['class' => 'delete', 'icon' => 'fa-trash'];
    return ['class' => 'update', 'icon' => 'fa-circle'];
}

function formatActionForProfile($action, $entityType) {
    $actionLabel = ucwords(str_replace('_', ' ', $action));
    $entityLabel = $entityType ? ucwords(str_replace('_', ' ', $entityType)) : '';
    return $actionLabel . ($entityLabel ? ' ' . $entityLabel : '');
}
?>

<script>
/**
 * Preview avatar image before upload
 */
function previewAvatar(event) {
    const input = event.target;
    const preview = document.getElementById('avatarPreview');
    const previewImg = document.getElementById('avatarPreviewImg');
    const previewLetter = document.getElementById('avatarPreviewLetter');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (previewImg) {
                previewImg.src = e.target.result;
            } else {
                // Replace letter with image
                if (previewLetter) previewLetter.style.display = 'none';
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = 'Avatar Preview';
                img.id = 'avatarPreviewImg';
                preview.appendChild(img);
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Toggle password field visibility
 */
function togglePasswordVisibility(fieldId, btn) {
    const field = document.getElementById(fieldId);
    const icon = btn.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

/**
 * Check password strength and update meter
 */
function checkPasswordStrength(password) {
    const meter = document.getElementById('strengthMeter');
    const reqs = document.getElementById('passwordReqs');
    const segments = [
        document.getElementById('seg1'),
        document.getElementById('seg2'),
        document.getElementById('seg3'),
        document.getElementById('seg4')
    ];
    const strengthText = document.getElementById('strengthText');

    if (!password) {
        meter.style.display = 'none';
        reqs.style.display = 'none';
        return;
    }

    meter.style.display = 'block';
    reqs.style.display = 'block';

    // Check requirements
    const hasLength = password.length >= 8;
    const hasUpper  = /[A-Z]/.test(password);
    const hasLower  = /[a-z]/.test(password);
    const hasNumber = /[0-9]/.test(password);

    // Update requirement items
    updateReq('req-length', hasLength);
    updateReq('req-upper', hasUpper);
    updateReq('req-lower', hasLower);
    updateReq('req-number', hasNumber);

    // Calculate strength score
    let score = 0;
    if (hasLength) score++;
    if (hasUpper)  score++;
    if (hasLower)  score++;
    if (hasNumber) score++;

    // Bonus for longer passwords or special chars
    if (password.length >= 12) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    // Normalize to 1-4
    let level = 0;
    if (score <= 2) level = 1;
    else if (score === 3) level = 2;
    else if (score === 4) level = 3;
    else level = 4;

    // Reset segments
    segments.forEach(seg => {
        seg.className = 'strength-segment';
    });

    // Activate segments
    const levelClass = ['', 'weak', 'fair', 'good', 'strong'][level];
    const levelLabel = ['', 'Weak', 'Fair', 'Good', 'Strong'][level];
    const levelColor = ['', 'var(--danger)', 'var(--warning)', 'var(--info)', 'var(--success)'][level];

    for (let i = 0; i < level; i++) {
        segments[i].classList.add('active', levelClass);
    }

    strengthText.textContent = levelLabel;
    strengthText.style.color = levelColor;
}

function updateReq(id, met) {
    const el = document.getElementById(id);
    const icon = el.querySelector('i');
    if (met) {
        el.classList.add('met');
        icon.classList.remove('fa-circle');
        icon.classList.add('fa-check-circle');
    } else {
        el.classList.remove('met');
        icon.classList.remove('fa-check-circle');
        icon.classList.add('fa-circle');
    }
}

/**
 * Check if password and confirm password match
 */
function checkPasswordMatch() {
    const newPass = document.getElementById('newPassword').value;
    const confirmPass = document.getElementById('confirmPassword').value;
    const hint = document.getElementById('matchHint');

    if (!confirmPass) {
        hint.style.display = 'none';
        return;
    }

    hint.style.display = 'block';
    if (newPass === confirmPass) {
        hint.textContent = '✓ Passwords match';
        hint.style.color = 'var(--success)';
    } else {
        hint.textContent = '✗ Passwords do not match';
        hint.style.color = 'var(--danger)';
    }
}

/**
 * Validate change password form before submit
 */
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    const newPass = document.getElementById('newPassword').value;
    const confirmPass = document.getElementById('confirmPassword').value;

    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('New password and confirm password do not match.');
        return false;
    }
});
</script>

</body>
</html>
