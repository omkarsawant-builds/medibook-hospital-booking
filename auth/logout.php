<?php
require_once '../includes/db.php';
session_destroy();
header("Location: ../auth/login.php");
exit();
?>
