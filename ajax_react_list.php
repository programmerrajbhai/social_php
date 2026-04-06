<?php
require 'db.php';
if (isset($_GET['post_id'])) {
    $stmt = $pdo->prepare("SELECT u.name, r.reaction_type FROM reactions r JOIN users u ON r.user_id = u.id WHERE r.post_id = ?");
    $stmt->execute([$_GET['post_id']]);
    $reacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($reacts);
}
?>