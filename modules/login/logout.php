<?php
session_start();
session_destroy();
header("Location: login.php"); // Cambiado de login.php a login.html
exit;
?>
