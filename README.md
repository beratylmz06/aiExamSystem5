# AI Exam System

## Project Overview
AI Exam System is a web-based examination platform developed using PHP (MVC architecture) and MySQL.  
The system supports multiple exam types and provides AI-assisted feedback and reporting for both students and teachers.

## User Roles

### Teacher
- Create exams (MCQ, Matching, Dialog)
- View class reports
- Download reports as PDF or Word
- View registered students

### Student
- Register and log in
- Take exams
- View results and feedback
- Download personal reports

## Exam Types

### Multiple Choice (MCQ)
- 3–4 options per question
- One correct answer
- Automatically graded

### Matching
- One matching question per exam
- 4 left-right pairs
- Dropdown-based answers

### Dialog (Scenario-Based)
- Open-ended text questions
- Text-based evaluation

## AI Features

### Risk Analysis
Students may be flagged as **At Risk** based on performance.

### Weekly Recommendations
Generated automatically based on exam score:
- 100: No deficiencies – Excellent performance
- 67–99: General Revision
- 34–66: General Knowledge
- 0–33: General Knowledge & Previous Topics

## Reporting
- Student reports: score, risk, weekly recommendation
- Class reports: all students, averages, risk indicators
- Export formats: PDF and Word

## Technical Details
- Language: PHP
- Database: MySQL
- Architecture: MVC
- PDF Generation: Dompdf
- Security: sessions, role-based access, prepared statements

## Installation
1. Import the database.
2. Configure `/config/database.php`.
3. Run the project under a local server.
4. Open `/public` directory in browser.

## Notes
This project was developed for academic purposes and includes full inline documentation.
