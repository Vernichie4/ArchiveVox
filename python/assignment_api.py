from __future__ import annotations

import os
from datetime import datetime
from decimal import Decimal
from typing import Any

import pymysql
from dotenv import load_dotenv
from flask import Flask, jsonify, request
from flask_cors import CORS


# ============================================================
# CONFIGURATION
# ============================================================

load_dotenv()

app = Flask(__name__)

CORS(
    app,
    resources={
        r"/api/*": {
            "origins": "*"
        }
    }
)


DB_CONFIG = {
    "host": os.getenv("DB_HOST", "127.0.0.1"),
    "port": int(os.getenv("DB_PORT", "3306")),
    "user": os.getenv("DB_USER", "root"),
    "password": os.getenv("DB_PASSWORD", ""),
    "database": os.getenv("DB_NAME", "archivevox"),
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor,
    "autocommit": False,
}


DEV_MODE = (
    os.getenv("DEV_MODE", "true").lower()
    in {"1", "true", "yes"}
)


# ============================================================
# DATABASE
# ============================================================

def get_db():
    return pymysql.connect(**DB_CONFIG)


# ============================================================
# RESPONSE HELPERS
# ============================================================

def success(
    data: dict[str, Any] | None = None,
    status: int = 200
):
    payload = {
        "success": True
    }

    if data:
        payload.update(data)

    return jsonify(payload), status


def error(
    message: str,
    status: int = 400
):
    return jsonify({
        "success": False,
        "message": message
    }), status


def serialize(value: Any):

    if isinstance(value, Decimal):
        return float(value)

    if isinstance(value, datetime):
        return value.isoformat(sep=" ")

    if isinstance(value, dict):
        return {
            key: serialize(item)
            for key, item in value.items()
        }

    if isinstance(value, list):
        return [
            serialize(item)
            for item in value
        ]

    return value


# ============================================================
# SCHEMA HELPERS
# ============================================================

def table_exists(
    cursor,
    table_name: str
) -> bool:

    cursor.execute(
        """
        SELECT COUNT(*) AS n
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = %s
        """,
        (table_name,)
    )

    return bool(
        cursor.fetchone()["n"]
    )


def column_exists(
    cursor,
    table_name: str,
    column_name: str
) -> bool:

    cursor.execute(
        """
        SELECT COUNT(*) AS n
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = %s
          AND COLUMN_NAME = %s
        """,
        (
            table_name,
            column_name
        )
    )

    return bool(
        cursor.fetchone()["n"]
    )


# ============================================================
# REQUIRED EXISTING SCHEMA CHECK
# ============================================================

def validate_schema():

    required_tables = [
        "teacher",
        "class",
        "student",
        "reading_material",
        "reading_assignment",
        "reading_assignment_material",
        "quiz",
        "quiz_question",
        "quiz_choice",
        "quiz_attempt",
        "quiz_answer",
        "reading_activity",
        "assessment_result",
    ]

    connection = get_db()

    try:

        with connection.cursor() as cursor:

            missing = [
                table
                for table in required_tables
                if not table_exists(cursor, table)
            ]

            if missing:

                raise RuntimeError(
                    "Missing required ArchiveVox table(s): "
                    + ", ".join(missing)
                )

            required_reading_activity_columns = [
                "student_id",
                "material_id",
                "assignment_id",
                "activity_date",
                "started_at",
                "attempt_number",
                "activity_status",
            ]

            missing_activity_columns = [
                column
                for column in required_reading_activity_columns
                if not column_exists(
                    cursor,
                    "reading_activity",
                    column
                )
            ]

            if missing_activity_columns:

                raise RuntimeError(
                    "reading_activity is missing required "
                    "column(s): "
                    + ", ".join(missing_activity_columns)
                )

            required_attempt_columns = [
                "quiz_id",
                "student_id",
                "assignment_material_id",
                "activity_id",
                "score",
                "total_questions",
                "percentage",
                "started_at",
                "completed_at",
                "status",
            ]

            missing_attempt_columns = [
                column
                for column in required_attempt_columns
                if not column_exists(
                    cursor,
                    "quiz_attempt",
                    column
                )
            ]

            if missing_attempt_columns:

                raise RuntimeError(
                    "quiz_attempt is missing required "
                    "column(s): "
                    + ", ".join(missing_attempt_columns)
                )

    finally:
        connection.close()


# ============================================================
# STUDENT
# ============================================================

def get_student(
    student_id: int
):

    connection = get_db()

    try:

        with connection.cursor() as cursor:

            cursor.execute(
                """
                SELECT
                    s.student_id,
                    s.lrn,
                    s.first_name,
                    s.middle_name,
                    s.last_name,
                    s.class_id,

                    c.teacher_id,
                    c.grade_level,
                    c.section,
                    c.school_year

                FROM student s

                LEFT JOIN class c
                    ON c.class_id = s.class_id

                WHERE s.student_id = %s
                  AND s.is_active = 1

                LIMIT 1
                """,
                (student_id,)
            )

            return cursor.fetchone()

    finally:
        connection.close()


# ============================================================
# TEACHER OWNERSHIP
# ============================================================

def teacher_owns_class(
    cursor,
    teacher_id: int,
    class_id: int
) -> bool:

    cursor.execute(
        """
        SELECT 1

        FROM class

        WHERE class_id = %s
          AND teacher_id = %s

        LIMIT 1
        """,
        (
            class_id,
            teacher_id
        )
    )

    return cursor.fetchone() is not None


def teacher_owns_material(
    cursor,
    teacher_id: int,
    material_id: int
) -> bool:

    cursor.execute(
        """
        SELECT 1

        FROM reading_material

        WHERE material_id = %s
          AND teacher_id = %s
          AND status = 'Active'

        LIMIT 1
        """,
        (
            material_id,
            teacher_id
        )
    )

    return cursor.fetchone() is not None


# ============================================================
# ASSIGNMENT ACCESS
# ============================================================

