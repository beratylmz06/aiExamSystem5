<?php
session_start();
require_once __DIR__ . "/../../controllers/AuthController.php";

if (isset($_SESSION["user"])) {
    header("Location: ../dashboard.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $auth = new AuthController();
    $result = $auth->login($email, $password, "teacher");

    if ($result === true) {
        header("Location: ../dashboard.php"); 
        exit;
    } elseif ($result === "role_mismatch") {
        $error = "Access Denied. Only Teachers can login here.";
    } else {
        $error = "Invalid email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login - AI Exam System</title>
    <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
    <style>
        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; height: 100vh; overflow: hidden; }
        .page-wrapper {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100%;
            width: 100%;
        }
        .login-card { 
            background: rgba(255, 255, 255, 0.03); padding: 40px; border-radius: 20px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1);
            width: 100%; max-width: 380px; text-align: center; backdrop-filter: blur(10px);
        }
        .icon-circle {
            width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            border-radius: 50%; display: flex; align-items: center; justify-content: center; 
            margin: 0 auto 20px auto; font-size: 40px; box-shadow: 0 10px 20px rgba(118, 75, 162, 0.4);
        }
        .header h1 { font-size: 24px; color: #fff; margin-bottom: 25px; font-weight: 700; }
        
        .btn-primary { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border:none; 
            width:100%; padding:14px; border-radius:8px; font-weight:600; cursor:pointer;
            transition: all 0.2s; margin-top: 10px;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(118, 75, 162, 0.4); }
        
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-control { 
            width: 100%; padding: 14px; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; 
            box-sizing: border-box; background: rgba(255,255,255,0.05); color: white; font-size: 1rem;
        }
        .form-control::placeholder { color: #718096; }
        .form-control:focus { border-color: #667eea; outline: none; background: rgba(255,255,255,0.1); }
        
        .alert { 
            padding: 12px; border-radius: 8px; background: rgba(245, 101, 101, 0.1); 
            border: 1px solid #e53e3e; color: #fc8181; font-size: 14px; margin-bottom: 20px; 
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <div class="login-card">
        <div class="icon-circle">👩‍🏫</div>
        <div class="header">
            <h1>Teacher Login</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <input type="email" name="email" class="form-control" required placeholder="Teacher Email">
            </div>
            
            <div class="form-group">
                <input type="password" name="password" class="form-control" required placeholder="Password">
            </div>

            <button type="submit" class="btn-primary">Access Admin Panel</button>
        </form>

        <a href="../login.php" style="display:block; margin-top:25px; color:#a0aec0; text-decoration:none; font-size:13px; transition:0.2s;">
            ← Back to Role Selection
        </a>
    </div>
</div>

</body>
</html>
