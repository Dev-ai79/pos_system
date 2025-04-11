<?php
$password = "password123";
$hash = password_hash($password, PASSWORD_DEFAULT);
echo $hash;
?>