def get_assignment_for_student(
    cursor,
    assignment_id: int,
    student_id: int
):

    cursor.execute(
        """
        SELECT
            ra.assignment_id,
            ra.teacher_id,
            ra.class_id,
            ra.title,
            ra.instructions,
            ra.assigned_at,
            ra.due_date,
            ra.status,

            s.student_id,

            c.grade_level,
            c.section,
            c.school_year

        FROM reading_assignment ra

        INNER JOIN student s
            ON s.class_id = ra.class_id

        INNER JOIN class c
            ON c.class_id = ra.class_id

        WHERE ra.assignment_id = %s
          AND s.student_id = %s
          AND s.is_active = 1
          AND ra.status = 'assigned'

        LIMIT 1
        """,
        (
            assignment_id,
            student_id
        )
    )

    return cursor.fetchone()


# ============================================================
# ASSIGNMENT MATERIALS
# ============================================================

def get_assignment_materials(
    cursor,
    assignment_id: int
):

    cursor.execute(
        """
        SELECT

            ram.assignment_material_id,
            ram.assignment_id,
            ram.material_id,
            ram.assigned_order,

            rm.title AS material_title,
            rm.description,
            rm.language,
            rm.material_type,
            rm.grade_level,
            rm.difficulty,
            rm.ocr_text,
            rm.total_words,

            q.quiz_id,
            q.title AS quiz_title,
            q.instructions AS quiz_instructions,
            q.total_questions,
            q.status AS quiz_status

        FROM reading_assignment_material ram

        INNER JOIN reading_material rm
            ON rm.material_id = ram.material_id

        LEFT JOIN quiz q
            ON q.material_id = rm.material_id

        WHERE ram.assignment_id = %s

        ORDER BY ram.assigned_order ASC
        """,
        (assignment_id,)
    )

    return cursor.fetchall()


# ============================================================
# QUIZ QUESTIONS
# ============================================================

def get_quiz_questions(
    cursor,
    quiz_id: int
):

    cursor.execute(
        """
        SELECT
            question_id,
            quiz_id,
            question_number,
            question_text

        FROM quiz_question

        WHERE quiz_id = %s

        ORDER BY question_number ASC
        """,
        (quiz_id,)
    )

    questions = cursor.fetchall()

    for question in questions:

        cursor.execute(
            """
            SELECT
                choice_id,
                question_id,
                choice_label,
                choice_text

            FROM quiz_choice

            WHERE question_id = %s

            ORDER BY choice_label ASC
            """,
            (question["question_id"],)
        )

        question["choices"] = cursor.fetchall()

    return questions


# ============================================================
# CREATE ASSIGNMENT
# ============================================================

def create_assignment(
    payload: dict[str, Any]
):

    try:

        teacher_id = int(
            payload.get("teacher_id", 0)
        )

        class_id = int(
            payload.get("class_id", 0)
        )

    except (TypeError, ValueError):

        raise ValueError(
            "teacher_id and class_id are required."
        )

    title = str(
        payload.get("title") or ""
    ).strip()

    instructions = str(
        payload.get("instructions") or ""
    ).strip()

    due_date = (
        payload.get("due_date")
        or None
    )

    materials = payload.get(
        "materials"
    )
    

    # --------------------------------------------------------
    # BASIC VALIDATION
    # --------------------------------------------------------

    if not teacher_id:

        raise ValueError(
            "teacher_id is required."
        )

    if not class_id:

        raise ValueError(
            "class_id is required."
        )

    if not title:

        raise ValueError(
            "Assignment title is required."
        )

    if not isinstance(materials, list):

        raise ValueError(
            "materials must be an array."
        )

    if len(materials) < 1 or len(materials) > 2:

        raise ValueError(
            "An assignment must contain 1 to 2 materials."
        )

    # --------------------------------------------------------
    # NORMALIZE MATERIAL IDS
    # --------------------------------------------------------

    material_ids = []

    for item in materials:

        if isinstance(item, dict):

            material_id = item.get(
                "material_id"
            )

        else:

            material_id = item

        try:

            material_id = int(
                material_id
            )

        except (TypeError, ValueError):

            raise ValueError(
                "Each material must contain a valid material_id."
            )

        if material_id in material_ids:

            raise ValueError(
                "The same material cannot be assigned twice."
            )

        material_ids.append(
            material_id
        )

    connection = get_db()

    try:

        with connection.cursor() as cursor:

            # ------------------------------------------------
            # TEACHER → CLASS OWNERSHIP
            # ------------------------------------------------

            if not teacher_owns_class(
                cursor,
                teacher_id,
                class_id
            ):

                raise PermissionError(
                    "This class does not belong to the teacher."
                )

            # ------------------------------------------------
            # CLASS GRADE
            # ------------------------------------------------

            cursor.execute(
                """
                SELECT
                    grade_level

                FROM class

                WHERE class_id = %s

                LIMIT 1
                """,
                (class_id,)
            )

            class_row = cursor.fetchone()

            if not class_row:

                raise ValueError(
                    "Class not found."
                )

            class_grade = (
                class_row["grade_level"]
            )

            # ------------------------------------------------
            # VALIDATE EACH MATERIAL
            # ------------------------------------------------

            validated_materials = []

            for order, material_id in enumerate(
                material_ids,
                start=1
            ):

                if not teacher_owns_material(
                    cursor,
                    teacher_id,
                    material_id
                ):

                    raise PermissionError(
                        f"Material {material_id} "
                        "does not belong to the teacher."
                    )

                cursor.execute(
                    """
                    SELECT
                        material_id,
                        title,
                        grade_level,
                        status

                    FROM reading_material

                    WHERE material_id = %s

                    LIMIT 1
                    """,
                    (material_id,)
                )

                material = cursor.fetchone()

                if not material:

                    raise ValueError(
                        f"Material {material_id} was not found."
                    )

                if (
                    class_grade
                    and material["grade_level"]
                    and class_grade
                    != material["grade_level"]
                ):

                    raise ValueError(
                        f"Material '{material['title']}' "
                        "does not match the class grade."
                    )

                # --------------------------------------------
                # EXISTING MATERIAL QUIZ
                # --------------------------------------------

                cursor.execute(
                    """
                    SELECT
                        quiz_id,
                        material_id,
                        title,
                        total_questions,
                        status

                    FROM quiz

                    WHERE material_id = %s

                    LIMIT 1
                    """,
                    (material_id,)
                )

                quiz = cursor.fetchone()

                if not quiz:

                    raise ValueError(
                        f"Material '{material['title']}' "
                        "does not have a quiz yet."
                    )

                if quiz["status"] != "active":

                    raise ValueError(
                        f"The quiz for material "
                        f"'{material['title']}' "
                        "is not active."
                    )

                if quiz["total_questions"] != 5:

                    raise ValueError(
                        f"The quiz for material "
                        f"'{material['title']}' "
                        "must contain exactly 5 questions."
                    )

                cursor.execute(
                    """
                    SELECT COUNT(*) AS question_count

                    FROM quiz_question

                    WHERE quiz_id = %s
                    """,
                    (quiz["quiz_id"],)
                )

                question_count = cursor.fetchone()[
                    "question_count"
                ]

                if question_count != 5:

                    raise ValueError(
                        f"The quiz for material "
                        f"'{material['title']}' "
                        "must have exactly 5 questions."
                    )

                validated_materials.append({
                    "material_id": material_id,
                    "assigned_order": order,
                    "material": material,
                    "quiz": quiz
                })

            # ------------------------------------------------
            # CREATE ASSIGNMENT
            # ------------------------------------------------

            cursor.execute(
                """
                INSERT INTO reading_assignment
                (
                    teacher_id,
                    class_id,
                    title,
                    instructions,
                    due_date,
                    status
                )

                VALUES
                (
                    %s,
                    %s,
                    %s,
                    %s,
                    %s,
                    'assigned'
                )
                """,
                (
                    teacher_id,
                    class_id,
                    title,
                    instructions or None,
                    due_date
                )
            )

            assignment_id = cursor.lastrowid

            # ------------------------------------------------
            # ATTACH 1–2 MATERIALS
            # ------------------------------------------------

            for item in validated_materials:

                cursor.execute(
                    """
                    INSERT INTO reading_assignment_material
                    (
                        assignment_id,
                        material_id,
                        assigned_order
                    )

                    VALUES
                    (
                        %s,
                        %s,
                        %s
                    )
                    """,
                    (
                        assignment_id,
                        item["material_id"],
                        item["assigned_order"]
                    )
                )

        connection.commit()

        return {
            "message": "Assignment created successfully.",
            "assignment_id": assignment_id,
            "materials": [
                {
                    "material_id": item["material_id"],
                    "assigned_order": item["assigned_order"],
                    "quiz_id": item["quiz"]["quiz_id"]
                }
                for item in validated_materials
            ]
        }, 201

    except Exception:

        connection.rollback()

        raise

    finally:

        connection.close()


