<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$first_name = explode(' ', $user_name)[0];

// ==========================================
// 1. Fetch Notifications
// ==========================================
$noti_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE receiver_id = ? AND is_read = 0");
$noti_count_stmt->execute([$user_id]);
$unread_count = $noti_count_stmt->fetchColumn();

$noti_stmt = $pdo->prepare("SELECT n.*, u.name FROM notifications n JOIN users u ON n.sender_id = u.id WHERE n.receiver_id = ? ORDER BY n.created_at DESC LIMIT 10");
$noti_stmt->execute([$user_id]);
$notifications = $noti_stmt->fetchAll();

// ==========================================
// 2. Fetch Network Data (Friends Tab)
// ==========================================
$city_stmt = $pdo->prepare("SELECT city FROM users WHERE id = ?");
$city_stmt->execute([$user_id]);
$my_city = $city_stmt->fetchColumn() ?: 'Dhaka';

$req_stmt = $pdo->prepare("SELECT f.sender_id, u.name, u.city FROM friendships f JOIN users u ON f.sender_id = u.id WHERE f.receiver_id = ? AND f.status = 'pending'");
$req_stmt->execute([$user_id]);
$requests = $req_stmt->fetchAll();

$nearby_friends_stmt = $pdo->prepare("
    SELECT u.id, u.name FROM users u 
    JOIN friendships f ON (f.sender_id = u.id OR f.receiver_id = u.id)
    WHERE u.city = ? AND u.id != ? AND f.status = 'accepted' AND (f.sender_id = ? OR f.receiver_id = ?)
");
$nearby_friends_stmt->execute([$my_city, $user_id, $user_id, $user_id]);
$nearby_friends = $nearby_friends_stmt->fetchAll();

$suggest_stmt = $pdo->prepare("
    SELECT id, name, city FROM users 
    WHERE id != ? AND id NOT IN (SELECT sender_id FROM friendships WHERE receiver_id = ?) AND id NOT IN (SELECT receiver_id FROM friendships WHERE sender_id = ?) LIMIT 10
");
$suggest_stmt->execute([$user_id, $user_id, $user_id]);
$suggestions = $suggest_stmt->fetchAll();

// ==========================================
// 3. Handle Post/Story Upload (Includes Video)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_type'])) {
    $targetDir = 'uploads/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    if ($_POST['post_type'] == 'story_upload' && isset($_FILES['story_image']) && $_FILES['story_image']['error'] == 0) {
        $ext = pathinfo($_FILES['story_image']['name'], PATHINFO_EXTENSION);
        $targetFile = $targetDir . 'story_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($_FILES['story_image']['tmp_name'], $targetFile)) {
            $pdo->prepare("INSERT INTO stories (user_id, media_url) VALUES (?, ?)")->execute([$user_id, $targetFile]);
        }
    }
    header("Location: index.php"); exit;
}

