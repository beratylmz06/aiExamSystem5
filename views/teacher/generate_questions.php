<?php
session_start();
// Redirect unauthenticated users to login page
if (!isset($_SESSION["user"])) { header("Location: ../../public/login.php"); exit; }
if ($_SESSION["user"]["role"] !== "teacher") { header("Location: ../../public/unauthorized.php"); exit; }

// Load question controller
require_once __DIR__ . "/../../controllers/QuestionController.php";
require_once __DIR__ . "/../../repositories/UserRepository.php";

$controller = new QuestionController();
$options = $controller->getOptions();

// Load students list for assignment
$userRepo = new UserRepository();
$students = $userRepo->listByRole("student");

$msg = "";
$err = "";
$generatedQuestions = [];
$showPreview = false;
$showSuccess = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "generate";
    
    if ($action === "generate") {
        // Generate questions
        $params = [
            "subject" => trim($_POST["subject"] ?? ""),
            "topic" => trim($_POST["topic"] ?? ""),
            "difficulty" => $_POST["difficulty"] ?? "medium",
            "question_type" => $_POST["question_type"] ?? "mcq",
            "num_questions" => (int)($_POST["num_questions"] ?? 5),
            "constraints" => [
                "avoid_topics" => array_filter(array_map("trim", explode(",", $_POST["avoid_topics"] ?? ""))),
                "language" => "en",
                "time_limit_minutes" => (int)($_POST["time_limit"] ?? 30),
                "style" => $_POST["style"] ?? "university_exam",
                "needs_explanations" => isset($_POST["needs_explanations"]),
                "score_threshold_high" => (int)($_POST["score_threshold_high"] ?? 70),
                "score_threshold_medium" => (int)($_POST["score_threshold_medium"] ?? 50)
            ]
        ];
        
        $result = $controller->generate($params);
        
        if (isset($result["status"]) && $result["status"] === "ok") {
            $generatedQuestions = $result["questions"] ?? [];
            $showPreview = true;
            $msg = count($generatedQuestions) . " questions generated successfully!";
            // Store in session for save action
            $_SESSION["generated_questions"] = $generatedQuestions;
            $_SESSION["generation_params"] = $params;
        } else {
            $err = $result["message"] ?? "Question generation failed.";
            if (isset($result["missing"])) {
                $err .= " Missing fields: " . implode(", ", $result["missing"]);
            }
        }
    } elseif ($action === "save") {
        // Save to new exam
        $generatedQuestions = $_SESSION["generated_questions"] ?? [];
        $params = $_SESSION["generation_params"] ?? [];
        
        if (empty($generatedQuestions)) {
            $err = "No questions found to save. Generate questions first.";
        } else {
            $examData = [
                "title" => trim($_POST["exam_title"] ?? ($params["subject"] . " - " . $params["topic"])),
                "teacher_id" => (int)$_SESSION["user"]["id"],
                "assignment_type" => $_POST["assignment_type"] ?? "all",
                "assigned_students" => $_POST["assigned_students"] ?? [],
                "score_threshold_high" => $params["constraints"]["score_threshold_high"] ?? 70,
                "score_threshold_medium" => $params["constraints"]["score_threshold_medium"] ?? 50
            ];
            
            $service = new \QuestionGeneratorService();
            $result = $service->createExamWithQuestions($examData, $generatedQuestions);
            
            if ($result["success"]) {
                $msg = "Exam created successfully! (ID: " . $result["exam_id"] . ")";
                $showSuccess = true;
                // Clear session
                unset($_SESSION["generated_questions"], $_SESSION["generation_params"]);
                $generatedQuestions = [];
                $showPreview = false;
            } else {
                $err = "Exam could not be saved. " . implode(", ", $result["errors"]);
                $showPreview = true;
            }
        }
    }
}