# ============================================================
# HEALTH
# ============================================================

@app.get("/api/health")
def health():

    connection = None

    try:

        connection = get_db()

        with connection.cursor() as cursor:

            cursor.execute(
                "SELECT 1 AS ok"
            )

            row = cursor.fetchone()

        return success({
            "message":
                "ArchiveVox Assignment API is running.",
            "database":
                row["ok"] == 1
        })

    except Exception as exc:

        return error(
            f"Database connection failed: {exc}",
            500
        )

    finally:

        if connection:
            connection.close()


# ============================================================
# SCHEMA VALIDATION
# ============================================================

@app.post("/api/setup")
def setup():

    if not DEV_MODE:

        return error(
            "Setup endpoint is disabled.",
            403
        )

    try:

        validate_schema()

        return success({
            "message":
                "Existing ArchiveVox assignment schema "
                "validated successfully."
        })

    except Exception as exc:

        return error(
            f"Schema validation failed: {exc}",
            500
        )


# ============================================================
# TEACHER — CREATE ASSIGNMENT
# ============================================================

@app.post("/api/teacher/assignments")
def teacher_create_assignment():

    payload = request.get_json(silent=True) or {}
    # Merge teacher_id from query string (if present)
    query_teacher_id = request.args.get('teacher_id', type=int)
    if query_teacher_id and 'teacher_id' not in payload:
        payload['teacher_id'] = query_teacher_id

    try:

        data, status = create_assignment(
            payload
        )

        return success(
            data,
            status
        )

    except PermissionError as exc:

        return error(
            str(exc),
            403
        )

    except ValueError as exc:

        return error(
            str(exc),
            400
        )

    except Exception as exc:

        return error(
            f"Failed to create assignment: {exc}",
            500
        )


# ============================================================
# TEACHER — LIST ASSIGNMENTS
# ============================================================

@app.get("/api/teacher/assignments")
def teacher_list_assignments():

    teacher_id = request.args.get(
        "teacher_id",
        type=int
    )

    if not teacher_id:

        return error(
            "teacher_id is required."
        )

    connection = get_db()

    try:

        with connection.cursor() as cursor:

            cursor.execute(
                """
                SELECT

                    ra.assignment_id,
                    ra.title,
                    ra.instructions,
                    ra.status,
                    ra.assigned_at,
                    ra.due_date,

                    ra.class_id,
                    c.grade_level,
                    c.section,
                    c.school_year

                FROM reading_assignment ra

                INNER JOIN class c
                    ON c.class_id = ra.class_id

                WHERE ra.teacher_id = %s

                ORDER BY ra.assigned_at DESC
                """,
                (teacher_id,)
            )

            assignments = cursor.fetchall()

            for assignment in assignments:
                materials = get_assignment_materials(cursor, assignment["assignment_id"])
                assignment["material_titles"] = ", ".join(m["material_title"] for m in materials)
                assignment["materials"] = materials   # keep full array for detail endpoints


            return success({
                "assignments":
                    serialize(assignments)
            })

    finally:

        connection.close()


# ============================================================
# STUDENT — LIST ASSIGNMENTS
# ============================================================

