<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$first_name = explode(' ', $user_name)[0];

// ==========================================
// Handle Post Upload (Text + Image/Video)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $body = htmlspecialchars($_POST['post_body'] ?? '');
    $imagePath = null;
    $videoPath = null;

    $targetDir = 'uploads/';
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    // চেক করা হচ্ছে ফাইল আপলোড হয়েছে কিনা
    if (isset($_FILES['post_media']) && $_FILES['post_media']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['post_media']['name'], PATHINFO_EXTENSION));
        
        // ভিডিও ফরম্যাটগুলো চেক করা
        $is_video = in_array($ext, ['mp4', 'webm', 'ogg', 'mov']);
        
        // ফাইলের নাম সেট করা
        $newFileName = ($is_video ? 'vid_' : 'img_') . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $targetFile = $targetDir . $newFileName;
        
        // ফাইল মুভ করা এবং সঠিক ভ্যারিয়েবলে পাথ সেট করা
        if (move_uploaded_file($_FILES['post_media']['tmp_name'], $targetFile)) { 
            if ($is_video) {
                $videoPath = $targetFile;
            } else {
                $imagePath = $targetFile; 
            }
        }
    }
    
    // ডাটাবেসে সেভ করা (টেক্সট, ছবি অথবা ভিডিও থাকলে)
    if (!empty(trim($body)) || $imagePath || $videoPath) {
        $pdo->prepare("INSERT INTO posts (user_id, body, image, video) VALUES (?, ?, ?, ?)")->execute([$user_id, $body, $imagePath, $videoPath]);
    }
    
    // সফলভাবে পোস্ট হওয়ার পর হোম পেজে রিডাইরেক্ট
    header("Location: index.php"); exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Create Post | Dark UI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #18191a; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .create-box { background: #242526; width: 100%; max-width: 500px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.5); overflow: hidden; border: 1px solid #3a3b3c; }
        
        .box-header { display: flex; justify-content: space-between; align-items: center; padding: 16px; border-bottom: 1px solid #3a3b3c; }
        .box-header h2 { font-size: 20px; font-weight: 700; color: #e4e6eb; margin: 0; text-align: center; flex: 1; }
        .close-btn { width: 36px; height: 36px; background: #3a3b3c; border-radius: 50%; display: flex; justify-content: center; align-items: center; cursor: pointer; color: #b0b3b8; font-size: 20px; transition: 0.2s; }
        .close-btn:hover { background: #4e4f50; color: #e4e6eb;}
        
        .box-body { padding: 16px; }
        .user-info { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .privacy-badge { background: #3a3b3c; padding: 4px 8px; border-radius: 6px; font-size: 13px; font-weight: 600; color: #e4e6eb; display: inline-flex; align-items: center; gap: 4px; margin-top: 2px;}
        
        .post-textarea { width: 100%; border: none; outline: none; font-size: 24px; min-height: 150px; resize: none; background: transparent; color: #e4e6eb; }
        .post-textarea::placeholder { color: #b0b3b8; }
        
        .add-to-post { border: 1px solid #3a3b3c; border-radius: 8px; padding: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .add-to-post span { font-weight: 600; color: #e4e6eb; }
        .add-icons { display: flex; gap: 15px; font-size: 24px; }
        .add-icons i { cursor: pointer; transition: 0.2s; }
        .add-icons i:hover { transform: scale(1.1); }
        
        .submit-btn { width: 100%; background: #2d88ff; color: #fff; border: none; padding: 10px; font-size: 15px; font-weight: bold; border-radius: 6px; cursor: pointer; transition: 0.2s; }
        .submit-btn:hover { background: #1877f2; }

        .preview-text { color: #45bd62; font-weight: bold; margin-bottom: 10px; text-align: center; font-size: 14px; display: none; }
    </style>
</head>
<body>

    <div class="create-box">
        <div class="box-header">
            <div style="width:36px;"></div> 
            <h2>Create post</h2>
            <div class="close-btn" onclick="window.location.href='index.php'"><i class="fa-solid fa-xmark"></i></div>
        </div>
        
        <div class="box-body">
            <div class="user-info">
                <div style="width:40px; height:40px; background:#2d88ff; color:#fff; border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:18px; font-weight:bold;">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight:600; font-size:15px; color:#e4e6eb;"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="privacy-badge"><i class="fa-solid fa-earth-americas"></i> Public</div>
                </div>
            </div>
            
            <form method="POST" action="createpost.php" enctype="multipart/form-data">
                <textarea name="post_body" class="post-textarea" placeholder="What's on your mind, <?php echo $first_name; ?>?" autofocus></textarea>
                
                <div id="media-preview-text" class="preview-text">
                    <i class="fa-solid fa-circle-check"></i> <span id="media-type-label">Media</span> selected for upload!
                </div>
                
                <div class="add-to-post">
                    <span>Add to your post</span>
                    <div class="add-icons">
                        <label for="post_media" style="cursor:pointer;" title="Photo/Video">
                            <i class="fa-regular fa-images" style="color:#45bd62;"></i>
                        </label>
                        <input type="file" name="post_media" id="post_media" accept="image/*,video/*" style="display:none;" onchange="handleMediaSelect()">
                        
                        <i class="fa-solid fa-user-tag" style="color:#2d88ff;" onclick="alert('Feature coming soon!')"></i>
                        <i class="fa-regular fa-face-smile" style="color:#eab026;"></i>
                        <i class="fa-solid fa-location-dot" style="color:#f5533d;"></i>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">Post</button>
            </form>
        </div>
    </div>

    <script>
        // ইউজার ছবি সিলেক্ট করেছে নাকি ভিডিও, তা চেক করে মেসেজ দেখানো
        function handleMediaSelect() {
            const fileInput = document.getElementById('post_media');
            const previewBox = document.getElementById('media-preview-text');
            const label = document.getElementById('media-type-label');
            
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const isVideo = file.type.startsWith('video/');
                
                label.innerText = isVideo ? 'Video' : 'Image';
                previewBox.style.display = 'block';
            }
        }
    </script>

</body>
</html>