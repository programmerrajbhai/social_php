<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$first_name = explode(' ', $user_name)[0];

// ==========================================
// 0. Fetch Current User's Profile Picture
// ==========================================
$curr_user_stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
$curr_user_stmt->execute([$user_id]);
$current_user_dp = $curr_user_stmt->fetchColumn();

// ==========================================
// 1. Fetch Notifications
// ==========================================
$noti_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE receiver_id = ? AND is_read = 0");
$noti_count_stmt->execute([$user_id]);
$unread_count = $noti_count_stmt->fetchColumn();

$noti_stmt = $pdo->prepare("SELECT n.*, u.name, u.profile_pic as noti_dp FROM notifications n JOIN users u ON n.sender_id = u.id WHERE n.receiver_id = ? ORDER BY n.created_at DESC LIMIT 10");
$noti_stmt->execute([$user_id]);
$notifications = $noti_stmt->fetchAll();

// ==========================================
// 2. Fetch Network Data (Friends Tab)
// ==========================================
$city_stmt = $pdo->prepare("SELECT city FROM users WHERE id = ?");
$city_stmt->execute([$user_id]);
$my_city = $city_stmt->fetchColumn() ?: 'Dhaka';

$req_stmt = $pdo->prepare("SELECT f.sender_id, u.name, u.city, u.profile_pic FROM friendships f JOIN users u ON f.sender_id = u.id WHERE f.receiver_id = ? AND f.status = 'pending'");
$req_stmt->execute([$user_id]);
$requests = $req_stmt->fetchAll();

$nearby_friends_stmt = $pdo->prepare("
    SELECT u.id, u.name, u.profile_pic FROM users u 
    JOIN friendships f ON (f.sender_id = u.id OR f.receiver_id = u.id)
    WHERE u.city = ? AND u.id != ? AND f.status = 'accepted' AND (f.sender_id = ? OR f.receiver_id = ?)
");
$nearby_friends_stmt->execute([$my_city, $user_id, $user_id, $user_id]);
$nearby_friends = $nearby_friends_stmt->fetchAll();

$suggest_stmt = $pdo->prepare("
    SELECT id, name, city, profile_pic FROM users 
    WHERE id != ? AND id NOT IN (SELECT sender_id FROM friendships WHERE receiver_id = ?) AND id NOT IN (SELECT receiver_id FROM friendships WHERE sender_id = ?) LIMIT 10
");
$suggest_stmt->execute([$user_id, $user_id, $user_id]);
$suggestions = $suggest_stmt->fetchAll();

// ==========================================
// 3. Handle Story Upload
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_type']) && $_POST['post_type'] == 'story_upload') {
    if (isset($_FILES['story_image']) && $_FILES['story_image']['error'] == 0) {
        $ext = pathinfo($_FILES['story_image']['name'], PATHINFO_EXTENSION);
        $targetDir = 'uploads/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $targetFile = $targetDir . 'story_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($_FILES['story_image']['tmp_name'], $targetFile)) {
            $pdo->prepare("INSERT INTO stories (user_id, media_url) VALUES (?, ?)")->execute([$user_id, $targetFile]);
        }
    }
    header("Location: index.php"); exit;
}

