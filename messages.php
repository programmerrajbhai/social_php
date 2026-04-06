<?php
session_start();
require 'db.php';
$user_id = $_SESSION['user_id'];

// যারা বন্ধু তাদের চ্যাট লিস্টে দেখাবে
$chat_stmt = $pdo->prepare("SELECT u.id, u.name, u.city FROM users u 
    JOIN friendships f ON (f.sender_id = u.id OR f.receiver_id = u.id)
    WHERE (f.sender_id = ? OR f.receiver_id = ?) AND f.status = 'accepted' AND u.id != ?");
$chat_stmt->execute([$user_id, $user_id, $user_id]);
$chats = $chat_stmt->fetchAll();
?>
<div style="padding: 16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0;">Chats</h2>
        <div style="display:flex; gap:15px; font-size:20px; color:#b0b3b8;">
            <i class="fa-solid fa-camera"></i>
            <i class="fa-solid fa-pen-to-square"></i>
        </div>
    </div>
    
    <div style="display:flex; gap:15px; overflow-x:auto; padding-bottom:15px; margin-bottom:15px; border-bottom:1px solid #3a3b3c;">
        <div style="text-align:center;">
            <div style="width:55px; height:55px; background:#3a3b3c; border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:24px;">
                <i class="fa-solid fa-plus"></i>
            </div>
            <div style="font-size:11px; margin-top:5px; color:#b0b3b8;">Your story</div>
        </div>
        <?php foreach($chats as $c): ?>
        <div style="text-align:center;">
            <div style="position:relative; width:55px; height:55px;">
                <div class="default-avatar" style="width:100%; height:100%; font-size:18px;"><?php echo strtoupper(substr($c['name'], 0, 1)); ?></div>
                <div style="position:absolute; bottom:2px; right:2px; width:14px; height:14px; background:#31a24c; border-radius:50%; border:2px solid #18191a;"></div>
            </div>
            <div style="font-size:11px; margin-top:5px; color:#e4e6eb;"><?php echo explode(' ', $c['name'])[0]; ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php foreach($chats as $c): ?>
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:15px; cursor:pointer;" onclick="alert('Chat with <?php echo $c['name']; ?> coming soon!')">
        <div style="position:relative; width:60px; height:60px;">
            <div class="default-avatar" style="width:100%; height:100%; font-size:22px;"><?php echo strtoupper(substr($c['name'], 0, 1)); ?></div>
            <div style="position:absolute; bottom:3px; right:3px; width:15px; height:15px; background:#31a24c; border-radius:50%; border:2px solid #18191a;"></div>
        </div>
        <div style="flex:1;">
            <div style="font-weight:600; color:#e4e6eb;"><?php echo htmlspecialchars($c['name']); ?></div>
            <div style="font-size:13px; color:#b0b3b8; display:flex; justify-content:space-between;">
                <span>Active 5m ago</span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>