@app.get("/api/student/assignments")
def student_list_assignments():

    student_id = request.args.get(
        "student_id",
        type=int
    )

    if not student_id:

        return error(
            "student_id is required."
        )

    student = get_student(
        student_id
    )

    if not student:

        return error(
            "Student not found.",
            404
        )

    if not student["class_id"]:

        return success({
            "student":
                serialize(student),
            "assignments": []
        })

    connection = get_db()

    try:

        with connection.cursor() as cursor:

            cursor.execute(
                """
                SELECT

                    ra.assignment_id,
                    ra.title,
                    ra.instructions,
                    ra.status,
                    ra.assigned_at,
                    ra.due_date,

                    c.grade_level,
                    c.section,
                    c.school_year

                FROM reading_assignment ra

                INNER JOIN class c
                    ON c.class_id = ra.class_id

                WHERE ra.class_id = %s
                  AND ra.status = 'assigned'

                ORDER BY ra.assigned_at DESC
                """,
                (student["class_id"],)
            )

            assignments = cursor.fetchall()

            for assignment in assignments:

                assignment["materials"] = (
                    get_assignment_materials(
                        cursor,
                        assignment["assignment_id"]
                    )
                )

                # --------------------------------------------
                # STUDENT PROGRESS
                # --------------------------------------------

                for material in assignment["materials"]:

                    cursor.execute(
                        """
                        SELECT

                            qa.attempt_id,
                            qa.quiz_id,
                            qa.score,
                            qa.total_questions,
                            qa.percentage,
                            qa.started_at,
                            qa.completed_at,
                            qa.status

                        FROM quiz_attempt qa

                        WHERE qa.student_id = %s
                          AND qa.assignment_material_id = %s

                        ORDER BY qa.attempt_id DESC

                        LIMIT 1
                        """,
                        (
                            student_id,
                            material[
                                "assignment_material_id"
                            ]
                        )
                    )

                    material["quiz_attempt"] = (
                        cursor.fetchone()
                    )

                    cursor.execute(
                        """
                        SELECT

                            ra.activity_id,
                            ra.activity_status,
                            ra.started_at,
                            ra.finished_at,

                            ar.assessment_id,
                            ar.accuracy_percentage,
                            ar.wcpm,
                            ar.final_reading_level,
                            ar.observation_level,
                            ar.comprehension_score

                        FROM reading_activity ra

                        LEFT JOIN assessment_result ar
                            ON ar.activity_id =
                               ra.activity_id

                        WHERE ra.student_id = %s
                          AND ra.assignment_id = %s
                          AND ra.material_id = %s

                        ORDER BY ra.activity_id DESC

                        LIMIT 1
                        """,
                        (
                            student_id,
                            assignment["assignment_id"],
                            material["material_id"]
                        )
                    )

                    material["reading_result"] = (
                        cursor.fetchone()
                    )

            return success({
                "student":
                    serialize(student),
                "assignments":
                    serialize(assignments)
            })

    finally:

        connection.close()


# ============================================================
# STUDENT — ASSIGNMENT DETAIL
# ============================================================

@app.get(
    "/api/student/assignments/<int:assignment_id>"
)
def student_assignment_detail(
    assignment_id: int
):

    student_id = request.args.get(
        "student_id",
        type=int
    )

    if not student_id:

        return error(
            "student_id is required."
        )

    connection = get_db()

    try:

        with connection.cursor() as cursor:

            # --------------------------------------------
            # SECURITY CHECK
            # --------------------------------------------

            assignment = (
                get_assignment_for_student(
                    cursor,
                    assignment_id,
                    student_id
                )
            )

            if not assignment:

                return error(
                    "Assignment not found or "
                    "not assigned to this student's class.",
                    404
                )

            # --------------------------------------------
            # MATERIALS
            # --------------------------------------------

            materials = (
                get_assignment_materials(
                    cursor,
                    assignment_id
                )
            )

            for material in materials:

                quiz_id = material["quiz_id"]

                if quiz_id:

                    material["questions"] = (
                        get_quiz_questions(
                            cursor,
                            quiz_id
                        )
                    )

                else:

                    material["questions"] = []

                # ----------------------------------------
                # LAST QUIZ ATTEMPT
                # ----------------------------------------

                cursor.execute(
                    """
                    SELECT

                        attempt_id,
                        quiz_id,
                        activity_id,
                        score,
                        total_questions,
                        percentage,
                        started_at,
                        completed_at,
                        status

                    FROM quiz_attempt

                    WHERE student_id = %s
                      AND assignment_material_id = %s

                    ORDER BY attempt_id DESC

                    LIMIT 1
                    """,
                    (
                        student_id,
                        material[
                            "assignment_material_id"
                        ]
                    )
                )

                material["quiz_attempt"] = (
                    cursor.fetchone()
                )

                # ----------------------------------------
                # READING / CRLA RESULT
                # ----------------------------------------

                cursor.execute(
                    """
                    SELECT

                        ra.activity_id,
                        ra.activity_status,
                        ra.started_at,
                        ra.finished_at,

                        ar.assessment_id,
                        ar.total_words,
                        ar.words_correct,
                        ar.accuracy_percentage,
                        ar.wcpm,
                        ar.reading_time_seconds,

                        ar.part1_total_score,
                        ar.part1_reading_level,

                        ar.comprehension_score,
                        ar.final_reading_level,
                        ar.observation_level,

                        ar.assessed_at

                    FROM reading_activity ra

                    LEFT JOIN assessment_result ar
                        ON ar.activity_id =
                           ra.activity_id

                    WHERE ra.student_id = %s
                      AND ra.assignment_id = %s
                      AND ra.material_id = %s

                    ORDER BY ra.activity_id DESC

                    LIMIT 1
                    """,
                    (
                        student_id,
                        assignment_id,
                        material["material_id"]
                    )
                )

                material["reading_result"] = (
                    cursor.fetchone()
                )

            return success({
                "assignment":
                    serialize(assignment),

                "materials":
                    serialize(materials)
            })

    finally:

        connection.close()


# ============================================================
# STUDENT — START READING
# ============================================================

