<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) exit;
$user_id = $_SESSION['user_id'];

// ==========================================
// 1. Send Message
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'send_msg') {
    $receiver = intval($_POST['receiver_id']);
    $msg = htmlspecialchars(trim($_POST['message']));
    
    if (!empty($msg)) {
        // নতুন মেসেজ পাঠালে is_read = 0 (Unseen) থাকবে
        $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_read) VALUES (?, ?, ?, 0)")->execute([$user_id, $receiver, $msg]);
        echo json_encode(['status' => 'success']);
    }
    exit;
}

// ==========================================
// 2. Fetch Messages & Mark as Read
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] == 'fetch_msgs') {
    $target = intval($_GET['target_id']);
    
    // (A) আমি চ্যাট ওপেন করেছি, তাই ওর পাঠানো সব মেসেজ 'Seen' (1) করে দাও
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")->execute([$target, $user_id]);

    // (B) টার্গেট ইউজারের প্রোফাইল পিকচার নিয়ে আসো (Seen Icon দেখানোর জন্য)
    $t_stmt = $pdo->prepare("SELECT name, profile_pic FROM users WHERE id = ?");
    $t_stmt->execute([$target]);
    $t_user = $t_stmt->fetch();
    $t_dp = $t_user['profile_pic'] ? $t_user['profile_pic'] : null;
    $t_initial = strtoupper(substr($t_user['name'], 0, 1));
    
    // (C) সব মেসেজ নিয়ে আসো
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $stmt->execute([$user_id, $target, $target, $user_id]);
    $msgs = $stmt->fetchAll();
    
    $html = '';
    $total_msgs = count($msgs);
    $last_my_msg_index = -1;
    
    // আমার পাঠানো সর্বশেষ মেসেজটি বের করা (যাতে শুধু লাস্ট মেসেজেই Seen/Unseen দেখায়)
    for($i = $total_msgs - 1; $i >= 0; $i--) {
        if($msgs[$i]['sender_id'] == $user_id) {
            $last_my_msg_index = $i;
            break;
        }
    }

    foreach($msgs as $index => $m) {
        if ($m['sender_id'] == $user_id) {
            // MY MESSAGE (Right Side)
            $status_html = '';
            
            if ($index === $last_my_msg_index) {
                if ($m['is_read'] == 1) {
                    // SEEN: Show receiver's mini profile pic
                    if ($t_dp) {
                        $status_html = "<img src='{$t_dp}' style='width:14px; height:14px; border-radius:50%; object-fit:cover; margin-left:5px; align-self:flex-end; margin-bottom:2px;' title='Seen'>";
                    } else {
                        $status_html = "<div style='width:14px; height:14px; border-radius:50%; background:#3a3b3c; font-size:8px; display:flex; align-items:center; justify-content:center; margin-left:5px; align-self:flex-end; margin-bottom:2px; color:#fff;' title='Seen'>{$t_initial}</div>";
                    }
                } else {
                    // SENT / DELIVERED: Show Blue Tick
                    $status_html = "<i class='fa-solid fa-circle-check' style='color:#0084ff; font-size:14px; margin-left:5px; align-self:flex-end; margin-bottom:2px;' title='Delivered'></i>";
                }
            }
            
            $html .= "<div class='msg-row my-msg-row' style='display:flex; justify-content:flex-end; margin-bottom:4px;'><div class='msg-bubble my-msg'>" . nl2br($m['message']) . "</div>{$status_html}</div>";
        } else {
            // THEIR MESSAGE (Left Side)
            $html .= "<div class='msg-row their-msg-row' style='display:flex; margin-bottom:4px;'><div class='msg-bubble their-msg'>" . nl2br($m['message']) . "</div></div>";
        }
    }
    echo $html;
    exit;
}
?>