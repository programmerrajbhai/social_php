<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $post_id = $_POST['post_id'];
    $text = htmlspecialchars($_POST['comment_text'] ?? '');
    $parent_id = (isset($_POST['parent_id']) && !empty($_POST['parent_id'])) ? $_POST['parent_id'] : null;
    
    $imagePath = null;
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    
    if (isset($_FILES['comment_image']) && $_FILES['comment_image']['error'] == 0) {
        $ext = pathinfo($_FILES['comment_image']['name'], PATHINFO_EXTENSION);
        $targetFile = $targetDir . 'comment_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($_FILES['comment_image']['tmp_name'], $targetFile)) $imagePath = $targetFile;
    }

    if (!empty(trim($text)) || $imagePath) {
        $pdo->prepare("INSERT INTO comments (post_id, parent_id, user_id, comment_text, image) VALUES (?, ?, ?, ?, ?)")->execute([$post_id, $parent_id, $user_id, $text, $imagePath]);
        
        // --- Notification Logic ---
        $post_owner = $pdo->query("SELECT user_id FROM posts WHERE id = $post_id")->fetchColumn();
        
        if ($parent_id) {
            // It's a reply: Notify the original comment owner
            $comment_owner = $pdo->query("SELECT user_id FROM comments WHERE id = $parent_id")->fetchColumn();
            if($comment_owner && $comment_owner != $user_id) {
                $pdo->prepare("INSERT INTO notifications (receiver_id, sender_id, post_id, type) VALUES (?, ?, ?, 'reply')")->execute([$comment_owner, $user_id, $post_id]);
            }
        } else {
            // It's a comment: Notify post owner
            if($post_owner && $post_owner != $user_id) {
                $pdo->prepare("INSERT INTO notifications (receiver_id, sender_id, post_id, type) VALUES (?, ?, ?, 'comment')")->execute([$post_owner, $user_id, $post_id]);
            }
        }
        
        echo json_encode(["status" => "success"]);
    }
}
?>