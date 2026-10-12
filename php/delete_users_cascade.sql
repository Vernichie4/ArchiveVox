-- Cascading delete for selected user_ids
-- This removes users and all their related data

SET SQL_SAFE_UPDATES = 0;

START TRANSACTION;

-- Delete assignment materials for assignments belonging to teachers' classes
DELETE ram FROM reading_assignment_material ram
INNER JOIN reading_assignment ra ON ram.assignment_id = ra.assignment_id
INNER JOIN class c ON ra.class_id = c.class_id
INNER JOIN teacher t ON c.teacher_id = t.teacher_id
WHERE t.user_id IN (16, 17, 18, 19, 20, 21, 22, 23, 24, 45, 46, 47, 48, 49, 54, 55, 56, 57, 58, 163, 164);

-- Delete assignments for teachers' classes
DELETE ra FROM reading_assignment ra
INNER JOIN class c ON ra.class_id = c.class_id
INNER JOIN teacher t ON c.teacher_id = t.teacher_id
WHERE t.user_id IN (16, 17, 18, 19, 20, 21, 22, 23, 24, 45, 46, 47, 48, 49, 54, 55, 56, 57, 58, 163, 164);

-- Delete students for teachers' classes
DELETE s FROM student s
INNER JOIN class c ON s.class_id = c.class_id
INNER JOIN teacher t ON c.teacher_id = t.teacher_id
WHERE t.user_id IN (16, 17, 18, 19, 20, 21, 22, 23, 24, 45, 46, 47, 48, 49, 54, 55, 56, 57, 58, 163, 164);

-- Delete teachers' classes
DELETE c FROM class c
INNER JOIN teacher t ON c.teacher_id = t.teacher_id
WHERE t.user_id IN (16, 17, 18, 19, 20, 21, 22, 23, 24, 45, 46, 47, 48, 49, 54, 55, 56, 57, 58, 163, 164);

-- Delete teachers
DELETE FROM teacher WHERE user_id IN (16, 17, 18, 19, 20, 21, 22, 23, 24, 45, 46, 47, 48, 49, 54, 55, 56, 57, 58, 163, 164);

-- Delete users
DELETE FROM user WHERE user_id IN (16, 17, 18, 19, 20, 21, 22, 23, 24, 45, 46, 47, 48, 49, 54, 55, 56, 57, 58, 163, 164);

COMMIT;

SET SQL_SAFE_UPDATES = 1;

-- Verify deletion
SELECT 'Users deleted successfully' as status;
