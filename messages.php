<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) exit;
$user_id = $_SESSION['user_id'];

$chat_stmt = $pdo->prepare("
    SELECT u.id, u.name, u.profile_pic, u.city 
    FROM users u 
    JOIN friendships f ON (f.sender_id = u.id OR f.receiver_id = u.id)
    WHERE (f.sender_id = ? OR f.receiver_id = ?) AND f.status = 'accepted' AND u.id != ?
");
$chat_stmt->execute([$user_id, $user_id, $user_id]);
$chats = $chat_stmt->fetchAll();
?>

<div style="padding: 16px; color: #e4e6eb; padding-bottom: 80px;">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2 style="margin:0; font-size:26px; font-weight:800;">Chats</h2>
        <div style="display:flex; gap:15px; font-size:18px; color:#b0b3b8;">
            <div style="width:36px; height:36px; background:#3a3b3c; border-radius:50%; display:flex; justify-content:center; align-items:center; cursor:pointer;"><i class="fa-solid fa-camera"></i></div>
            <div style="width:36px; height:36px; background:#3a3b3c; border-radius:50%; display:flex; justify-content:center; align-items:center; cursor:pointer;"><i class="fa-solid fa-pen-to-square"></i></div>
        </div>
    </div>

    <div style="background:#242526; border-radius:20px; padding:10px 15px; display:flex; align-items:center; gap:10px; margin-bottom:20px; border: 1px solid #3a3b3c;">
        <i class="fa-solid fa-magnifying-glass" style="color:#b0b3b8;"></i>
        <input type="text" placeholder="Search" style="background:transparent; border:none; outline:none; color:#e4e6eb; width:100%; font-size:15px;">
    </div>
    
    <div style="display:flex; gap:15px; overflow-x:auto; padding-bottom:15px; margin-bottom:15px; scrollbar-width:none;">
        <?php foreach($chats as $c): $dp = $c['profile_pic'] ? $c['profile_pic'] : null; ?>
        <div style="text-align:center; flex-shrink:0; cursor:pointer;" onclick="window.location.href='chat.php?id=<?php echo $c['id']; ?>'">
            <div style="position:relative; width:55px; height:55px; margin:0 auto;">
                <?php if($dp): ?><img src="<?php echo $dp; ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;"><?php else: ?><div style="width:100%; height:100%; border-radius:50%; background:#3a3b3c; display:flex; justify-content:center; align-items:center; font-size:20px; font-weight:bold;"><?php echo strtoupper(substr($c['name'], 0, 1)); ?></div><?php endif; ?>
                <div style="position:absolute; bottom:2px; right:2px; width:14px; height:14px; background:#31a24c; border-radius:50%; border:2px solid #18191a;"></div>
            </div>
            <div style="font-size:12px; margin-top:6px; color:#e4e6eb;"><?php echo htmlspecialchars(explode(' ', $c['name'])[0]); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div>
        <?php 
        if (count($chats) == 0) echo "<div style='text-align:center; color:#b0b3b8; padding:40px;'>No friends available to chat.</div>";
        
        foreach($chats as $c): 
            $dp = $c['profile_pic'] ? $c['profile_pic'] : null;
            
            $last_msg_stmt = $pdo->prepare("SELECT message, sender_id, is_read, created_at FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at DESC LIMIT 1");
            $last_msg_stmt->execute([$user_id, $c['id'], $c['id'], $user_id]);
            $last_msg = $last_msg_stmt->fetch();
            
            $msg_text = "Say hi!";
            $msg_time = "";
            $is_my_msg = false;
            $is_unread = false;
            
            if ($last_msg) {
                $is_my_msg = ($last_msg['sender_id'] == $user_id);
                $prefix = $is_my_msg ? "You: " : "";
                $msg_text = $prefix . htmlspecialchars($last_msg['message']);
                if (strlen($msg_text) > 30) $msg_text = substr($msg_text, 0, 30) . '...';
                $msg_time = " • " . date('M j', strtotime($last_msg['created_at']));
                
                // যদি মেসেজটা ও পাঠায় এবং আমি না পড়ে থাকি (Unread Check)
                if (!$is_my_msg && $last_msg['is_read'] == 0) {
                    $is_unread = true;
                }
            }
            
            // Unread হলে Bold এবং সাদা রঙ হবে
            $text_style = $is_unread ? "font-weight:bold; color:#e4e6eb;" : "font-weight:normal; color:#b0b3b8;";
            $dot_html = $is_unread ? "<div style='width:12px; height:12px; background:#0084ff; border-radius:50%; margin-left:auto;'></div>" : "";
        ?>
        <div style="display:flex; align-items:center; gap:15px; margin-bottom:5px; cursor:pointer; padding:10px; border-radius:10px; transition:0.2s;" onmouseover="this.style.background='#242526'" onmouseout="this.style.background='transparent'" onclick="window.location.href='chat.php?id=<?php echo $c['id']; ?>'">
            
            <div style="position:relative; width:60px; height:60px; flex-shrink:0;">
                <?php if($dp): ?><img src="<?php echo $dp; ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;"><?php else: ?><div style="width:100%; height:100%; border-radius:50%; background:#3a3b3c; display:flex; justify-content:center; align-items:center; font-size:24px; font-weight:bold;"><?php echo strtoupper(substr($c['name'], 0, 1)); ?></div><?php endif; ?>
                <div style="position:absolute; bottom:3px; right:3px; width:15px; height:15px; background:#31a24c; border-radius:50%; border:2px solid #18191a;"></div>
            </div>
            
            <div style="flex:1; overflow:hidden;">
                <div style="font-weight:600; font-size:16px; color:#e4e6eb; margin-bottom:4px;"><?php echo htmlspecialchars($c['name']); ?></div>
                <div style="font-size:14px; display:flex; align-items:center; <?php echo $text_style; ?>">
                    <?php echo $msg_text . $msg_time; ?>
                </div>
            </div>
            
            <?php echo $dot_html; ?>
            
            <?php if($is_my_msg && !$is_unread): ?>
                <div style="color:#b0b3b8; font-size:12px; margin-left:10px;">
                    <?php if($last_msg['is_read'] == 1): ?>
                        <?php if($dp): ?>
                            <img src="<?php echo $dp; ?>" style="width:14px; height:14px; border-radius:50%; object-fit:cover;">
                        <?php else: ?>
                            <i class="fa-solid fa-circle-check" style="color:#b0b3b8;"></i>
                        <?php endif; ?>
                    <?php else: ?>
                        <i class="fa-solid fa-circle-check" style="color:#0084ff;"></i>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
        </div>
        <?php endforeach; ?>
    </div>
</div>