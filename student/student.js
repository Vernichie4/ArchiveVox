/* ============================================================
   ARCHIVEVOX — STUDENT DASHBOARD (Full with Quiz)
   ============================================================ */

const API_BASE_URL = "http://127.0.0.1:5000";
// Grab the logged-in user from localStorage
const storedUser = JSON.parse(localStorage.getItem("archivevox_user") || "null");

// Redirect to login page if no user is found!
if (!storedUser) {
    window.location.href = "/index.php"; // Update this path to your actual login page
}

const STUDENT_ID = storedUser ? storedUser.student_id : null;

/* ============================================================
   STATE
============================================================ */

const state = {
    student: null,
    assignments: [],
    currentAssignment: null,
    currentView: "dashboard",
    loading: false,
    currentActivityId: null,
    currentAttemptId: null,
    currentQuizId: null,
    currentQuestions: null,
    currentAssignmentDetail: null,
    selectedAnswers: {}
};

// === READING ASSESSMENT STATE (inside student.js) ===
const readingState = {
    audioBlob: null,
    mediaRecorder: null,
    audioChunks: [],
    stream: null,
    recording: false,
    seconds: 0,
    timerInterval: null,
    playbackUrl: null,
    submitted: false,
    results: null,          // will hold WCPM, accuracy, etc.
    activityId: null,
    assessmentId: null,
};

/* ============================================================
   DOM HELPERS
============================================================ */

function $(selector) {
    return document.querySelector(selector);
}

function $all(selector) {
    return document.querySelectorAll(selector);
}

/* ============================================================
   API HELPER
============================================================ */

async function fetchJson(url, options = {}) {
    const response = await fetch(`${API_BASE_URL}${url}`, {
        ...options,
        headers: {
            "Accept": "application/json",
            ...(options.body ? { "Content-Type": "application/json" } : {}),
            ...(options.headers || {})
        }
    });

    let data = null;
    try {
        data = await response.json();
    } catch (error) {
        throw new Error(`Server returned invalid JSON (${response.status}).`);
    }

    if (!response.ok) {
        throw new Error(data?.message || `Request failed with status ${response.status}.`);
    }

    if (data && data.success === false) {
        throw new Error(data.message || "The request was unsuccessful.");
    }

    return data;
}

/* ============================================================
   INIT & GLOBAL STATE
============================================================ */
document.addEventListener("DOMContentLoaded", () => {
    // Start the dashboard
    initializeStudentDashboard();

    // 2. Safely attach the logout listener
    const logoutBtn = document.getElementById("studentLogoutBtn"); 
    
    if (logoutBtn) {
        logoutBtn.addEventListener("click", handleLogout);
    } else {
        console.warn("Logout button with ID 'logoutButton' not found in HTML.");
    }
});

async function initializeStudentDashboard() {
    // If no student is found in localStorage, kick them back to login
    if (!STUDENT_ID) {
        console.error("No valid student found. Redirecting to login...");
        await handleLogout();
        return;
    }

    setupNavigation();
    setupModal();
    setupViewButtons();
    setStudentPlaceholder();
    
    await loadAssignments();
    loadCompletedAssignments();
}

/* ============================================================
   LOGOUT FUNCTIONALITY
============================================================ */
async function handleLogout() {
    try {
        // UPDATE: Added { method: 'POST' }
        await fetch('../php/api/auth/logout.php', { method: 'POST' }); 
        
        // Clear the student data from the browser's memory
        localStorage.removeItem('archivevox_user');
        
        // Redirect back to the main login page
        window.location.href = '../index.php'; 
        
    } catch (error) {
        console.error('Logout error:', error);
        
        localStorage.removeItem('archivevox_user');
        window.location.href = '../index.php';
    }
}

/* ============================================================
   STUDENT PLACEHOLDER
============================================================ */

function setStudentPlaceholder() {
    const nameElements = [
        $("#welcomeStudentName"),
        $("#sidebarStudentName"),
        $("#topbarStudentName")
    ];
    nameElements.forEach(el => {
        if (el) el.textContent = "Student";
    });
}

/* ============================================================
   LOAD ASSIGNMENTS
============================================================ */

async function loadAssignments() {
    if (!STUDENT_ID) {
        showError("No student ID configured.");
        renderEmptyAssignments($("#dashboardAssignments"), "Student ID required.");
        renderEmptyAssignments($("#allAssignments"), "Student ID required.");
        return;
    }

    state.loading = true;
    renderLoading($("#dashboardAssignments"));
    renderLoading($("#allAssignments"));

    try {
        const data = await fetchJson(`/api/student/assignments?student_id=${encodeURIComponent(STUDENT_ID)}`);
        const assignments = extractAssignments(data);

        state.assignments = assignments;
        updateAssignmentStatistics();
        renderDashboardAssignments();
        renderAllAssignments();

        if (data.student) {
            updateStudentInfo(data.student);
        }

    } catch (error) {
        console.error("Failed to load assignments:", error);
        showError(error.message || "Unable to load assignments.");
        renderEmptyAssignments($("#dashboardAssignments"), "Could not load assignments.");
        renderEmptyAssignments($("#allAssignments"), "Could not load assignments.");
    } finally {
        state.loading = false;
    }
}

