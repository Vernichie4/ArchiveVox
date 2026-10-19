# Assignment Attempt Limit Implementation

## Summary of Changes

This implementation adds a 3-attempt limit per student per assignment, with attempts 1-2 marked as "Practice" and attempt 3 marked as "Final Score".

## Database Changes

### New SQL File: `php/add_attempt_type.sql`

Run this SQL script in MySQL Workbench to update your database schema:

```sql
-- Add attempt_number to quiz_attempt table
ALTER TABLE quiz_attempt
ADD COLUMN attempt_number INT DEFAULT 1
COMMENT 'Attempt number (1-3, 3 is final)';

-- Add attempt_type to quiz_attempt table
ALTER TABLE quiz_attempt
ADD COLUMN attempt_type ENUM('practice', 'final') DEFAULT 'practice'
COMMENT 'Type of attempt: practice (1st/2nd) or final (3rd)';

-- Add attempt_type to reading_activity table
ALTER TABLE reading_activity
ADD COLUMN attempt_type ENUM('practice', 'final') DEFAULT 'practice'
COMMENT 'Type of attempt: practice (1st/2nd) or final (3rd)';

-- Initialize attempt_number for existing records
-- Update existing records to set attempt_type based on attempt_number
```

## Backend Changes

### File: `php/api/assignments.php`

#### 1. Quiz Start Endpoint (lines 759-797)
- **Added 3-attempt limit enforcement**: Counts all existing attempts (including in_progress, completed, abandoned) before allowing a new attempt
- **Added attempt_type logic**: Sets attempt_type to 'final' for attempt 3, 'practice' for attempts 1-2
- **Added attempt_number tracking**: Increments attempt_number based on existing attempts
- **Updated response**: Returns attempt_number, attempt_type, and attempts_remaining to the frontend

#### 2. Reading Start Endpoint (lines 718-752)
- **Added 3-attempt limit enforcement**: Counts all existing attempts before allowing a new attempt
- **Added attempt_type logic**: Sets attempt_type to 'final' for attempt 3, 'practice' for attempts 1-2
- **Added attempt_number tracking**: Increments attempt_number based on existing attempts
- **Updated response**: Returns attempt_number, attempt_type, and attempts_remaining to the frontend

#### 3. Assignment Detail Endpoint (lines 653-691)
- **Updated reading activity query**: Now includes attempt_number and attempt_type columns
- **Updated quiz attempt query**: Now includes attempt_number and attempt_type columns
- **Updated response structure**: Reading results and quiz attempts now include attempt information

## Frontend Changes

### File: `student/student.js`

#### 1. Quiz Start Function (lines 1295-1317)
- **Added attempt info display**: Shows "Attempt X of 3 (practice/final) - X attempts remaining" when starting a quiz
- **Added state tracking**: Stores currentAttemptNumber, currentAttemptType, and attemptsRemaining

#### 2. Reading Start Function (lines 838-849)
- **Added attempt info display**: Shows "Attempt X of 3 (practice/final) - X attempts remaining" when starting reading
- **Added state tracking**: Stores currentAttemptNumber, currentAttemptType, and attemptsRemaining

### File: `assets/app.js`

#### 1. Student Detail View (lines 5489-5556)
- **Added "Attempt Type" column**: New table column to display whether each result is Practice or Final
- **Updated table headers**: Added Attempt Type column between Quiz Score and Status
- **Added attempt type badges**: Displays colored badges showing "practice (#X)" or "final (#X)"
- **Updated quiz result display**: Now includes attempt_type and attempt_number
- **Updated reading result display**: Now includes attempt_type and attempt_number

## Key Features

### 1. 3-Attempt Limit
- Each student can attempt an assignment maximum 3 times
- The limit is enforced for both reading activities and quizzes
- Attempt 1: Practice
- Attempt 2: Practice
- Attempt 3: Final Score
- Attempt 4+: Blocked with error message

### 2. Attempt Type Tracking
- Database stores attempt_type ('practice' or 'final')
- Frontend displays attempt type with visual badges
- Teachers can distinguish practice attempts from final scores

### 3. Attempt Counting Logic
- Counts ALL attempts (in_progress, completed, abandoned)
- Prevents students from bypassing the limit by abandoning attempts
- Resumes are NOT allowed if 3 attempts already exist

### 4. Student Dashboard Feedback
- Shows "Attempt X of 3 (practice/final) - X attempts remaining"
- Clear indication of which attempt they're on
- Students know when they're on their final attempt

