<?php
require_once 'config.php';

// Logout
session_unset();
session_destroy();

// Go back to login
header("Location: login.php");
exit();
?>