<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// ==========================================
// Fetch Only Video Posts for Reels
// ==========================================
$stmt = $pdo->query("SELECT p.*, u.name, u.city,
                    (SELECT COUNT(*) FROM reactions WHERE post_id = p.id) as total_reacts,
                    (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as total_comments,
                    (SELECT reaction_type FROM reactions WHERE post_id = p.id AND user_id = $user_id LIMIT 1) as my_react
                    FROM posts p JOIN users u ON p.user_id = u.id 
                    WHERE p.video IS NOT NULL AND p.video != ''
                    ORDER BY p.created_at DESC");
$reels = $stmt->fetchAll();

$react_icons = ['like'=>'👍', 'dislike'=>'👎', 'love'=>'❤️', 'haha'=>'😂', 'wow'=>'😮', 'sad'=>'😢'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Facebook Reels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #000; color: #fff; font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 0; overflow: hidden; }
        * { box-sizing: border-box; }
        
        /* Main Container for Reels (Mobile App View) */
        .reels-app-container { max-width: 450px; margin: 0 auto; height: 100vh; position: relative; background: #000; }
        
        /* Top Navigation Overlay */
        .reels-nav { position: absolute; top: 0; width: 100%; padding: 15px; display: flex; justify-content: space-between; align-items: center; z-index: 100; background: linear-gradient(to bottom, rgba(0,0,0,0.6) 0%, transparent 100%); }
        .back-btn { color: #fff; font-size: 24px; cursor: pointer; text-shadow: 0 1px 3px rgba(0,0,0,0.8); }
        .reels-title { font-size: 20px; font-weight: bold; text-shadow: 0 1px 3px rgba(0,0,0,0.8); }
        .camera-btn { color: #fff; font-size: 24px; cursor: pointer; text-shadow: 0 1px 3px rgba(0,0,0,0.8); }

        /* Vertical Scroll Container */
        .reels-scroll-container { height: 100vh; overflow-y: scroll; scroll-snap-type: y mandatory; scrollbar-width: none; }
        .reels-scroll-container::-webkit-scrollbar { display: none; }
        
        /* Single Reel Item */
        .reel-item { width: 100%; height: 100vh; scroll-snap-align: start; position: relative; display: flex; justify-content: center; align-items: center; background: #18191a; }
        .reel-video { width: 100%; height: 100%; object-fit: cover; cursor: pointer; }
        
        /* Bottom Gradient Overlay for Text Readability */
        .reel-overlay { position: absolute; bottom: 0; width: 100%; height: 40%; background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, transparent 100%); pointer-events: none; }

        /* Reel Info (Left Bottom) */
        .reel-info { position: absolute; left: 15px; bottom: 30px; z-index: 10; max-width: 75%; }
        .reel-user-wrap { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .reel-avatar { width: 40px; height: 40px; border-radius: 50%; border: 2px solid #fff; background: #3a3b3c; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 16px; color: #fff; overflow: hidden; }
        .reel-user-name { font-size: 16px; font-weight: bold; text-shadow: 0 1px 2px rgba(0,0,0,0.8); }
        .follow-btn { border: 1px solid #fff; padding: 3px 10px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; background: rgba(255,255,255,0.1); backdrop-filter: blur(5px); }
        .reel-desc { font-size: 14px; line-height: 1.4; margin-bottom: 8px; text-shadow: 0 1px 2px rgba(0,0,0,0.8); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .reel-music { display: flex; align-items: center; gap: 8px; font-size: 13px; text-shadow: 0 1px 2px rgba(0,0,0,0.8); }
        .music-marquee { display: inline-block; white-space: nowrap; overflow: hidden; max-width: 150px; }

        /* Reel Actions (Right Bottom) */
        .reel-actions { position: absolute; right: 15px; bottom: 30px; display: flex; flex-direction: column; gap: 20px; align-items: center; z-index: 10; }
        .action-btn-wrap { display: flex; flex-direction: column; align-items: center; gap: 5px; cursor: pointer; position: relative; }
        .action-icon { font-size: 28px; color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.8); transition: transform 0.2s; }
        .action-icon:active { transform: scale(0.9); }
        .action-count { font-size: 13px; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.8); }
        
        .active-like { color: #2d88ff !important; }

        /* Hover React Box for Reels */
        .reel-react-box { position: absolute; right: 50px; bottom: 0; background: rgba(36, 37, 38, 0.9); backdrop-filter: blur(10px); border-radius: 30px; padding: 8px 15px; display: flex; gap: 15px; opacity: 0; visibility: hidden; transition: 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); }
        .action-btn-wrap:hover .reel-react-box { opacity: 1; visibility: visible; right: 60px; }
        .reel-react-box span { font-size: 28px; cursor: pointer; transition: 0.2s; }
        .reel-react-box span:hover { transform: scale(1.3) translateY(-5px); }

        /* Play/Pause Icon Animation */
        .play-pause-indicator { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 60px; color: rgba(255,255,255,0.7); opacity: 0; transition: opacity 0.3s; pointer-events: none; z-index: 5; }
        
        /* Empty State */
        .empty-reels { display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100vh; color: #b0b3b8; text-align: center; padding: 20px; }
        .empty-reels i { font-size: 60px; margin-bottom: 20px; color: #3a3b3c; }
    </style>
</head>
<body>

    <div class="reels-app-container">
        
        <div class="reels-nav">
            <i class="fa-solid fa-arrow-left back-btn" onclick="window.location.href='index.php'"></i>
            <div class="reels-title">Reels</div>
            <i class="fa-solid fa-camera camera-btn" onclick="window.location.href='createpost.php'"></i>
        </div>

        <div class="reels-scroll-container" id="reels-scroll-area">
            
            <?php if(count($reels) == 0): ?>
                <div class="empty-reels">
                    <i class="fa-solid fa-video-slash"></i>
                    <h3>No Reels Available</h3>
                    <p>Be the first one to create a reel!</p>
                    <button style="padding:10px 20px; background:#2d88ff; color:#fff; border:none; border-radius:6px; font-weight:bold; margin-top:15px; cursor:pointer;" onclick="window.location.href='createpost.php'">Create Reel</button>
                </div>
            <?php else: ?>
                
                <?php foreach($reels as $reel): ?>
                    <?php 
                        $is_liked = $reel['my_react'] ? true : false;
                        $icon_color = $is_liked ? "color: #2d88ff;" : "color: #fff;";
                        $display_icon = $is_liked ? $react_icons[$reel['my_react']] : '<i class="fa-solid fa-thumbs-up action-icon"></i>';
                    ?>
                    <div class="reel-item" id="reel-<?php echo $reel['id']; ?>">
                        
                        <video class="reel-video" loop playsinline onclick="togglePlayPause(this, <?php echo $reel['id']; ?>)">
                            <source src="<?php echo htmlspecialchars($reel['video']); ?>">
                        </video>
                        
                        <i class="fa-solid fa-play play-pause-indicator" id="indicator-<?php echo $reel['id']; ?>"></i>
                        <div class="reel-overlay"></div>

                        <div class="reel-info">
                            <div class="reel-user-wrap">
                                <div class="reel-avatar"><?php echo strtoupper(substr($reel['name'], 0, 1)); ?></div>
                                <div class="reel-user-name"><?php echo htmlspecialchars($reel['name']); ?></div>
                                <div class="follow-btn">Follow</div>
                            </div>
                            <?php if(!empty($reel['body'])): ?>
                                <div class="reel-desc"><?php echo htmlspecialchars($reel['body']); ?></div>
                            <?php endif; ?>
                            <div class="reel-music">
                                <i class="fa-solid fa-music" style="font-size:12px;"></i>
                                <div class="music-marquee">Original Audio - <?php echo htmlspecialchars($reel['name']); ?></div>
                            </div>
                        </div>

                        <div class="reel-actions">
                            
                            <div class="action-btn-wrap">
                                <div id="reel-react-btn-<?php echo $reel['id']; ?>" onclick="sendReact(<?php echo $reel['id']; ?>, 'like')" style="<?php echo $icon_color; ?> font-size:28px; text-shadow:0 2px 4px rgba(0,0,0,0.8);">
                                    <?php echo $is_liked && isset($react_icons[$reel['my_react']]) ? $react_icons[$reel['my_react']] : '<i class="fa-solid fa-thumbs-up"></i>'; ?>
                                </div>
                                <span class="action-count" id="reel-react-count-<?php echo $reel['id']; ?>"><?php echo $reel['total_reacts']; ?></span>
                                
                                <div class="reel-react-box">
                                    <span onclick="sendReact(<?php echo $reel['id']; ?>, 'like')">👍</span>
                                    <span onclick="sendReact(<?php echo $reel['id']; ?>, 'love')">❤️</span>
                                    <span onclick="sendReact(<?php echo $reel['id']; ?>, 'haha')">😂</span>
                                    <span onclick="sendReact(<?php echo $reel['id']; ?>, 'wow')">😮</span>
                                    <span onclick="sendReact(<?php echo $reel['id']; ?>, 'sad')">😢</span>
                                </div>
                            </div>
                            
                            <div class="action-btn-wrap" onclick="window.location.href='post.php?id=<?php echo $reel['id']; ?>'">
                                <i class="fa-solid fa-comment-dots action-icon"></i>
                                <span class="action-count"><?php echo $reel['total_comments']; ?></span>
                            </div>
                            
                            <div class="action-btn-wrap" onclick="shareReel(<?php echo $reel['id']; ?>)">
                                <i class="fa-solid fa-share action-icon"></i>
                                <span class="action-count">Share</span>
                            </div>
                            
                            <div class="action-btn-wrap" style="margin-top:10px;">
                                <img src="https://picsum.photos/40?random=<?php echo $reel['id']; ?>" style="width:36px; height:36px; border-radius:5px; border:2px solid #fff; object-fit:cover;">
                            </div>

                        </div>

                    </div>
                <?php endforeach; ?>
                
            <?php endif; ?>

        </div>
    </div>

    <script>
        const iconsMap = { 'like':'👍', 'dislike':'👎', 'love':'❤️', 'haha':'😂', 'wow':'😮', 'sad':'😢' };

        // ================= Auto-Play Logic (TikTok Style) =================
        document.addEventListener("DOMContentLoaded", function() {
            const videos = document.querySelectorAll('.reel-video');
            
            const observerOptions = {
                root: document.getElementById('reels-scroll-area'),
                rootMargin: '0px',
                threshold: 0.6 // Play when 60% of the video is visible
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    const video = entry.target;
                    if (entry.isIntersecting) {
                        video.play().catch(e => console.log("Autoplay blocked by browser. User interaction needed."));
                    } else {
                        video.pause();
                        video.currentTime = 0; // Reset video when out of screen
                    }
                });
            }, observerOptions);

            videos.forEach(video => { observer.observe(video); });
        });

        // ================= Play/Pause on Click =================
        function togglePlayPause(videoElement, reelId) {
            const indicator = document.getElementById('indicator-' + reelId);
            
            if (videoElement.paused) {
                videoElement.play();
                indicator.className = 'fa-solid fa-play play-pause-indicator';
            } else {
                videoElement.pause();
                indicator.className = 'fa-solid fa-pause play-pause-indicator';
            }
            
            // Flash the icon then hide it
            indicator.style.opacity = '1';
            setTimeout(() => { indicator.style.opacity = '0'; }, 500);
        }

        // ================= Reaction Logic =================
        function sendReact(postId, type) {
            event.stopPropagation(); // Prevent video pause when clicking react
            let formData = new FormData();
            formData.append('post_id', postId);
            formData.append('type', type);

            fetch('ajax_react.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                document.getElementById('reel-react-count-' + postId).innerText = data.total;
                let btn = document.getElementById('reel-react-btn-' + postId);
                
                if(data.current_react === 'none') {
                    btn.innerHTML = '<i class="fa-solid fa-thumbs-up action-icon"></i>';
                    btn.style.color = '#fff';
                } else {
                    btn.innerHTML = iconsMap[data.current_react];
                    btn.style.color = '#2d88ff'; // Ensure color applies if it's the default thumb
                }
            });
        }

        // ================= Share Logic =================
        function shareReel(postId) {
            event.stopPropagation();
            if(confirm("Share this Reel to your timeline?")) {
                let formData = new FormData();
                formData.append('action', 'share_post');
                formData.append('post_id', postId);
                
                fetch('ajax_network.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'shared') {
                        alert("Reel shared successfully!");
                    }
                });
            }
        }
    </script>
</body>
</html>