/* ============================================================
   STUDENT INFO
============================================================ */

function updateStudentInfo(student) {
    const firstName = student.first_name || "Student";
    const lastName = student.last_name || "";
    const fullName = `${firstName} ${lastName}`.trim();
    const grade = student.grade_level || "Learner";

    const nameEls = [
        $("#welcomeStudentName"),
        $("#sidebarStudentName"),
        $("#topbarStudentName")
    ];
    nameEls.forEach(el => { if (el) el.textContent = fullName; });

    const avatarEls = [
        $("#sidebarAvatar"),
        $("#topbarAvatar")
    ];
    avatarEls.forEach(el => { if (el) el.textContent = firstName.charAt(0).toUpperCase(); });

    const gradeEl = $("#sidebarStudentGrade");
    if (gradeEl) gradeEl.textContent = grade;
}

/* ============================================================
   NORMALIZE API RESPONSE
============================================================ */

function extractAssignments(data) {
    if (!data) return [];
    if (Array.isArray(data)) return data;
    if (Array.isArray(data.assignments)) return data.assignments;
    if (data.data && Array.isArray(data.data)) return data.data;
    if (data.data && Array.isArray(data.data.assignments)) return data.data.assignments;
    return [];
}

function normalizeAssignment(assignment) {
    const materials = assignment.materials || assignment.reading_materials || assignment.material || [];
    const normalizedMaterials = Array.isArray(materials) ? materials : [materials];

    // --- NEW LOGIC: Calculate progress based on completed quizzes ---
    let completedCount = 0;
    let totalScore = 0;

    normalizedMaterials.forEach(m => {
        // If the backend attached a completed quiz attempt, count it!
        if (m.quiz_attempt && m.quiz_attempt.status === 'completed') {
            completedCount++;
            totalScore += Number(m.quiz_attempt.percentage || 0);
        }
    });

    const totalMaterials = normalizedMaterials.length;
    const isCompleted = (totalMaterials > 0 && completedCount === totalMaterials);
    const calcProgress = totalMaterials > 0 ? (completedCount / totalMaterials) * 100 : 0;
    const avgScore = completedCount > 0 ? (totalScore / completedCount) : null;

    return {
        id: assignment.assignment_id ?? assignment.id,
        title: assignment.title || "Reading Assignment",
        instructions: assignment.instructions || "Complete the reading activity.",
        status: isCompleted ? "completed" : (assignment.status || "assigned"),
        teacherName: assignment.teacher_name || assignment.teacher || "",
        materials: normalizedMaterials,
        progress: calcProgress,
        completed: isCompleted,
        score: avgScore // This feeds the "Average Score" star badge!
    };
}

/* ============================================================
   RENDER DASHBOARD
============================================================ */

function renderDashboardAssignments() {
    const container = $("#dashboardAssignments");
    if (!container) return;

    const assignments = state.assignments.slice(0, 3);

    if (!assignments.length) {
        renderEmptyAssignments(container, "No assignments yet.");
        return;
    }

    container.innerHTML = assignments
        .map(a => createAssignmentCard(normalizeAssignment(a)))
        .join("");
}

function renderAllAssignments() {
    const container = $("#allAssignments");
    if (!container) return;

    if (!state.assignments.length) {
        renderEmptyAssignments(container, "No assignments yet.");
        return;
    }

    container.innerHTML = state.assignments
        .map(a => createAssignmentCard(normalizeAssignment(a)))
        .join("");
}

function createAssignmentCard(assignment) {
    const status = getAssignmentStatus(assignment);
    const materialsCount = Array.isArray(assignment.materials) ? assignment.materials.length : 0;
    const progress = Math.max(0, Math.min(100, Number(assignment.progress || 0)));

    return `
        <article class="assignment-card" data-assignment-id="${escapeHtml(assignment.id)}">
            <div class="assignment-card-top">
                <div class="assignment-book-icon">📖</div>
                <span class="status-badge ${status.className}">${escapeHtml(status.label)}</span>
            </div>
            <div class="assignment-card-content">
                <p class="assignment-type">READING ASSIGNMENT</p>
                <h3>${escapeHtml(assignment.title)}</h3>
                <p class="assignment-instructions">${escapeHtml(assignment.instructions)}</p>
                <div class="assignment-meta">
                    <span>📚 ${materialsCount} ${materialsCount === 1 ? "story" : "stories"}</span>
                    ${assignment.teacherName ? `<span>👨‍🏫 ${escapeHtml(assignment.teacherName)}</span>` : ""}
                </div>
                <div class="progress-container">
                    <div class="progress-label">
                        <span>Progress</span>
                        <strong>${progress}%</strong>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${progress}%"></div>
                    </div>
                </div>
            </div>
            <div class="assignment-card-footer">
                <button type="button" class="primary-button assignment-start-button"
                        data-assignment-id="${escapeHtml(assignment.id)}">
                    ${assignment.completed ? "View Assignment" : "Start Assignment"} →
                </button>
            </div>
        </article>
    `;
}

function getAssignmentStatus(assignment) {
    if (assignment.completed) return { label: "Completed", className: "completed" };
    const status = String(assignment.status || "").toLowerCase();
    if (status === "completed" || status === "complete") return { label: "Completed", className: "completed" };
    if (status === "in_progress" || status === "started" || status === "ongoing") return { label: "In Progress", className: "in-progress" };
    return { label: "Assigned", className: "assigned" };
}

