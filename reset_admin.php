<?php
// DesiVastra - Emergency Admin Password Reset
// Access: https://desivastra.in/reset_admin.php?key=dv_reset_2024

$secret = "dv_reset_2024";
if (($_GET["key"] ?? "") !== $secret) {
    die("<h2>Access Denied</h2>");
}

require_once __DIR__ . "/includes/functions.php";

$newPass  = "DesiVastra@2024";
$newHash  = password_hash($newPass, PASSWORD_BCRYPT);
$email    = "admin@desivastra.in";

try {
    $db = getDB();

    // Update existing admin password
    $stmt = $db->prepare("UPDATE `admins` SET `password`=?, `status`=1 WHERE `email`=?");
    $stmt->execute([$newHash, $email]);

    if ($stmt->rowCount() === 0) {
        // Admin doesn't exist — insert fresh
        $ins = $db->prepare("INSERT INTO `admins` (`name`,`email`,`password`,`role`,`status`) VALUES (?,?,?,?,1)");
        $ins->execute(["Super Admin", $email, $newHash, "super_admin"]);
        echo "<p style=\"color:green\">✓ Admin user CREATED</p>";
    } else {
        echo "<p style=\"color:green\">✓ Admin password UPDATED</p>";
    }

    echo "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>";
    echo "<p><strong>Password:</strong> " . htmlspecialchars($newPass) . "</p>";
    echo "<p><a href=\"/admin-login\">→ Go to Admin Login</a></p>";
    echo "<p style=\"color:red\"><strong>DELETE this file after use!</strong></p>";

} catch (Exception $e) {
    echo "<p style=\"color:red\">Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
