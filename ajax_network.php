<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $action = $_POST['action'];
    $target_id = $_POST['target_id'];

    if ($action === 'send_request') {
        $pdo->prepare("INSERT INTO friendships (sender_id, receiver_id, status) VALUES (?, ?, 'pending')")->execute([$user_id, $target_id]);
        echo json_encode(['status' => 'request_sent']);
    } 
    elseif ($action === 'accept_request') {
        $pdo->prepare("UPDATE friendships SET status = 'accepted' WHERE sender_id = ? AND receiver_id = ?")->execute([$target_id, $user_id]);
        // নোটিফিকেশন পাঠানো 
        $pdo->prepare("INSERT INTO notifications (receiver_id, sender_id, type) VALUES (?, ?, 'like')")->execute([$target_id, $user_id]); // type can be 'accept' if added to DB enum
        echo json_encode(['status' => 'accepted']);
    }
    elseif ($action === 'delete_request') {
        $pdo->prepare("DELETE FROM friendships WHERE sender_id = ? AND receiver_id = ?")->execute([$target_id, $user_id]);
        echo json_encode(['status' => 'deleted']);
    }
}
?>