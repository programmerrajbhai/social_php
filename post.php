<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

if (!isset($_GET['id']) || empty($_GET['id'])) { header("Location: index.php"); exit; }
$post_id = intval($_GET['id']);

// Fetch specific post data
$stmt = $pdo->prepare("SELECT p.*, u.name, 
                    (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as total_reacts,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as total_comments,
                    (SELECT reaction_type FROM reactions WHERE post_id = p.id AND user_id = ? LIMIT 1) as my_react,
                    sp.body as shared_body, su.name as shared_author
                    FROM posts p JOIN users u ON p.user_id = u.id 
                    LEFT JOIN posts sp ON p.shared_post_id = sp.id
                    LEFT JOIN users su ON sp.user_id = su.id
                    WHERE p.id = ?");
$stmt->execute([$user_id, $post_id]);
$post = $stmt->fetch();

if (!$post) { echo "<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h2>This post isn't available</h2><a href='index.php' style='color:#0866ff; text-decoration:none;'>Go to News Feed</a></div>"; exit; }

$react_icons = ['like'=>'👍 Like', 'dislike'=>'👎 Dislike', 'love'=>'❤️ Love', 'haha'=>'😂 Haha', 'wow'=>'😮 Wow', 'sad'=>'😢 Sad'];
$active_class = $post['my_react'] ? "react-active-".$post['my_react'] : "";
$display_text = $post['my_react'] ? $react_icons[$post['my_react']] : '<i class="fa-regular fa-thumbs-up"></i> Like';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($post['name']); ?>'s Post</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; }
        .single-post-container { display: flex; justify-content: center; padding: 20px; }
        .single-post-card { background: #fff; width: 100%; max-width: 680px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); margin: 0 auto; overflow: hidden;}
        
        .post-header { padding: 16px; display: flex; justify-content: space-between; align-items: center; }
        .post-user-info { display: flex; align-items: center; gap: 12px; }
        .post-user-details h4 { font-size: 16px; margin-bottom: 2px; color: #050505; }
        .post-time { font-size: 13px; color: #65676b; }
        
        .post-body-text { padding: 0 16px 16px; font-size: 18px; line-height: 1.5; color: #050505; }
        .post-image-large { width: 100%; max-height: 700px; object-fit: cover; display: block; }
        
        .interaction-stats { display: flex; justify-content: space-between; padding: 10px 16px; color: #65676b; font-size: 15px; border-bottom: 1px solid #ced0d4; }
        .action-buttons { display: flex; justify-content: space-around; padding: 4px 16px; border-bottom: 1px solid #ced0d4; }
        .action-btn { flex: 1; padding: 8px; background: transparent; border: none; font-size: 15px; font-weight: 600; color: #65676b; cursor: pointer; border-radius: 4px; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .action-btn:hover { background: #f0f2f5; }
        .react-active-like { color: #0866ff !important; }
        
        /* React Hover Box */
        .react-container { position: relative; }
        .react-box { position: absolute; bottom: 45px; left: 50%; transform: translateX(-50%) translateY(10px); background: #fff; border-radius: 30px; padding: 5px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); display: flex; gap: 10px; opacity: 0; visibility: hidden; transition: 0.3s ease; z-index: 10; border: 1px solid #eee; }
        .react-container:hover .react-box { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }
        .react-box span { font-size: 26px; cursor: pointer; transition: transform 0.2s; }
        .react-box span:hover { transform: scale(1.3); }

        .comments-area { padding: 16px; background: #fff; }
        .comment-input-wrap { display: flex; gap: 10px; margin-bottom: 20px; }
        .comment-input-box { flex: 1; display: flex; background: #f0f2f5; border-radius: 20px; padding: 8px 15px; align-items: center; }
        .comment-input-box input { flex: 1; border: none; background: transparent; outline: none; font-size: 15px; }

        /* Comment Nested Styles */
        .comment-bubble { background: #f0f2f5; padding: 8px 12px; border-radius: 18px; display: inline-block; position: relative; }
        .comment-name { font-size: 13px; font-weight: bold; color: #050505; }
        .comment-text-body { font-size: 14px; margin-top: 2px; }
        .comment-actions { margin-left: 12px; margin-top: 2px; font-size: 12px; font-weight: 600; color: #65676b; display: flex; gap: 12px; align-items: center; }
        .comment-actions span { cursor: pointer; transition: 0.2s; }
        .comment-actions span:hover { text-decoration: underline; }
        .active-comment-react { color: #0866ff !important; }
        .reply-container { margin-left: 40px; margin-top: 5px; border-left: 2px solid #e4e6eb; padding-left: 10px;}
        .reply-input-box { margin-left: 40px; margin-top: 5px; display: none; }
        .comment-react-badge { display: inline-flex; align-items: center; background: #fff; border-radius: 10px; padding: 2px 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.2); font-size: 11px; color: #65676b; position: absolute; bottom: -8px; right: -15px; z-index: 2; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="nav-left">
            <a href="index.php" style="text-decoration:none;"><i class="fa-brands fa-facebook fb-logo"></i></a>
        </div>
        <div class="nav-right">
            <a href="index.php" style="text-decoration:none; margin-right:15px; font-weight:600; color:#0866ff; background:#e7f3ff; padding:8px 15px; border-radius:20px;">Back to Home</a>
            <div class="default-avatar nav-profile-pic"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
        </div>
    </nav>

    <div class="single-post-container">
        <div class="single-post-card" id="post-<?php echo $post['id']; ?>">
            
            <div class="post-header">
                <div class="post-user-info">
                    <div class="default-avatar" style="width:44px; height:44px; font-size:18px; background:#0866ff; color:#fff;"><?php echo strtoupper(substr($post['name'], 0, 1)); ?></div>
                    <div class="post-user-details">
                        <h4><?php echo htmlspecialchars($post['name']); ?></h4>
                        <div class="post-time"><?php echo date('F j, Y \a\t g:i a', strtotime($post['created_at'])); ?> • <i class="fa-solid fa-earth-americas" style="font-size:12px;"></i></div>
                    </div>
                </div>
                <div style="color:#65676b; cursor:pointer;"><i class="fa-solid fa-ellipsis" style="font-size:20px;"></i></div>
            </div>

            <?php if(!empty($post['body'])): ?>
                <div class="post-body-text">
                    <?php echo nl2br(htmlspecialchars($post['body'])); ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($post['image'])): ?>
                <div style="position:relative; width:100%; display:flex; justify-content:center; background:#000;">
                    <img src="<?php echo htmlspecialchars($post['image']); ?>" alt="Post Image" class="post-image-large">
                    <div style="position:absolute; bottom:-14px; left:50%; transform:translateX(-50%); background:#0866ff; color:#fff; width:28px; height:28px; border-radius:50%; display:flex; justify-content:center; align-items:center; border:2px solid #fff; box-shadow:0 1px 3px rgba(0,0,0,0.2);">
                        <i class="fa-solid fa-heart" style="font-size:14px;"></i>
                    </div>
                </div>
            <?php endif; ?>

            <div style="margin-top: <?php echo empty($post['image']) ? '0' : '15px'; ?>;">
                <div class="interaction-stats">
                    <span style="cursor:pointer;" onclick="showReactors(<?php echo $post['id']; ?>)">
                        <span id="react-count-<?php echo $post['id']; ?>"><?php echo $post['total_reacts']; ?></span> Reactions
                    </span>
                    <span><span id="comment-count-<?php echo $post['id']; ?>"><?php echo $post['total_comments']; ?></span> Comments</span>
                </div>

                <div id="reactor-list-<?php echo $post['id']; ?>" class="react-list-box" style="margin:10px 16px; display:none; background:#fff; border:1px solid #ddd; padding:10px; border-radius:8px; max-height:150px; overflow-y:auto;"></div>

                <div class="action-buttons">
                    <div class="react-container" style="flex:1;">
                        <button id="main-react-btn-<?php echo $post['id']; ?>" class="action-btn <?php echo $active_class; ?>" onclick="sendReact(<?php echo $post['id']; ?>, 'like')">
                            <?php echo $display_text; ?>
                        </button>
                        
                        <div class="react-box">
                            <span onclick="sendReact(<?php echo $post['id']; ?>, 'like')">👍</span>
                            <span onclick="sendReact(<?php echo $post['id']; ?>, 'love')">❤️</span>
                            <span onclick="sendReact(<?php echo $post['id']; ?>, 'haha')">😂</span>
                            <span onclick="sendReact(<?php echo $post['id']; ?>, 'wow')">😮</span>
                            <span onclick="sendReact(<?php echo $post['id']; ?>, 'sad')">😢</span>
                        </div>
                    </div>

                    <button class="action-btn" onclick="document.getElementById('comment-input-<?php echo $post['id']; ?>').focus();">
                        <i class="fa-regular fa-comment"></i> Comment
                    </button>
                    
                    <button class="action-btn" onclick="shareActualPost(<?php echo $post['id']; ?>)">
                        <i class="fa-solid fa-share"></i> Share
                    </button>
                </div>

                <div class="comments-area">
                    <div class="comment-input-wrap">
                        <div class="default-avatar" style="width:36px; height:36px; font-size:14px; background:#e4e6eb;"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                        <div class="comment-input-box">
                            <input type="text" id="comment-input-<?php echo $post['id']; ?>" placeholder="Write a public comment...">
                            <div style="display:flex; gap:12px; color:#65676b; align-items:center;">
                                <label for="img-input-<?php echo $post['id']; ?>" style="cursor:pointer;"><i class="fa-solid fa-camera"></i></label>
                                <input type="file" id="img-input-<?php echo $post['id']; ?>" accept="image/*" style="display:none;">
                                <i class="fa-solid fa-paper-plane" onclick="postComment(<?php echo $post['id']; ?>)" style="cursor:pointer; color:#0866ff; font-size:16px;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div id="comment-list-<?php echo $post['id']; ?>">
                        <?php
                        // Fetch all comments and their reactions
                        $c_stmt = $pdo->prepare("SELECT c.*, u.name, 
                                                (SELECT COUNT(*) FROM comment_reactions WHERE comment_id = c.id) as c_reacts,
                                                (SELECT reaction_type FROM comment_reactions WHERE comment_id = c.id AND user_id = ? LIMIT 1) as my_c_react
                                                FROM comments c JOIN users u ON c.user_id = u.id 
                                                WHERE c.post_id = ? ORDER BY c.created_at ASC");
                        $c_stmt->execute([$user_id, $post['id']]);
                        $all_comments = $c_stmt->fetchAll();

                        $parents = []; $replies = [];
                        foreach($all_comments as $c) {
                            if($c['parent_id'] == null) { $parents[] = $c; } 
                            else { $replies[$c['parent_id']][] = $c; }
                        }

                        foreach($parents as $parent): 
                            $c_active = $parent['my_c_react'] ? "active-comment-react" : "";
                        ?>
                            <div style="display:flex; gap:10px; margin-bottom:15px;" id="comment-<?php echo $parent['id']; ?>">
                                <div class="default-avatar" style="width:36px; height:36px; font-size:14px; flex-shrink:0;"><?php echo strtoupper(substr($parent['name'], 0, 1)); ?></div>
                                <div style="width:100%;">
                                    <div class="comment-bubble">
                                        <div class="comment-name"><?php echo htmlspecialchars($parent['name']); ?></div>
                                        <?php if($parent['comment_text']) echo "<div class='comment-text-body'>".htmlspecialchars($parent['comment_text'])."</div>"; ?>
                                        
                                        <div class="comment-react-badge" id="c-badge-<?php echo $parent['id']; ?>" style="display: <?php echo $parent['c_reacts'] > 0 ? 'inline-flex' : 'none'; ?>">
                                            👍 <span id="c-react-count-<?php echo $parent['id']; ?>" style="margin-left:3px;"><?php echo $parent['c_reacts']; ?></span>
                                        </div>
                                    </div>
                                    <?php if($parent['image']) echo "<img src='{$parent['image']}' style='max-width:250px; border-radius:8px; display:block; margin-top:5px;'>"; ?>
                                    
                                    <div class="comment-actions">
                                        <span id="c-btn-<?php echo $parent['id']; ?>" class="<?php echo $c_active; ?>" onclick="reactComment(<?php echo $parent['id']; ?>)">Like</span>
                                        <span onclick="toggleReplyBox(<?php echo $parent['id']; ?>)">Reply</span>
                                        <span style="font-weight:normal;"><?php echo date('M j, g:i a', strtotime($parent['created_at'])); ?></span>
                                    </div>

                                    <div class="reply-container" id="reply-list-<?php echo $parent['id']; ?>">
                                        <?php 
                                        if(isset($replies[$parent['id']])): 
                                            foreach($replies[$parent['id']] as $reply): 
                                                $r_active = $reply['my_c_react'] ? "active-comment-react" : "";
                                        ?>
                                                <div style="display:flex; gap:8px; margin-top:10px;">
                                                    <div class="default-avatar" style="width:24px; height:24px; font-size:11px; flex-shrink:0;"><?php echo strtoupper(substr($reply['name'], 0, 1)); ?></div>
                                                    <div>
                                                        <div class="comment-bubble" style="padding:6px 10px;">
                                                            <div class="comment-name"><?php echo htmlspecialchars($reply['name']); ?></div>
                                                            <?php if($reply['comment_text']) echo "<div class='comment-text-body' style='font-size:13px;'>".htmlspecialchars($reply['comment_text'])."</div>"; ?>
                                                            <div class="comment-react-badge" id="c-badge-<?php echo $reply['id']; ?>" style="display: <?php echo $reply['c_reacts'] > 0 ? 'inline-flex' : 'none'; ?>; bottom:-5px;">
                                                                👍 <span id="c-react-count-<?php echo $reply['id']; ?>" style="margin-left:3px;"><?php echo $reply['c_reacts']; ?></span>
                                                            </div>
                                                        </div>
                                                        <?php if($reply['image']) echo "<img src='{$reply['image']}' style='max-width:150px; border-radius:8px; display:block; margin-top:5px;'>"; ?>
                                                        <div class="comment-actions">
                                                            <span id="c-btn-<?php echo $reply['id']; ?>" class="<?php echo $r_active; ?>" onclick="reactComment(<?php echo $reply['id']; ?>)">Like</span>
                                                            <span onclick="toggleReplyBox(<?php echo $parent['id']; ?>)">Reply</span>
                                                        </div>
                                                    </div>
                                                </div>
                                        <?php 
                                            endforeach; 
                                        endif; 
                                        ?>
                                    </div>

                                    <div class="reply-input-box" id="reply-box-<?php echo $parent['id']; ?>">
                                        <div class="comment-input-box" style="background:#fff; border:1px solid #ced0d4;">
                                            <input type="text" id="reply-input-<?php echo $parent['id']; ?>" placeholder="Write a reply...">
                                            <i class="fa-solid fa-paper-plane" onclick="postComment(<?php echo $post['id']; ?>, <?php echo $parent['id']; ?>)" style="cursor:pointer; color:#0866ff;"></i>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        const iconsMap = { 'like':'👍 Like', 'dislike':'👎 Dislike', 'love':'❤️ Love', 'haha':'😂 Haha', 'wow':'😮 Wow', 'sad':'😢 Sad' };

        // Post Reaction
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
                    btn.style.color = '#65676b';
                } else {
                    btn.classList.add('react-active-' + data.current_react);
                    btn.innerHTML = iconsMap[data.current_react];
                }
            });
        }

        // View Post Reactors
        function showReactors(postId) {
            let box = document.getElementById('reactor-list-' + postId);
            if(box.style.display === 'block') { box.style.display = 'none'; return; }
            fetch('ajax_react_list.php?post_id=' + postId)
            .then(res => res.json())
            .then(data => {
                if(data.length === 0) { box.innerHTML = "<p>No reactions yet.</p>"; }
                else {
                    let html = "";
                    data.forEach(user => {
                        let icon = iconsMap[user.reaction_type].split(' ')[0];
                        html += `<div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #eee; font-weight:500;"><span>${user.name}</span> <span>${icon}</span></div>`;
                    });
                    box.innerHTML = html;
                }
                box.style.display = 'block';
            });
        }

        // Toggle Reply Input
        function toggleReplyBox(commentId) {
            let box = document.getElementById('reply-box-' + commentId);
            box.style.display = (box.style.display === 'block') ? 'none' : 'block';
            if(box.style.display === 'block') document.getElementById('reply-input-' + commentId).focus();
        }

        // React to Comment (Like)
        function reactComment(commentId) {
            let formData = new FormData();
            formData.append('comment_id', commentId);

            fetch('ajax_comment_react.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                let btn = document.getElementById('c-btn-' + commentId);
                let badge = document.getElementById('c-badge-' + commentId);
                let countSpan = document.getElementById('c-react-count-' + commentId);

                if (data.status === 'liked') {
                    btn.classList.add('active-comment-react');
                } else {
                    btn.classList.remove('active-comment-react');
                }

                if (data.total > 0) {
                    badge.style.display = 'inline-flex';
                    countSpan.innerText = data.total;
                } else {
                    badge.style.display = 'none';
                }
            });
        }

        // Post Comment or Reply
        function postComment(postId, parentId = null) {
            let inputId = parentId ? 'reply-input-' + parentId : 'comment-input-' + postId;
            let fileId = parentId ? null : 'img-input-' + postId;
            
            let inputField = document.getElementById(inputId);
            let text = inputField.value.trim();
            let file = fileId ? document.getElementById(fileId).files[0] : null;
            
            if(text === '' && !file) return;

            let formData = new FormData();
            formData.append('post_id', postId);
            formData.append('comment_text', text);
            if(parentId) formData.append('parent_id', parentId);
            if(file) formData.append('comment_image', file);

            fetch('ajax_comment.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    location.reload(); // Quick refresh to show nested layout properly
                }
            });
        }

        // Share Post
        function shareActualPost(postId) {
            if(confirm("Share this post to your timeline?")) {
                let formData = new FormData();
                formData.append('action', 'share_post');
                formData.append('post_id', postId);

                fetch('ajax_network.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'shared') {
                        alert("Shared to your timeline!");
                        window.location.href = "index.php"; 
                    }
                });
            }
        }
    </script>
</body>
</html>