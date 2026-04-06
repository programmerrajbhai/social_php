<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$current_user_id = $_SESSION['user_id'];

// Get Profile ID
$profile_id = isset($_GET['id']) ? intval($_GET['id']) : $current_user_id;
$is_own_profile = ($profile_id === $current_user_id);

// ==========================================
// Handle Profile Lock / Unlock Toggle
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_lock' && $is_own_profile) {
    $new_status = $_POST['current_lock_status'] == 1 ? 0 : 1;
    $pdo->prepare("UPDATE users SET is_locked = ? WHERE id = ?")->execute([$new_status, $current_user_id]);
    header("Location: profile.php?id=" . $current_user_id); exit;
}

// ==========================================
// Handle Edit Profile Submission
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_profile' && $is_own_profile) {
    $name = htmlspecialchars($_POST['name']);
    $bio = htmlspecialchars($_POST['bio']);
    $city = htmlspecialchars($_POST['city']);
    $workplace = htmlspecialchars($_POST['workplace'] ?? '');
    $education = htmlspecialchars($_POST['education'] ?? '');
    
    $profile_pic = $_POST['old_profile_pic'] ?? null;
    $cover_pic = $_POST['old_cover_pic'] ?? null;
    
    $targetDir = 'uploads/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $profile_pic = $targetDir . 'dp_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['profile_pic']['tmp_name'], $profile_pic);
    }
    
    if (isset($_FILES['cover_pic']) && $_FILES['cover_pic']['error'] == 0) {
        $ext = pathinfo($_FILES['cover_pic']['name'], PATHINFO_EXTENSION);
        $cover_pic = $targetDir . 'cover_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['cover_pic']['tmp_name'], $cover_pic);
    }

    $pdo->prepare("UPDATE users SET name=?, bio=?, city=?, workplace=?, education=?, profile_pic=?, cover_pic=? WHERE id=?")
        ->execute([$name, $bio, $city, $workplace, $education, $profile_pic, $cover_pic, $current_user_id]);
    
    $_SESSION['user_name'] = $name;
    header("Location: profile.php?id=" . $current_user_id); exit;
}

// ==========================================
// Fetch Profile User Data
// ==========================================
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$profile_id]);
$user = $stmt->fetch();
if (!$user) { echo "<h2 style='color:#fff; text-align:center;'>User not found! <a href='index.php'>Go Back</a></h2>"; exit; }

$p_first_name = explode(' ', $user['name'])[0];
$is_locked = isset($user['is_locked']) ? $user['is_locked'] : 0;

// ==========================================
// Check Friendship Status & Access Logic
// ==========================================
$is_friend = false;
if (!$is_own_profile) {
    $check_friend = $pdo->prepare("SELECT status FROM friendships WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) AND status = 'accepted'");
    $check_friend->execute([$current_user_id, $profile_id, $profile_id, $current_user_id]);
    if ($check_friend->rowCount() > 0) {
        $is_friend = true;
    }
}

// CAN VIEW LOGIC (The core of Profile Lock)
$can_view_profile = true;
if (!$is_own_profile && $is_locked == 1 && !$is_friend) {
    $can_view_profile = false; // Lock the profile!
}

// Friend Count
$friend_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM friendships WHERE (sender_id = ? OR receiver_id = ?) AND status = 'accepted'");
$friend_count_stmt->execute([$profile_id, $profile_id]);
$real_friend_count = $friend_count_stmt->fetchColumn();

// Fallback images
$dp = $user['profile_pic'] ? $user['profile_pic'] : null;
$cover = $user['cover_pic'] ? $user['cover_pic'] : 'https://images.unsplash.com/photo-1504805572947-34fad45aed93?w=600'; 

