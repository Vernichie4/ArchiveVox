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