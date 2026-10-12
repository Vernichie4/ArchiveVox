-- Add transcription job tracking columns to reading_activity table
ALTER TABLE reading_activity 
ADD COLUMN transcription_job_id VARCHAR(255) NULL,
ADD COLUMN transcription_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending';
