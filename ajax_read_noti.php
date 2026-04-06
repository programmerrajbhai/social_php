<?php
session_start();
require 'db.php';
if (isset($_SESSION['user_id'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE receiver_id = ?")->execute([$_SESSION['user_id']]);
    echo "success";
}
?>