@app.post(
    "/api/student/assignments/"
    "<int:assignment_id>/materials/"
    "<int:assignment_material_id>/start-reading"
)
def student_start_reading(
    assignment_id: int,
    assignment_material_id: int
):

    data = (
        request.get_json(
            silent=True
        )
        or {}
    )

    try:

        student_id = int(
            data.get("student_id")
        )

    except (TypeError, ValueError):

        return error(
            "student_id is required."
        )

    connection = get_db()

    try:

        with connection.cursor() as cursor:

            # --------------------------------------------
            # ASSIGNMENT ACCESS
            # --------------------------------------------

            assignment = (
                get_assignment_for_student(
                    cursor,
                    assignment_id,
                    student_id
                )
            )

            if not assignment:

                return error(
                    "Assignment access denied.",
                    403
                )

            # --------------------------------------------
            # MATERIAL MUST BELONG TO ASSIGNMENT
            # --------------------------------------------

            cursor.execute(
                """
                SELECT

                    ram.assignment_material_id,
                    ram.material_id,
                    ram.assigned_order

                FROM reading_assignment_material ram

                WHERE ram.assignment_material_id = %s
                  AND ram.assignment_id = %s

                LIMIT 1
                """,
                (
                    assignment_material_id,
                    assignment_id
                )
            )

            assignment_material = (
                cursor.fetchone()
            )

            if not assignment_material:

                return error(
                    "This material is not part "
                    "of this assignment.",
                    403
                )

            material_id = (
                assignment_material["material_id"]
            )

            # --------------------------------------------
            # NEXT READING ATTEMPT
            # --------------------------------------------

            cursor.execute(
                """
                SELECT
                    COALESCE(
                        MAX(attempt_number),
                        0
                    ) + 1 AS next_attempt

                FROM reading_activity

                WHERE student_id = %s
                  AND material_id = %s
                """,
                (
                    student_id,
                    material_id
                )
            )

            attempt_number = (
                cursor.fetchone()[
                    "next_attempt"
                ]
            )

            # --------------------------------------------
            # CREATE READING ACTIVITY
            # --------------------------------------------

            cursor.execute(
                """
                INSERT INTO reading_activity
                (
                    student_id,
                    material_id,
                    assignment_id,
                    activity_date,
                    started_at,
                    attempt_number,
                    activity_status
                )

                VALUES
                (
                    %s,
                    %s,
                    %s,
                    NOW(),
                    NOW(),
                    %s,
                    'Pending'
                )
                """,
                (
                    student_id,
                    material_id,
                    assignment_id,
                    attempt_number
                )
            )

            activity_id = cursor.lastrowid

        connection.commit()

        return success({
            "assignment_id":
                assignment_id,

            "assignment_material_id":
                assignment_material_id,

            "material_id":
                material_id,

            "activity_id":
                activity_id,

            "attempt_number":
                attempt_number
        }, 201)

    except Exception as exc:

        connection.rollback()

        return error(
            f"Unable to start reading activity: {exc}",
            500
        )

    finally:

        connection.close()


# ============================================================
# STUDENT — START QUIZ
# ============================================================

@app.post(
    "/api/student/assignments/"
    "<int:assignment_id>/materials/"
    "<int:assignment_material_id>/quiz/start"
)
def student_start_quiz(
    assignment_id: int,
    assignment_material_id: int
):

    data = (
        request.get_json(
            silent=True
        )
        or {}
    )

    try:

        student_id = int(
            data.get("student_id")
        )

    except (TypeError, ValueError):

        return error(
            "student_id is required."
        )

    connection = get_db()

    try:

        with connection.cursor() as cursor:

            # --------------------------------------------
            # SECURITY
            # --------------------------------------------

            assignment = (
                get_assignment_for_student(
                    cursor,
                    assignment_id,
                    student_id
                )
            )

            if not assignment:

                return error(
                    "Assignment access denied.",
                    403
                )

            # --------------------------------------------
            # ASSIGNMENT MATERIAL
            # --------------------------------------------

            cursor.execute(
                """
                SELECT

                    ram.assignment_material_id,
                    ram.material_id

                FROM reading_assignment_material ram

                WHERE ram.assignment_material_id = %s
                  AND ram.assignment_id = %s

                LIMIT 1
                """,
                (
                    assignment_material_id,
                    assignment_id
                )
            )

            assignment_material = (
                cursor.fetchone()
            )

            if not assignment_material:

                return error(
                    "This material is not part "
                    "of this assignment.",
                    403
                )

            material_id = (
                assignment_material["material_id"]
            )

            # --------------------------------------------
            # EXISTING QUIZ
            # --------------------------------------------

            cursor.execute(
                """
                SELECT

                    quiz_id,
                    material_id,
                    title,
                    instructions,
                    total_questions,
                    status

                FROM quiz

                WHERE material_id = %s
                  AND status = 'active'

                LIMIT 1
                """,
                (material_id,)
            )

            quiz = cursor.fetchone()

            if not quiz:

                return error(
                    "No active quiz exists "
                    "for this material.",
                    404
                )

            # --------------------------------------------
            # OPTIONAL READING ACTIVITY LINK
            # --------------------------------------------

            activity_id = data.get(
                "activity_id"
            )

            if activity_id:

                try:

                    activity_id = int(
                        activity_id
                    )

                except (TypeError, ValueError):

                    activity_id = None

            # --------------------------------------------
            # CREATE QUIZ ATTEMPT
            # --------------------------------------------

            cursor.execute(
                """
                INSERT INTO quiz_attempt
                (
                    quiz_id,
                    student_id,
                    assignment_material_id,
                    activity_id,
                    score,
                    total_questions,
                    percentage,
                    started_at,
                    status
                )

                VALUES
                (
                    %s,
                    %s,
                    %s,
                    %s,
                    0,
                    %s,
                    0.00,
                    NOW(),
                    'in_progress'
                )
                """,
                (
                    quiz["quiz_id"],
                    student_id,
                    assignment_material_id,
                    activity_id,
                    quiz["total_questions"]
                )
            )

            attempt_id = cursor.lastrowid

        connection.commit()

        return success({
            "attempt_id":
                attempt_id,

            "quiz_id":
                quiz["quiz_id"],

            "assignment_material_id":
                assignment_material_id,

            "activity_id":
                activity_id
        }, 201)

    except Exception as exc:

        connection.rollback()

        return error(
            f"Unable to start quiz: {exc}",
            500
        )

    finally:

        connection.close()


# ============================================================
# STUDENT — SUBMIT QUIZ
# ============================================================

