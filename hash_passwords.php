<?php
$passwords = [
    'manager123',
    'cashier123',
    'waiter123'
];

foreach ($passwords as $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "Password: $password<br>";
    echo "Hash: $hash<br><br>";
}
?>