// Restore from session if available
if (empty($generatedQuestions) && isset($_SESSION["generated_questions"])) {
    $generatedQuestions = $_SESSION["generated_questions"];
    $showPreview = !empty($generatedQuestions);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>AI Question Generator</title>
    <link rel="stylesheet" href="/aiExamSystem/assets/css/style.css">
    <style>
        /* Modern Dark Theme Overrides */
        .card { 
            border: 1px solid var(--border); 
            box-shadow: 0 25px 60px rgba(0,0,0,0.5); 
            background: linear-gradient(180deg, rgba(20,11,45,0.95), rgba(8,4,20,0.95));
            backdrop-filter: blur(14px);
        }
        
        .form-header { margin-bottom: 25px; border-bottom: 1px solid var(--border); padding-bottom: 15px; }
        .form-header h3 { margin: 0; color: white; font-size: 1.5rem; }
        .text-muted { color: var(--muted) !important; font-size: 0.9rem; margin-top: 5px; }
        
        .form-section { margin-bottom: 20px; }
        .secondary-bg { 
            background-color: rgba(255,255,255,0.03); 
            padding: 20px; border-radius: 12px; 
            border: 1px solid var(--border); 
        }
        
        .form-label { display: block; font-weight: 600; color: var(--muted); margin-bottom: 8px; font-size: 0.95rem; }
        
        /* Remove whitespace overrides so global style.css inputs work */
        .form-control, .form-select {
            width: 100%; padding: 12px 15px; border-radius: 16px; 
            border: 1px solid var(--border);
            background: rgba(139,92,246,0.08); /* Transparent Purple */
            color: white; /* White text */
            font-size: 1rem; transition: all 0.2s;
        }
        .form-control:focus, .form-select:focus { 
            border-color: #22d3ee; box-shadow: 0 0 0 3px rgba(34,211,238,0.25); outline: none; 
        }
        
        .input-with-icon { position: relative; }
        .input-with-icon .icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); opacity: 0.7; font-size: 1.2rem; }
        .input-with-icon input { padding-left: 45px; }
        
        .form-row { display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
        .col-md-6 { flex: 1 1 45%; }
        .col-md-4 { flex: 1 1 30%; }
        
        /* Toggle Section */
        .advanced-settings-toggle {
            cursor: pointer; color: #a9a9c5; font-weight: 600; display: flex; align-items: center; gap: 10px;
            padding: 15px; border-radius: 12px; background: rgba(255,255,255,0.03); border: 1px solid var(--border);
            transition: 0.2s;
        }
        .advanced-settings-toggle:hover { color: white; border-color: #667eea; background: rgba(102, 126, 234, 0.1); }
        .advanced-settings-toggle .arrow { transition: transform 0.3s ease; font-size: 0.8rem; margin-left: auto; }
        
        /* Button & Footer */
        .form-footer { margin-top: 30px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 25px; }
        .btn-primary { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;
            padding: 14px 40px; border-radius: 18px; font-weight: 700; cursor: pointer; transition: transform 0.1s;
            box-shadow: 0 10px 20px rgba(118, 75, 162, 0.4);
        }
        .btn-primary:hover { box-shadow: 0 15px 30px rgba(118, 75, 162, 0.6); transform: translateY(-2px); }
        .btn-block { width: auto; }
        
        /* Checkbox Styling */
        .checkbox-wrapper { 
            display: inline-flex; align-items: center; cursor: pointer; user-select: none; gap: 10px; 
            padding: 12px 20px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 16px;
            transition: all 0.2s; color: var(--muted);
        }
        .checkbox-wrapper:hover { border-color: #667eea; color: white; }
        .checkbox-wrapper input { display: none; }
        
        .checkmark {
            position: relative; height: 22px; width: 22px; min-width: 22px; flex-shrink: 0;
            background-color: rgba(255,255,255,0.1); border-radius: 6px;
            border: 1px solid var(--border); transition: all 0.2s;
        }
        .checkbox-wrapper input:checked ~ .checkmark { background-color: #667eea; border-color: #667eea; }
        .checkmark:after {
            content: ""; position: absolute; display: none;
            left: 7px; top: 3px; width: 6px; height: 12px;
            border: solid white; border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .checkbox-wrapper input:checked ~ .checkmark:after { display: block; }
        .checkbox-label-text { font-weight: 500; font-size: 0.95rem; }
        
        .form-text { display: block; font-size: 0.85rem; color: var(--muted); margin-top: 5px; }

        /* Preview styles */
        .ai-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block; margin-left: 8px; box-shadow: 0 0 15px rgba(102, 126, 234, 0.5); }
        
        .question-preview { 
            background: rgba(255,255,255,0.02); 
            border-left: 4px solid #667eea; padding: 25px; margin: 25px 0; 
            border-radius: 0 12px 12px 0; 
        }
        .question-preview h4 { margin: 0 0 15px 0; color: white; font-size: 1.1rem; }
        
        .choice-list { list-style: none; padding: 0; }
        .choice-list li { 
            padding: 12px 18px; margin: 10px 0; 
            background: rgba(255,255,255,0.03); border-radius: 12px; 
            border: 1px solid var(--border); color: var(--muted); 
            transition: 0.2s;
        }
        .choice-list li.correct { 
            background: rgba(45, 212, 191, 0.1); border-color: rgba(45, 212, 191, 0.5); color: #2dd4bf; 
        }
        
        .explanation-box { 
            background: rgba(235, 140, 2, 0.1); padding: 15px; border-radius: 8px; margin-top: 20px; 
            border-left: 3px solid #ed8936; color: #fbd38d; font-size: 0.95rem; 
        }
        
        .save-section { 
            background: rgba(20, 11, 45, 0.8); padding: 30px; border-radius: 16px; margin-top: 40px; 
            border: 1px solid #667eea; box-shadow: 0 0 40px rgba(102, 126, 234, 0.15);
        }
        .save-section h4 { color: white; margin-top: 0; margin-bottom: 20px; font-size: 1.3rem; }

        /* Student Checkbox Styles */
        .student-checkbox-item:hover {
            background: rgba(102, 126, 234, 0.15) !important;
            border-color: #667eea !important;
        }
        .student-checkbox-item:has(input:checked) {
            background: rgba(102, 126, 234, 0.2) !important;
            border-color: #667eea !important;
        }
        .save-section label:not(.checkbox-wrapper):not(.student-checkbox-item) { font-weight: 600; color: var(--muted); display: block; margin-bottom: 10px; }
        .save-section .checkbox-wrapper { display: inline-flex !important; }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(8, 4, 20, 0.95);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 25px;
        }
        .loading-overlay.active {
            display: flex;
        }
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(102, 126, 234, 0.2);
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-text {
            color: white;
            font-size: 1.4rem;
            font-weight: 600;
            text-align: center;
        }
        .loading-subtext {
            color: var(--muted);
            font-size: 0.95rem;
        }

        /* Success Overlay */
        .success-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(8, 4, 20, 0.95);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 25px;
        }
        .success-overlay.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .success-checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2dd4bf 0%, #22c55e 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            animation: scaleIn 0.5s ease;
            box-shadow: 0 0 40px rgba(45, 212, 191, 0.5);
        }
        @keyframes scaleIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .success-checkmark svg {
            width: 40px;
            height: 40px;
            stroke: white;
            stroke-width: 3;
            fill: none;
            animation: drawCheck 0.5s ease 0.3s forwards;
            stroke-dasharray: 50;
            stroke-dashoffset: 50;
        }
        @keyframes drawCheck {
            to { stroke-dashoffset: 0; }
        }
        .success-text {
            color: #2dd4bf;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            animation: slideUp 0.4s ease 0.2s both;
        }
        .success-subtext {
            color: var(--muted);
            font-size: 0.95rem;
            animation: slideUp 0.4s ease 0.3s both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body class="page">

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner"></div>
    <div class="loading-text">✨ Generating Questions...</div>
    <div class="loading-subtext">Please wait while AI creates your questions</div>
</div>

<!-- Success Overlay -->
<div id="successOverlay" class="success-overlay">
    <div class="success-checkmark">
        <svg viewBox="0 0 24 24">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
    </div>
    <div class="success-text">✅ Exam Saved Successfully!</div>
    <div class="success-subtext">Your exam has been created and saved</div>
</div>

<div class="container">

    <div class="topbar">
        <div>
            <h2>AI Question Generator <span class="ai-badge">AI Powered</span></h2>
            <div class="muted">Generate automated questions with AI</div>
        </div>
        <a class="btn btn-light" href="../../public/dashboard.php">Back</a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- Generation Form -->
    <form method="post" class="card form">
        <input type="hidden" name="action" value="generate">
        
        <div class="form-header">
            <h3>✨ Question Parameters</h3>
            <p class="text-muted">Select the topic you want to generate questions for.</p>
        </div>
        
        <!-- Temel Ayarlar -->
        <div class="form-section">
            <div class="form-row">
                <div class="col-md-6">
                    <label class="form-label">Subject / Field</label>
                    <div class="input-with-icon">
                        <span class="icon">📚</span>
                        <input name="subject" list="subjects" required placeholder="Ex: Java, Math, History..." class="form-control" oninvalid="this.setCustomValidity('Please fill out this field.')" oninput="this.setCustomValidity('')">
                    </div>
                    <datalist id="subjects">
                        <?php foreach ($options["subjects"] as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Topic Title</label>
                    <div class="input-with-icon">
                        <span class="icon">🎯</span>
                        <input name="topic" required placeholder="Ex: Functions, Ottoman Empire..." class="form-control" oninvalid="this.setCustomValidity('Please fill out this field.')" oninput="this.setCustomValidity('')">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="col-md-4">
                    <label class="form-label">Difficulty Level</label>
                    <select name="difficulty" class="form-select">
                        <?php foreach ($options["difficulties"] as $d): ?>
                            <option value="<?= $d["value"] ?>"><?= $d["label"] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Question Type</label>
                    <select name="question_type" class="form-select">
                        <?php foreach ($options["question_types"] as $qt): ?>
                            <option value="<?= $qt["value"] ?>"><?= $qt["label"] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Number of Questions</label>
                    <input type="number" name="num_questions" value="5" class="form-control"
                           min="<?= $options["num_questions_range"]["min"] ?>" 
                           max="<?= $options["num_questions_range"]["max"] ?>">
                </div>
            </div>
        </div>
        
        
        <!-- Advanced Settings (Always Visible) -->
        <div class="form-section" style="margin-top: 20px;">
            <h4 style="color: var(--muted); margin-bottom: 15px;">⚙️ Additional Settings</h4>
            <div class="secondary-bg">
                <div class="form-row">
                    <div class="col-md-6">
                        <label class="form-label">Exam Format</label>
                        <select name="style" class="form-select">
                            <?php foreach ($options["styles"] as $s): ?>
                                <option value="<?= $s["value"] ?>"><?= $s["label"] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Determines the tone and style of the questions.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Estimated Time (min)</label>
                        <input type="number" name="time_limit" value="30" min="5" max="180" class="form-control">
                    </div>
                </div>
                
                <div class="form-group mt-3">
                    <label class="form-label">Topics to Avoid</label>
                    <input name="avoid_topics" placeholder="Do not ask questions from these topics (e.g. Interface, Abstract Class)" class="form-control">
                </div>
                
                <!-- Passing Grade Threshold -->
                <div class="form-row" style="margin-top: 20px;">
                    <div class="col-md-6">
                        <label class="form-label">📊 Passing Grade</label>
                        <input type="number" name="score_threshold_high" value="60" min="0" max="100" class="form-control">
                        <small class="form-text">Scores >= this value = 🟢 Green (Pass), below = 🔴 Red (Fail)</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="form-footer">
            <label class="checkbox-wrapper">
                <input type="checkbox" name="needs_explanations" checked>
                <span class="checkmark"></span>
                <span class="checkbox-label-text">Include detailed explanations in answers</span>
            </label>
            
            <button type="submit" class="btn btn-primary btn-lg btn-block">
                ✨ Generate Questions
            </button>
        </div>
    </form>

    <script>
    function toggleAdvanced(element) {
        const content = document.getElementById('advancedOptions');
        const arrow = element.querySelector('.arrow');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            arrow.style.transform = 'rotate(180deg)';
            element.classList.add('active');
        } else {
            content.style.display = 'none';
            arrow.style.transform = 'rotate(0deg)';
            element.classList.remove('active');
        }
    }

    function toggleSaveStudentSelect() {
        const specificRadio = document.querySelector('#saveExamForm input[name="assignment_type"][value="specific"]');
        const container = document.getElementById('saveStudentSelectContainer');
        
        if (specificRadio && specificRadio.checked) {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }

    // Show loading overlay when generating questions
    document.addEventListener('DOMContentLoaded', function() {
        const generateForm = document.querySelector('form[action=""], form:not([action])'); 
        const forms = document.querySelectorAll('form');
        
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const action = form.querySelector('input[name="action"]');
                if (action && action.value === 'generate') {
                    document.getElementById('loadingOverlay').classList.add('active');
                }
            });
        });

        // Show success overlay if exam was saved
        <?php if ($showSuccess): ?>
        const successOverlay = document.getElementById('successOverlay');
        successOverlay.classList.add('active');
        
        // Auto-hide after 3 seconds
        setTimeout(function() {
            successOverlay.classList.remove('active');
        }, 3000);
        
        // Click to dismiss
        successOverlay.addEventListener('click', function() {
            successOverlay.classList.remove('active');
        });
        <?php endif; ?>
    });
    </script>

    <!-- Preview Section -->
    <?php if ($showPreview && !empty($generatedQuestions)): ?>
    <div class="card" style="margin-top: 20px;">
        <h3>Generated Questions (<?= count($generatedQuestions) ?>)</h3>
        
        <?php foreach ($generatedQuestions as $index => $q): ?>
        <div class="question-preview">
            <h4>Question <?= $index + 1 ?>: <?= htmlspecialchars($q["prompt"] ?? "") ?></h4>
            
            <?php if (!empty($q["choices"])): ?>
            <ul class="choice-list">
                <?php foreach ($q["choices"] as $choice): ?>
                <li class="<?= ($choice["key"] ?? "") === ($q["answer_key"] ?? "") ? "correct" : "" ?>">
                    <strong><?= htmlspecialchars($choice["key"] ?? "") ?>)</strong> 
                    <?= htmlspecialchars($choice["text"] ?? "") ?>
                    <?php if (($choice["key"] ?? "") === ($q["answer_key"] ?? "")): ?>
                        ✓
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php elseif ($q["type"] === "true_false"): ?>
            <p><strong>Correct Answer:</strong> <?= $q["answer_key"] === "true" ? "True" : "False" ?></p>
            <?php else: ?>
            <p><strong>Expected Answer:</strong> <?= htmlspecialchars($q["answer_key"] ?? "") ?></p>
            <?php if (!empty($q["rubric"])): ?>
            <p><strong>Rubric:</strong> <?= htmlspecialchars($q["rubric"]) ?></p>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php if (!empty($q["explanation"])): ?>
            <div class="explanation-box">
                <strong>📝 Explanation:</strong> <?= htmlspecialchars($q["explanation"]) ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($q["tags"])): ?>
            <div class="tags">
                <?php foreach ($q["tags"] as $tag): ?>
                <span class="tag"><?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        
        <!-- Save Section -->
        <div class="save-section">
            <h4>Save Questions as Exam</h4>
            <form method="post" id="saveExamForm">
                <input type="hidden" name="action" value="save">
                
                <div style="margin-bottom: 15px;">
                    <label>Exam Title</label>
                    <input name="exam_title" required class="form-control"
                           value="<?= htmlspecialchars(($_SESSION["generation_params"]["subject"] ?? "") . " - " . ($_SESSION["generation_params"]["topic"] ?? "")) ?>" oninvalid="this.setCustomValidity('Please fill out this field.')" oninput="this.setCustomValidity('')">
                </div>
                
                <!-- Student Assignment Section -->
                <div style="margin-bottom: 15px; border-top: 1px solid var(--border); padding-top: 15px;">
                    <label style="display: block; margin-bottom: 10px;">📋 Assign Exam To</label>
                    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                        <label class="checkbox-wrapper" style="flex: 1;">
                            <input type="radio" name="assignment_type" value="all" checked onchange="toggleSaveStudentSelect()">
                            <span class="checkmark"></span>
                            <span class="checkbox-label-text">All Students</span>
                        </label>
                        <label class="checkbox-wrapper" style="flex: 1;">
                            <input type="radio" name="assignment_type" value="specific" onchange="toggleSaveStudentSelect()">
                            <span class="checkmark"></span>
                            <span class="checkbox-label-text">Specific Students</span>
                        </label>
                    </div>
                    
                    <div id="saveStudentSelectContainer" style="display: none;">
                        <label style="display: block; margin-bottom: 8px;">Select Students</label>
                        <div class="student-checkbox-list" style="max-height: 180px; overflow-y: auto; background: rgba(255,255,255,0.02); border: 1px solid var(--border); border-radius: 12px; padding: 10px;">
                            <?php foreach ($students as $student): ?>
                                <label class="student-checkbox-item" style="display: flex; align-items: center; padding: 10px 15px; margin: 5px 0; background: rgba(255,255,255,0.03); border-radius: 8px; cursor: pointer; transition: all 0.2s; border: 1px solid transparent;">
                                    <input type="checkbox" name="assigned_students[]" value="<?= $student['id'] ?>" style="width: 18px; height: 18px; margin-right: 12px; accent-color: #667eea; cursor: pointer;">
                                    <span style="color: white; font-size: 0.95rem;">
                                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                        <?php if (!empty($student['student_number'])): ?>
                                            <span style="color: var(--muted); font-size: 0.85rem;">(<?= htmlspecialchars($student['student_number']) ?>)</span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="form-text">Click checkboxes to select students</small>
                    </div>
                </div>
                
                <button type="submit" class="btn" style="margin-top: 10px;">💾 Save as Exam</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