/* ============================================================
   STATISTICS
============================================================ */

function updateAssignmentStatistics() {
    const total = state.assignments.length;
    const completed = state.assignments.filter(a => {
        const n = normalizeAssignment(a);
        return n.completed || getAssignmentStatus(n).className === "completed";
    }).length;
    const avg = calculateAverageScore();

    const totalEl = $("#statAssignments");
    const compEl = $("#statCompleted");
    const scoreEl = $("#statAverageScore");

    if (totalEl) totalEl.textContent = total;
    if (compEl) compEl.textContent = completed;
    if (scoreEl) scoreEl.textContent = avg === null ? "—" : `${avg}%`;
}

function calculateAverageScore() {
    const scores = state.assignments
        .map(a => a.score ?? a.quiz_score ?? a.percentage)
        .filter(s => s !== null && s !== undefined && !Number.isNaN(Number(s)))
        .map(s => Number(s));

    if (!scores.length) return null;
    const avg = scores.reduce((sum, s) => sum + s, 0) / scores.length;
    return Math.round(avg);
}

/* ============================================================
   MODAL SETUP
============================================================ */

function setupModal() {
    const modal = $("#assignmentModal");
    const closeButton = $("#closeAssignmentModal");
    const overlay = modal?.querySelector(".modal-overlay");
    const startButton = $("#modalStartButton");

    closeButton?.addEventListener("click", closeAssignmentModal);
    overlay?.addEventListener("click", closeAssignmentModal);

    document.addEventListener("keydown", event => {
        if (event.key === "Escape" && modal && !modal.classList.contains("hidden")) {
            closeAssignmentModal();
        }
    });

    document.addEventListener("click", event => {
        const button = event.target.closest(".assignment-start-button");
        if (!button) return;
        const assignmentId = button.dataset.assignmentId;
        openAssignmentModal(assignmentId);
    });

    startButton?.addEventListener("click", function() {
        const action = this.dataset.action || "show-reading";
        if (action === "show-reading") {
            showReadingContent();
        } else if (action === "start-reading") {
            startReadingActivity();
        } else if (action === "start-quiz") {
            startQuiz();
        } else if (action === "submit-quiz") {
            submitQuiz();
        } else if (action === "close") {
            closeAssignmentModal();
        }

        if (action === "submit-reading") {
            submitReading();
        } else if (action === "show-reading") {
            showReadingContent();
        } else if (action === "start-quiz") {
            startQuiz();
        } else if (action === "submit-quiz") {
            submitQuiz();
        } else if (action === "close") {
            closeAssignmentModal();
        }
    });
}

/* ============================================================
   OPEN ASSIGNMENT (DETAILS VIEW)
============================================================ */

async function openAssignmentModal(assignmentId) {
    // First, find the assignment from the list (for basic info)
    const listAssignment = state.assignments.find(item =>
        String(item.assignment_id ?? item.id) === String(assignmentId)
    );

    if (!listAssignment) {
        showError("Assignment not found.");
        return;
    }

    // Fetch the full detail from the API
    try {
        const data = await fetchJson(
            `/api/student/assignments/${assignmentId}?student_id=${encodeURIComponent(STUDENT_ID)}`
        );

        // Store the detailed data
        state.currentAssignmentDetail = data;

        // Normalize using the detail data
        const normalized = normalizeAssignment({
            ...listAssignment,
            materials: data.materials || []   // override with detailed materials
        });

        state.currentAssignment = normalized;

        // Render the modal (details view)
        const modal = $("#assignmentModal");
        const modalCard = modal.querySelector(".modal-card");
        modalCard.classList.remove("reading-modal");

        const modalIcon = modal.querySelector(".modal-icon");
        const modalTitle = $("#modalAssignmentTitle");
        const modalBody = modal.querySelector(".modal-body");
        const startButton = $("#modalStartButton");

        if (modalIcon) modalIcon.textContent = "📖";
        if (modalTitle) modalTitle.textContent = normalized.title;

        modalBody.innerHTML = `
            <div id="modalInstructions" class="instructions-box">${escapeHtml(normalized.instructions)}</div>
            <div class="modal-section">
                <h3>Reading Materials</h3>
                <div id="modalMaterials" class="material-list"></div>
            </div>
        `;

        renderModalMaterials(normalized.materials);

        startButton.textContent = "Start Assignment →";
        startButton.dataset.action = "show-reading";

        modal.classList.remove("hidden");

    } catch (error) {
        console.error("Failed to load assignment detail:", error);
        showError(error.message || "Could not load assignment details.");
    }
}

function renderModalMaterials(materials) {
    const container = $("#modalMaterials");
    if (!container) return;

    if (!Array.isArray(materials) || !materials.length) {
        container.innerHTML = `
            <div class="empty-materials">
                <span>📚</span>
                <p>Reading materials will appear here.</p>
            </div>
        `;
        return;
    }

    container.innerHTML = materials
        .map((material, index) => {
            const title = material.title || material.material_title || material.name || `Story ${index + 1}`;
            const description = material.description || material.summary || "Reading material";
            return `
                <div class="material-item">
                    <div class="material-number">${index + 1}</div>
                    <div class="material-info">
                        <strong>${escapeHtml(title)}</strong>
                        <span>${escapeHtml(description)}</span>
                    </div>
                </div>
            `;
        })
        .join("");
}

