const fs = require('fs');
const filePath = './assets/app.js';
let content = fs.readFileSync(filePath, 'utf8');

// First, update renderAssessment router to handle step 4
const oldRouter = `    if (assessmentState.step === 1) {\n        renderStep1SelectStudent();\n    } else if (assessmentState.step === 2) {\n        renderStep2SelectMaterial();\n    } else if (assessmentState.step === 3) {\n        renderStep3Record();\n    } else if (assessmentState.step === 4) {\n        renderStep4Results();`;

const newRouter = `    if (assessmentState.step === 1) {\n        renderStep1SelectStudent();\n    } else if (assessmentState.step === 2) {\n        renderStep2SelectMaterial();\n    } else if (assessmentState.step === 3) {\n        renderStep3Record();\n    } else if (assessmentState.step === 4) {\n        renderStep4ReviewComprehension();\n    } else if (assessmentState.step === 5) {\n        renderStep4Results();`;

if (content.includes(oldRouter)) {
    content = content.replace(oldRouter, newRouter);
    console.log('✓ Updated renderAssessment router');
}

// Add the new review screen function before renderStep4Results
const renderStep4ResultsMarker = 'function renderStep4Results() {';
const idx = content.indexOf(renderStep4ResultsMarker);

if (idx !== -1) {
    const reviewFunction = `
// ============================================================
// STEP 4: REVIEW AUTO-DETECTED MISCUES & ENTER COMPREHENSION
// ============================================================

function renderStep4ReviewComprehension() {
    const main = document.querySelector('main');
    if (!main) return;

    const scores = assessmentState.detectedScores || {};
    const student = assessmentState.student;
    const material = assessmentState.material;

    main.innerHTML = \`
        <div class="container">
            <div class="step-indicator">
                <div class="step-dot active">1</div>
                <div class="step-dot active">2</div>
                <div class="step-dot active">3</div>
                <div class="step-dot active">4</div>
                <div class="step-dot">5</div>
            </div>

            <h2>Step 4: Review Assessment & Comprehension</h2>
            <p class="step-subtitle">Verify auto-detected miscues and enter comprehension score</p>

            <div class="assessment-summary">
                <p><strong>Student:</strong> \${student?.student_name || 'Unknown'}</p>
                <p><strong>Material:</strong> \${material?.title || 'Unknown'}</p>
            </div>

            <div class="assessment-results">
                <h3>📊 Auto-Detected Reading Metrics</h3>
                <div class="metrics-grid">
                    <div class="metric-box">
                        <div class="metric-label">Accuracy</div>
                        <div class="metric-value">\${(scores.accuracy || 0).toFixed(1)}%</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">WCPM</div>
                        <div class="metric-value">\${Math.round(scores.wcpm || 0)}</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Substitutions</div>
                        <div class="metric-value">\${scores.substitutions || 0}</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Omissions</div>
                        <div class="metric-value">\${scores.omissions || 0}</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Insertions</div>
                        <div class="metric-value">\${scores.insertions || 0}</div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Repetitions</div>
                        <div class="metric-value">\${scores.repetitions || 0}</div>
                    </div>
                </div>
            </div>

            <div class="transcript-section">
                <h3>📝 Transcribed Text (What Whisper heard)</h3>
                <div class="transcript-display">
                    \${scores.transcript || 'No transcript available'}
                </div>
            </div>

            <div class="comprehension-section">
                <h3>💭 Comprehension Score (Enter Manually)</h3>
                <p class="instruction-text">Teacher: Ask comprehension questions and rate 0-7</p>
                <div class="form-group">
                    <label for="comprehension-input">Comprehension Score (0-7):</label>
                    <div class="comprehension-input-group">
                        <input type="range" id="comprehension-input" min="0" max="7" value="0" class="slider">
                        <div class="comprehension-score-display">0</div>
                    </div>
                    <div class="comprehension-labels">
                        <span>0 (None)</span>
                        <span>7 (Complete)</span>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <button id="back-to-record-btn" class="btn btn-secondary">← Back to Recording</button>
                <button id="confirm-comprehension-btn" class="btn btn-primary">✓ Confirm & Save Assessment</button>
            </div>

            <div id="comprehension-status" class="status-message"></div>
        </div>
    \`;

    // Event listeners
    const comprehensionInput = document.getElementById('comprehension-input');
    const scoreDisplay = document.querySelector('.comprehension-score-display');
    const backBtn = document.getElementById('back-to-record-btn');
    const confirmBtn = document.getElementById('confirm-comprehension-btn');

    if (comprehensionInput) {
        comprehensionInput.addEventListener('input', (e) => {
            if (scoreDisplay) scoreDisplay.textContent = e.target.value;
        });
    }

    if (backBtn) {
        backBtn.addEventListener('click', () => {
            assessmentState.step = 3;
            renderAssessment();
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', async () => {
            const comprehensionScore = parseInt(comprehensionInput?.value || 0);
            await finalizeAssessmentWithComprehension(comprehensionScore);
        });
    }
}

async function finalizeAssessmentWithComprehension(comprehensionScore) {
    const statusEl = document.getElementById('comprehension-status');
    const confirmBtn = document.getElementById('confirm-comprehension-btn');

    if (!assessmentState.detectedScores?.activity_id) {
        alert('Missing activity data. Please try again.');
        return;
    }

    if (confirmBtn) confirmBtn.disabled = true;
    if (statusEl) statusEl.textContent = 'Saving comprehension score and calculating final CRLA profile...';

    try {
        const response = await fetch('php/api/shared/assessment.php?action=update_comprehension', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                activity_id: assessmentState.detectedScores.activity_id,
                comprehension_score: comprehensionScore
            })
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to save comprehension score.');
        }

        // Update results with final profile
        if (data.updated_reading_profile) {
            assessmentState.results.reading_profile = data.updated_reading_profile;
        }

        assessmentState.step = 5;
        await loadAssessments();
        await loadDashboardData();
        renderAssessment();
    } catch (error) {
        if (statusEl) statusEl.textContent = '❌ Error saving. Please try again.';
        alert('Save error: ' + error.message);
        if (confirmBtn) confirmBtn.disabled = false;
    }
}

`;

    const newContent = content.slice(0, idx) + reviewFunction + content.slice(idx);
    fs.writeFileSync(filePath, newContent, 'utf8');
    console.log('✓ Added renderStep4ReviewComprehension and finalizeAssessmentWithComprehension');
}

// Update step counter in rendering functions
const stepCounterUpdates = [
    ['<div class="step-dot">4</div>', '<div class="step-dot">5</div>'],
    ['step-counter">4', 'step-counter">5']
];

let updateCount = 0;
stepCounterUpdates.forEach(([oldStr, newStr]) => {
    if (content.includes(oldStr)) {
        // Only replace in renderStep1, renderStep2, renderStep3
        const pattern = new RegExp(`(renderStep[123].*?)(${oldStr.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gs');
        const newContent2 = content.replace(pattern, `$1${newStr}`);
        if (newContent2 !== content) {
            updateCount++;
        }
    }
});

console.log(`✓ Updated ${updateCount} step indicators`);

fs.writeFileSync(filePath, content, 'utf8');
console.log('\n✅ All workflow updates completed!');
