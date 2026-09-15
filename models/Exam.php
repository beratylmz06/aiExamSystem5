<?php
// Exam domain entity model
class Exam {               // Unique identifier for the exam
    public int $id;
    public string $title;
    public int $created_by;
    public array $questions;
// Constructor to initialize exam data
    public function __construct(int $id, string $title, int $created_by, array $questions = []) {
        $this->id = $id;
        $this->title = $title;
        $this->created_by = $created_by;
        $this->questions = $questions;
    }
}