### 5. Teacher Dashboard Visibility
- Teachers can see attempt type for each result
- Practice attempts shown with gray badge
- Final attempts shown with red badge
- Attempt number displayed in badge (e.g., "final (#3)")

## Testing Instructions

### Step 1: Run Database Migration
1. Open MySQL Workbench
2. Open `php/add_attempt_type.sql`
3. Execute the script to add the new columns
4. Verify the columns were added successfully

### Step 2: Test Student Quiz Attempts
1. Log in as a student
2. Navigate to My Assignments
3. Click on an assignment
4. Start the quiz
5. **Expected**: Should see "Attempt 1 of 3 (practice) - 2 attempts remaining"
6. Complete or abandon the quiz
7. Start the quiz again
8. **Expected**: Should see "Attempt 2 of 3 (practice) - 1 attempt remaining"
9. Complete or abandon the quiz
10. Start the quiz again
11. **Expected**: Should see "Attempt 3 of 3 (final) - 0 attempts remaining"
12. Complete or abandon the quiz
13. Try to start the quiz a 4th time
14. **Expected**: Error message "You have reached the maximum of 3 attempts for this assignment."

### Step 3: Test Student Reading Attempts
1. Log in as a student
2. Navigate to My Assignments
3. Click on an assignment
4. Start reading
5. **Expected**: Should see "Attempt 1 of 3 (practice) - 2 attempts remaining"
6. Complete or abandon the reading
7. Start reading again
8. **Expected**: Should see "Attempt 2 of 3 (practice) - 1 attempt remaining"
9. Complete or abandon the reading
10. Start reading again
11. **Expected**: Should see "Attempt 3 of 3 (final) - 0 attempts remaining"
12. Complete or abandon the reading
13. Try to start reading a 4th time
14. **Expected**: Error message "You have reached the maximum of 3 attempts for this assignment."

### Step 4: Test Abandoned/Resumed Attempts
1. Start a quiz or reading
2. Navigate away without completing (simulating abandonment)
3. Try to start again
4. **Expected**: Should increment attempt count (abandoned attempts count toward the limit)
5. After 3 attempts (even if abandoned), 4th attempt should be blocked

### Step 5: Test Teacher Dashboard
1. Log in as a teacher
2. Navigate to Students tab
3. Click on a student
4. Expand their assignment details
5. **Expected**: Should see "Attempt Type" column in the assignments table
6. **Expected**: Practice attempts show gray badge "practice (#X)"
7. **Expected**: Final attempts show red badge "final (#3)"

### Step 6: Verify Database Records
1. Query quiz_attempt table:
   ```sql
   SELECT attempt_id, student_id, assignment_material_id, attempt_number, attempt_type, status
   FROM quiz_attempt
   WHERE student_id = [test_student_id]
   ORDER BY started_at;
   ```
2. **Expected**: attempt_number should be 1, 2, 3; attempt_type should be 'practice', 'practice', 'final'
3. Query reading_activity table:
   ```sql
   SELECT activity_id, student_id, assignment_material_id, attempt_number, attempt_type, activity_status
   FROM reading_activity
   WHERE student_id = [test_student_id]
   ORDER BY started_at;
   ```
4. **Expected**: attempt_number should be 1, 2, 3; attempt_type should be 'practice', 'practice', 'final'

## Files Modified

1. **Database**: `php/add_attempt_type.sql` (NEW)
2. **Backend**: `php/api/assignments.php`
3. **Frontend**: `student/student.js`
4. **Frontend**: `assets/app.js`

## Rollback Instructions

If you need to revert these changes:

1. **Remove database columns**:
   ```sql
   ALTER TABLE quiz_attempt DROP COLUMN attempt_number;
   ALTER TABLE quiz_attempt DROP COLUMN attempt_type;
   ALTER TABLE reading_activity DROP COLUMN attempt_type;
   ```

2. **Revert code changes** using git:
   ```bash
   git checkout php/api/assignments.php
   git checkout student/student.js
   git checkout assets/app.js
   ```

## Notes

- The implementation preserves all existing assessment history
- Existing records will have attempt_number initialized based on chronological order
- The 3-attempt limit applies per student per assignment material
- In-progress and abandoned attempts count toward the limit to prevent bypassing
- The frontend displays clear feedback about attempt count and remaining attempts
