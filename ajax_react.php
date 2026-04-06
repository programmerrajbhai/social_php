<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $post_id = $_POST['post_id'];
    $type = 'like'; 

    // Find Post Owner
    $stmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post_owner = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT id FROM comment_reactions WHERE comment_id = ? AND user_id = ?"); // Assuming it's post reaction actually, wait, the previous code had 'comment_reactions' or 'reactions'. Use the correct one.
    // For post reacts:
    $stmt = $pdo->prepare("SELECT id FROM reactions WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$post_id, $user_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->prepare("DELETE FROM reactions WHERE id = ?")->execute([$existing['id']]);
        $status = 'unliked';
    } else {
        $pdo->prepare("INSERT INTO reactions (post_id, user_id, reaction_type) VALUES (?, ?, ?)")->execute([$post_id, $user_id, $type]);
        $status = 'liked';
        
        // Send Notification if user is not liking their own post
        if($post_owner && $post_owner != $user_id) {
            $pdo->prepare("INSERT INTO notifications (receiver_id, sender_id, post_id, type) VALUES (?, ?, ?, 'like')")->execute([$post_owner, $user_id, $post_id]);
        }
    }

    $count = $pdo->prepare("SELECT COUNT(*) FROM reactions WHERE post_id = ?");
    $count->execute([$post_id]);
    echo json_encode(["total" => $count->fetchColumn(), "status" => $status]);
}
?>