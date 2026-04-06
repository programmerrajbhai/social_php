<?php
session_start();
require 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || empty($_GET['id'])) { header("Location: index.php"); exit; }
$chat_user_id = intval($_GET['id']);

$stmt = $pdo->prepare("SELECT name, profile_pic FROM users WHERE id = ?");
$stmt->execute([$chat_user_id]);
$chat_user = $stmt->fetch();
if (!$chat_user) { echo "User not found!"; exit; }
$dp = $chat_user['profile_pic'] ? $chat_user['profile_pic'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #18191a; color: #e4e6eb; font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 0; }
        * { box-sizing: border-box; }
        .chat-app-container { max-width: 600px; margin: 0 auto; height: 100vh; display: flex; flex-direction: column; background-color: #18191a; border-left: 1px solid #3a3b3c; border-right: 1px solid #3a3b3c;}
        
        .chat-header { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; background-color: #242526; border-bottom: 1px solid #3a3b3c; flex-shrink: 0;}
        .chat-header-left { display: flex; align-items: center; gap: 12px; }
        .back-btn { font-size: 20px; color: #2d88ff; cursor: pointer; padding-right: 5px;}
        .chat-dp { width: 40px; height: 40px; border-radius: 50%; background: #3a3b3c; display: flex; justify-content: center; align-items: center; font-weight: bold; overflow: hidden; color: #fff;}
        .chat-dp img { width: 100%; height: 100%; object-fit: cover; }
        .chat-name { font-size: 16px; font-weight: 700; color: #e4e6eb; margin-bottom: 2px;}
        .chat-status { font-size: 12px; color: #b0b3b8; }
        .chat-header-right { display: flex; gap: 18px; color: #2d88ff; font-size: 20px; }

        .chat-box { flex: 1; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; background: #18191a; }
        .chat-box::-webkit-scrollbar { width: 6px; }
        .chat-box::-webkit-scrollbar-thumb { background: #3a3b3c; border-radius: 4px; }
        
        .msg-row { display: flex; width: 100%; }
        .my-msg-row { justify-content: flex-end; }
        .their-msg-row { justify-content: flex-start; }
        
        .msg-bubble { max-width: 75%; padding: 10px 14px; border-radius: 18px; font-size: 15px; line-height: 1.4; word-wrap: break-word;}
        .my-msg { background-color: #0084ff; color: #fff; border-bottom-right-radius: 4px; }
        .their-msg { background-color: #3a3b3c; color: #e4e6eb; border-bottom-left-radius: 4px; }

        .chat-input-area { display: flex; align-items: center; gap: 10px; padding: 10px 15px; background-color: #242526; border-top: 1px solid #3a3b3c; flex-shrink: 0;}
        .input-icons { color: #2d88ff; font-size: 20px; cursor: pointer; display: flex; gap: 12px;}
        .chat-input-wrapper { flex: 1; background: #3a3b3c; border-radius: 20px; padding: 8px 15px; display: flex; align-items: center; }
        .chat-input-wrapper input { flex: 1; background: transparent; border: none; outline: none; color: #e4e6eb; font-size: 15px; }
        .send-btn { color: #0084ff; font-size: 22px; cursor: pointer; background: transparent; border: none; outline: none; display: none; transition: 0.2s;}
    </style>
</head>
<body>

    <div class="chat-app-container">
        
        <div class="chat-header">
            <div class="chat-header-left">
                <i class="fa-solid fa-arrow-left back-btn" onclick="window.history.back();"></i>
                <div class="chat-dp">
                    <?php if($dp): ?><img src="<?php echo $dp; ?>"><?php else: ?><?php echo strtoupper(substr($chat_user['name'], 0, 1)); ?><?php endif; ?>
                </div>
                <div>
                    <div class="chat-name"><?php echo htmlspecialchars($chat_user['name']); ?></div>
                    <div class="chat-status">Active now</div>
                </div>
            </div>
            <div class="chat-header-right">
                <i class="fa-solid fa-phone"></i>
                <i class="fa-solid fa-video"></i>
                <i class="fa-solid fa-circle-info"></i>
            </div>
        </div>

        <div class="chat-box" id="chat-box">
            <div style="text-align:center; padding:20px; color:#b0b3b8;"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>
        </div>

        <div class="chat-input-area">
            <div class="input-icons">
                <i class="fa-solid fa-circle-plus"></i>
                <i class="fa-solid fa-camera"></i>
                <i class="fa-solid fa-image"></i>
                <i class="fa-solid fa-microphone"></i>
            </div>
            <div class="chat-input-wrapper">
                <input type="text" id="msg-input" placeholder="Message" onkeyup="toggleSendBtn()" onkeypress="handleEnter(event)" autofocus>
                <i class="fa-solid fa-face-smile" style="color:#2d88ff; font-size:20px; margin-left:10px;"></i>
            </div>
            <button class="send-btn" id="send-btn" onclick="sendMessage()"><i class="fa-solid fa-paper-plane"></i></button>
        </div>

    </div>

    <script>
        const targetUserId = <?php echo $chat_user_id; ?>;
        const chatBox = document.getElementById('chat-box');
        const msgInput = document.getElementById('msg-input');
        const sendBtn = document.getElementById('send-btn');
        const inputIcons = document.querySelector('.input-icons');

        function toggleSendBtn() {
            if (msgInput.value.trim() !== '') {
                sendBtn.style.display = 'block';
                inputIcons.style.display = 'none';
            } else {
                sendBtn.style.display = 'none';
                inputIcons.style.display = 'flex';
            }
        }

        function handleEnter(e) { if (e.key === 'Enter') sendMessage(); }

        function scrollToBottom() { chatBox.scrollTop = chatBox.scrollHeight; }

        // Fetch Messages and Auto Refresh
        function fetchMessages() {
            fetch('ajax_chat.php?action=fetch_msgs&target_id=' + targetUserId)
            .then(res => res.text())
            .then(html => {
                const isScrolledToBottom = chatBox.scrollHeight - chatBox.clientHeight <= chatBox.scrollTop + 50;
                chatBox.innerHTML = html;
                if(isScrolledToBottom) scrollToBottom(); 
            });
        }

        function sendMessage() {
            const text = msgInput.value.trim();
            if (text === '') return;

            let formData = new FormData();
            formData.append('action', 'send_msg');
            formData.append('receiver_id', targetUserId);
            formData.append('message', text);

            msgInput.value = ''; 
            toggleSendBtn();
            
            // Immediate optimistic UI Update
            chatBox.innerHTML += `<div class='msg-row my-msg-row' style='display:flex; justify-content:flex-end; margin-bottom:4px;'><div class='msg-bubble my-msg'>${text}</div><i class='fa-regular fa-circle-check' style='color:#b0b3b8; font-size:14px; margin-left:5px; align-self:flex-end; margin-bottom:2px;'></i></div>`;
            scrollToBottom();

            fetch('ajax_chat.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => { if(data.status === 'success') fetchMessages(); });
        }

        fetchMessages();
        setTimeout(scrollToBottom, 500); 
        setInterval(fetchMessages, 2000); // 2 সেকেন্ড পর পর মেসেজ রিফ্রেশ এবং Seen আপডেট হবে
    </script>
</body>
</html>