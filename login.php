<?php
session_start();
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid email or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - SocialPHP</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="background-color: #f0f2f5;">
    <div class="login-container">
        <div class="login-left">
            <h1>SocialPHP</h1>
            <p>Connect with friends and the world around you on SocialPHP.</p>
        </div>
        <div class="login-box">
            <?php if(isset($error)) echo "<p style='color:red; margin-bottom:10px;'>$error</p>"; ?>
            <form method="POST" action="">
                <input type="email" name="email" placeholder="Email address" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Log In</button>
            </form>
            <hr style="margin: 20px 0; border: 0.5px solid #dadde1;">
            <a href="register.php"><button class="create-btn">Create new account</button></a>
        </div>
    </div>
</body>
</html>