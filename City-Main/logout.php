<?php
session_start();

// Destroy session fully
$_SESSION = [];
session_unset();
session_destroy();

// Redirect to login page
header("Location: /-CITY-CARE/Forms/login.php");
exit;
