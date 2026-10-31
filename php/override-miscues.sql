ALTER TABLE assessment_result 
ADD COLUMN is_overridden TINYINT(1) DEFAULT 0,
ADD COLUMN manual_substitutions INT(11) DEFAULT NULL,
ADD COLUMN manual_omissions INT(11) DEFAULT NULL,
ADD COLUMN manual_insertions INT(11) DEFAULT NULL,
ADD COLUMN manual_repetitions INT(11) DEFAULT NULL,
ADD COLUMN manual_miscues INT(11) DEFAULT NULL,
ADD COLUMN manual_words_correct INT(11) DEFAULT NULL,
ADD COLUMN manual_accuracy_percentage DECIMAL(5,2) DEFAULT NULL,
ADD COLUMN manual_wcpm DECIMAL(6,2) DEFAULT NULL;

ALTER TABLE assessment_result
  -- 1. Preserve original Whisper/ASR baseline metrics before teacher edits
  ADD COLUMN system_accuracy_percentage DECIMAL(5,2) NULL DEFAULT NULL AFTER accuracy_percentage,
  ADD COLUMN system_wcpm DECIMAL(6,2) NULL DEFAULT NULL AFTER wcpm,
  ADD COLUMN system_miscues INT(11) NULL DEFAULT NULL AFTER miscues,

  -- 2. Track validation status and timestamp
  ADD COLUMN validation_status ENUM('unverified', 'confirmed', 'adjusted') NOT NULL DEFAULT 'unverified' AFTER teacher_feedback,
  ADD COLUMN validated_at DATETIME NULL DEFAULT NULL AFTER validation_status;
