"use strict";

/*
 * ============================================================
 * ARCHIVEVOX STUDENT API CONNECTOR
 * ============================================================
 *
 * Small API helper used by student.js.
 *
 * Python backend:
 *
 *     http://127.0.0.1:5000
 *
 * These functions will eventually replace the hardcoded
 * assignment data currently inside student.js.
 *
 * ============================================================
 */

const ARCHIVEVOX_API_BASE =
    "http://127.0.0.1:5000/api";


/* ============================================================
   GENERIC REQUEST HELPER
============================================================ */

async function apiRequest(
    endpoint,
    options = {}
) {
    const url =
        `${ARCHIVEVOX_API_BASE}${endpoint}`;

    const response =
        await fetch(url, {
            credentials: "include",

            headers: {
                "Content-Type":
                    "application/json",

                ...(options.headers || {})
            },

            ...options
        });


    /*
     * Try to parse JSON.
     */
    let data = null;

    try {

        data =
            await response.json();

    } catch (error) {

        throw new Error(
            `Invalid response from ArchiveVox API (${response.status}).`
        );

    }


    /*
     * HTTP error
     */
    if (!response.ok) {

        throw new Error(
            data?.message ||
            `ArchiveVox API request failed (${response.status}).`
        );

    }


    /*
     * Application-level error
     */
    if (
        data &&
        data.success === false
    ) {

        throw new Error(
            data.message ||
            "ArchiveVox API request failed."
        );

    }


    return data;
}


/* ============================================================
   HEALTH CHECK
============================================================ */

async function checkArchiveVoxApi() {

    return apiRequest(
        "/health",
        {
            method: "GET"
        }
    );

}


/* ============================================================
   LOAD STUDENT ASSIGNMENTS
============================================================ */

/*
 * Example:
 *
 * const data =
 *     await loadStudentAssignments(91);
 *
 * data.assignments
 */

async function loadStudentAssignments(
    studentId
) {

    if (!studentId) {

        throw new Error(
            "Student ID is required."
        );

    }


    return apiRequest(
        `/student/assignments?student_id=${encodeURIComponent(studentId)}`,
        {
            method: "GET"
        }
    );

}


/* ============================================================
   LOAD ONE ASSIGNMENT
============================================================ */

/*
 * Returns:
 *
 * assignment
 * material
 * quiz
 * result
 */

async function loadStudentAssignment(
    studentId,
    assignmentId
) {

    if (!studentId) {

        throw new Error(
            "Student ID is required."
        );

    }


    if (!assignmentId) {

        throw new Error(
            "Assignment ID is required."
        );

    }


    return apiRequest(
        `/student/assignments/${encodeURIComponent(assignmentId)}?student_id=${encodeURIComponent(studentId)}`,
        {
            method: "GET"
        }
    );

}


/* ============================================================
   START READING ACTIVITY
============================================================ */

/*
 * Creates a reading_activity record.
 *
 * Returns:
 *
 * activity_id
 * assignment_id
 * material_id
 * attempt_number
 */

async function startStudentReading(
    studentId,
    assignmentId
) {

    if (!studentId) {

        throw new Error(
            "Student ID is required."
        );

    }


    if (!assignmentId) {

        throw new Error(
            "Assignment ID is required."
        );

    }


    return apiRequest(
        `/student/assignments/${encodeURIComponent(assignmentId)}/start-reading`,
        {
            method: "POST",

            body: JSON.stringify({
                student_id:
                    Number(studentId)
            })
        }
    );

}


/* ============================================================
   SUBMIT QUIZ
============================================================ */

/*
 * Example:
 *
 * const answers = [
 *
 *     {
 *         question_id: 1,
 *         choice_id: 2
 *     },
 *
 *     {
 *         question_id: 2,
 *         choice_id: 6
 *     }
 *
 * ];
 *
 * const result =
 *     await submitStudentQuiz(
 *         91,
 *         1,
 *         answers
 *     );
 */

async function submitStudentQuiz(
    studentId,
    assignmentId,
    answers
) {

    if (!studentId) {

        throw new Error(
            "Student ID is required."
        );

    }


    if (!assignmentId) {

        throw new Error(
            "Assignment ID is required."
        );

    }


    if (!Array.isArray(answers)) {

        throw new Error(
            "Quiz answers must be an array."
        );

    }


    return apiRequest(
        `/student/assignments/${encodeURIComponent(assignmentId)}/quiz-attempt`,
        {
            method: "POST",

            body: JSON.stringify({

                student_id:
                    Number(studentId),

                answers:
                    answers

            })
        }
    );

}


/* ============================================================
   DEVELOPMENT: CREATE TEST ASSIGNMENT
============================================================ */

/*
 * This is ONLY for development/testing.
 *
 * It creates:
 *
 * Reading Practice #1
 *         ↓
 * Class 4
 *         ↓
 * Ang Pamilya Ko
 *         ↓
 * 5-question quiz
 *
 * It uses the development seed endpoint in
 * assignment_api.py.
 */

async function seedTestAssignment() {

    return apiRequest(
        "/dev/seed-test-assignment",
        {
            method: "POST"
        }
    );

}


/* ============================================================
   EXPORT
============================================================ */

/*
 * Make the API available through:
 *
 * ArchiveVoxStudentApi
 */

window.ArchiveVoxStudentApi = {

    checkArchiveVoxApi,

    loadStudentAssignments,

    loadStudentAssignment,

    startStudentReading,

    submitStudentQuiz,

    seedTestAssignment

};