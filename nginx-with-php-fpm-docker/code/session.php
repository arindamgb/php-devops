<?php
session_start();
$_SESSION['test'] = 'Hello, Session!';
echo "Session is working. Value: " . $_SESSION['test'];
?>