// ==========================================
// 4. Fetch Home Feed & Posts
// ==========================================
// Added u.profile_pic as poster_dp to load actual avatars
$stmt = $pdo->query("SELECT p.*, u.name, u.profile_pic as poster_dp,
                    (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as total_reacts,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as total_comments,
                    (SELECT reaction_type FROM reactions WHERE post_id = p.id AND user_id = $user_id LIMIT 1) as my_react
                    FROM posts p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC");
$posts = $stmt->fetchAll();

$story_stmt = $pdo->query("SELECT s.*, u.name, u.profile_pic as story_dp FROM stories s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 10");
$stories = $story_stmt->fetchAll();

$react_icons = ['like'=>'👍 Like', 'dislike'=>'👎 Dislike', 'love'=>'❤️ Love', 'haha'=>'😂 Haha', 'wow'=>'😮 Wow', 'sad'=>'😢 Sad'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Facebook | Dark UI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Dark Mode Global Styles */
        body { background-color: #18191a; color: #e4e6eb; font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 0; }
        * { box-sizing: border-box; }
        a { text-decoration: none; color: inherit; }
        .hidden-input { display: none; }
        
        .main-app-container { max-width: 600px; margin: 0 auto; background-color: #18191a; min-height: 100vh; position: relative; }

        /* Top Nav */
        .top-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background-color: #242526; position: sticky; top: 0; z-index: 100;}
        .fb-text-logo { font-size: 28px; font-weight: 800; color: #e4e6eb; letter-spacing: -1px; }
        .top-nav-actions { display: flex; gap: 10px; }
        .nav-circle-btn { width: 36px; height: 36px; border-radius: 50%; background-color: #3a3b3c; display: flex; justify-content: center; align-items: center; font-size: 18px; color: #e4e6eb; cursor: pointer;}
        
        /* Tab Nav */
        .tab-nav { display: flex; justify-content: space-between; padding: 0 10px; background-color: #242526; border-bottom: 1px solid #3a3b3c; position: sticky; top: 56px; z-index: 99;}
        .tab-btn { flex: 1; padding: 12px 0; text-align: center; color: #b0b3b8; font-size: 22px; cursor: pointer; position: relative; border-bottom: 3px solid transparent;}
        .tab-btn.active { color: #2d88ff; border-bottom: 3px solid #2d88ff; }
        .badge-red { position: absolute; top: 4px; right: 20%; background: #e41e3f; color: white; font-size: 10px; font-weight: bold; padding: 2px 5px; border-radius: 10px; border: 2px solid #242526; }

        /* Notification Dropdown */
        .noti-dropdown { position: absolute; top: 50px; right: 0; width: 360px; background: #242526; border-radius: 8px; border: 1px solid #3a3b3c; box-shadow: 0 4px 12px rgba(0,0,0,0.5); display: none; flex-direction: column; z-index: 1000; cursor: default; text-align: left; }
        .noti-header { padding: 15px; font-size: 20px; font-weight: 700; color: #e4e6eb; }
        .noti-body { max-height: 400px; overflow-y: auto; }
        .noti-item { display: flex; gap: 12px; padding: 12px 15px; text-decoration: none; color: #e4e6eb; transition: 0.2s; align-items: center;}
        .noti-item:hover { background: #3a3b3c; }
        .noti-item.unread { background: rgba(45, 136, 255, 0.1); }
        .noti-small-icon { position: absolute; bottom: -2px; right: -2px; width: 24px; height: 24px; border-radius: 50%; color: white; display: flex; justify-content: center; align-items: center; font-size: 12px; border: 2px solid #242526; }

        /* Cards & Common */
        .card { background-color: #242526; margin-top: 8px; padding: 12px 0; }
        .default-avatar { border-radius: 50%; background-color: #3a3b3c; display: flex; justify-content: center; align-items: center; font-weight: bold; color: #e4e6eb; overflow: hidden;}
        .default-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        /* Create Post */
        .create-post-section { padding: 12px 16px; display: flex; align-items: center; gap: 12px; }
        .cp-input-box { flex: 1; background-color: #3a3b3c; padding: 10px 15px; border-radius: 20px; color: #b0b3b8; font-size: 15px; cursor: pointer; }
        .cp-photo-btn { display: flex; flex-direction: column; align-items: center; gap: 4px; cursor: pointer; padding-left: 10px;}
        .cp-photo-btn i { color: #45bd62; font-size: 20px; }

        /* Stories */
        .stories-wrapper { display: flex; gap: 8px; overflow-x: auto; padding: 0 16px; scrollbar-width: none; }
        .stories-wrapper::-webkit-scrollbar { display: none; }
        .story-card { width: 100px; height: 170px; border-radius: 12px; flex-shrink: 0; background-color: #3a3b3c; position: relative; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.2); cursor: pointer;}
        .story-bg { width: 100%; height: 100%; object-fit: cover; }
        .story-overlay { position: absolute; inset: 0; background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 50%); pointer-events: none;}
        .create-story-btn { width: 32px; height: 32px; background: #2d88ff; border: 3px solid #242526; border-radius: 50%; position: absolute; top: 94px; left: 50%; transform: translateX(-50%); display: flex; justify-content: center; align-items: center; color: white; font-size: 18px; }
        
        /* Post Feed */
        .post-header { display: flex; justify-content: space-between; align-items: center; padding: 0 16px 10px; }
        .post-user-details h4 { margin: 0; font-size: 15px; font-weight: 600; color: #e4e6eb; }
        .post-text { padding: 0 16px 12px; font-size: 15px; line-height: 1.4; color: #e4e6eb;}
        .post-media-container img, .post-media-container video { width: 100%; max-height: 600px; object-fit: cover; display: block; cursor: pointer;}
        
        .interaction-stats { display: flex; justify-content: space-between; padding: 10px 16px; border-bottom: 1px solid #3a3b3c; color: #b0b3b8; font-size: 14px; }
        .action-buttons { display: flex; justify-content: space-around; padding: 4px 16px; border-bottom: 1px solid #3a3b3c; }
        .action-btn { flex: 1; display: flex; justify-content: center; align-items: center; gap: 6px; padding: 8px 0; background: transparent; border: none; color: #b0b3b8; font-size: 15px; font-weight: 600; cursor: pointer; border-radius: 4px; }
        .action-btn:hover { background-color: #3a3b3c; }
        .react-active-like { color: #2d88ff !important; }

        /* Network Tab Custom UI */
        .network-container { padding: 0 16px 16px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin: 20px 0 10px; }
        .section-title { font-size: 20px; font-weight: 700; color: #e4e6eb; margin: 0;}
        
        .req-card { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .btn-confirm { flex: 1; background: #2d88ff; color: #fff; border: none; padding: 8px 0; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer;}
        .btn-delete { flex: 1; background: #3a3b3c; color: #e4e6eb; border: none; padding: 8px 0; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer;}
        
        .nearby-scroll-wrapper { display: flex; gap: 15px; overflow-x: auto; padding-bottom: 10px; scrollbar-width: none; }
        .suggest-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .suggest-card { background: #242526; border: 1px solid #3a3b3c; border-radius: 10px; overflow: hidden; padding-bottom: 12px; text-align: center;}
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
            <div class="tab-btn active" onclick="switchTab('home')" id="btn-home"><i class="fa-solid fa-house"></i></div>
            <div class="tab-btn" onclick="switchTab('network')" id="btn-network"><i class="fa-solid fa-user-group"></i></div>
            <div class="tab-btn" onclick="switchTab('messages')" id="btn-messages"><i class="fa-brands fa-facebook-messenger"></i></div>
            
            <div class="tab-btn" style="position:relative;" onclick="toggleNoti(event)">
                <i class="fa-solid fa-bell"></i>
                <?php if($unread_count > 0): ?>
                    <span class="badge-red" id="noti-badge" style="right:20%;"><?php echo $unread_count; ?></span>
                <?php endif; ?>
                
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
                                        <div class="default-avatar" style="width:100%; height:100%; font-size:20px;">
                                            <?php if($noti['noti_dp']): ?><img src="<?php echo htmlspecialchars($noti['noti_dp']); ?>"><?php else: ?><?php echo strtoupper(substr($noti['name'], 0, 1)); ?><?php endif; ?>
                                        </div>
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
            
            <div class="tab-btn" onclick="window.location.href='reels.php'"><i class="fa-solid fa-tv"></i></div>
            <div class="tab-btn" id="btn-menu" onclick="switchTab('menu')"><i class="fa-solid fa-bars"></i></div>
        </div>

        <div id="content-container">
            <div id="tab-home">
                
                <div class="card create-post-section">
                    <a href="profile.php?id=<?php echo $user_id; ?>" style="position:relative; width:40px; height:40px; display:block;">
                        <div class="default-avatar" style="width:100%; height:100%; font-size:16px;">
                            <?php if($current_user_dp): ?><img src="<?php echo htmlspecialchars($current_user_dp); ?>"><?php else: ?><?php echo strtoupper(substr($user_name, 0, 1)); ?><?php endif; ?>
                        </div>
                        <div style="position:absolute; bottom:0; right:0; width:12px; height:12px; background:#31a24c; border-radius:50%; border:2px solid #242526;"></div>
                    </a>
                    <div class="cp-input-box" onclick="window.location.href='createpost.php'">What's on your mind?</div>
                    <div class="cp-photo-btn" onclick="window.location.href='createpost.php'"><i class="fa-solid fa-image"></i><span style="font-size:11px; color:#b0b3b8;">Photo</span></div>
                </div>

                <div class="card" style="padding-bottom:12px;">
                    <div class="stories-wrapper">
                        <div class="story-card create-story-card" onclick="document.getElementById('story-upload-input').click();" style="background:#242526; border:1px solid #3a3b3c;">
                            <div class="default-avatar" style="height:110px; width:100%; border-radius:0; font-size:40px; background:#3a3b3c;">
                                <?php if($current_user_dp): ?><img src="<?php echo htmlspecialchars($current_user_dp); ?>"><?php else: ?><?php echo strtoupper(substr($user_name, 0, 1)); ?><?php endif; ?>
                            </div>
                            <div class="create-story-btn"><i class="fa-solid fa-plus"></i></div>
                            <div style="text-align:center; font-size:12px; font-weight:600; margin-top:20px; color:#fff;">Create story</div>
                        </div>
                        
                        <form id="story-form" method="POST" enctype="multipart/form-data" class="hidden-input">
                            <input type="hidden" name="post_type" value="story_upload">
                            <input type="file" name="story_image" id="story-upload-input" accept="image/*" onchange="document.getElementById('story-form').submit();">
                        </form>

                        <?php foreach($stories as $story): ?>
                            <div class="story-card" onclick="window.location.href='profile.php?id=<?php echo $story['user_id']; ?>'">
                                <img src="<?php echo htmlspecialchars($story['media_url']); ?>" class="story-bg">
                                <div class="story-overlay"></div>
                                <div class="default-avatar" style="position:absolute; top:8px; left:8px; width:32px; height:32px; border:3px solid #2d88ff; z-index:2; font-size:14px;">
                                    <?php if($story['story_dp']): ?><img src="<?php echo htmlspecialchars($story['story_dp']); ?>"><?php else: ?><?php echo strtoupper(substr($story['name'], 0, 1)); ?><?php endif; ?>
                                </div>
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
                        
                        <div class="post-header">
                            <div style="display:flex; gap:10px; align-items:center;">
                                <a href="profile.php?id=<?php echo $post['user_id']; ?>" style="text-decoration:none;">
                                    <div class="default-avatar" style="width:40px; height:40px; font-size:16px;">
                                        <?php if($post['poster_dp']): ?><img src="<?php echo htmlspecialchars($post['poster_dp']); ?>"><?php else: ?><?php echo strtoupper(substr($post['name'], 0, 1)); ?><?php endif; ?>
                                    </div>
                                </a>
                                <div>
                                    <h4 style="margin:0; font-size:15px; font-weight:600;">
                                        <a href="profile.php?id=<?php echo $post['user_id']; ?>" style="color:#e4e6eb; text-decoration:none;"><?php echo htmlspecialchars($post['name']); ?></a>
                                    </h4>
                                    <div style="font-size:13px; color:#b0b3b8; display:flex; align-items:center; gap:4px; margin-top:2px;">
                                        <a href="post.php?id=<?php echo $post['id']; ?>" style="color:#b0b3b8; text-decoration:none;"><?php echo date('M j, g:i a', strtotime($post['created_at'])); ?></a> • <i class="fa-solid fa-earth-americas" style="font-size:11px;"></i>
                                    </div>
                                </div>
                            </div>
                            <div style="color:#b0b3b8; font-size:18px; display:flex; gap:15px;"><i class="fa-solid fa-ellipsis"></i></div>
                        </div>

                        <?php if(!empty($post['body'])): ?>
                            <div class="post-text"><?php echo nl2br(htmlspecialchars($post['body'])); ?></div>
                        <?php endif; ?>

                        <?php if(!empty($post['image'])): ?>
                            <div class="post-media-container">
                                <a href="post.php?id=<?php echo $post['id']; ?>"><img src="<?php echo htmlspecialchars($post['image']); ?>"></a>
                            </div>
                        <?php elseif(!empty($post['video'])): ?>
                            <div class="post-media-container"><video controls muted><source src="<?php echo htmlspecialchars($post['video']); ?>"></video></div>
                        <?php endif; ?>

                        <div class="interaction-stats">
                            <span style="display:flex; align-items:center; gap:5px;">
                                <i class="fa-solid fa-thumbs-up" style="color:#2d88ff; background:white; border-radius:50%; padding:2px;"></i>
                                <span id="react-count-<?php echo $post['id']; ?>"><?php echo $post['total_reacts']; ?></span>
                            </span>
                            <span><?php echo $post['total_comments']; ?> Comments</span>
                        </div>

                        <div class="action-buttons">
                            <button id="main-react-btn-<?php echo $post['id']; ?>" class="action-btn <?php echo $active_class; ?>" onclick="sendReact(<?php echo $post['id']; ?>, 'like')">
                                <?php echo $display_text; ?>
                            </button>
                            <button class="action-btn" onclick="window.location.href='post.php?id=<?php echo $post['id']; ?>'"><i class="fa-regular fa-comment"></i> Comment</button>
                            <button class="action-btn" onclick="alert('Share feature active!')"><i class="fa-solid fa-share"></i> Share</button>
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

                <?php if(count($requests) == 0): ?>
                    <p style="color:#b0b3b8; font-size:15px;">No new requests.</p>
                <?php else: ?>
                    <?php foreach($requests as $req): ?>
                        <div class="req-card" id="req-card-<?php echo $req['sender_id']; ?>">
                            <a href="profile.php?id=<?php echo $req['sender_id']; ?>" style="text-decoration:none;">
                                <div class="default-avatar" style="width:80px; height:80px; font-size:30px; flex-shrink:0;">
                                    <?php if($req['profile_pic']): ?><img src="<?php echo htmlspecialchars($req['profile_pic']); ?>"><?php else: ?><?php echo strtoupper(substr($req['name'], 0, 1)); ?><?php endif; ?>
                                </div>
                            </a>
                            <div style="flex:1;">
                                <div style="font-size:17px; font-weight:600; margin-bottom:4px;">
                                    <a href="profile.php?id=<?php echo $req['sender_id']; ?>" style="color:#e4e6eb; text-decoration:none;"><?php echo htmlspecialchars($req['name']); ?></a>
                                </div>
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
                                <a href="profile.php?id=<?php echo $nf['id']; ?>" style="text-decoration:none;">
                                    <div style="width:70px; height:70px; margin:0 auto 8px; position:relative; border:2px solid #2d88ff; padding:2px; border-radius:50%;">
                                        <div class="default-avatar" style="width:100%; height:100%; font-size:28px;">
                                            <?php if($nf['profile_pic']): ?><img src="<?php echo htmlspecialchars($nf['profile_pic']); ?>"><?php else: ?><?php echo strtoupper(substr($nf['name'], 0, 1)); ?><?php endif; ?>
                                        </div>
                                        <div style="position:absolute; bottom:2px; right:2px; width:14px; height:14px; background:#31a24c; border-radius:50%; border:2px solid #242526;"></div>
                                    </div>
                                    <div style="font-size:13px; font-weight:600; color:#e4e6eb; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($nf['name']); ?></div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="height:1px; background:#3a3b3c; margin:15px 0;"></div>
                <?php endif; ?>

                <div class="section-header"><h2 class="section-title">People you may know</h2></div>
                <div class="suggest-grid">
                    <?php foreach($suggestions as $sg): ?>
                        <div class="suggest-card">
                            <a href="profile.php?id=<?php echo $sg['id']; ?>" style="text-decoration:none;">
                                <div class="default-avatar" style="width:100%; height:150px; font-size:50px; border-radius:0;">
                                    <?php if($sg['profile_pic']): ?><img src="<?php echo htmlspecialchars($sg['profile_pic']); ?>"><?php else: ?><?php echo strtoupper(substr($sg['name'], 0, 1)); ?><?php endif; ?>
                                </div>
                                <div style="padding:10px 12px 0;">
                                    <div style="font-size:16px; font-weight:600; color:#e4e6eb; margin-bottom:4px;"><?php echo htmlspecialchars($sg['name']); ?></div>
                                    <div style="font-size:13px; color:#b0b3b8; margin-bottom:12px;"><?php echo htmlspecialchars($sg['city'] ?: 'Dhaka'); ?></div>
                                </div>
                            </a>
                            <div style="padding:0 12px 12px;">
                                <button id="add-btn-<?php echo $sg['id']; ?>" class="btn-confirm" style="width:100%; background:#3a3b3c; color:#e4e6eb;" onclick="sendFriendRequest(<?php echo $sg['id']; ?>)">
                                    <i class="fa-solid fa-user-plus"></i> Add Friend
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="dynamic-view" style="display:none; padding:16px;"></div>
            
        </div>
    </div>

    <script>
        const iconsMap = { 'like':'👍 Like', 'love':'❤️ Love', 'haha':'😂 Haha', 'wow':'😮 Wow', 'sad':'😢 Sad' };

        // Tab Navigation
        function switchTab(tabName) {
            const homeView = document.getElementById('tab-home');
            const networkView = document.getElementById('tab-network');
            const dynamicView = document.getElementById('dynamic-view');
            
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            const activeBtn = document.getElementById('btn-' + tabName);
            if(activeBtn) activeBtn.classList.add('active');

            homeView.style.display = 'none';
            networkView.style.display = 'none';
            dynamicView.style.display = 'none';

            if(tabName === 'home') {
                homeView.style.display = 'block';
            } else if (tabName === 'network') {
                networkView.style.display = 'block';
            } else {
                dynamicView.style.display = 'block';
                dynamicView.innerHTML = '<div style="text-align:center; padding:50px; color:#b0b3b8;"><i class="fa-solid fa-spinner fa-spin" style="font-size:30px; margin-bottom:10px;"></i><br>Loading...</div>';
                
                fetch(tabName + '.php').then(res => {
                    if (!res.ok) throw new Error("File not found");
                    return res.text();
                }).then(html => { dynamicView.innerHTML = html; })
                .catch(err => { dynamicView.innerHTML = '<p style="text-align:center; color:#e41e3f;">Coming soon...</p>'; });
            }
        }

        // Notification System
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

        // React
        function sendReact(postId, type) {
            let formData = new FormData();
            formData.append('post_id', postId);
            formData.append('type', type);
            fetch('ajax_react.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                document.getElementById('react-count-' + postId).innerText = data.total;
                let btn = document.getElementById('main-react-btn-' + postId);
                btn.className = 'action-btn'; 
                if(data.current_react === 'none') {
                    btn.innerHTML = '<i class="fa-regular fa-thumbs-up"></i> Like';
                } else {
                    btn.classList.add('react-active-like');
                    btn.innerHTML = iconsMap[data.current_react];
                }
            });
        }

        // Friend Requests
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