// Fetch Posts (Only if can view)
$posts = [];
if ($can_view_profile) {
    $post_stmt = $pdo->prepare("SELECT p.*, u.name, u.profile_pic as poster_dp,
                               (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as total_reacts,
                               (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as total_comments,
                               (SELECT reaction_type FROM reactions WHERE post_id = p.id AND user_id = ? LIMIT 1) as my_react
                               FROM posts p JOIN users u ON p.user_id = u.id 
                               WHERE p.user_id = ? ORDER BY p.created_at DESC");
    $post_stmt->execute([$current_user_id, $profile_id]);
    $posts = $post_stmt->fetchAll();
}

$react_icons = ['like'=>'👍 Like', 'dislike'=>'👎 Dislike', 'love'=>'❤️ Love', 'haha'=>'😂 Haha', 'wow'=>'😮 Wow', 'sad'=>'😢 Sad'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($user['name']); ?> | Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #18191a; color: #e4e6eb; font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 0; }
        * { box-sizing: border-box; }
        a { text-decoration: none; color: inherit; }
        .main-app-container { max-width: 600px; margin: 0 auto; background-color: #18191a; min-height: 100vh; position: relative; }
        
        /* Top Nav */
        .top-nav { display: flex; justify-content: space-between; align-items: center; padding: 10px 16px; background-color: #242526; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid #3a3b3c;}
        .nav-circle-btn { width: 36px; height: 36px; border-radius: 50%; background-color: #3a3b3c; display: flex; justify-content: center; align-items: center; font-size: 18px; color: #e4e6eb; cursor: pointer;}
        
        /* Profile Header */
        .cover-photo { width: 100%; height: 220px; background: #3a3b3c; background-image: url('<?php echo $cover; ?>'); background-size: cover; background-position: center; position: relative; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;}
        
        .profile-pic-container { position: absolute; bottom: -50px; left: 50%; transform: translateX(-50%); width: 160px; height: 160px; border-radius: 50%; border: 4px solid #18191a; background: #3a3b3c; display: flex; justify-content: center; align-items: center; overflow: hidden; font-size: 70px; font-weight: bold; color: #fff; z-index: 2;}
        .profile-pic-container img { width: 100%; height: 100%; object-fit: cover; }
        
        .profile-info { padding: 60px 16px 20px; background: #242526; border-bottom: 1px solid #3a3b3c; text-align: center;}
        .profile-name { font-size: 28px; font-weight: 800; margin: 0 0 5px 0; color: #e4e6eb;}
        .profile-friends-count { font-size: 16px; font-weight: 600; color: #b0b3b8; margin-bottom: 10px;}
        .profile-bio { font-size: 15px; color: #e4e6eb; margin-bottom: 15px; line-height: 1.4; padding: 0 10px;}
        
        /* Lock Badge */
        .lock-badge { background: #2d88ff; color: white; display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 13px; font-weight: bold; margin-bottom: 10px;}

        /* Actions */
        .profile-actions { display: flex; gap: 10px; margin-top: 15px; justify-content: center;}
        .btn-primary { flex: 1; max-width: 200px; background: #2d88ff; color: #fff; border: none; padding: 10px; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; display:flex; justify-content:center; align-items:center; gap:8px;}
        .btn-secondary { flex: 1; max-width: 200px; background: #3a3b3c; color: #e4e6eb; border: none; padding: 10px; border-radius: 6px; font-weight: 600; font-size: 15px; cursor: pointer; display:flex; justify-content:center; align-items:center; gap:8px;}
        
        /* Intro Details */
        .intro-box { background: #242526; padding: 16px; margin-top: 8px; border-radius: 8px;}
        .intro-item { display: flex; gap: 12px; align-items: center; color: #e4e6eb; font-size: 15px; margin-bottom: 15px;}
        .intro-item i { color: #8c939d; font-size: 20px; width: 24px; text-align: center;}

        /* Locked Screen Notice */
        .locked-screen { background: #242526; padding: 30px 20px; margin-top: 8px; border-radius: 8px; text-align: center; border: 1px solid #3a3b3c;}
        .locked-screen i { font-size: 45px; color: #2d88ff; margin-bottom: 15px; background: rgba(45, 136, 255, 0.1); padding: 15px; border-radius: 50%;}
        .locked-screen h3 { margin: 0 0 10px 0; color: #e4e6eb; font-size: 18px;}
        .locked-screen p { margin: 0; color: #b0b3b8; font-size: 15px; line-height: 1.4;}
        
        /* Feed Cards */
        .card { background-color: #242526; margin-top: 8px; padding: 12px 0; }
        .post-header { display: flex; justify-content: space-between; align-items: center; padding: 0 16px 10px; }
        .post-text { padding: 0 16px 12px; font-size: 15px; line-height: 1.4;}
        .post-media-container img, .post-media-container video { width: 100%; max-height: 600px; object-fit: cover; display: block; }
        .interaction-stats { display: flex; justify-content: space-between; padding: 10px 16px; border-bottom: 1px solid #3a3b3c; color: #b0b3b8; font-size: 14px; }
        .action-buttons { display: flex; justify-content: space-around; padding: 4px 16px; border-bottom: 1px solid #3a3b3c; }
        .action-btn { flex: 1; display: flex; justify-content: center; align-items: center; gap: 6px; padding: 8px 0; background: transparent; border: none; color: #b0b3b8; font-size: 15px; font-weight: 600; cursor: pointer; border-radius: 4px; }
        .react-active-like { color: #2d88ff !important; }

        /* Edit Profile Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; display: none; justify-content: center; align-items: center; padding: 10px; }
        .modal-content { background: #242526; width: 100%; max-width: 500px; border-radius: 8px; overflow: hidden; border: 1px solid #3a3b3c;}
        .modal-header { padding: 16px; border-bottom: 1px solid #3a3b3c; display: flex; justify-content: space-between; align-items: center; font-weight: bold; font-size: 20px;}
        .modal-body { padding: 16px; max-height: 80vh; overflow-y: auto;}
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #e4e6eb; font-size: 16px;}
        .form-group input[type="text"], .form-group textarea { width: 100%; padding: 12px; background: #3a3b3c; border: 1px solid #4e4f50; border-radius: 6px; color: #e4e6eb; outline: none; font-size: 15px;}
        .edit-img-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .edit-text-btn { color: #2d88ff; cursor: pointer; font-size: 15px; font-weight: 600; }
    </style>
</head>
<body>

    <div class="main-app-container">
        
        <div class="top-nav">
            <div style="display:flex; align-items:center; gap:15px;">
                <i class="fa-solid fa-arrow-left" style="font-size:20px; cursor:pointer;" onclick="window.location.href='index.php'"></i>
                <div style="font-size: 18px; font-weight: 600;"><?php echo htmlspecialchars($user['name']); ?></div>
            </div>
            <div class="nav-circle-btn"><i class="fa-solid fa-magnifying-glass"></i></div>
        </div>

        <div class="cover-photo" style="<?php echo (!$can_view_profile) ? 'filter: blur(4px);' : ''; ?>">
            <div class="profile-pic-container" style="<?php echo (!$can_view_profile) ? 'filter: blur(4px);' : ''; ?>">
                <?php if($dp): ?>
                    <img src="<?php echo $dp; ?>" alt="DP">
                <?php else: ?>
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-info">
            <h1 class="profile-name"><?php echo htmlspecialchars($user['name']); ?></h1>
            <div class="profile-friends-count"><?php echo $real_friend_count; ?> friends</div>
            
            <?php if($is_locked == 1): ?>
                <div class="lock-badge"><i class="fa-solid fa-lock"></i> <?php echo $is_own_profile ? "You locked your profile" : "Profile is locked"; ?></div>
            <?php endif; ?>
            
            <?php if(!empty($user['bio']) && $can_view_profile): ?>
                <div class="profile-bio"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></div>
            <?php endif; ?>

            <div class="profile-actions">
                <?php if($is_own_profile): ?>
                    <button class="btn-primary" onclick="openModal()"><i class="fa-solid fa-pen"></i> Edit Profile</button>
                    
                    <form method="POST" style="flex:1; max-width:200px;">
                        <input type="hidden" name="action" value="toggle_lock">
                        <input type="hidden" name="current_lock_status" value="<?php echo $is_locked; ?>">
                        <button type="submit" class="btn-secondary" style="width:100%; background:#4e4f50;">
                            <?php if($is_locked == 1): ?>
                                <i class="fa-solid fa-unlock"></i> Unlock Profile
                            <?php else: ?>
                                <i class="fa-solid fa-lock"></i> Lock Profile
                            <?php endif; ?>
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn-primary"><i class="fa-solid fa-user-plus"></i> Add Friend</button>
                    <button class="btn-secondary"><i class="fa-brands fa-facebook-messenger"></i> Message</button>
                <?php endif; ?>
                <button class="btn-secondary" style="flex:0; padding:10px 15px;"><i class="fa-solid fa-ellipsis"></i></button>
            </div>
        </div>

        <?php if(!$can_view_profile): ?>
            
            <div class="locked-screen">
                <i class="fa-solid fa-shield-halved"></i>
                <h3><?php echo htmlspecialchars($p_first_name); ?> locked their profile</h3>
                <p>Only their friends can see the photos and posts they share on their timeline.</p>
            </div>

        <?php else: ?>
            
            <div class="intro-box">
                <h3 style="margin-top:0; font-size:20px;">Details</h3>
                <?php if(!empty($user['workplace'])): ?>
                    <div class="intro-item"><i class="fa-solid fa-briefcase"></i> Works at <strong><?php echo htmlspecialchars($user['workplace']); ?></strong></div>
                <?php endif; ?>
                <?php if(!empty($user['education'])): ?>
                    <div class="intro-item"><i class="fa-solid fa-graduation-cap"></i> Studied at <strong><?php echo htmlspecialchars($user['education']); ?></strong></div>
                <?php endif; ?>
                <?php if(!empty($user['city'])): ?>
                    <div class="intro-item"><i class="fa-solid fa-house-chimney"></i> Lives in <strong><?php echo htmlspecialchars($user['city']); ?></strong></div>
                <?php endif; ?>
                <div class="intro-item"><i class="fa-solid fa-clock"></i> Joined Facebook Clone recently</div>
            </div>

            <div style="background:#242526; padding:12px 16px; margin-top:8px; font-weight:bold; font-size:18px;">Posts</div>

            <?php if($is_own_profile): ?>
            <div class="card" style="padding:12px 16px; display:flex; gap:12px; align-items:center;">
                <div style="width:40px; height:40px; border-radius:50%; background:#3a3b3c; color:#fff; display:flex; justify-content:center; align-items:center; font-weight:bold; overflow:hidden;">
                    <?php if($dp): ?><img src="<?php echo $dp; ?>" style="width:100%; height:100%; object-fit:cover;"><?php else: ?><?php echo strtoupper(substr($user['name'], 0, 1)); ?><?php endif; ?>
                </div>
                <div style="flex:1; background:#3a3b3c; padding:10px 15px; border-radius:20px; color:#b0b3b8; cursor:pointer;" onclick="window.location.href='createpost.php'">What's on your mind?</div>
            </div>
            <?php endif; ?>

            <?php if(count($posts) == 0): ?>
                <div style="text-align:center; padding:40px; color:#b0b3b8; background:#242526;">No posts available.</div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <?php 
                        $active_class = $post['my_react'] ? "react-active-like" : "";
                        $display_text = $post['my_react'] ? $react_icons[$post['my_react']] : '<i class="fa-regular fa-thumbs-up"></i> Like';
                    ?>
                    <div class="card" id="post-<?php echo $post['id']; ?>">
                        <div class="post-header">
                            <div style="display:flex; gap:10px; align-items:center;">
                                <div style="width:40px; height:40px; border-radius:50%; background:#3a3b3c; color:#fff; display:flex; justify-content:center; align-items:center; font-weight:bold; overflow:hidden;">
                                    <?php if($post['poster_dp']): ?><img src="<?php echo $post['poster_dp']; ?>" style="width:100%; height:100%; object-fit:cover;"><?php else: ?><?php echo strtoupper(substr($post['name'], 0, 1)); ?><?php endif; ?>
                                </div>
                                <div>
                                    <h4 style="margin:0; font-size:15px; font-weight:600;"><a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['name']); ?></a></h4>
                                    <div style="font-size:13px; color:#b0b3b8; margin-top:2px;">
                                        <?php echo date('M j, g:i a', strtotime($post['created_at'])); ?> • <i class="fa-solid fa-earth-americas" style="font-size:11px;"></i>
                                    </div>
                                </div>
                            </div>
                            <div style="color:#b0b3b8; font-size:18px;"><i class="fa-solid fa-ellipsis"></i></div>
                        </div>

                        <?php if(!empty($post['body'])): ?>
                            <div class="post-text"><?php echo nl2br(htmlspecialchars($post['body'])); ?></div>
                        <?php endif; ?>

                        <?php if(!empty($post['image'])): ?>
                            <div class="post-media-container"><a href="post.php?id=<?php echo $post['id']; ?>"><img src="<?php echo htmlspecialchars($post['image']); ?>"></a></div>
                        <?php elseif(!empty($post['video'])): ?>
                            <div class="post-media-container"><video controls muted><source src="<?php echo htmlspecialchars($post['video']); ?>"></video></div>
                        <?php endif; ?>

                        <div class="interaction-stats">
                            <span style="display:flex; align-items:center; gap:5px;"><i class="fa-solid fa-thumbs-up" style="color:#2d88ff;"></i> <span id="react-count-<?php echo $post['id']; ?>"><?php echo $post['total_reacts']; ?></span></span>
                            <span><?php echo $post['total_comments']; ?> Comments</span>
                        </div>

                        <div class="action-buttons">
                            <button class="action-btn <?php echo $active_class; ?>" onclick="sendReact(<?php echo $post['id']; ?>, 'like')"><?php echo $display_text; ?></button>
                            <button class="action-btn" onclick="window.location.href='post.php?id=<?php echo $post['id']; ?>'"><i class="fa-regular fa-comment"></i> Comment</button>
                            <button class="action-btn"><i class="fa-solid fa-share"></i> Share</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php endif; ?>
        <?php if($is_own_profile): ?>
        <div class="modal-overlay" id="editModal">
            <div class="modal-content">
                <div class="modal-header">
                    <span>Edit Profile</span>
                    <i class="fa-solid fa-xmark" style="cursor:pointer; color:#b0b3b8; background:#3a3b3c; padding:8px 10px; border-radius:50%;" onclick="closeModal()"></i>
                </div>
                <div class="modal-body">
                    <form action="profile.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit_profile">
                        <input type="hidden" name="old_profile_pic" value="<?php echo $user['profile_pic']; ?>">
                        <input type="hidden" name="old_cover_pic" value="<?php echo $user['cover_pic']; ?>">
                        
                        <div class="form-group" style="border-bottom:1px solid #3a3b3c; padding-bottom:15px;">
                            <div class="edit-img-header">
                                <label style="margin:0;">Profile Picture</label>
                                <span class="edit-text-btn" onclick="document.getElementById('dp_input').click()">Edit</span>
                            </div>
                            <input type="file" name="profile_pic" id="dp_input" accept="image/*" style="display:none;" onchange="previewImage(event, 'dp_preview_img', 'dp_fallback')">
                            
                            <div style="width:140px; height:140px; border-radius:50%; margin:10px auto 0; background:#3a3b3c; overflow:hidden; display:flex; justify-content:center; align-items:center; font-size:60px; font-weight:bold;">
                                <img id="dp_preview_img" src="<?php echo $dp ? $dp : ''; ?>" style="width:100%; height:100%; object-fit:cover; display:<?php echo $dp ? 'block' : 'none'; ?>;">
                                <div id="dp_fallback" style="display:<?php echo $dp ? 'none' : 'block'; ?>;"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                            </div>
                        </div>

                        <div class="form-group" style="border-bottom:1px solid #3a3b3c; padding-bottom:15px;">
                            <div class="edit-img-header">
                                <label style="margin:0;">Cover Photo</label>
                                <span class="edit-text-btn" onclick="document.getElementById('cover_input').click()">Edit</span>
                            </div>
                            <input type="file" name="cover_pic" id="cover_input" accept="image/*" style="display:none;" onchange="previewImage(event, 'cover_preview_img', null)">
                            
                            <div style="width:100%; height:150px; background:#3a3b3c; border-radius:8px; overflow:hidden; margin-top:10px;">
                                <img id="cover_preview_img" src="<?php echo $cover; ?>" style="width:100%; height:100%; object-fit:cover;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Bio</label>
                            <textarea name="bio" rows="3" placeholder="Describe who you are..."><?php echo htmlspecialchars($user['bio']); ?></textarea>
                        </div>
                        
                        <div style="font-size:18px; font-weight:700; margin: 25px 0 10px;">Customize Your Intro</div>
                        
                        <div class="form-group">
                            <label>Workplace</label>
                            <input type="text" name="workplace" value="<?php echo htmlspecialchars($user['workplace'] ?? ''); ?>" placeholder="E.g. Software Engineer at Google">
                        </div>
                        <div class="form-group">
                            <label>Education</label>
                            <input type="text" name="education" value="<?php echo htmlspecialchars($user['education'] ?? ''); ?>" placeholder="E.g. Studied Computer Science at DU">
                        </div>
                        <div class="form-group">
                            <label>Current City</label>
                            <input type="text" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" placeholder="E.g. Dhaka, Bangladesh">
                        </div>
                        
                        <button type="submit" class="btn-primary" style="width:100%; padding:12px; margin-top:10px;">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script>
        const iconsMap = { 'like':'👍 Like', 'love':'❤️ Love', 'haha':'😂 Haha', 'wow':'😮 Wow', 'sad':'😢 Sad' };

        function sendReact(postId, type) {
            let f = new FormData(); f.append('post_id', postId); f.append('type', type);
            fetch('ajax_react.php', {method:'POST', body:f})
            .then(res => res.json())
            .then(data => {
                document.getElementById('react-count-' + postId).innerText = data.total;
                let btn = document.querySelector(`#post-${postId} .action-btn`);
                btn.className = 'action-btn';
                if(data.current_react === 'none') {
                    btn.innerHTML = '<i class="fa-regular fa-thumbs-up"></i> Like';
                } else {
                    btn.classList.add('react-active-like');
                    btn.innerHTML = iconsMap[data.current_react];
                }
            });
        }

        // Modal Controls
        function openModal() { document.getElementById('editModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('editModal').style.display = 'none'; }

        // Live Image Preview Logic
        function previewImage(event, imgId, fallbackId) {
            const input = event.target;
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgElement = document.getElementById(imgId);
                    imgElement.src = e.target.result;
                    imgElement.style.display = 'block';
                    
                    if(fallbackId) {
                        document.getElementById(fallbackId).style.display = 'none';
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>