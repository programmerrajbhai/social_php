<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $comment_id = $_POST['comment_id'];
    $type = 'like'; // Default Facebook just has like for comments, but you can expand

    $stmt = $pdo->prepare("SELECT id FROM comment_reactions WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$comment_id, $user_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Unlike
        $pdo->prepare("DELETE FROM comment_reactions WHERE id = ?")->execute([$existing['id']]);
        $status = 'unliked';
    } else {
        // Like
        $pdo->prepare("INSERT INTO comment_reactions (comment_id, user_id, reaction_type) VALUES (?, ?, ?)")->execute([$comment_id, $user_id, $type]);
        $status = 'liked';
    }

    $count = $pdo->prepare("SELECT COUNT(*) FROM comment_reactions WHERE comment_id = ?");
    $count->execute([$comment_id]);
    $total = $count->fetchColumn();

    echo json_encode(["total" => $total, "status" => $status]);
}
?>