/* ============================================================
   READING CONTENT
============================================================ */

function showReadingContent() {
    const detail = state.currentAssignmentDetail;
    if (!detail) {
        showError("No assignment detail loaded.");
        return;
    }
    const material = detail.materials && detail.materials[0];
    if (!material) {
        showError("No reading material found.");
        return;
    }

    // Reset reading state
    Object.assign(readingState, {
        audioBlob: null,
        mediaRecorder: null,
        audioChunks: [],
        stream: null,
        recording: false,
        seconds: 0,
        timerInterval: null,
        playbackUrl: null,
        submitted: false,
        results: null,
        activityId: null,
        assessmentId: null
    });

    const modal = $("#assignmentModal");
    const modalCard = modal.querySelector(".modal-card");
    modalCard.classList.add("reading-modal");

    const modalIcon = modal.querySelector(".modal-icon");
    const modalTitle = $("#modalAssignmentTitle");
    const modalBody = modal.querySelector(".modal-body");
    const startButton = $("#modalStartButton");

    const title = material.material_title || material.title || state.currentAssignment.title;
    if (modalIcon) modalIcon.textContent = "📖";
    if (modalTitle) modalTitle.textContent = title;

    const ocrText = material.ocr_text || "Reading material content is not available.";
    const wordCount = material.total_words ?? "?";
    const language = material.language || "Unknown";
    const gradeLevel = material.grade_level || "N/A";

    // Build recording UI
    modalBody.innerHTML = `
        <div class="reading-content">
            <div class="reading-meta">
                <span>🌐 ${escapeHtml(language)}</span>
                <span>📚 Grade ${escapeHtml(gradeLevel)}</span>
                <span>📝 ${escapeHtml(String(wordCount))} words</span>
            </div>
            <div class="reading-text">
                ${escapeHtml(ocrText).replace(/\n/g, '<br>')}
            </div>
            <div class="reading-divider">─────────</div>

            <!-- Recording controls -->
            <div class="recording-controls">
                <div class="timer-row">
                    <span id="timer-display" class="timer-display">00:00</span>
                </div>
                <div class="button-row">
                    <button id="start-record-btn" class="btn-primary btn-record">🎤 Start Recording</button>
                    <button id="stop-record-btn" class="btn-danger btn-record hidden">⏹️ Stop</button>
                    <button id="play-record-btn" class="btn-secondary" disabled>▶️ Playback</button>
                    <button id="clear-record-btn" class="btn-secondary" disabled>🗑️ Clear</button>
                </div>
                <div id="recording-status" class="recording-status-msg">Press "Start Recording" to begin.</div>
            </div>
        </div>
    `;

    // Bind recording events
    document.getElementById('start-record-btn').addEventListener('click', startRecording);
    document.getElementById('stop-record-btn').addEventListener('click', stopRecording);
    document.getElementById('play-record-btn').addEventListener('click', playRecording);
    document.getElementById('clear-record-btn').addEventListener('click', clearRecording);

    // Set footer button to "Submit Reading"
    startButton.textContent = "📤 Submit Reading";
    startButton.dataset.action = "submit-reading";
    startButton.disabled = true;  // enabled after recording
    // We'll enable it when audioBlob is available (in onstop)
    // But we also need a way to re-enable if the user records again.
    // We'll add a listener to enable when audioBlob changes.
    // We'll also override the onclick for submit-reading later.
}

/* ============================================================
   START READING (API Call)
============================================================ */

async function startReadingActivity() {
    const detail = state.currentAssignmentDetail;
    if (!detail) {
        showError("No assignment detail loaded.");
        return;
    }

    const material = detail.materials && detail.materials[0];
    if (!material) {
        showError("No reading material found.");
        return;
    }

    const assignment = state.currentAssignment;
    const assignmentId = assignment.id;
    const assignmentMaterialId = material.assignment_material_id;

    if (!assignmentId || !assignmentMaterialId) {
        showError("Missing assignment or material ID.");
        return;
    }

    try {
        const startButton = $("#modalStartButton");
        startButton.disabled = true;
        startButton.textContent = "⏳ Starting...";

        const response = await fetchJson(
            `/api/student/assignments/${assignmentId}/materials/${assignmentMaterialId}/start-reading`,
            {
                method: "POST",
                body: JSON.stringify({ student_id: STUDENT_ID })
            }
        );

        state.currentActivityId = response.activity_id;
        showQuizStartScreen();

    } catch (error) {
        console.error("Start reading failed:", error);
        showError(error.message || "Unable to start reading activity.");
        const startButton = $("#modalStartButton");
        startButton.disabled = false;
        startButton.textContent = "🎙 Start Reading";
    }
}

