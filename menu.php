<?php
session_start();
if (!isset($_SESSION['user_id'])) exit;
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
?>
<style>
    .menu-wrap { padding: 16px; color: #e4e6eb; padding-bottom: 80px; }
    .menu-card { background: #242526; border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.2); cursor: pointer; transition: 0.2s; border: 1px solid #3a3b3c;}
    .menu-card:hover { background: #3a3b3c; }
    .menu-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
    .grid-item { background: #242526; padding: 15px; border-radius: 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.2); cursor: pointer; transition: 0.2s; border: 1px solid #3a3b3c;}
    .grid-item:hover { background: #3a3b3c; }
    
    /* Accordion Settings */
    .menu-accordion { background: #242526; border-radius: 8px; border: 1px solid #3a3b3c; overflow: hidden; margin-bottom: 10px; }
    .accordion-header { display: flex; justify-content: space-between; padding: 15px; cursor: pointer; font-weight: 600; font-size: 16px; transition: 0.2s; }
    .accordion-header:hover { background: #3a3b3c; }
    .accordion-body { display: none; background: #18191a; padding: 5px 15px; border-top: 1px solid #3a3b3c; }
    .accordion-item { padding: 12px 0; border-bottom: 1px solid #3a3b3c; color: #b0b3b8; font-size: 15px; cursor: pointer; display: flex; align-items: center; gap: 12px; }
    .accordion-item:last-child { border-bottom: none; }
    .accordion-item:hover { color: #e4e6eb; }
    .accordion-icon { font-size: 20px; color: #b0b3b8; width: 25px; text-align: center; }
</style>

<div class="menu-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <h2 style="margin:0; font-size: 24px;">Menu</h2>
        <div style="background:#3a3b3c; width:36px; height:36px; border-radius:50%; display:flex; justify-content:center; align-items:center; cursor:pointer;"><i class="fa-solid fa-magnifying-glass"></i></div>
    </div>
    
    <div class="menu-card" onclick="window.location.href='profile.php?id=<?php echo $user_id; ?>'">
        <div style="width:40px; height:40px; background:#2d88ff; color:#fff; border-radius:50%; display:flex; justify-content:center; align-items:center; font-size:18px; font-weight:bold;">
            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
        </div>
        <div style="flex:1;">
            <div style="font-weight:700; font-size:16px;"><?php echo htmlspecialchars($user_name); ?></div>
            <div style="font-size:13px; color:#b0b3b8; margin-top:2px;">See your profile</div>
        </div>
        <div style="width:30px; height:30px; background:#3a3b3c; border-radius:50%; display:flex; justify-content:center; align-items:center;"><i class="fa-solid fa-chevron-down" style="color:#e4e6eb;"></i></div>
    </div>

    <div class="menu-grid">
        <div class="grid-item"><i class="fa-solid fa-bookmark" style="color:#c538ab; font-size:24px;"></i><div style="margin-top:10px; font-weight:600;">Saved</div></div>
        <div class="grid-item"><i class="fa-solid fa-users" style="color:#2d88ff; font-size:24px;"></i><div style="margin-top:10px; font-weight:600;">Groups</div></div>
        <div class="grid-item"><i class="fa-solid fa-clock-rotate-left" style="color:#18abff; font-size:24px;"></i><div style="margin-top:10px; font-weight:600;">Memories</div></div>
        <div class="grid-item"><i class="fa-solid fa-video" style="color:#2d88ff; font-size:24px;"></i><div style="margin-top:10px; font-weight:600;">Videos</div></div>
        <div class="grid-item"><i class="fa-solid fa-flag" style="color:#f5533d; font-size:24px;"></i><div style="margin-top:10px; font-weight:600;">Pages</div></div>
        <div class="grid-item"><i class="fa-solid fa-calendar-star" style="color:#e42645; font-size:24px;"></i><div style="margin-top:10px; font-weight:600;">Events</div></div>
    </div>

    <div class="menu-accordion">
        <div class="accordion-header" onclick="toggleMenu('acc-settings')">
            <span style="display:flex; align-items:center; gap:12px;"><i class="fa-solid fa-gear" style="font-size:22px; color:#b0b3b8;"></i> Settings & Privacy</span>
            <i class="fa-solid fa-chevron-down" id="icon-acc-settings"></i>
        </div>
        <div class="accordion-body" id="acc-settings">
            <div class="accordion-item"><i class="fa-solid fa-user-shield accordion-icon"></i> Settings</div>
            <div class="accordion-item"><i class="fa-solid fa-lock accordion-icon"></i> Privacy Shortcuts</div>
            <div class="accordion-item"><i class="fa-solid fa-clock accordion-icon"></i> Your Time on Facebook</div>
            <div class="accordion-item"><i class="fa-solid fa-language accordion-icon"></i> Language</div>
        </div>
    </div>

    <div class="menu-accordion">
        <div class="accordion-header" onclick="toggleMenu('acc-help')">
            <span style="display:flex; align-items:center; gap:12px;"><i class="fa-solid fa-circle-question" style="font-size:22px; color:#b0b3b8;"></i> Help & Support</span>
            <i class="fa-solid fa-chevron-down" id="icon-acc-help"></i>
        </div>
        <div class="accordion-body" id="acc-help">
            <div class="accordion-item"><i class="fa-solid fa-life-ring accordion-icon"></i> Help Center</div>
            <div class="accordion-item"><i class="fa-solid fa-envelope-open-text accordion-icon"></i> Support Inbox</div>
            <div class="accordion-item"><i class="fa-solid fa-triangle-exclamation accordion-icon"></i> Report a Problem</div>
        </div>
    </div>

    <button onclick="window.location.href='logout.php'" style="width:100%; background:#3a3b3c; color:#e4e6eb; border:none; padding:12px; border-radius:8px; margin-top:10px; font-weight:bold; font-size:16px; cursor:pointer;">Log Out</button>
</div>

<script>
    function toggleMenu(id) {
        let body = document.getElementById(id);
        let icon = document.getElementById('icon-' + id);
        if (body.style.display === 'block') {
            body.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        } else {
            body.style.display = 'block';
            icon.style.transform = 'rotate(180deg)';
        }
    }
</script>