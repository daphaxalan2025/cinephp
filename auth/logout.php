<?php
// auth/logout.php
require_once '../includes/functions.php';

logoutUser();
setFlash('You have been logged out', 'info');
header('Location: ../index.php');
exit;
?>