// ---- Recording helpers ----
function startRecording() {
    if (readingState.recording) return;
    navigator.mediaDevices.getUserMedia({ audio: true })
        .then(stream => {
            readingState.stream = stream;
            readingState.mediaRecorder = new MediaRecorder(stream);
            readingState.audioChunks = [];
            readingState.seconds = 0;

            readingState.mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) readingState.audioChunks.push(e.data);
            };

            readingState.mediaRecorder.onstop = () => {
                readingState.audioBlob = new Blob(readingState.audioChunks, { type: 'audio/webm' });
                // Enable submit / playback buttons
                document.getElementById('play-record-btn').disabled = false;
                document.getElementById('clear-record-btn').disabled = false;
                document.getElementById('modalStartButton').disabled = false;
                document.getElementById('recording-status').textContent = 'Recording complete. Ready to submit.';
            };

            readingState.mediaRecorder.start();
            readingState.recording = true;
            readingState.timerInterval = setInterval(() => {
                readingState.seconds++;
                updateTimerDisplay();
            }, 1000);

            // Update UI
            document.getElementById('start-record-btn').classList.add('hidden');
            document.getElementById('stop-record-btn').classList.remove('hidden');
            document.getElementById('recording-status').textContent = '⏺️ Recording...';
        })
        .catch(err => {
            alert('Microphone access denied: ' + err.message);
        });
}

function stopRecording() {
    if (readingState.mediaRecorder && readingState.mediaRecorder.state === 'recording') {
        readingState.mediaRecorder.stop();
    }
    if (readingState.stream) {
        readingState.stream.getTracks().forEach(t => t.stop());
        readingState.stream = null;
    }
    readingState.recording = false;
    if (readingState.timerInterval) {
        clearInterval(readingState.timerInterval);
        readingState.timerInterval = null;
    }
    document.getElementById('start-record-btn').classList.remove('hidden');
    document.getElementById('stop-record-btn').classList.add('hidden');
}

function playRecording() {
    if (!readingState.audioBlob) return;
    if (readingState.playbackUrl) URL.revokeObjectURL(readingState.playbackUrl);
    readingState.playbackUrl = URL.createObjectURL(readingState.audioBlob);
    const audio = new Audio(readingState.playbackUrl);
    audio.play();
}

function clearRecording() {
    readingState.audioBlob = null;
    readingState.audioChunks = [];
    readingState.seconds = 0;
    updateTimerDisplay();
    document.getElementById('play-record-btn').disabled = true;
    document.getElementById('clear-record-btn').disabled = true;
    document.getElementById('submit-reading-btn').disabled = true;
    document.getElementById('recording-status').textContent = 'Recording cleared.';
}

function updateTimerDisplay() {
    const mins = String(Math.floor(readingState.seconds / 60)).padStart(2, '0');
    const secs = String(readingState.seconds % 60).padStart(2, '0');
    const el = document.getElementById('timer-display');
    if (el) el.textContent = `${mins}:${secs}`;
}

// Sumbit Reading

