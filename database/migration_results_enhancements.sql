-- Migration: Add project_score to result_scores if missing
ALTER TABLE result_scores ADD COLUMN IF NOT EXISTS project_score DECIMAL(5,2) DEFAULT 0.00 AFTER test2_score;

-- Migration: Broadcast log table
CREATE TABLE IF NOT EXISTS broadcast_log (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT(11) NOT NULL,
    recipient_type ENUM('student','parent','teacher','all') NOT NULL DEFAULT 'student',
    channel ENUM('sms','email','both') NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent','failed','pending') NOT NULL DEFAULT 'sent',
    sent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recipient (recipient_id, recipient_type),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