@app.post(
    "/api/student/assignments/"
    "<int:assignment_id>/materials/"
    "<int:assignment_material_id>/quiz/submit"
)
def student_submit_quiz(
    assignment_id: int,
    assignment_material_id: int
):

    data = (
        request.get_json(
            silent=True
        )
        or {}
    )

    try:

        student_id = int(
            data.get("student_id")
        )

        attempt_id = int(
            data.get("attempt_id")
        )

    except (TypeError, ValueError):

        return error(
            "student_id and attempt_id are required."
        )

    answers = data.get(
        "answers",
        []
    )

    if not isinstance(
        answers,
        list
    ):

        return error(
            "answers must be an array."
        )

    connection = get_db()

    try:

        with connection.cursor() as cursor:

            # --------------------------------------------
            # SECURITY
            # --------------------------------------------

            assignment = (
                get_assignment_for_student(
                    cursor,
                    assignment_id,
                    student_id
                )
            )

            if not assignment:

                return error(
                    "Assignment access denied.",
                    403
                )

            # --------------------------------------------
            # VERIFY ASSIGNMENT MATERIAL
            # --------------------------------------------

            cursor.execute(
                """
                SELECT

                    ram.assignment_material_id,
                    ram.material_id

                FROM reading_assignment_material ram

                WHERE ram.assignment_material_id = %s
                  AND ram.assignment_id = %s

                LIMIT 1
                """,
                (
                    assignment_material_id,
                    assignment_id
                )
            )

            assignment_material = (
                cursor.fetchone()
            )

            if not assignment_material:

                return error(
                    "Invalid assignment material.",
                    403
                )

            # --------------------------------------------
            # VERIFY ATTEMPT
            # --------------------------------------------

            cursor.execute(
                """
                SELECT

                    attempt_id,
                    quiz_id,
                    student_id,
                    assignment_material_id,
                    activity_id,
                    status

                FROM quiz_attempt

                WHERE attempt_id = %s
                  AND student_id = %s
                  AND assignment_material_id = %s

                LIMIT 1
                """,
                (
                    attempt_id,
                    student_id,
                    assignment_material_id
                )
            )

            attempt = cursor.fetchone()

            if not attempt:

                return error(
                    "Quiz attempt not found.",
                    404
                )

            if attempt["status"] != "in_progress":

                return error(
                    "This quiz attempt is no longer active.",
                    400
                )

            quiz_id = attempt["quiz_id"]

            # --------------------------------------------
            # QUESTIONS
            # --------------------------------------------

            cursor.execute(
                """
                SELECT
                    question_id,
                    question_number

                FROM quiz_question

                WHERE quiz_id = %s

                ORDER BY question_number ASC
                """,
                (quiz_id,)
            )

            questions = cursor.fetchall()

            question_ids = {
                question["question_id"]
                for question in questions
            }

            # --------------------------------------------
            # SAVE ANSWERS
            # --------------------------------------------

            score = 0
            answered_questions = set()

            for answer in answers:

                try:

                    question_id = int(
                        answer.get(
                            "question_id"
                        )
                    )

                except (TypeError, ValueError):

                    continue

                if question_id not in question_ids:

                    continue

                if question_id in answered_questions:

                    continue

                answered_questions.add(
                    question_id
                )

                selected_choice_id = (
                    answer.get(
                        "choice_id"
                    )
                )

                try:

                    if selected_choice_id is not None:

                        selected_choice_id = int(
                            selected_choice_id
                        )

                except (TypeError, ValueError):

                    selected_choice_id = None

                is_correct = 0

                # ----------------------------------------
                # VERIFY CHOICE BELONGS TO QUESTION
                # ----------------------------------------

                if selected_choice_id is not None:

                    cursor.execute(
                        """
                        SELECT
                            is_correct

                        FROM quiz_choice

                        WHERE choice_id = %s
                          AND question_id = %s

                        LIMIT 1
                        """,
                        (
                            selected_choice_id,
                            question_id
                        )
                    )

                    choice = cursor.fetchone()

                    if (
                        choice
                        and choice["is_correct"]
                    ):

                        is_correct = 1
                        score += 1

                cursor.execute(
                    """
                    INSERT INTO quiz_answer
                    (
                        attempt_id,
                        question_id,
                        selected_choice_id,
                        is_correct,
                        answered_at
                    )

                    VALUES
                    (
                        %s,
                        %s,
                        %s,
                        %s,
                        NOW()
                    )
                    """,
                    (
                        attempt_id,
                        question_id,
                        selected_choice_id,
                        is_correct
                    )
                )

            # --------------------------------------------
            # CALCULATE QUIZ SCORE ONLY
            # --------------------------------------------

            total_questions = len(
                questions
            )

            percentage = (
                score
                / total_questions
                * 100
                if total_questions
                else 0
            )

            # --------------------------------------------
            # COMPLETE ATTEMPT
            # --------------------------------------------

            cursor.execute(
                """
                UPDATE quiz_attempt

                SET

                    score = %s,
                    total_questions = %s,
                    percentage = %s,
                    completed_at = NOW(),
                    status = 'completed'

                WHERE attempt_id = %s
                """,
                (
                    score,
                    total_questions,
                    percentage,
                    attempt_id
                )
            )

        connection.commit()

        return success({
            "attempt_id":
                attempt_id,

            "score":
                score,

            "total_questions":
                total_questions,

            "percentage":
                percentage,

            "status":
                "completed"
        }, 200)

    except Exception as exc:

        connection.rollback()

        return error(
            f"Unable to save quiz attempt: {exc}",
            500
        )

    finally:

        connection.close()


# ============================================================
# STUDENT — READING RESULT
# ============================================================

