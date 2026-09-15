<?php
class Result {
    public int $id;
    public int $user_id;                // Unique identifier for the result record
    public int $exam_id;
    public int $score;
    public string $feedback;
    public string $submitted_at;
    // Constructor to initialize the evaluation data
    public function __construct(
        int $id,
        int $user_id,
        int $exam_id,
        int $score,
        string $feedback,
        string $submitted_at
    ) {
        $this->id = $id;
        $this->user_id = $user_id;
        $this->exam_id = $exam_id;
        $this->score = $score;
        $this->feedback = $feedback;
        $this->submitted_at = $submitted_at;
    }
}
