<?php
session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit;
// After logout
$auditIntegration->logAuthAction(
    'logout', 
    $user_id, 
    "User logged out"
);
?>