@app.get(
    "/api/student/assignments/"
    "<int:assignment_id>/materials/"
    "<int:assignment_material_id>/reading-result"
)
def student_reading_result(
    assignment_id: int,
    assignment_material_id: int
):

    student_id = request.args.get(
        "student_id",
        type=int
    )

    if not student_id:

        return error(
            "student_id is required."
        )

    connection = get_db()

    try:

        with connection.cursor() as cursor:

            assignment = (
                get_assignment_for_student(
                    cursor,
                    assignment_id,
                    student_id
                )
            )

            if not assignment:

                return error(
                    "Assignment access denied.",
                    403
                )

            cursor.execute(
                """
                SELECT
                    ram.material_id

                FROM reading_assignment_material ram

                WHERE ram.assignment_material_id = %s
                  AND ram.assignment_id = %s

                LIMIT 1
                """,
                (
                    assignment_material_id,
                    assignment_id
                )
            )

            material = cursor.fetchone()

            if not material:

                return error(
                    "Invalid assignment material.",
                    404
                )

            cursor.execute(
                """
                SELECT

                    ra.activity_id,
                    ra.activity_status,
                    ra.started_at,
                    ra.finished_at,

                    ar.assessment_id,
                    ar.total_words,
                    ar.words_correct,
                    ar.accuracy_percentage,
                    ar.wcpm,
                    ar.reading_time_seconds,

                    ar.part1_total_score,
                    ar.part1_reading_level,

                    ar.comprehension_score,
                    ar.final_reading_level,
                    ar.observation_level,

                    ar.assessed_at

                FROM reading_activity ra

                LEFT JOIN assessment_result ar
                    ON ar.activity_id =
                       ra.activity_id

                WHERE ra.student_id = %s
                  AND ra.assignment_id = %s
                  AND ra.material_id = %s

                ORDER BY ra.activity_id DESC

                LIMIT 1
                """,
                (
                    student_id,
                    assignment_id,
                    material["material_id"]
                )
            )

            result = cursor.fetchone()

            return success({
                "reading_result":
                    serialize(result)
            })

    finally:

        connection.close()

def complete_student_activity(activity_id, metrics):
    connection = get_db()
    with connection.cursor() as cursor:
        # 1. Update the parent activity status so PHP picks it up
        cursor.execute(
            """
            UPDATE reading_activity 
            SET activity_status = 'Completed', finished_at = NOW() 
            WHERE activity_id = %s
            """,
            (activity_id,)
        )
        
        # 2. Insert into assessment_result to populate teacher metrics
        cursor.execute(
            """
            INSERT INTO assessment_result (
                activity_id, total_words, words_correct, accuracy_percentage, 
                wcpm, reading_level, assessed_at
            ) VALUES (%s, %s, %s, %s, %s, %s, NOW())
            """,
            (
                activity_id, 
                metrics['total_words'], 
                metrics['words_correct'], 
                metrics['accuracy'], 
                metrics['wcpm'], 
                metrics['reading_level']
            )
        )
    connection.commit()
    connection.close()


# ============================================================
# TEACHER — ASSIGNMENT RESULTS
# ============================================================

@app.get(
    "/api/teacher/assignments/"
    "<int:assignment_id>/results"
)
def teacher_assignment_results(
    assignment_id: int
):

    teacher_id = request.args.get(
        "teacher_id",
        type=int
    )

    if not teacher_id:

        return error(
            "teacher_id is required."
        )

    connection = get_db()

    try:

        with connection.cursor() as cursor:

            # --------------------------------------------
            # VERIFY TEACHER OWNERSHIP
            # --------------------------------------------

            cursor.execute(
                """
                SELECT

                    ra.assignment_id,
                    ra.title,
                    ra.class_id

                FROM reading_assignment ra

                WHERE ra.assignment_id = %s
                  AND ra.teacher_id = %s

                LIMIT 1
                """,
                (
                    assignment_id,
                    teacher_id
                )
            )

            assignment = cursor.fetchone()

            if not assignment:

                return error(
                    "Assignment not found.",
                    404
                )

            # --------------------------------------------
            # STUDENTS
            # --------------------------------------------

            cursor.execute(
                """
                SELECT

                    s.student_id,
                    s.lrn,
                    s.first_name,
                    s.middle_name,
                    s.last_name,

                    c.grade_level,
                    c.section,

                    ram.assignment_material_id,
                    ram.material_id,
                    ram.assigned_order,

                    rm.title AS material_title

                FROM student s

                INNER JOIN class c
                    ON c.class_id = s.class_id

                INNER JOIN reading_assignment_material ram
                    ON ram.assignment_id = %s

                INNER JOIN reading_material rm
                    ON rm.material_id =
                       ram.material_id

                WHERE s.class_id = %s
                  AND s.is_active = 1

                ORDER BY
                    s.last_name,
                    s.first_name,
                    ram.assigned_order
                """,
                (
                    assignment_id,
                    assignment["class_id"]
                )
            )

            rows = cursor.fetchall()

            # --------------------------------------------
            # ADD READING + QUIZ RESULTS
            # --------------------------------------------

            for row in rows:

                cursor.execute(
                    """
                    SELECT

                        ra.activity_id,

                        ar.assessment_id,
                        ar.accuracy_percentage,
                        ar.wcpm,
                        ar.part1_total_score,
                        ar.part1_reading_level,
                        ar.comprehension_score,
                        ar.final_reading_level,
                        ar.observation_level,
                        ar.assessed_at

                    FROM reading_activity ra

                    LEFT JOIN assessment_result ar
                        ON ar.activity_id =
                           ra.activity_id

                    WHERE ra.student_id = %s
                      AND ra.assignment_id = %s
                      AND ra.material_id = %s

                    ORDER BY ra.activity_id DESC

                    LIMIT 1
                    """,
                    (
                        row["student_id"],
                        assignment_id,
                        row["material_id"]
                    )
                )

                row["reading_result"] = (
                    cursor.fetchone()
                )

                cursor.execute(
                    """
                    SELECT

                        attempt_id,
                        quiz_id,
                        activity_id,
                        score,
                        total_questions,
                        percentage,
                        started_at,
                        completed_at,
                        status

                    FROM quiz_attempt

                    WHERE student_id = %s
                      AND assignment_material_id = %s

                    ORDER BY attempt_id DESC

                    LIMIT 1
                    """,
                    (
                        row["student_id"],
                        row[
                            "assignment_material_id"
                        ]
                    )
                )

                row["quiz_result"] = (
                    cursor.fetchone()
                )

            return success({
                "assignment":
                    serialize(assignment),

                "results":
                    serialize(rows)
            })

    finally:

        connection.close()

# ============================================================
# GET SINGLE ASSIGNMENT
# ============================================================