// ==========================================
// 4. Fetch Home Feed & Reels
// ==========================================
$stmt = $pdo->query("SELECT p.*, u.name, 
                    (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as total_reacts,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as total_comments,
                    (SELECT reaction_type FROM reactions WHERE post_id = p.id AND user_id = $user_id LIMIT 1) as my_react
                    FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
$posts = $stmt->fetchAll();

// Filter Reels (Only posts with videos)
$reels = array_filter($posts, function($p) { return !empty($p['video']); });

$stories = $pdo->query("SELECT s.*, u.name FROM stories s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 10")->fetchAll();

$react_icons = ['like'=>'👍 Like', 'dislike'=>'👎 Dislike', 'love'=>'❤️ Love', 'haha'=>'😂 Haha', 'wow'=>'😮 Wow', 'sad'=>'😢 Sad'];
$reels_react_icons = ['like'=>'👍', 'dislike'=>'👎', 'love'=>'❤️', 'haha'=>'😂', 'wow'=>'😮', 'sad'=>'😢'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Facebook | Dark UI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ================== Global Styles ================== */
        body { background-color: #18191a; color: #e4e6eb; font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 0; overflow-x: hidden;}
        * { box-sizing: border-box; }
        a { text-decoration: none; color: inherit; }
        .hidden-input { display: none; }
        
        .main-app-container { max-width: 600px; margin: 0 auto; background-color: #18191a; min-height: 100vh; position: relative; }

        /* ================== Navigations ================== */
        .top-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background-color: #242526; position: sticky; top: 0; z-index: 101;}
        .fb-text-logo { font-size: 28px; font-weight: 800; color: #e4e6eb; letter-spacing: -1px; }
        .top-nav-actions { display: flex; gap: 10px; }
        .nav-circle-btn { width: 36px; height: 36px; border-radius: 50%; background-color: #3a3b3c; display: flex; justify-content: center; align-items: center; font-size: 18px; color: #e4e6eb; cursor: pointer;}
        
        .tab-nav { display: flex; justify-content: space-between; padding: 0 10px; background-color: #242526; border-bottom: 1px solid #3a3b3c; position: sticky; top: 56px; z-index: 100;}
        .tab-btn { flex: 1; padding: 12px 0; text-align: center; color: #b0b3b8; font-size: 22px; cursor: pointer; position: relative; border-bottom: 3px solid transparent;}
        .tab-btn.active { color: #2d88ff; border-bottom: 3px solid #2d88ff; }
        .badge-red { position: absolute; top: 4px; right: 20%; background: #e41e3f; color: white; font-size: 10px; font-weight: bold; padding: 2px 5px; border-radius: 10px; border: 2px solid #242526; }

        .noti-dropdown { position: absolute; top: 50px; right: 0; width: 360px; background: #242526; border-radius: 8px; border: 1px solid #3a3b3c; box-shadow: 0 4px 12px rgba(0,0,0,0.5); display: none; flex-direction: column; z-index: 1000; cursor: default; text-align: left; }
        .noti-header { padding: 15px; font-size: 20px; font-weight: 700; color: #e4e6eb; }
        .noti-body { max-height: 400px; overflow-y: auto; }
        .noti-item { display: flex; gap: 12px; padding: 12px 15px; text-decoration: none; color: #e4e6eb; transition: 0.2s; align-items: center;}
        .noti-item:hover { background: #3a3b3c; }
        .noti-item.unread { background: rgba(45, 136, 255, 0.1); }
        .noti-small-icon { position: absolute; bottom: -2px; right: -2px; width: 24px; height: 24px; border-radius: 50%; color: white; display: flex; justify-content: center; align-items: center; font-size: 12px; border: 2px solid #242526; }

        /* ================== Components ================== */
        .card { background-color: #242526; margin-top: 8px; padding: 12px 0; }
        .default-avatar { border-radius: 50%; background-color: #3a3b3c; display: flex; justify-content: center; align-items: center; font-weight: bold; color: #e4e6eb; overflow: hidden;}
        
        .create-post-section { padding: 12px 16px; display: flex; align-items: center; gap: 12px; }
        .cp-input-box { flex: 1; background-color: #3a3b3c; padding: 10px 15px; border-radius: 20px; color: #b0b3b8; font-size: 15px; cursor: pointer; }
        
        .stories-wrapper { display: flex; gap: 8px; overflow-x: auto; padding: 0 16px; scrollbar-width: none; }
        .stories-wrapper::-webkit-scrollbar { display: none; }
        .story-card { width: 100px; height: 170px; border-radius: 12px; flex-shrink: 0; position: relative; overflow: hidden; background:#3a3b3c;}
        .post-media-container img, .post-media-container video { width: 100%; max-height: 600px; object-fit: cover; display: block; }
        
        .action-buttons { display: flex; justify-content: space-around; padding: 4px 16px; border-top: 1px solid #3a3b3c; margin-top: 10px; }
        .action-btn { flex: 1; display: flex; justify-content: center; align-items: center; gap: 6px; padding: 8px 0; background: transparent; border: none; color: #b0b3b8; font-size: 15px; font-weight: 600; cursor: pointer; }
        .react-active-like { color: #2d88ff !important; }

        /* ================== Network Tab CSS ================== */
        .network-container { padding: 0 16px 16px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin: 20px 0 10px; }
        .section-title { font-size: 20px; font-weight: 700; color: #e4e6eb; margin: 0;}
        .req-card { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .btn-confirm { flex: 1; background: #2d88ff; color: #fff; border: none; padding: 8px 0; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer;}
        .btn-delete { flex: 1; background: #3a3b3c; color: #e4e6eb; border: none; padding: 8px 0; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer;}
        .suggest-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }

        /* ================== REELS TAB CSS (Nested under Nav) ================== */
        .reels-container { height: calc(100vh - 110px); overflow-y: scroll; scroll-snap-type: y mandatory; scrollbar-width: none; background: #000; }
        .reels-container::-webkit-scrollbar { display: none; }
        .reel-item { width: 100%; height: 100%; scroll-snap-align: start; position: relative; display: flex; justify-content: center; align-items: center; background: #000; }
        .reel-video { width: 100%; height: 100%; object-fit: cover; cursor: pointer; }
        .reel-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 50%); pointer-events: none; }
        
        .reel-info { position: absolute; left: 15px; bottom: 20px; z-index: 10; max-width: 75%; color:#fff; text-shadow: 0 1px 3px rgba(0,0,0,0.8);}
        .reel-user-wrap { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-weight:bold; font-size:16px;}
        .reel-desc { font-size: 14px; line-height: 1.4; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        
        .reel-actions { position: absolute; right: 15px; bottom: 40px; display: flex; flex-direction: column; gap: 20px; align-items: center; z-index: 10; }
        .reel-action-btn { display: flex; flex-direction: column; align-items: center; gap: 5px; cursor: pointer; position: relative; color:#fff; text-shadow: 0 2px 4px rgba(0,0,0,0.8);}
        .reel-action-btn i { font-size: 28px; transition: transform 0.2s; }
        .reel-action-btn i:active { transform: scale(0.9); }
        .reel-action-btn span { font-size: 13px; font-weight: 600; }
        
        /* Reels Hover Box */
        .reel-react-box { position: absolute; right: 50px; bottom: 0; background: rgba(36, 37, 38, 0.9); backdrop-filter: blur(10px); border-radius: 30px; padding: 8px 15px; display: flex; gap: 15px; opacity: 0; visibility: hidden; transition: 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        .reel-action-btn:hover .reel-react-box { opacity: 1; visibility: visible; }
        .reel-react-box span { font-size: 26px; cursor: pointer; transition: 0.2s; }
        .reel-react-box span:hover { transform: scale(1.3) translateY(-5px); }

        .play-pause-indicator { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 60px; color: rgba(255,255,255,0.7); opacity: 0; transition: opacity 0.3s; pointer-events: none; z-index: 5; }
    </style>
</head>
<body>

    <div class="main-app-container">
        
        <div class="top-nav">
            <div class="fb-text-logo">facebook</div>
            <div class="top-nav-actions">
                <div class="nav-circle-btn" onclick="window.location.href='createpost.php'"><i class="fa-solid fa-plus"></i></div>
                <div class="nav-circle-btn"><i class="fa-solid fa-magnifying-glass"></i></div>
                <div class="nav-circle-btn" onclick="switchTab('menu')"><i class="fa-solid fa-bars"></i></div>
            </div>
        </div>

        <div class="tab-nav">
            <div class="tab-btn active" id="btn-home" onclick="switchTab('home')"><i class="fa-solid fa-house"></i></div>
            <div class="tab-btn" id="btn-network" onclick="switchTab('network')"><i class="fa-solid fa-user-group"></i></div>
            <div class="tab-btn" id="btn-messages" onclick="switchTab('messages')"><i class="fa-brands fa-facebook-messenger"></i></div>
            
            <div class="tab-btn" id="btn-noti" style="position:relative;" onclick="toggleNoti(event)">
                <i class="fa-solid fa-bell"></i>
                <?php if($unread_count > 0): ?><span class="badge-red" id="noti-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                
                <div class="noti-dropdown" id="noti-dropdown" onclick="event.stopPropagation();">
                    <div class="noti-header">Notifications</div>
                    <div class="noti-body">
                        <?php if(count($notifications) == 0): ?>
                            <div style="padding:15px; text-align:center; color:#b0b3b8;">No notifications yet.</div>
                        <?php else: ?>
                            <?php foreach($notifications as $noti): 
                                if($noti['type'] == 'like') { $action = "reacted to your post."; $n_icon = "fa-solid fa-thumbs-up"; $n_color = "#2d88ff"; }
                                elseif($noti['type'] == 'comment') { $action = "commented on your post."; $n_icon = "fa-solid fa-comment"; $n_color = "#31a24c"; }
                                elseif($noti['type'] == 'reply') { $action = "replied to your comment."; $n_icon = "fa-solid fa-reply"; $n_color = "#f5533d"; }
                            ?>
                                <a href="post.php?id=<?php echo $noti['post_id']; ?>" class="noti-item <?php echo $noti['is_read'] ? '' : 'unread'; ?>">
                                    <div style="position:relative; width:56px; height:56px; flex-shrink:0;">
                                        <div class="default-avatar" style="width:100%; height:100%; font-size:20px;"><?php echo strtoupper(substr($noti['name'],0,1)); ?></div>
                                        <div class="noti-small-icon" style="background:<?php echo $n_color; ?>;"><i class="<?php echo $n_icon; ?>"></i></div>
                                    </div>
                                    <div style="flex:1; font-size:14px;">
                                        <strong style="color:#e4e6eb;"><?php echo htmlspecialchars($noti['name']); ?></strong> <span style="color:#b0b3b8;"><?php echo $action; ?></span>
                                        <div style="color:#2d88ff; font-size:12px; margin-top:3px;"><?php echo date('M j, g:i a', strtotime($noti['created_at'])); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="tab-btn" id="btn-reels" onclick="switchTab('reels')"><i class="fa-solid fa-tv"></i></div>
            <div class="tab-btn" id="btn-menu" onclick="switchTab('menu')"><i class="fa-solid fa-bars"></i></div>
        </div>

        <div id="content-container">
            
            <div id="tab-home">
                <div class="card create-post-section">
                    <div style="position:relative; width:40px; height:40px;">
                        <div class="default-avatar" style="width:100%; height:100%; font-size:16px;"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                        <div style="position:absolute; bottom:0; right:0; width:12px; height:12px; background:#31a24c; border-radius:50%; border:2px solid #242526;"></div>
                    </div>
                    <div class="cp-input-box" onclick="window.location.href='createpost.php'">What's on your mind?</div>
                    <div class="cp-photo-btn" onclick="window.location.href='createpost.php'"><i class="fa-solid fa-image"></i><span style="font-size:11px; color:#b0b3b8;">Photo</span></div>
                </div>

                <div class="card" style="padding-bottom:12px;">
                    <div class="stories-wrapper">
                        <div class="story-card" style="background:#242526; border:1px solid #3a3b3c;" onclick="document.getElementById('story-upload-input').click();">
                            <div class="default-avatar" style="height:110px; width:100%; border-radius:0; font-size:40px;"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                            <div style="width:32px; height:32px; background:#2d88ff; border:3px solid #242526; border-radius:50%; position:absolute; top:94px; left:50%; transform:translateX(-50%); display:flex; justify-content:center; align-items:center; color:white; font-size:18px;"><i class="fa-solid fa-plus"></i></div>
                            <div style="text-align:center; font-size:12px; font-weight:600; margin-top:20px;">Create story</div>
                        </div>
                        <form id="story-form" method="POST" enctype="multipart/form-data" class="hidden-input">
                            <input type="hidden" name="post_type" value="story_upload">
                            <input type="file" name="story_image" id="story-upload-input" accept="image/*" onchange="document.getElementById('story-form').submit();">
                        </form>
                        <?php foreach($stories as $story): ?>
                            <div class="story-card">
                                <img src="<?php echo htmlspecialchars($story['media_url']); ?>" style="width:100%; height:100%; object-fit:cover;">
                                <div style="position:absolute; inset:0; background:linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 50%);"></div>
                                <div class="default-avatar" style="position:absolute; top:8px; left:8px; width:32px; height:32px; border:3px solid #2d88ff; z-index:2; font-size:14px;"><?php echo strtoupper(substr($story['name'], 0, 1)); ?></div>
                                <div style="position:absolute; bottom:8px; left:8px; color:white; font-size:12px; font-weight:600; z-index:2;"><?php echo htmlspecialchars($story['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php foreach ($posts as $post): ?>
                    <?php 
                        $active_class = $post['my_react'] ? "react-active-like" : "";
                        $display_text = $post['my_react'] ? $react_icons[$post['my_react']] : '<i class="fa-regular fa-thumbs-up"></i> Like';
                    ?>
                    <div class="card" id="post-<?php echo $post['id']; ?>">
                        <div class="post-header" style="display:flex; justify-content:space-between; align-items:center; padding:0 16px 10px;">
                            <div style="display:flex; gap:10px; align-items:center;">
                                <div class="default-avatar" style="width:40px; height:40px; font-size:16px;"><?php echo strtoupper(substr($post['name'], 0, 1)); ?></div>
                                <div>
                                    <h4 style="margin:0; font-size:15px; font-weight:600; color:#e4e6eb;"><a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['name']); ?></a></h4>
                                    <div style="font-size:13px; color:#b0b3b8; display:flex; align-items:center; gap:4px; margin-top:2px;">
                                        <a href="post.php?id=<?php echo $post['id']; ?>" style="color:#b0b3b8; text-decoration:none;"><?php echo date('M j, g:i a', strtotime($post['created_at'])); ?></a> • <i class="fa-solid fa-earth-americas" style="font-size:11px;"></i>
                                    </div>
                                </div>
                            </div>
                            <div style="color:#b0b3b8; font-size:18px; display:flex; gap:15px;"><i class="fa-solid fa-ellipsis"></i> <i class="fa-solid fa-xmark"></i></div>
                        </div>

                        <?php if(!empty($post['body'])): ?>
                            <div style="padding:0 16px 12px; font-size:15px; line-height:1.4; color:#e4e6eb;"><?php echo nl2br(htmlspecialchars($post['body'])); ?></div>
                        <?php endif; ?>

                        <?php if(!empty($post['image'])): ?>
                            <div class="post-media-container"><a href="post.php?id=<?php echo $post['id']; ?>"><img src="<?php echo htmlspecialchars($post['image']); ?>"></a></div>
                        <?php elseif(!empty($post['video'])): ?>
                            <div class="post-media-container"><video controls muted><source src="<?php echo htmlspecialchars($post['video']); ?>"></video></div>
                        <?php endif; ?>

                        <div style="display:flex; justify-content:space-between; padding:10px 16px; border-bottom:1px solid #3a3b3c; color:#b0b3b8; font-size:14px;">
                            <span style="display:flex; align-items:center; gap:5px;">
                                <i class="fa-solid fa-thumbs-up" style="color:#2d88ff; background:white; border-radius:50%; padding:2px;"></i>
                                <span id="react-count-<?php echo $post['id']; ?>"><?php echo $post['total_reacts']; ?></span>
                            </span>
                            <span><?php echo $post['total_comments']; ?> Comments</span>
                        </div>

                        <div class="action-buttons">
                            <button id="main-react-btn-<?php echo $post['id']; ?>" class="action-btn <?php echo $active_class; ?>" onclick="sendReact(<?php echo $post['id']; ?>, 'like', false)">
                                <?php echo $display_text; ?>
                            </button>
                            <button class="action-btn" onclick="window.location.href='post.php?id=<?php echo $post['id']; ?>'"><i class="fa-regular fa-comment"></i> Comment</button>
                            <button class="action-btn"><i class="fa-solid fa-share"></i> Share</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="tab-network" class="network-container" style="display: none;">
                <div style="display:flex; gap:10px; margin-top:15px; margin-bottom: 20px;">
                    <div style="background:#3a3b3c; padding:8px 16px; border-radius:20px; font-weight:600; font-size:15px;">Suggestions</div>
                    <div style="background:#3a3b3c; padding:8px 16px; border-radius:20px; font-weight:600; font-size:15px;">Your Friends</div>
                </div>

                <div class="section-header">
                    <h2 class="section-title">Friend requests <span style="color:#e41e3f; font-size:18px;"><?php echo count($requests); ?></span></h2>
                    <div style="color:#2d88ff; font-size:15px; cursor:pointer;">See all</div>
                </div>

                <?php if(count($requests) == 0): ?><p style="color:#b0b3b8; font-size:15px;">No new requests.</p><?php else: ?>
                    <?php foreach($requests as $req): ?>
                        <div class="req-card" id="req-card-<?php echo $req['sender_id']; ?>">
                            <div class="default-avatar" style="width:80px; height:80px; font-size:30px; flex-shrink:0;"><?php echo strtoupper(substr($req['name'], 0, 1)); ?></div>
                            <div style="flex:1;">
                                <div style="font-size:17px; font-weight:600; color:#e4e6eb; margin-bottom:4px;"><?php echo htmlspecialchars($req['name']); ?></div>
                                <div style="font-size:13px; color:#b0b3b8; margin-bottom:8px;"><?php echo htmlspecialchars($req['city'] ?: 'Bangladesh'); ?> • 4 mutual friends</div>
                                <div style="display:flex; gap:10px;">
                                    <button class="btn-confirm" onclick="handleRequest('accept_request', <?php echo $req['sender_id']; ?>)">Confirm</button>
                                    <button class="btn-delete" onclick="handleRequest('delete_request', <?php echo $req['sender_id']; ?>)">Delete</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div style="height:1px; background:#3a3b3c; margin:15px 0;"></div>

                <?php if(count($nearby_friends) > 0): ?>
                    <div class="section-header"><h2 class="section-title">Nearby Friends</h2></div>
                    <div style="display:flex; gap:15px; overflow-x:auto; padding-bottom:10px; scrollbar-width:none;">
                        <?php foreach($nearby_friends as $nf): ?>
                            <div style="width:75px; flex-shrink:0; text-align:center;">
                                <div style="width:70px; height:70px; margin:0 auto 8px; position:relative; border:2px solid #2d88ff; padding:2px; border-radius:50%;">
                                    <div class="default-avatar" style="width:100%; height:100%; font-size:28px;"><?php echo strtoupper(substr($nf['name'], 0, 1)); ?></div>
                                    <div style="position:absolute; bottom:2px; right:2px; width:14px; height:14px; background:#31a24c; border-radius:50%; border:2px solid #242526;"></div>
                                </div>
                                <div style="font-size:13px; font-weight:600; color:#e4e6eb; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($nf['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="height:1px; background:#3a3b3c; margin:15px 0;"></div>
                <?php endif; ?>

                <div class="section-header"><h2 class="section-title">People you may know</h2></div>
                <div class="suggest-grid">
                    <?php foreach($suggestions as $sg): ?>
                        <div style="background:#242526; border:1px solid #3a3b3c; border-radius:10px; overflow:hidden; padding-bottom:12px; text-align:center;">
                            <div class="default-avatar" style="width:100%; height:150px; font-size:50px; border-radius:0;"><?php echo strtoupper(substr($sg['name'], 0, 1)); ?></div>
                            <div style="padding:10px 12px 0;">
                                <div style="font-size:16px; font-weight:600; color:#e4e6eb; margin-bottom:4px;"><?php echo htmlspecialchars($sg['name']); ?></div>
                                <div style="font-size:13px; color:#b0b3b8; margin-bottom:12px;"><?php echo htmlspecialchars($sg['city'] ?: 'Dhaka'); ?></div>
                                <button id="add-btn-<?php echo $sg['id']; ?>" class="btn-confirm" style="width:100%; background:#3a3b3c; color:#e4e6eb;" onclick="sendFriendRequest(<?php echo $sg['id']; ?>)">
                                    <i class="fa-solid fa-user-plus"></i> Add Friend
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="tab-reels" class="reels-container" style="display: none;">
                <?php if(count($reels) == 0): ?>
                    <div style="display:flex; flex-direction:column; justify-content:center; align-items:center; height:100%; color:#b0b3b8; text-align:center; padding:20px;">
                        <i class="fa-solid fa-video-slash" style="font-size:60px; margin-bottom:20px; color:#3a3b3c;"></i>
                        <h3>No Reels Available</h3>
                        <p>Upload a video post to see it here!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($reels as $reel): ?>
                        <?php 
                            $is_liked = $reel['my_react'] ? true : false;
                            $icon_color = $is_liked ? "color: #2d88ff;" : "color: #fff;";
                            $display_icon = $is_liked ? $reels_react_icons[$reel['my_react']] : '<i class="fa-solid fa-thumbs-up"></i>';
                        ?>
                        <div class="reel-item" id="reel-<?php echo $reel['id']; ?>">
                            <video class="reel-video" loop playsinline onclick="togglePlayPause(this, <?php echo $reel['id']; ?>)">
                                <source src="<?php echo htmlspecialchars($reel['video']); ?>">
                            </video>
                            <i class="fa-solid fa-play play-pause-indicator" id="indicator-<?php echo $reel['id']; ?>"></i>
                            <div class="reel-overlay"></div>

                            <div class="reel-info">
                                <div class="reel-user-wrap">
                                    <div class="default-avatar" style="width:40px; height:40px; border:2px solid #fff;"><?php echo strtoupper(substr($reel['name'], 0, 1)); ?></div>
                                    <span style="font-size:16px; font-weight:bold; text-shadow:0 1px 2px rgba(0,0,0,0.8);"><?php echo htmlspecialchars($reel['name']); ?></span>
                                    <span style="border:1px solid #fff; padding:3px 10px; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; background:rgba(255,255,255,0.1); backdrop-filter:blur(5px);">Follow</span>
                                </div>
                                <?php if(!empty($reel['body'])): ?><div class="reel-desc"><?php echo htmlspecialchars($reel['body']); ?></div><?php endif; ?>
                            </div>

                            <div class="reel-actions">
                                <div class="action-btn-wrap">
                                    <div id="reel-react-btn-<?php echo $reel['id']; ?>" onclick="sendReact(<?php echo $reel['id']; ?>, 'like', true)" style="<?php echo $icon_color; ?> font-size:28px; text-shadow:0 2px 4px rgba(0,0,0,0.8);">
                                        <?php echo $display_icon; ?>
                                    </div>
                                    <span style="font-size:13px; font-weight:600; text-shadow:0 1px 2px rgba(0,0,0,0.8); color:#fff;" id="reel-react-count-<?php echo $reel['id']; ?>"><?php echo $reel['total_reacts']; ?></span>
                                    
                                    <div class="reel-react-box">
                                        <span onclick="sendReact(<?php echo $reel['id']; ?>, 'like', true)">👍</span>
                                        <span onclick="sendReact(<?php echo $reel['id']; ?>, 'love', true)">❤️</span>
                                        <span onclick="sendReact(<?php echo $reel['id']; ?>, 'haha', true)">😂</span>
                                    </div>
                                </div>
                                
                                <div class="action-btn-wrap" onclick="window.location.href='post.php?id=<?php echo $reel['id']; ?>'">
                                    <i class="fa-solid fa-comment-dots" style="font-size:28px; color:#fff; text-shadow:0 2px 4px rgba(0,0,0,0.8);"></i>
                                    <span style="font-size:13px; font-weight:600; text-shadow:0 1px 2px rgba(0,0,0,0.8); color:#fff;"><?php echo $reel['total_comments']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="dynamic-view" style="display:none; padding:16px;"></div>
            
        </div>
    </div>

    <script>
        const iconsMap = { 'like':'👍 Like', 'love':'❤️ Love', 'haha':'😂 Haha', 'wow':'😮 Wow', 'sad':'😢 Sad' };
        const reelsIconsMap = { 'like':'👍', 'love':'❤️', 'haha':'😂', 'wow':'😮', 'sad':'😢' };

        // 1. Dynamic Tab Switch Logic (Handling Home, Network, Reels, AJAX)
        function switchTab(tabName) {
            const homeView = document.getElementById('tab-home');
            const networkView = document.getElementById('tab-network');
            const reelsView = document.getElementById('tab-reels');
            const dynamicView = document.getElementById('dynamic-view');
            
            // Update Active Icon Class
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            const activeBtn = document.getElementById('btn-' + tabName);
            if(activeBtn) activeBtn.classList.add('active');

            // Hide all tabs
            homeView.style.display = 'none';
            networkView.style.display = 'none';
            reelsView.style.display = 'none';
            dynamicView.style.display = 'none';

            // Pause all videos when switching away
            document.querySelectorAll('video').forEach(v => v.pause());

            if(tabName === 'home') {
                homeView.style.display = 'block';
            } else if (tabName === 'network') {
                networkView.style.display = 'block';
            } else if (tabName === 'reels') {
                reelsView.style.display = 'block';
                // Auto play first reel
                let firstVisible = document.querySelector('.reel-item video');
                if(firstVisible) firstVisible.play().catch(e => console.log(e));
            } else {
                // AJAX Load for Messages & Menu
                dynamicView.style.display = 'block';
                dynamicView.innerHTML = '<div style="text-align:center; padding:50px; color:#b0b3b8;"><i class="fa-solid fa-spinner fa-spin" style="font-size:30px; margin-bottom:10px;"></i><br>Loading...</div>';
                fetch(tabName + '.php').then(res => {
                    if (!res.ok) throw new Error("File not found");
                    return res.text();
                }).then(html => { dynamicView.innerHTML = html; })
                .catch(err => { dynamicView.innerHTML = '<p style="text-align:center; padding:20px; color:#e41e3f;">Page not found: ' + tabName + '.php</p>'; });
            }
        }

        // 2. Reels Auto-Play Observer (TikTok Style)
        document.addEventListener("DOMContentLoaded", function() {
            const videos = document.querySelectorAll('.reel-video');
            const observerOptions = { root: document.getElementById('tab-reels'), rootMargin: '0px', threshold: 0.6 };
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    const video = entry.target;
                    if (entry.isIntersecting && document.getElementById('tab-reels').style.display === 'block') {
                        video.play().catch(e => console.log("Autoplay blocked"));
                    } else {
                        video.pause();
                        video.currentTime = 0;
                    }
                });
            }, observerOptions);
            videos.forEach(video => { observer.observe(video); });
        });

        // Reel Click Play/Pause
        function togglePlayPause(videoElement, reelId) {
            const indicator = document.getElementById('indicator-' + reelId);
            if (videoElement.paused) {
                videoElement.play();
                indicator.className = 'fa-solid fa-play play-pause-indicator';
            } else {
                videoElement.pause();
                indicator.className = 'fa-solid fa-pause play-pause-indicator';
            }
            indicator.style.opacity = '1';
            setTimeout(() => { indicator.style.opacity = '0'; }, 500);
        }

        // 3. Global Notification Dropdown
        function toggleNoti(event) {
            event.stopPropagation();
            let dropdown = document.getElementById('noti-dropdown');
            let badge = document.getElementById('noti-badge');
            
            if(dropdown.style.display === 'flex') {
                dropdown.style.display = 'none';
            } else {
                dropdown.style.display = 'flex';
                if(badge) {
                    badge.style.display = 'none';
                    fetch('ajax_read_noti.php').then(() => {
                        document.querySelectorAll('.noti-item.unread').forEach(item => { item.classList.remove('unread'); });
                    });
                }
            }
        }
        document.addEventListener('click', function() {
            let dropdown = document.getElementById('noti-dropdown');
            if (dropdown.style.display === 'flex') dropdown.style.display = 'none';
        });

        // 4. Universal React Logic (Handles both Home & Reels)
        function sendReact(postId, type, isReel = false) {
            event.stopPropagation();
            let formData = new FormData();
            formData.append('post_id', postId);
            formData.append('type', type);

            fetch('ajax_react.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(isReel) {
                    document.getElementById('reel-react-count-' + postId).innerText = data.total;
                    let iconBtn = document.getElementById('reel-react-btn-' + postId);
                    if(data.current_react === 'none') {
                        iconBtn.innerHTML = '<i class="fa-solid fa-thumbs-up"></i>';
                        iconBtn.style.color = '#fff';
                    } else {
                        iconBtn.innerHTML = reelsIconsMap[data.current_react];
                        iconBtn.style.color = '#2d88ff';
                    }
                } else {
                    document.getElementById('react-count-' + postId).innerText = data.total;
                    let btn = document.getElementById('main-react-btn-' + postId);
                    btn.className = 'action-btn'; 
                    if(data.current_react === 'none') {
                        btn.innerHTML = '<i class="fa-regular fa-thumbs-up"></i> Like';
                    } else {
                        btn.classList.add('react-active-like');
                        btn.innerHTML = iconsMap[data.current_react];
                    }
                }
            });
        }

        // 5. Network Tab Handlers
        function handleRequest(action, senderId) {
            let formData = new FormData(); formData.append('action', action); formData.append('target_id', senderId);
            fetch('ajax_network.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                let card = document.getElementById('req-card-' + senderId);
                card.innerHTML = `<div style="width:100%; text-align:center; padding:20px; color:#b0b3b8;">Request ${data.status === 'accepted' ? 'Accepted ✅' : 'Removed ❌'}</div>`;
                setTimeout(() => { card.style.display = 'none'; }, 1500);
            });
        }

        function sendFriendRequest(targetId) {
            let formData = new FormData(); formData.append('action', 'send_request'); formData.append('target_id', targetId);
            fetch('ajax_network.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'request_sent') {
                    let btn = document.getElementById('add-btn-' + targetId);
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Requested';
                    btn.disabled = true; btn.style.background = '#3a3b3c';
                }
            });
        }
    </script>
</body>
</html>