async function submitReading() {
    if (!readingState.audioBlob) {
        showError("No recording to submit.");
        return;
    }
    if (!state.currentAssignment || !state.currentAssignmentDetail) {
        showError("Assignment data missing.");
        return;
    }

    const material = state.currentAssignmentDetail.materials[0];
    const studentId = STUDENT_ID;
    const materialId = material.material_id;

    const formData = new FormData();
    formData.append('audio', readingState.audioBlob, 'reading.webm');
    formData.append('student_id', studentId);
    formData.append('material_id', materialId);
    formData.append('duration_seconds', String(Math.max(1, readingState.seconds)));

    // If you have original text for alignment, include it
    formData.append('original_text', material.ocr_text || '');

    const submitBtn = $("#modalStartButton");
    const statusEl = document.getElementById('recording-status');
    submitBtn.disabled = true;
    submitBtn.textContent = "⏳ Processing...";
    if (statusEl) statusEl.textContent = "Submitting and analyzing audio...";

    try {
        const response = await fetch('../php/api/shared/assessment.php?action=record', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Assessment processing failed.');
        }

        // Save results
        readingState.results = data;
        readingState.activityId = data.activity_id;
        readingState.assessmentId = data.assessment_id;
        readingState.submitted = true;

        // Show results in the modal
        showReadingResults(data);

    } catch (error) {
        if (statusEl) statusEl.textContent = 'Submission failed.';
        showError('Error: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.textContent = "📤 Submit Reading";
    }
}

// Show Reading Result
function showReadingResults(results) {
    const modalBody = document.querySelector(".modal-body");
    const startButton = $("#modalStartButton");

    const profile = results.reading_profile || {};
    const assessment = results.assessment || {};

    const wcpm = profile.wcpm || 0;
    const accuracy = profile.accuracy_percentage || 0;
    const level = profile.reading_level || assessment.reading_level || 'Pending';

    modalBody.innerHTML = `
        <div class="reading-results">
            <h3>📊 Reading Assessment Results</h3>
            <div class="result-grid">
                <div class="result-item">
                    <span class="result-label">Words per Minute</span>
                    <span class="result-value">${wcpm.toFixed(1)}</span>
                </div>
                <div class="result-item">
                    <span class="result-label">Accuracy</span>
                    <span class="result-value">${accuracy.toFixed(1)}%</span>
                </div>
                <div class="result-item">
                    <span class="result-label">Reading Level</span>
                    <span class="result-value">${escapeHtml(level)}</span>
                </div>
                <div class="result-item">
                    <span class="result-label">Time</span>
                    <span class="result-value">${Math.floor(readingState.seconds / 60)}:${String(readingState.seconds % 60).padStart(2, '0')}</span>
                </div>
            </div>
            <p class="result-message">✅ Reading recorded and scored. You may now proceed to the quiz.</p>
        </div>
    `;

    // Update footer button to "Take Quiz"
    startButton.textContent = "📝 Take Quiz";
    startButton.dataset.action = "start-quiz";
    startButton.disabled = false;
}

/* ============================================================
   QUIZ START SCREEN
============================================================ */

function showQuizStartScreen() {
    const modalBody = document.querySelector(".modal-body");
    const startButton = $("#modalStartButton");

    modalBody.innerHTML = `
        <div style="text-align: center; padding: 20px 0;">
            <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
            <h3 style="margin-bottom: 8px;">Reading Started!</h3>
            <p style="color: var(--muted); margin-bottom: 4px;">
                Activity ID: ${readingState.activityId}
            </p>
            <p style="color: var(--muted); margin-top: 8px;">
                Now you can proceed to the comprehension quiz.
            </p>
        </div>
    `;

    startButton.textContent = "📝 Take Quiz";
    startButton.dataset.action = "start-quiz";
    startButton.disabled = false;
}

/* ============================================================
   START QUIZ (API Call)
============================================================ */

async function startQuiz() {
    if (state.isStartingQuiz) return;
    state.isStartingQuiz = true;

    const detail = state.currentAssignmentDetail;
    if (!detail) {
        showError("No assignment detail loaded.");
        state.isStartingQuiz = false; // Release lock
        return;
    }

    const material = detail.materials && detail.materials[0];
    if (!material) {
        showError("No reading material found.");
        return;
    }

    const assignment = state.currentAssignment;
    const assignmentId = assignment.assignment_id || assignment.id; // Added fallback just in case!
    const assignmentMaterialId = material.assignment_material_id;

    // FIX 1: Look at readingState instead of state
    if (!readingState.activityId) {
        showError("No reading activity found. Please start reading first.");
        return;
    }

    try {
        const startButton = $("#modalStartButton");
        startButton.disabled = true;
        startButton.textContent = "⏳ Loading quiz...";

        const response = await fetchJson(
            `/api/student/assignments/${assignmentId}/materials/${assignmentMaterialId}/quiz/start`,
            {
                method: "POST",
                body: JSON.stringify({
                    student_id: STUDENT_ID,
                    // FIX 2: Send the correct readingState ID to the backend!
                    activity_id: readingState.activityId 
                })
            }
        );

        state.currentAttemptId = response.attempt_id;
        state.currentQuizId = response.quiz_id;

        // Use questions from the detail data
        const questions = material.questions || [];
        if (!questions.length) {
            throw new Error("No quiz questions found for this material.");
        }

        state.currentQuestions = questions;
        state.selectedAnswers = {};

        renderQuiz();

    } catch (error) {
        console.error("Start quiz failed:", error);
        showError(error.message || "Unable to start quiz.");
        const startButton = $("#modalStartButton");
        startButton.disabled = false;
        startButton.textContent = "📝 Take Quiz";
    } finally {
        // 2. ADD THIS FINALLY BLOCK TO RELEASE THE LOCK
        state.isStartingQuiz = false;
    }
}

/* ============================================================
   RENDER QUIZ
============================================================ */

function renderQuiz() {
    const questions = state.currentQuestions;
    if (!questions || !questions.length) {
        showError("No quiz questions available.");
        return;
    }

    const modalBody = document.querySelector(".modal-body");
    const startButton = $("#modalStartButton");

    let html = `
        <div style="margin-bottom: 16px;">
            <p style="font-size: 13px; color: var(--muted);">
                Answer all ${questions.length} questions based on the story.
            </p>
        </div>
        <form id="quizForm">
    `;

    questions.forEach((q, index) => {
        const choices = q.choices || [];
        html += `
            <div style="margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border);">
                <p style="font-weight: 600; margin-bottom: 10px;">
                    ${index + 1}. ${escapeHtml(q.question_text)}
                </p>
                <div style="display: flex; flex-direction: column; gap: 6px; padding-left: 8px;">
        `;
        choices.forEach(choice => {
            const choiceId = choice.choice_id;
            html += `
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px;">
                    <input type="radio" name="q${q.question_id}" value="${choiceId}" style="width: 18px; height: 18px; accent-color: var(--primary);">
                    ${escapeHtml(choice.choice_label)}. ${escapeHtml(choice.choice_text)}
                </label>
            `;
        });
        html += `</div></div>`;
    });

    html += `</form>`;

    modalBody.innerHTML = html;

    startButton.textContent = "✅ Submit Quiz";
    startButton.dataset.action = "submit-quiz";
    startButton.disabled = false;
}

/* ============================================================
   SUBMIT QUIZ (API Call)
============================================================ */

async function submitQuiz() {
    if (state.isSubmittingQuiz) return;
    state.isSubmittingQuiz = true;

    const form = document.getElementById("quizForm");
    if (!form) {
        showError("Quiz form not found.");
        state.isSubmittingQuiz = false; // Release lock
        return;
    }

    const formData = new FormData(form);
    const answers = [];
    for (let [name, value] of formData.entries()) {
        const questionId = parseInt(name.replace('q', ''));
        const choiceId = parseInt(value);
        answers.push({ question_id: questionId, choice_id: choiceId });
    }

    if (answers.length !== state.currentQuestions.length) {
        showError(`Please answer all ${state.currentQuestions.length} questions.`);
        return;
    }

    const assignment = state.currentAssignment;
    const material = assignment.materials[0];
    const assignmentId = assignment.id;
    const assignmentMaterialId = material.assignment_material_id;

    try {
        const submitButton = $("#modalStartButton");
        submitButton.disabled = true;
        submitButton.textContent = "⏳ Submitting...";

        const response = await fetchJson(
            `/api/student/assignments/${assignmentId}/materials/${assignmentMaterialId}/quiz/submit`,
            {
                method: "POST",
                body: JSON.stringify({
                    student_id: STUDENT_ID,
                    attempt_id: state.currentAttemptId,
                    answers: answers
                })
            }
        );

        showQuizResults(response);

    } catch (error) {
        console.error("Submit quiz failed:", error);
        showError(error.message || "Unable to submit quiz.");
        const submitButton = $("#modalStartButton");
        submitButton.disabled = false;
        submitButton.textContent = "✅ Submit Quiz";
    } finally {
        state.isSubmittingQuiz = false;
    }
}

/* ============================================================
   QUIZ RESULTS
============================================================ */

function showQuizResults(result) {
    const modalBody = document.querySelector(".modal-body");
    const startButton = $("#modalStartButton");

    const score = result.score || 0;
    const total = result.total_questions || 0;
    const percentage = result.percentage || 0;

    modalBody.innerHTML = `
        <div style="text-align: center; padding: 20px 0;">
            <div style="font-size: 56px; margin-bottom: 12px;">🎉</div>
            <h2 style="margin-bottom: 8px;">Great job!</h2>
            <p style="font-size: 18px; margin-bottom: 4px;">
                You scored <strong>${score}</strong> out of <strong>${total}</strong>
            </p>
            <p style="font-size: 24px; font-weight: 700; color: var(--primary);">
                ${Math.round(percentage)}%
            </p>
        </div>
    `;

    startButton.textContent = "📊 Done";
    startButton.dataset.action = "close";
    startButton.disabled = false;

    // Override click to close modal
    startButton.onclick = function() {
        closeAssignmentModal();
    };
}


/* ============================================================
   CLOSE MODAL
============================================================ */

function closeAssignmentModal() {
    $("#assignmentModal")?.classList.add("hidden");
    state.currentAssignment = null;
    state.currentAssignmentDetail = null;
    state.currentActivityId = null;
    state.currentAttemptId = null;
    const modalCard = document.querySelector(".modal-card");
    if (modalCard) modalCard.classList.remove("reading-modal");
    // Reset button handler
    const startButton = $("#modalStartButton");
    startButton.onclick = null;
    startButton.dataset.action = "show-reading";
    startButton.textContent = "Start Assignment →";

    if (readingState.recording) {
        stopRecording();
    }
    if (readingState.playbackUrl) {
        URL.revokeObjectURL(readingState.playbackUrl);
        readingState.playbackUrl = null;
    }
}

// ============================================================
// LOAD COMPLETED ASSIGNMENTS
// ============================================================

async function loadCompletedAssignments() {
    const container = document.getElementById('completed-assignments-container');
    if (!container) return; 

    try {
        container.innerHTML = '<p>Loading your past scores...</p>';
        const response = await fetchJson(`/api/student/${STUDENT_ID}/completed`);

        if (!response.completed_assignments || response.completed_assignments.length === 0) {
            container.innerHTML = '<p style="color: var(--muted);">No completed assignments yet. Keep reading!</p>';
            return;
        }

        let html = '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">';
        
        response.completed_assignments.forEach(item => {
            html += `
                <div style="background: var(--bg-alt); padding: 16px; border: 1px solid var(--border); border-radius: 8px;">
                    <h4 style="margin: 0 0 4px 0;">${escapeHtml(item.assignment_title)}</h4>
                    <p style="margin: 0 0 16px 0; font-size: 13px; color: var(--muted);">${escapeHtml(item.material_title)}</p>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Accuracy:</span>
                        <strong style="color: var(--primary);">${Math.round(item.accuracy_percentage || 0)}%</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span>Fluency (WCPM):</span>
                        <strong style="color: var(--primary);">${item.wcpm || 0}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Quiz Score:</span>
                        <strong style="color: var(--primary);">${item.score}/${item.total_questions}</strong>
                    </div>

                    <hr style="border-top: 1px solid var(--border); margin: 12px 0;">
                    <button class="btn-secondary" style="width: 100%;" onclick="viewActivityDetails(${item.activity_id})">
                        🔍 View Details
                    </button>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;

    } catch (error) {
        console.error("Failed to load completed assignments:", error);
        container.innerHTML = '<p style="color: red;">Error loading your scores.</p>';
    }
}

// ============================================================
// VIEW ACTIVITY DETAILS
// ============================================================

async function viewActivityDetails(activityId) {
    try {
        // We will reuse your existing assignment modal to display this!
        const modal = $("#assignmentModal");
        const modalTitle = $("#modalAssignmentTitle");
        const modalBody = modal.querySelector(".modal-body");
        const startButton = $("#modalStartButton");

        modalTitle.textContent = "Assessment Details";
        modalBody.innerHTML = '<div style="text-align:center; padding: 40px;">⏳ Loading details...</div>';
        
        // Show modal and hide action button since this is just a view
        modal.classList.remove("hidden");
        startButton.style.display = "none"; 

        const response = await fetchJson(`/api/shared/activity/${activityId}/details`);
        const data = response.assessment;
        const quiz = response.quiz_details;

        modalBody.innerHTML = `
            <div style="padding: 16px;">
                <h3 style="margin-top: 0;">Reading Fluency</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px;">
                    <div style="background: var(--bg-alt); padding: 12px; border-radius: 8px;">
                        <span style="font-size: 12px; color: var(--muted);">WCPM</span>
                        <div style="font-size: 24px; font-weight: bold; color: var(--primary);">${data.wcpm}</div>
                    </div>
                    <div style="background: var(--bg-alt); padding: 12px; border-radius: 8px;">
                        <span style="font-size: 12px; color: var(--muted);">Accuracy</span>
                        <div style="font-size: 24px; font-weight: bold; color: var(--primary);">${Math.round(data.accuracy_percentage)}%</div>
                    </div>
                </div>

                <h4 style="margin-bottom: 8px;">Miscue Breakdown (AI Analysis)</h4>
                <div style="display: flex; gap: 16px; margin-bottom: 24px; font-size: 14px;">
                    <span style="color: #d97706;">🔄 Substitutions: <strong>${data.substitutions || 0}</strong></span>
                    <span style="color: #dc2626;">➖ Omissions: <strong>${data.omissions || 0}</strong></span>
                    <span style="color: #2563eb;">➕ Insertions: <strong>${data.insertions || 0}</strong></span>
                </div>

                <h4 style="margin-bottom: 8px;">What You Read:</h4>
                <div style="background: #f8fafc; padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-style: italic; color: #475569; margin-bottom: 32px;">
                    "${escapeHtml(data.transcript || 'No transcript available.')}"
                </div>
                
                <h3 style="margin-top: 0; border-top: 1px solid var(--border); padding-top: 24px;">Quiz Results</h3>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    ${quiz.map(q => `
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <span style="font-size: 18px;">${q.is_correct ? '✅' : '❌'}</span>
                            <div>
                                <div style="font-weight: 500; font-size: 14px;">${q.question_number}. ${escapeHtml(q.question_text)}</div>
                                <div style="font-size: 13px; color: ${q.is_correct ? 'var(--success)' : 'var(--danger)'};">
                                    Your answer: ${escapeHtml(q.student_answer)}
                                </div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;

        // When they close the modal, make sure the button comes back for future assignments
        const closeBtn = modal.querySelector(".modal-close");
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                startButton.style.display = "block";
            }, { once: true });
        }

    } catch (error) {
        console.error("Error loading details:", error);
        document.querySelector(".modal-body").innerHTML = `<div style="color: red; padding: 20px;">Failed to load details: ${error.message}</div>`;
    }
}

/* ============================================================
   NAVIGATION
============================================================ */

function setupNavigation() {
    $all(".nav-item").forEach(button => {
        button.addEventListener("click", () => {
            const view = button.dataset.view;
            if (view) switchView(view);
        });
    });
}

function setupViewButtons() {
    $all("[data-view-target]").forEach(button => {
        button.addEventListener("click", () => {
            const view = button.dataset.viewTarget;
            if (view) switchView(view);
        });
    });
}

function switchView(viewName) {
    const validViews = ["dashboard", "assignments", "progress"];
    if (!validViews.includes(viewName)) return;

    state.currentView = viewName;

    $all(".view").forEach(view => view.classList.remove("active"));
    const targetView = $(`#${viewName}View`);
    if (targetView) targetView.classList.add("active");

    $all(".nav-item").forEach(button => {
        button.classList.toggle("active", button.dataset.view === viewName);
    });
}

/* ============================================================
   LOADING / EMPTY STATES
============================================================ */

function renderLoading(container) {
    if (!container) return;
    container.innerHTML = `
        <div class="loading-state">
            <div class="spinner"></div>
            <p>Loading...</p>
        </div>
    `;
}

function renderEmptyAssignments(container, message) {
    if (!container) return;
    container.innerHTML = `
        <div class="empty-state">
            <div class="empty-icon">📚</div>
            <h3>No assignments yet</h3>
            <p>${escapeHtml(message)}</p>
        </div>
    `;
}

/* ============================================================
   MESSAGES
============================================================ */

function showError(message) {
    const element = $("#errorMessage");
    if (!element) return;
    element.textContent = message;
    element.classList.remove("hidden");
    clearTimeout(showError.timeout);
    showError.timeout = setTimeout(() => element.classList.add("hidden"), 6000);
}

function showInfo(message) {
    const element = $("#errorMessage");
    if (!element) return;
    element.textContent = message;
    element.classList.remove("hidden");
    clearTimeout(showInfo.timeout);
    showInfo.timeout = setTimeout(() => element.classList.add("hidden"), 5000);
}

/* ============================================================
   HTML ESCAPING
============================================================ */

function escapeHtml(value) {
    if (value === null || value === undefined) return "";
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}