@app.get("/api/teacher/assignments/<int:assignment_id>")
def teacher_get_assignment(assignment_id: int):
    teacher_id = request.args.get("teacher_id", type=int)
    if not teacher_id:
        return error("teacher_id is required.")

    connection = get_db()
    try:
        with connection.cursor() as cursor:
            cursor.execute("""
                SELECT assignment_id, teacher_id, class_id, title, instructions,
                       assigned_at, due_date, status
                FROM reading_assignment
                WHERE assignment_id = %s AND teacher_id = %s
            """, (assignment_id, teacher_id))
            assignment = cursor.fetchone()
            if not assignment:
                return error("Assignment not found or not owned by this teacher.", 404)

            # Fetch materials
            assignment["materials"] = get_assignment_materials(cursor, assignment_id)

            return success({"assignment": serialize(assignment)})
    finally:
        connection.close()

# ============================================================
# UPDATE ASSIGNMENT
# ============================================================

@app.put("/api/teacher/assignments/<int:assignment_id>")
def teacher_update_assignment(assignment_id: int):
    teacher_id = request.args.get("teacher_id", type=int)  # or from JSON
    if not teacher_id:
        return error("teacher_id is required.")

    payload = request.get_json(silent=True) or {}

    # (Optional) Merge teacher_id from query into payload – not strictly needed
    query_teacher_id = request.args.get('teacher_id', type=int)
    if query_teacher_id and 'teacher_id' not in payload:
        payload['teacher_id'] = query_teacher_id

    title = payload.get("title")
    instructions = payload.get("instructions")
    due_date = payload.get("due_date")

    if not title:
        return error("Title is required.")

    connection = get_db()
    try:
        with connection.cursor() as cursor:
            # Verify ownership
            cursor.execute("""
                SELECT 1 FROM reading_assignment
                WHERE assignment_id = %s AND teacher_id = %s
            """, (assignment_id, teacher_id))
            if not cursor.fetchone():
                return error("Assignment not found or not owned by this teacher.", 404)

            cursor.execute("""
                UPDATE reading_assignment
                SET title = %s, instructions = %s, due_date = %s
                WHERE assignment_id = %s
            """, (title, instructions, due_date, assignment_id))

        connection.commit()
        return success({"message": "Assignment updated successfully."})
    except Exception as e:
        connection.rollback()
        return error(str(e), 500)
    finally:
        connection.close()

# ============================================================
# DELETE assignment (soft delete)
# ============================================================
@app.delete("/api/teacher/assignments/<int:assignment_id>")
def teacher_delete_assignment(assignment_id: int):
    teacher_id = request.args.get("teacher_id", type=int)
    if not teacher_id:
        return error("teacher_id is required.")

    connection = get_db()
    try:
        with connection.cursor() as cursor:
            cursor.execute("""
                UPDATE reading_assignment
                SET status = 'archived'
                WHERE assignment_id = %s AND teacher_id = %s
            """, (assignment_id, teacher_id))
            if cursor.rowcount == 0:
                return error("Assignment not found or not owned.", 404)
        connection.commit()
        return success({"message": "Assignment archived."})
    except Exception as e:
        connection.rollback()
        return error(str(e), 500)
    finally:
        connection.close()

# ============================================================
# TEACHER — LIST MATERIALS (for assignment dropdown)
# ============================================================

@app.get("/api/teacher/materials")
def teacher_list_materials():
    teacher_id = request.args.get("teacher_id", type=int)
    if not teacher_id:
        return error("teacher_id is required.")

    connection = get_db()
    try:
        with connection.cursor() as cursor:
            cursor.execute("""
                SELECT
                    material_id,
                    title,
                    grade_level,
                    language,
                    material_type
                FROM reading_material
                WHERE teacher_id = %s
                  AND status = 'Active'
                ORDER BY title ASC
            """, (teacher_id,))
            materials = cursor.fetchall()
            return success({"materials": serialize(materials)})
    finally:
        connection.close()

# ============================================================
# TEACHER — LIST CLASSES (for assignment dropdown)
# ============================================================

@app.get("/api/teacher/classes")
def teacher_list_classes():
    teacher_id = request.args.get("teacher_id", type=int)
    if not teacher_id:
        return error("teacher_id is required.")

    connection = get_db()
    try:
        with connection.cursor() as cursor:
            cursor.execute("""
                SELECT
                    class_id,
                    grade_level,
                    section,
                    school_year
                FROM class
                WHERE teacher_id = %s
                ORDER BY grade_level, section
            """, (teacher_id,))
            classes = cursor.fetchall()
            # format display name
            for cls in classes:
                cls["display_name"] = f"{cls['grade_level']} - {cls['section']} ({cls['school_year']})"
            return success({"classes": serialize(classes)})
    finally:
        connection.close()

# ============================================================
# DEV — SEED EXISTING ASSIGNMENT
# ============================================================

@app.post(
    "/api/dev/seed-test-assignment"
)
def seed_test_assignment():

    if not DEV_MODE:

        return error(
            "Development endpoint disabled.",
            403
        )

    payload = {
        "teacher_id": 2,
        "class_id": 4,

        "title":
            "Reading Practice #1",

        "instructions":
            "Read the story carefully, "
            "then answer the five "
            "comprehension questions.",

        "materials": [
            5
        ]
    }

    try:

        data, status = create_assignment(
            payload
        )

        return success(
            data,
            status
        )

    except PermissionError as exc:

        return error(
            str(exc),
            403
        )

    except ValueError as exc:

        return error(
            str(exc),
            400
        )

    except Exception as exc:

        return error(
            f"Failed to seed test assignment: {exc}",
            500
        )


# ============================================================
# ERROR HANDLERS
# ============================================================

@app.errorhandler(404)
def not_found(_error):

    return error(
        "API endpoint not found.",
        404
    )


@app.errorhandler(405)
def method_not_allowed(_error):

    return error(
        "HTTP method not allowed.",
        405
    )


# ============================================================
# MAIN
# ============================================================

if __name__ == "__main__":

    print(
        "ArchiveVox Assignment API"
    )

    print(
        f"Database: "
        f"{DB_CONFIG['host']}:"
        f"{DB_CONFIG['port']}/"
        f"{DB_CONFIG['database']}"
    )

    print(
        f"Development mode: "
        f"{DEV_MODE}"
    )

    validate_schema()

    app.run(
        host=os.getenv(
            "API_HOST",
            "127.0.0.1"
        ),

        port=int(
            os.getenv(
                "API_PORT",
                "5000"
            )
        ),

        debug=True
    )