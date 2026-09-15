<?php
session_start();
// If already logged in, redirect to dashboard
if (isset($_SESSION["user"])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Selection - AI Exam System</title>
    <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
    <style>
        .page { 
            background:
                radial-gradient(800px 400px at top left, rgba(139,92,246,.35), transparent),
                radial-gradient(700px 350px at bottom right, rgba(34,211,238,.25), transparent),
                linear-gradient(180deg,#05010d,#0b0616);
            display: flex; align-items: center; justify-content: center; min-height: 100vh; font-family: 'Inter', sans-serif;
        }
        .selection-container { text-align: center; }
        .title { font-size: 2.5rem; color: #fff; margin-bottom: 20px; font-weight: 800; }
        .subtitle { color: #a9a9c5; margin-bottom: 50px; font-size: 1.1rem; }
        .cards { display: flex; gap: 30px; justify-content: center; flex-wrap: wrap;}
        .role-card {
            background: rgba(255, 255, 255, 0.05); padding: 40px; border-radius: 20px; width: 280px;
            backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1);
            text-decoration: none; color: white; transition: all 0.3s ease;
        }
        .role-card:hover { 
            transform: translateY(-10px); 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: transparent;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .role-icon { font-size: 60px; margin-bottom: 20px; display: block; }
        .role-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 10px; display: block; }
        .role-desc { color: #a9a9c5; font-size: 0.95rem; line-height: 1.5; }
        .role-card:hover .role-desc { color: rgba(255,255,255,0.9); }
    </style>
</head>
<body class="page">

<div class="selection-container">
    <h1 class="title">Welcome Back</h1>
    <p class="subtitle">Select your role to continue to the AI Exam System</p>
    
    <div class="cards">
        <a href="login/student.php" class="role-card">
            <span class="role-icon">👨‍🎓</span>
            <span class="role-title">Student Login</span>
            <span class="role-desc">Access your exams, view results, and track your progress.</span>
        </a>

        <a href="login/teacher.php" class="role-card">
            <span class="role-icon">👩‍🏫</span>
            <span class="role-title">Teacher Login</span>
            <span class="role-desc">Create AI-powered exams, manage classes, and view reports.</span>
        </a>
    </div>
    
    <p style="margin-top: 50px; color: #a9a9c5;">
        Don't have an account? <a href="register.php" style="color: #667eea; font-weight: 600;">Register Now</a>
    </p>
</div>

</body>
</html>
