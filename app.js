// ============================================================
// 1. CONFIGURATION & STATE
// ============================================================

const state = {
    user: null,
    activeView: 'dashboard',
    chartInstances: {},
    dashboard: {
        my_students: 0,
        class_avg_wcpm: 0,
        my_assessments: 0,
        recent_assessments: [],
        class_performance: []
    },
    students: [],
    materials: [],
    assessments: [],
    teachers: [],
    importPreview: null,
    importFile: null,
    principalDashboard: null,
    reportData: null,
    selectedTeacherId: null,  
    selectedStudentId: null,
    selectedClass: null
};

// ============================================================
// 2. CORE UTILITIES (Continued: State 2)
// ============================================================

const assessmentState = {
    step: 1,
    student: null,
    material: null,
    audioBlob: null,
    recording: false,
    mediaRecorder: null,
    audioChunks: [],
    results: null,
    timerInterval: null,
    seconds: 0,
    stream: null,
    playbackUrl: null
};

// ============================================================
// 2. CORE UTILITIES (Continued: State 3)
// ============================================================

const loginView = document.getElementById('login-view');
const appShell = document.getElementById('app-shell');
const viewContainer = document.getElementById('view-container');
const navContainer = document.getElementById('nav-container');
const userBadge = document.getElementById('user-badge');
const roleBadge = document.getElementById('role-badge');
const loginForm = document.getElementById('login-form');
const logoutBtn = document.getElementById('logout-btn');
const settingsBtn = document.getElementById('settings-btn');
const nativeAlert = window.alert.bind(window);
const modalRuntime = {
    confirmResolver: null
};

const uiSettings = {
    fontSize: 'default',
    micDeviceId: '',
    audioOutputId: ''
};

const UI_SETTINGS_STORAGE_KEY = 'archivevox.ui-settings';
const SIDEBAR_STORAGE_KEY = 'archivevox.sidebar-collapsed';
let settingsPopover = null;
let settingsPopoverInitialized = false;
let styleGuardObserver = null;
let sidebarToggleInitialized = false;

const sidebarState = {
    collapsed: false,
    mobileOpen: false
};

function safeNumber(value, decimals = 0) {
    const num = parseFloat(value);
    return Number.isNaN(num) ? 0 : Number(num.toFixed(decimals));
}

function loadUiSettings() {
    try {
        const raw = window.localStorage.getItem(UI_SETTINGS_STORAGE_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
            uiSettings.fontSize = parsed.fontSize === 'large' ? 'large' : 'default';
            uiSettings.micDeviceId = typeof parsed.micDeviceId === 'string' ? parsed.micDeviceId : '';
            uiSettings.audioOutputId = typeof parsed.audioOutputId === 'string' ? parsed.audioOutputId : '';
        }
    } catch (error) {
        console.warn('Unable to load UI settings:', error);
    }
}

function persistUiSettings() {
    try {
        window.localStorage.setItem(UI_SETTINGS_STORAGE_KEY, JSON.stringify(uiSettings));
    } catch (error) {
        console.warn('Unable to save UI settings:', error);
    }
}

function applyFontSizeSetting() {
    document.documentElement.dataset.fontScale = uiSettings.fontSize === 'large' ? 'large' : 'default';
}

function loadSidebarPreference() {
    try {
        sidebarState.collapsed = window.localStorage.getItem(SIDEBAR_STORAGE_KEY) === 'true';
    } catch (error) {
        sidebarState.collapsed = false;
    }
}

function persistSidebarPreference() {
    try {
        window.localStorage.setItem(SIDEBAR_STORAGE_KEY, String(sidebarState.collapsed));
    } catch (error) {
        console.warn('Unable to save sidebar preference:', error);
    }
}

function isDesktopSidebarMode() {
    return window.matchMedia('(min-width: 700px)').matches;
}

function applySidebarState() {
    if (!appShell) return;

    const desktop = isDesktopSidebarMode();
    appShell.classList.toggle('sidebar-collapsed', desktop && sidebarState.collapsed);
    appShell.classList.toggle('sidebar-mobile-open', !desktop && sidebarState.mobileOpen);

    if (desktop) {
        appShell.classList.remove('sidebar-mobile-open');
    }

    const desktopToggle = document.getElementById('sidebar-toggle');
    const mobileToggle = document.getElementById('sidebar-toggle-mobile');
    const expanded = desktop ? !sidebarState.collapsed : sidebarState.mobileOpen;
    desktopToggle?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    mobileToggle?.setAttribute('aria-expanded', sidebarState.mobileOpen ? 'true' : 'false');
}

function closeMobileSidebar() {
    if (!sidebarState.mobileOpen) return;
    sidebarState.mobileOpen = false;
    applySidebarState();
}

function toggleSidebar() {
    if (isDesktopSidebarMode()) {
        sidebarState.collapsed = !sidebarState.collapsed;
        persistSidebarPreference();
    } else {
        sidebarState.mobileOpen = !sidebarState.mobileOpen;
    }
    applySidebarState();
}

function initSidebarToggle() {
    if (sidebarToggleInitialized) return;

    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebarToggle = document.getElementById('sidebar-toggle-mobile');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    if (!sidebarToggle && !mobileSidebarToggle) return;

    sidebarToggleInitialized = true;

    sidebarToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        toggleSidebar();
    });

    mobileSidebarToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        toggleSidebar();
    });

    sidebarOverlay?.addEventListener('click', closeMobileSidebar);

    window.addEventListener('resize', () => {
        if (isDesktopSidebarMode()) {
            sidebarState.mobileOpen = false;
        }
        applySidebarState();
    });
}

function shouldStripInlineColor(value) {
    return /#|rgb\(|hsl\(|oklch\(/i.test(String(value || ''));
}

function stripHardcodedInlineColors(root = document) {
    const scope = root instanceof Element ? root : document.body;
    if (!scope) return;

    const targets = [];
    if (scope.hasAttribute && scope.hasAttribute('style')) {
        targets.push(scope);
    }
    targets.push(...scope.querySelectorAll('[style]'));

    const colorProps = [
        'color',
        'background',
        'background-color',
        'border',
        'border-top',
        'border-right',
        'border-bottom',
        'border-left',
        'border-color',
        'border-top-color',
        'border-right-color',
        'border-bottom-color',
        'border-left-color'
    ];

    targets.forEach((el) => {
        let changed = false;
        colorProps.forEach((prop) => {
            const value = el.style.getPropertyValue(prop);
            if (value && shouldStripInlineColor(value)) {
                el.style.removeProperty(prop);
                changed = true;
            }
        });

        if (changed && !el.getAttribute('style')?.trim()) {
            el.removeAttribute('style');
        }
    });
}

function initStyleGuard() {
    if (styleGuardObserver || !document.body) return;

    stripHardcodedInlineColors(document.body);

    styleGuardObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes' && mutation.target instanceof Element) {
                stripHardcodedInlineColors(mutation.target);
            }

            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach((node) => {
                    if (node instanceof Element) {
                        stripHardcodedInlineColors(node);
                    }
                });
            }
        });
    });

    styleGuardObserver.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['style']
    });
}

// Replace ensureSettingsPopover() in app.js
function ensureSettingsPopover() {
    if (settingsPopover) return settingsPopover;

    settingsPopover = document.createElement('div');
    settingsPopover.id = 'settings-popover';
    settingsPopover.className = 'settings-popover hidden';
    
    // New HTML structure matching the mockup
    settingsPopover.innerHTML = `
        <div class="settings-popover-header">
            <div class="sph-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Settings
            </div>
            <button type="button" class="sph-close" id="settings-close-btn">×</button>
        </div>
        <div class="settings-popover-body">
            <div class="setting-row">
                <label><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 7 4 4 20 4 20 7"></polyline><line x1="9" y1="20" x2="15" y2="20"></line><line x1="12" y1="4" x2="12" y2="20"></line></svg> Font Size</label>
                <div class="font-size-toggles" id="settings-font-toggles">
                    <button type="button" data-size="small">Small</button>
                    <button type="button" data-size="default" class="active">Medium</button>
                    <button type="button" data-size="large">Large</button>
                </div>
                <div class="font-preview" id="settings-font-preview">15px — preview text</div>
            </div>
            <div class="setting-row">
                <label for="settings-mic-input"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg> Microphone Input</label>
                <div class="select-wrapper">
                    <select id="settings-mic-input">
                        <option value="">Default Microphone</option>
                    </select>
                </div>
            </div>
            <div class="setting-row">
                <label for="settings-audio-output"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg> Audio Output</label>
                <div class="select-wrapper">
                    <select id="settings-audio-output">
                        <option value="">Default Speaker</option>
                    </select>
                </div>
            </div>
            <button type="button" id="settings-signout" class="settings-signout-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Sign Out
            </button>
        </div>
    `;

    document.body.appendChild(settingsPopover);
    return settingsPopover;
}

// Replace setSettingsPopoverPosition() in app.js
function setSettingsPopoverPosition() {
    if (!settingsPopover || settingsPopover.classList.contains('hidden') || !settingsBtn) return;
    const rect = settingsBtn.getBoundingClientRect();
    
    const popoverWidth = 260; // Fixed width to match mockup
    let left = rect.left + rect.width / 2 - popoverWidth / 2;
    left = Math.max(16, Math.min(left, window.innerWidth - popoverWidth - 16)); // Keep on screen
    
    settingsPopover.style.width = `${popoverWidth}px`;
    settingsPopover.style.left = `${left}px`;
    
    // CRITICAL FIX: Position it ABOVE the gear icon instead of below
    const popoverHeight = settingsPopover.offsetHeight;
    const top = rect.top - popoverHeight - 12; // 12px gap above the button
    
    settingsPopover.style.top = `${top}px`;
}

async function refreshAudioDeviceOptions() {
    const popover = ensureSettingsPopover();
    const micSelect = popover.querySelector('#settings-mic-input');
    const outputSelect = popover.querySelector('#settings-audio-output');
    if (!micSelect || !outputSelect) return;

    micSelect.innerHTML = '<option value="">System default</option>';
    outputSelect.innerHTML = '<option value="">System default</option>';

    if (!navigator.mediaDevices?.enumerateDevices) return;

    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        let hasMic = false;
        let hasOutput = false;

        devices.forEach((device, index) => {
            const label = device.label || `${device.kind} ${index + 1}`;
            if (device.kind === 'audioinput') {
                hasMic = true;
                const option = document.createElement('option');
                option.value = device.deviceId;
                option.textContent = label;
                micSelect.appendChild(option);
            }
            if (device.kind === 'audiooutput') {
                hasOutput = true;
                const option = document.createElement('option');
                option.value = device.deviceId;
                option.textContent = label;
                outputSelect.appendChild(option);
            }
        });

        if (!hasMic) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No mic devices found';
            micSelect.appendChild(option);
        }

        if (!hasOutput) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No output devices found';
            outputSelect.appendChild(option);
        }
    } catch (error) {
        console.warn('Unable to enumerate audio devices:', error);
    }

    micSelect.value = uiSettings.micDeviceId || '';
    outputSelect.value = uiSettings.audioOutputId || '';
}

function closeSettingsPopover() {
    if (settingsPopover) {
        settingsPopover.classList.add('hidden');
    }
}

async function toggleSettingsPopover() {
    const popover = ensureSettingsPopover();
    const isHidden = popover.classList.contains('hidden');
    if (!isHidden) {
        closeSettingsPopover();
        return;
    }

    popover.classList.remove('hidden');
    setSettingsPopoverPosition();
    await refreshAudioDeviceOptions();
}

function applyAudioOutputPreference(audioElement) {
    const sinkId = uiSettings.audioOutputId || '';
    if (!audioElement || !sinkId || typeof audioElement.setSinkId !== 'function') return;

    audioElement.setSinkId(sinkId).catch((error) => {
        console.warn('Unable to apply selected audio output:', error);
    });
}

// Replace initSettingsControls() in app.js
function initSettingsControls() {
    if (settingsPopoverInitialized) return;
    settingsPopoverInitialized = true;

    const popover = ensureSettingsPopover();
    
    // Font Size Toggles logic
    const fontBtns = popover.querySelectorAll('#settings-font-toggles button');
    const fontPreview = popover.querySelector('#settings-font-preview');
    
    function updateFontSelection(size) {
        fontBtns.forEach(btn => btn.classList.toggle('active', btn.dataset.size === size));
        let px = size === 'large' ? '18px' : (size === 'small' ? '14px' : '15px');
        if (fontPreview) fontPreview.textContent = `${px} — preview text`;
    }

    // Set initial state
    updateFontSelection(uiSettings.fontSize || 'default');

    fontBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const size = btn.dataset.size;
            uiSettings.fontSize = size;
            updateFontSelection(size);
            document.documentElement.dataset.fontScale = size; // Apply immediately
            persistUiSettings();
        });
    });

    // Inputs & Signout logic
    const micSelect = popover.querySelector('#settings-mic-input');
    const outputSelect = popover.querySelector('#settings-audio-output');
    const signOutBtn = popover.querySelector('#settings-signout');
    const closeBtn = popover.querySelector('#settings-close-btn');

    if (micSelect) {
        micSelect.addEventListener('change', () => {
            uiSettings.micDeviceId = micSelect.value || '';
            persistUiSettings();
            showToast('Microphone preference updated.', 'info');
        });
    }

    if (outputSelect) {
        outputSelect.addEventListener('change', () => {
            uiSettings.audioOutputId = outputSelect.value || '';
            persistUiSettings();
            showToast('Audio output preference updated.', 'info');
        });
    }

    signOutBtn?.addEventListener('click', async () => await handleLogout());
    closeBtn?.addEventListener('click', closeSettingsPopover);

    settingsBtn?.addEventListener('click', async (event) => {
        event.stopPropagation();
        await toggleSettingsPopover();
    });

    document.addEventListener('click', (event) => {
        if (!settingsPopover || settingsPopover.classList.contains('hidden')) return;
        const target = event.target;
        if (target instanceof Node && (settingsPopover.contains(target) || settingsBtn?.contains(target))) return;
        closeSettingsPopover();
    });

    window.addEventListener('resize', setSettingsPopoverPosition);
}

// ============================================================
// JSON FETCH HELPER
// ============================================================

async function fetchJson(url, options = {}) {
    const headers = new Headers(options.headers || {});
    let body = options.body;

    // Auto-detect content type
    if (body && typeof body === 'object' && !(body instanceof FormData) && !(body instanceof URLSearchParams) && !(body instanceof Blob) && !(body instanceof ArrayBuffer)) {
        if (!headers.has('Content-Type')) {
            headers.set('Content-Type', 'application/json; charset=utf-8');
        }
        body = JSON.stringify(body);
    } else if (body && typeof body === 'string' && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json; charset=utf-8');
    }

    // Ensure we accept JSON
    if (!headers.has('Accept')) {
        headers.set('Accept', 'application/json; charset=utf-8');
    }

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers,
        body
    });

    // Parse JSON response, handle errors
    let data;
    try {
        const text = await response.text();
        data = text ? JSON.parse(text) : {};
    } catch (error) {
        throw new Error(`Invalid JSON response: ${error.message}`);
    }

    if (!response.ok) {
        throw new Error(data.message || `Request failed (${response.status})`);
    }

    return data;
}

// ============================================================
// ASSIGNMENT API (Python Flask)
// ============================================================

const ASSIGNMENT_API_BASE = 'http://127.0.0.1:5000/api';

async function fetchAssignmentApi(endpoint, options = {}) {
    const url = `${ASSIGNMENT_API_BASE}${endpoint}`;
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    
    let body = options.body;
    if (body && typeof body === 'object' && !(body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
        body = JSON.stringify(body);
    }

    // Build URL with query params
    let finalUrl = url;
    
    // Auto-add teacher_id for teacher routes
    const teacherId = state.user?.teacher_id;
    const isTeacherRoute = endpoint.includes('/teacher/');

    if (isTeacherRoute && teacherId) {
        const separator = finalUrl.includes('?') ? '&' : '?';
        if (!finalUrl.includes('teacher_id=')) {
            finalUrl += `${separator}teacher_id=${teacherId}`;
        }
    } else if (isTeacherRoute && !teacherId) {
        console.warn('Teacher route called without teacher_id:', endpoint);
    }
    
    const response = await fetch(finalUrl, {
        credentials: 'same-origin',
        ...options,
        headers,
        body
    });
    
    const data = await response.json();
    if (!response.ok || !data.success) {
        throw new Error(data.message || `Assignment API error (${response.status})`);
    }
    return data;
}

function ensureToastRoot() {
    let root = document.getElementById('toast-root');
    if (!root) {
        root = document.createElement('div');
        root.id = 'toast-root';
        root.className = 'toast-stack';
        document.body.appendChild(root);
    }
    return root;
}

function inferToastTone(message) {
    const text = String(message || '').toLowerCase();
    if (text.includes('<span class="icon-error" aria-hidden="true"><svg>...</svg></span>') || text.includes('success')) return 'success';
    if (text.includes('<span class="icon-error" aria-hidden="true"><svg>...</svg></span>') || text.includes('failed') || text.includes('error')) return 'error';
    return 'info';
}

function showToast(message, tone = 'info', timeout = 3200) {
    if (!document.body) {
        nativeAlert(message);
        return;
    }

    const root = ensureToastRoot();
    const toast = document.createElement('div');
    toast.className = `toast toast-${tone}`;
    toast.textContent = String(message || '');
    root.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.add('toast-visible');
    });

    const dismiss = () => {
        toast.classList.remove('toast-visible');
        window.setTimeout(() => {
            toast.remove();
            if (!root.childElementCount) {
                root.remove();
            }
        }, 180);
    };

    const timerId = window.setTimeout(dismiss, timeout);
    toast.addEventListener('click', () => {
        window.clearTimeout(timerId);
        dismiss();
    });
}

window.alert = (message) => {
    showToast(message, inferToastTone(message));
};

function ensureFieldHint(field) {
    if (!(field instanceof HTMLElement) || !field.parentElement) return null;

    let hint = field.parentElement.querySelector('.field-hint[data-for-field]');
    if (!hint) {
        hint = document.createElement('div');
        hint.className = 'field-hint';
        hint.dataset.forField = 'true';
        field.insertAdjacentElement('afterend', hint);
    }
    return hint;
}

function setFieldError(field, message) {
    if (!(field instanceof HTMLElement)) return;
    field.classList.add('field-invalid');
    field.setAttribute('aria-invalid', 'true');
    const hint = ensureFieldHint(field);
    if (hint) {
        hint.textContent = message;
        hint.classList.add('field-hint-error');
    }
}

function clearFieldError(field) {
    if (!(field instanceof HTMLElement)) return;
    field.classList.remove('field-invalid');
    field.removeAttribute('aria-invalid');
    const hint = ensureFieldHint(field);
    if (hint) {
        hint.textContent = '';
        hint.classList.remove('field-hint-error');
    }
}

function clearFormErrors(form) {
    form?.querySelectorAll('.field-invalid').forEach((field) => clearFieldError(field));
}

function setupModalFocusTrap(modal) {
    if (!(modal instanceof HTMLElement)) return;

    const selector = [
        'button:not([disabled])',
        'a[href]',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    const handler = (event) => {
        if (event.key !== 'Tab') return;

        const focusable = Array.from(modal.querySelectorAll(selector)).filter((node) => {
            return node instanceof HTMLElement && !node.hasAttribute('hidden');
        });

        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (event.shiftKey && active === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    };

    modal._focusTrapHandler = handler;
    modal.addEventListener('keydown', handler);

    const initialFocus = modal.querySelector(selector);
    if (initialFocus instanceof HTMLElement) {
        window.setTimeout(() => initialFocus.focus(), 0);
    }
}

function teardownModalFocusTrap(modal) {
    if (!(modal instanceof HTMLElement)) return;
    if (modal._focusTrapHandler) {
        modal.removeEventListener('keydown', modal._focusTrapHandler);
        delete modal._focusTrapHandler;
    }
}

function ensureConfirmModal() {
    let modal = document.getElementById('confirm-modal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'confirm-modal';
    modal.className = 'modal hidden';
    modal.style.zIndex = '13000';
    modal.innerHTML = `
        <div class="modal-content modal-content-compact">
            <div class="modal-header">
                <h3 id="confirm-modal-title">Please Confirm</h3>
                <button type="button" class="close-modal" id="confirm-modal-close"><span class="icon-error" aria-hidden="true"><svg>...</svg></span></button>
            </div>
            <p id="confirm-modal-message" class="confirm-message"></p>
            <div class="modal-actions">
                <button type="button" id="confirm-modal-cancel" class="btn-secondary">Cancel</button>
                <button type="button" id="confirm-modal-confirm" class="btn-danger">Confirm</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    const cancel = () => resolveConfirm(false);
    modal.querySelector('#confirm-modal-close')?.addEventListener('click', cancel);
    modal.querySelector('#confirm-modal-cancel')?.addEventListener('click', cancel);
    modal.querySelector('#confirm-modal-confirm')?.addEventListener('click', () => resolveConfirm(true));
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            resolveConfirm(false);
        }
    });

    return modal;
}

function resolveConfirm(value) {
    const modal = document.getElementById('confirm-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }

    const resolver = modalRuntime.confirmResolver;
    modalRuntime.confirmResolver = null;
    if (resolver) {
        resolver(value);
    }
}

function showConfirm(message, options = {}) {
    const modal = ensureConfirmModal();
    const title = options.title || 'Please Confirm';
    const confirmLabel = options.confirmLabel || 'Confirm';
    const cancelLabel = options.cancelLabel || 'Cancel';
    const confirmTone = options.confirmTone || 'danger';

    const titleEl = modal.querySelector('#confirm-modal-title');
    const messageEl = modal.querySelector('#confirm-modal-message');
    const confirmBtn = modal.querySelector('#confirm-modal-confirm');
    const cancelBtn = modal.querySelector('#confirm-modal-cancel');

    if (titleEl) titleEl.textContent = title;
    if (messageEl) messageEl.textContent = message;
    if (confirmBtn) {
        confirmBtn.textContent = confirmLabel;
        confirmBtn.className = confirmTone === 'primary' ? 'btn-primary' : 'btn-danger';
    }
    if (cancelBtn) cancelBtn.textContent = cancelLabel;

    modal.classList.remove('hidden');
    modal.style.display = 'flex';

    return new Promise((resolve) => {
        modalRuntime.confirmResolver = resolve;
    });
}

async function requestCloseModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return false;

    if (modal.dataset.dirty === 'true') {
        const shouldDiscard = await showConfirm(
            modal.dataset.dirtyMessage || 'Discard unsaved changes?',
            {
                title: 'Unsaved Changes',
                confirmLabel: 'Discard Changes',
                confirmTone: 'danger'
            }
        );
        if (!shouldDiscard) {
            return false;
        }
    }

    closeModal(modalId);
    return true;
}

function getTopVisibleModal() {
    const confirmModal = document.getElementById('confirm-modal');
    if (confirmModal && !confirmModal.classList.contains('hidden')) {
        return confirmModal;
    }

    const visible = Array.from(document.querySelectorAll('.modal')).filter((modal) => !modal.classList.contains('hidden'));
    return visible[visible.length - 1] || null;
}

function showLogin() {
    loginView.classList.remove('hidden');
    appShell.classList.add('hidden');
    sidebarState.mobileOpen = false;
    applySidebarState();
    closeSettingsPopover();
    document.body.className = '';
}

function showApp() {
    loginView.classList.add('hidden');
    appShell.classList.remove('hidden');
    applyFontSizeSetting();
    initStyleGuard();
    initSettingsControls();
    initSidebarToggle();
    applySidebarState();
    
    const user = state.user;
    if (user) {
        userBadge.textContent = user.username || 'User';
        if (roleBadge) roleBadge.textContent = user.role || 'Guest';
        
        // Apply role theme - principal gets premium interface
        const isAdmin = user.role === 'principal' || user.role === 'admin';
        document.body.classList.toggle('principal-mode', isAdmin);
        document.body.className = 'role-' + (user.role || '').toLowerCase();
        
        if (isAdmin) {
            document.body.classList.add('principal-mode');
        }
        
        renderTopbar();
    }
}

function setActiveView(viewName) {
    if (state.activeView === 'assessment' && viewName !== 'assessment') {
        cleanupAssessmentOnLeave();
    }

    state.activeView = viewName;
    if (!isDesktopSidebarMode()) {
        closeMobileSidebar();
    }
    document.querySelectorAll('.nav-btn').forEach((btn) => {
        btn.classList.toggle('active', btn.dataset.view === viewName);
    });
    renderView();
}

// ============================================================
// 3. VIEW ROUTING & NAVIGATION
// ============================================================

function renderNavigation() {
    if (!navContainer) return;
    const role = String(state.user?.role || '').toLowerCase();

    const iconByKey = {
        dashboard: {
            inactive: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 4 8 6.7v8.3h-5.2v-5.6H9.2V19H4v-8.3L12 4Zm0-2-10 8.3V21h9.2v-5.6h1.6V21H22V10.3L12 2Z"/></svg>',
            active: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 10.5 12 3l9 7.5v9.5a1 1 0 0 1-1 1h-5.5v-6h-5v6H4a1 1 0 0 1-1-1z"/></svg>'
        },
        teachers: {
            inactive: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16.5 13.5a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Zm0-2a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM7.5 13a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm0-2a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm9 3c3.2 0 6 1.9 6 4.5V22h-2v-3.5c0-1.2-1.7-2.5-4-2.5s-4 1.3-4 2.5V22h-2v-3.5c0-2.6 2.8-4.5 6-4.5ZM7.5 14c1 0 2 .2 2.8.6-.8.8-1.3 1.8-1.3 2.9V22h-2v-4.5c0-1.3-1.8-2.5-4-2.5V13c1.8 0 3.3.4 4.5 1Z"/></svg>',
            active: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16.5 12.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-9 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm9 2.5c-2.7 0-5 1.6-5 3.5V21h10v-2.5c0-1.9-2.3-3.5-5-3.5ZM7.5 14c-2.5 0-4.5 1.4-4.5 3.2V21h6v-2.3c0-1 .4-1.9 1.1-2.7A5.9 5.9 0 0 0 7.5 14Z"/></svg>'
        },
        students: {
            inactive: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 13a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-2a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm0 3c4.5 0 8 2.6 8 6v2H4v-2c0-3.4 3.5-6 8-6Zm0 2c-3.4 0-6 1.8-6 4h12c0-2.2-2.6-4-6-4Z"/></svg>',
            active: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-3.9 0-7 2.2-7 5v2h14v-2c0-2.8-3.1-5-7-5Z"/></svg>'
        },
        materials: {
            inactive: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M7.5 3H21v18H7.5A3.5 3.5 0 0 0 4 24V6.5A3.5 3.5 0 0 1 7.5 3Zm0 2A1.5 1.5 0 0 0 6 6.5v13.1c.5-.4 1.1-.6 1.8-.6H19V5H7.5Zm1.5 3h7v2H9V8Zm0 4h7v2H9v-2Z" transform="translate(0 -1)"/></svg>',
            active: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 4.5A2.5 2.5 0 0 1 7.5 2H20v17H7.5A2.5 2.5 0 0 0 5 21V4.5ZM4 6h1v13.5A1.5 1.5 0 0 1 3.5 21H3V7a1 1 0 0 1 1-1Zm5 1h8v2H9Zm0 4h8v2H9Z"/></svg>'
        },
        assessment: {
            inactive: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 2h9l4 4v16H6a3 3 0 0 1-3-3V5a3 3 0 0 1 3-3Zm0 2c-.6 0-1 .4-1 1v14c0 .6.4 1 1 1h11V7h-4V3.9L6 4Zm9 .9V6h1.1L15 4.9ZM8 10h8v2H8v-2Zm0 4h8v2H8v-2Zm0 4h5v2H8v-2Z"/></svg>',
            active: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 3h9l3 3v15H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm8 1.5V7h2.5L14 4.5ZM8 10h8v1.8H8Zm0 3.6h8v1.8H8Zm0 3.6h5v1.8H8Z"/></svg>'
        },
        assignments: {
            inactive: `<svg viewBox="0 0 24 24" ...><path d="M4 4h16v16H4V4zm2 2v12h12V6H6zm2 2h8v2H8V8zm0 4h8v2H8v-2zm0 4h5v2H8v-2z"/></svg>`,
            active: `<svg viewBox="0 0 24 24" ...><path d="M3 3h18v18H3V3zm2 2v14h14V5H5zm2 2h8v2H7V7zm0 4h8v2H7v-2zm0 4h5v2H7v-2z"/></svg>`
        },
        reports: {
            inactive: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 2h14a3 3 0 0 1 3 3v17h-6v-2h4V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v15h4v2H2V5a3 3 0 0 1 3-3Zm4 16h2V10H9v8Zm4 0h2V8h-2v10Zm4 0h2v-6h-2v6Z"/></svg>',
            active: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 3h14a2 2 0 0 1 2 2v14h-4v-2h2V5H5v14h2v2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm4 14h2V9H9v8Zm4 0h2V7h-2v10Zm4 0h2v-5h-2v5Z"/></svg>'
        }
    };

    const navByRole = {
        teacher: [
            { key: 'dashboard', label: 'Home' },
            { key: 'students', label: 'Students' },
            { key: 'materials', label: 'Library' },
            { key: 'assignments', label: 'Assignments' },
            { key: 'reports', label: 'Reports' }
        ],
        principal: [
            { key: 'dashboard', label: 'Home' },
            { key: 'teachers', label: 'Teachers' },
            { key: 'reports', label: 'Reports' }
        ],
        admin: [
            { key: 'dashboard', label: 'Home' },
            { key: 'teachers', label: 'Teachers' },
            { key: 'students', label: 'Students' },
            { key: 'materials', label: 'Library' },
            { key: 'reports', label: 'Reports' }
        ],
        student: [
            { key: 'dashboard', label: 'Home' },
            { key: 'assignments', label: 'Assignments' },
            { key: 'materials', label: 'Library' }
        ],
        parent: [
            { key: 'dashboard', label: 'Home' },
            { key: 'reports', label: 'Reports' }
        ]
    };

    const tabs = navByRole[role] || navByRole.teacher;

    navContainer.innerHTML = `
        ${tabs.map(tab => `
            <button class="nav-btn ${state.activeView === tab.key ? 'active' : ''}" data-view="${tab.key}" aria-label="${tab.label}" data-tooltip="${tab.label}">
                <span class="nav-icon nav-icon-inactive">${iconByKey[tab.key]?.inactive || ''}</span>
                <span class="nav-icon nav-icon-active">${iconByKey[tab.key]?.active || ''}</span>
                <span class="nav-label">${tab.label}</span>
            </button>
        `).join('')}
    `;

    navContainer.querySelectorAll('.nav-btn').forEach((btn) => {
        btn.addEventListener('click', () => setActiveView(btn.dataset.view));
    });
}

function renderTopbar() {
    const topbarContent = document.getElementById('topbar-content');
    if (!topbarContent) return;

    const user = state.user;
    const role = String(user?.role || '').toLowerCase();
    const activeView = state.activeView;
    const now = new Date();
    const dateText = now.toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
    const timeText = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });

    // Determine page title and subtitle based on active view
    let pageTitle = 'Dashboard';
    let pageSubtitle = '';

    switch (activeView) {
        case 'dashboard':
            if (role === 'teacher') {
                pageTitle = 'Dashboard';
                pageSubtitle = 'Your class overview and recent activity';
            } else if (role === 'principal' || role === 'admin') {
                pageTitle = 'School Dashboard';
                pageSubtitle = 'School-wide reading progress analytics';
            } else {
                pageTitle = 'Dashboard';
                pageSubtitle = 'Welcome to ArchiveVox';
            }
            break;
        case 'students':
            pageTitle = 'Students';
            pageSubtitle = 'Manage your students and track their reading progress';
            break;
        case 'teachers':
            pageTitle = 'Teachers';
            pageSubtitle = 'Manage school staff and their classes';
            break;
        case 'teacher-detail':
            pageTitle = 'Teacher Detail';
            pageSubtitle = 'Students and class information';
            break;
        case 'student-detail':
            pageTitle = 'Student Report';
            pageSubtitle = 'Individual performance overview';
            break;
        case 'materials':
            pageTitle = 'Library';
            pageSubtitle = 'Reading materials for assessments';
            break;
        case 'assessment':
            pageTitle = 'Assessment';
            pageSubtitle = 'Conduct reading fluency assessments';
            break;
        case 'reports':
            if (role === 'principal' || role === 'admin') {
                pageTitle = 'School Reports';
                pageSubtitle = 'School-wide reading progress analytics';
            } else if (role === 'teacher') {
                pageTitle = 'Class Reports';
                pageSubtitle = 'Your class performance analytics';
            } else {
                pageTitle = 'Reports';
                pageSubtitle = 'View your reading reports';
            }
            break;
        default:
            pageTitle = 'ArchiveVox';
            pageSubtitle = '';
    }

    topbarContent.innerHTML = `
        <div class="topbar-left">
            <div class="topbar-page-info">
                <h1 class="topbar-page-title" style="font-family: 'Inter', sans-serif; font-size: 20px; font-weight: 700; color: #0f172a;">${pageTitle}</h1>
                <p class="topbar-page-subtitle" style="font-size: 13px; color: #64748b; margin-top: 2px;">${dateText}</p>
            </div>
        </div>

        <div class="topbar-right" style="margin-left: auto;">
            <button class="icon-btn" style="border: 1px solid #e2e8f0; background: #fff; width: 40px; height: 40px; border-radius: 50%;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
            </button>
        </div>
    `;
}

function renderView() {
    renderTopbar();
    const role = String(state.user?.role || '').toLowerCase();

    if (state.activeView === 'dashboard') {
        if (role === 'teacher') {
            renderTeacherDashboard();
        } else if (role === 'principal' || role === 'admin') {
            loadPrincipalDashboard();
        } else if (role === 'student') {
            renderStudentDashboard();
        } else if (role === 'parent') {
            renderParentDashboard();
        } else {
            renderDefaultDashboard();
        }
        return;
    }

    if (state.activeView === 'students') {
        renderStudents();
        loadStudents();
        return;
    }

    if (state.activeView === 'teachers') {
        renderTeachers();
        loadTeachers();
        return;
    }

    if (state.activeView === 'materials') {
        renderMaterials();
        loadMaterials().then(() => {
            if (state.activeView !== 'materials') return;
            if (isModalVisible('upload-modal') || isModalVisible('view-material-modal')) return;
            renderMaterials();
        });
        return;
    }

    if (state.activeView === 'assessment') {
        renderAssessment();
        return;
    }

    if (state.activeView === 'reports') {
        renderReports();
        return;
    }

    if (state.activeView === 'teacher-detail') {
    renderTeacherDetail(state.selectedTeacherId);
    return;
    }

    if (state.activeView === 'assignments') {
    renderTeacherAssignments();
    return;
    }

    if (state.activeView === 'student-detail') {
        renderStudentDetail(state.selectedStudentId);
        return;
    }

    if (state.activeView === 'teacher-edit') {
        renderTeacherEdit(state.selectedTeacherId);
        return;
    }

    if (state.activeView === 'assignment-detail') {
    renderAssignmentDetail(state.selectedAssignmentId);
    return;
    }

    renderDefaultDashboard();
}

function renderTeacherDashboard() {
    const dashboard = state.dashboard || {};
    const recent = Array.isArray(dashboard.recent_assessments) ? dashboard.recent_assessments : [];
    const classPerf = Array.isArray(dashboard.class_performance) ? dashboard.class_performance : [];
    const recentStudents = Array.isArray(dashboard.recent_students) ? dashboard.recent_students : [];
    
    // Calculate real stats from database with safe defaults
    const myStudents = Number(dashboard.my_students || 0);
    const classAvgWcpm = Number(dashboard.class_avg_wcpm || 0);
    const myAssessments = Number(dashboard.my_assessments || 0);
    const studentsBelow = Number(dashboard.students_below || 0);
    
    // New stats for polished UI
    const avgAccuracy = Number(dashboard.avg_accuracy || 0); 
    const totalMaterials = state.materials ? state.materials.length : 0;
    
    // Check if we have performance data
    const hasPerformanceData = classPerf.length > 0;
    
    viewContainer.innerHTML = `
        <div class="dashboard-teacher mobile-polished">

            <!-- NEW POLISHED 2x2 STATS GRID -->
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-icon bg-blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div class="stat-text">
                        <span class="stat-title">My Students</span>
                        <span class="stat-val">${myStudents}</span>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon bg-purple">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path></svg>
                    </div>
                    <div class="stat-text">
                        <span class="stat-title">My Materials</span>
                        <span class="stat-val">${totalMaterials}</span>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon bg-green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    </div>
                    <div class="stat-text">
                        <span class="stat-title">Assessments</span>
                        <span class="stat-val">${myAssessments}</span>
                    </div>
                </div>
                <div class="stat-box">
                    <div class="stat-icon bg-orange">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                    </div>
                    <div class="stat-text">
                        <span class="stat-title">Avg. Accuracy</span>
                        <span class="stat-val">${avgAccuracy}<span class="stat-unit">%</span></span>
                    </div>
                </div>
            </div>

            <!-- TWO COLUMN LAYOUT: Search Panel & Recent Assessments -->
            <div class="grid two">
                <!-- PRESERVED Left Panel: Search Student -->
                <div class="panel">
                    <p class="u-text-muted u-mt-0">Search by LRN or student name</p>
                    <div class="u-inline-form-row">
                        <input type="text" id="student-id-input" placeholder="Enter LRN or name" class="input-inline-fill">
                        <button id="search-student-btn" class="btn-primary btn-inline">
                            <span class="icon-search" aria-hidden="true">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                </svg>
                            </span>
                            Search
                        </button>
                    </div>
                    <div id="student-search-result" class="panel search-result-panel hidden">
                        <div><strong id="found-student-name"></strong></div>
                        <div class="u-text-muted-sm u-mt-4">
                            Grade: <span id="found-student-grade">-</span> | Section: <span id="found-student-section">-</span>
                        </div>
                    </div>
                </div>

                <!-- NEW POLISHED Right Panel: Recent Assessments -->
                <div class="recent-assessments-panel">
                    <div class="panel-header u-mb-0" style="padding-bottom: 8px;">
                        <h3 class="panel-heading" style="margin:0;">Recent Assessments</h3>
                        ${recent.length > 0 ? `<button class="btn-secondary btn-sm view-all-btn" data-view="reports" style="border:none; padding:4px 8px; font-size:13px;">View All</button>` : ''}
                    </div>
                    <div class="recent-list">
                        ${recent.length ? recent.slice(0, 4).map(item => {
                            // Extract initials from student name
                            const initials = (item.student_name || 'S').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
                            const acc = Number(item.accuracy_percentage || 0);
                            const accColor = acc >= 80 ? 'text-green' : (acc >= 60 ? 'text-orange' : 'text-red');
                            const dateStr = item.assessed_at ? new Date(item.assessed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
                            
                            return `
                            <div class="recent-item">
                                <div class="avatar">${initials}</div>
                                <div class="item-info">
                                    <div class="item-name">${escapeAssessmentHtml(item.student_name || 'Student')}</div>
                                    <div class="item-mat">${escapeAssessmentHtml(item.material_title || 'Material')}</div>
                                </div>
                                <div class="item-metrics">
                                    <div class="item-acc ${accColor}">${acc.toFixed(0)}%</div>
                                    <div class="item-date">${dateStr}</div>
                                </div>
                            </div>
                            `;
                        }).join('') : `<p class="u-text-muted u-text-center u-py-20">No assessments yet.</p>`}
                    </div>
                </div>
            </div>

            <!-- PRESERVED LOWER PANELS (Warning, Chart, Student Table) -->
            ${studentsBelow > 0 ? `
            <div class="panel status-callout warning u-mt-16">
                <strong>Need Attention (${studentsBelow} students)</strong>
                <p class="u-mt-6 u-mb-0 u-text-md">Some students are below their target reading level.</p>
            </div>
            ` : ''}

            <!-- Class Performance Chart Section -->
            <div class="panel u-mt-16">
                <div class="panel-header">
                    <h3>Class Performance</h3>
                    <span class="chart-subtitle">Student reading fluency progress</span>
                </div>
                <div class="chart-container chart-h-300">
                    ${hasPerformanceData ? `
                        <canvas id="classPerformanceChart"></canvas>
                    ` : `
                        <div class="empty-state u-text-center u-py-20">
                            <p class="u-text-muted">No performance data available yet.</p>
                            <p class="u-text-muted-xs">Complete assessments to see class performance trends.</p>
                        </div>
                    `}
                </div>
            </div>

            <!-- Student Overview Table -->
            <div class="panel u-mt-16">
                <div class="panel-header">
                    <h3>Student Overview</h3>
                    <button id="view-all-students-btn" class="btn-secondary btn-sm">View All →</button>
                </div>
                ${recentStudents.length ? `
                    <div class="u-scroll-x">
                        <table class="table-clean">
                            <thead>
                                <tr class="table-head-accent">
                                    <th class="u-ta-left">Student</th>
                                    <th class="u-ta-left">LRN</th>
                                    <th class="u-ta-center">Grade</th>
                                    <th class="u-ta-center">Assessments</th>
                                    <th class="u-ta-center">Avg WCPM</th>
                                    <th class="u-ta-center">Latest Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${recentStudents.slice(0, 10).map(student => `
                                    <tr class="table-row">
                                        <td class="u-ta-left"><strong>${escapeAssessmentHtml((student.first_name || '') + ' ' + (student.last_name || ''))}</strong></td>
                                        <td class="u-ta-left">${escapeAssessmentHtml(student.lrn || 'N/A')}</td>
                                        <td class="u-ta-center">${escapeAssessmentHtml(student.grade_level || 'N/A')}</td>
                                        <td class="u-ta-center">${student.assessment_count || 0}</td>
                                        <td class="u-ta-center">${Number(student.avg_wcpm || 0).toFixed(1)}</td>
                                        <td class="u-ta-center">
                                        ${(() => {
                                            const rawLvl = student.reading_level || 'Pending';
                                            let lvl = rawLvl;
                                            let badgeClass = 'status-default'; // Gray
                                            const lowerLvl = rawLvl.toLowerCase();

                                            if (lowerLvl.includes('reading at grade level') || lowerLvl === 'grade level') {
                                                lvl = 'Reading At Grade Level';
                                                badgeClass = 'status-success'; // Green
                                            } 
                                            else if (lowerLvl.includes('transitioning')) {
                                                lvl = 'Transitioning Reader';
                                                badgeClass = 'status-info'; // Blue
                                            } 
                                            else if (lowerLvl.includes('developing')) {
                                                lvl = 'Developing Reader';
                                                badgeClass = 'status-warning'; // Yellow
                                            } 
                                            else if (lowerLvl.includes('high emerging')) {
                                                lvl = 'High Emerging Reader';
                                                badgeClass = 'status-orange'; // Orange
                                            } 
                                            else if (lowerLvl.includes('low emerging') || lowerLvl.includes('emerging') || lowerLvl === 'frustration') {
                                                lvl = 'Low Emerging Reader';
                                                badgeClass = 'status-danger'; // Red
                                            }

                                            return `<span class="status-badge ${badgeClass}">${escapeAssessmentHtml(lvl)}</span>`;
                                        })()}
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                ` : `
                    <div class="empty-state u-text-center u-py-20">
                        <p class="u-text-muted">No students added yet.</p>
                        <p class="u-text-muted-xs">Add students to track their reading progress.</p>
                    </div>
                `}
            </div>
        </div>
    `;

    // 1. PRESERVED EXISTING EVENT LISTENERS
    document.getElementById('search-student-btn')?.addEventListener('click', searchStudent);
    document.getElementById('start-assessment-btn')?.addEventListener('click', startAssessment);
    document.getElementById('view-all-students-btn')?.addEventListener('click', () => setActiveView('students'));
    document.querySelector('.view-all-btn')?.addEventListener('click', () => setActiveView('reports'));
    // Allow pressing "Enter" in the search box
    document.getElementById('student-id-input')?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') searchStudent();
    });
    // 2. NEW HERO BUTTON EVENT LISTENER
    document.getElementById('hero-start-assessment-btn')?.addEventListener('click', () => {
        resetAssessmentState();
        assessmentState.step = 1;
        setActiveView('assessment');
    });

    // 3. PRESERVED CHART INITIALIZATION
    if (hasPerformanceData && typeof Chart !== 'undefined') {
        setTimeout(() => {
            initClassPerformanceChart(classPerf);
        }, 200);
    }
}

async function renderTeacherAssignments() {
    viewContainer.innerHTML = `
        <div class="assignments-shell">
            <div class="assignments-toolbar">
                <h2>📋 My Assignments</h2>
                <button id="create-assignment-btn" class="btn-primary">+ New Assignment</button>
            </div>
            <div id="assignments-list" class="u-mt-16">
                <p class="u-text-muted">Loading assignments...</p>
            </div>
        </div>

        <!-- Create/Edit Assignment Modal -->
        <div id="assignment-modal" class="modal hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="assignment-modal-title">New Assignment</h3>
                    <button class="close-modal">×</button>
                </div>
                <form id="assignment-form">
                    <input type="hidden" id="assignment-id">
                    <div class="form-group">
                        <label>Assignment Title *</label>
                        <input type="text" id="assignment-title" required>
                    </div>
                    <div class="form-group">
                        <label>Instructions</label>
                        <textarea id="assignment-instructions" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Class *</label>
                        <select id="assignment-class" required>
                            <option value="">Select a class...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Material 1 *</label>
                        <select id="assignment-material-1" required></select>
                    </div>
                    <div class="form-group">
                        <label>Material 2 (optional)</label>
                        <select id="assignment-material-2"></select>
                    </div>
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" id="assignment-due">
                    </div>
                    <div class="modal-actions">
                        <button type="submit" id="assignment-save-btn">Create</button>
                        <button type="button" class="close-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    // Load assignments and populate the list
    await loadAssignmentList();

    // Event listeners
    document.getElementById('create-assignment-btn')?.addEventListener('click', () => {
        openAssignmentModal();
    });

    // Delegate click for edit/delete buttons (since they are added dynamically)
    document.getElementById('assignments-list')?.addEventListener('click', async (e) => {
        const target = e.target.closest('button');
        if (!target) return;

        if (target.classList.contains('edit-assignment')) {
            const id = target.dataset.id;
            await openAssignmentModal(id);
        } else if (target.classList.contains('delete-assignment')) {
            const id = target.dataset.id;
            const confirm = await showConfirm('Delete this assignment?', {
                title: 'Confirm Delete',
                confirmLabel: 'Delete',
                confirmTone: 'danger'
            });
            if (confirm) {
                await deleteAssignment(id);
                await loadAssignmentList();
            }
        }
    });

    // Close modal
    document.querySelectorAll('#assignment-modal .close-modal').forEach(btn => {
        btn.addEventListener('click', () => closeModal('assignment-modal'));
    });

    //Form
    document.getElementById('assignment-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const assignmentId = form.dataset.assignmentId;

        // Common fields (always present)
        const title = document.getElementById('assignment-title').value.trim();
        const instructions = document.getElementById('assignment-instructions').value.trim();
        const due_date = document.getElementById('assignment-due').value || null;

        // Start with base payload
        let payload = { title, instructions, due_date };

        // Only include class_id and materials when creating a NEW assignment
        if (!assignmentId) {
            const class_id = parseInt(document.getElementById('assignment-class').value) || 0;
            const materials = [
                document.getElementById('assignment-material-1').value,
                document.getElementById('assignment-material-2').value
            ].filter(id => id);

            if (!class_id) {
                showToast('Please select a class.', 'error');
                return;
            }
            if (!title || materials.length === 0) {
                showToast('Title and at least one material are required.', 'error');
                return;
            }

            payload.class_id = class_id;
            payload.materials = materials;
        } else {
            // Editing – only title, instructions, due_date are sent
            if (!title) {
                showToast('Title is required.', 'error');
                return;
            }
        }

        try {
            let result;
            if (assignmentId) {
                // PUT – update assignment
                result = await fetchAssignmentApi(`/teacher/assignments/${assignmentId}`, {
                    method: 'PUT',
                    body: payload
                });
            } else {
                // POST – create new assignment
                result = await fetchAssignmentApi('/teacher/assignments', {
                    method: 'POST',
                    body: payload
                });
            }
            if (result.success) {
                showToast(assignmentId ? 'Assignment updated!' : 'Assignment created!', 'success');
                closeModal('assignment-modal');
                await loadAssignmentList();
            }
        } catch (error) {
            showToast('Error: ' + error.message, 'error');
        }
    });
}

async function loadAssignmentList() {
    const container = document.getElementById('assignments-list');
    if (!container) return;

    const teacherId = state.user?.teacher_id;
    if (!teacherId) {
        container.innerHTML = '<p class="u-text-danger">Teacher ID not found.</p>';
        return;
    }

    container.innerHTML = `<div class="spinner">Loading assignments...</div>`;

    try {
        const data = await fetchAssignmentApi('/teacher/assignments');
        const assignments = data.assignments || [];

        if (!assignments.length) {
            container.innerHTML = `<p class="u-text-muted">No assignments created yet.</p>`;
            return;
        }

        container.innerHTML = `
            <div class="u-scroll-x">
                <table class="table-clean">
                    <thead>
                        <tr class="table-head-accent">
                            <th>Title</th>
                            <th>Materials</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${assignments.map(a => `
                            <tr>
                                <td><strong>${escapeAssessmentHtml(a.title)}</strong></td>
                                <td>${escapeAssessmentHtml(a.material_titles || '—')}</td>
                                <td>${a.due_date ? new Date(a.due_date).toLocaleDateString() : 'No due date'}</td>
                                <td><span class="status-badge ${a.status === 'assigned' ? 'active' : 'archived'}">${a.status}</span></td>
                                <td>
                                    <button class="action-btn view-assignment" data-id="${a.assignment_id}">View</button>
                                    <button class="action-btn edit-assignment" data-id="${a.assignment_id}">Edit</button>
                                    <button class="action-btn delete-assignment" data-id="${a.assignment_id}">Delete</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        // Attach view listeners
        container.querySelectorAll('.view-assignment').forEach(btn => {
            btn.addEventListener('click', () => viewAssignment(btn.dataset.id));
        });

    } catch (error) {
        container.innerHTML = `<p class="u-text-danger">${error.message}</p>`;
    }
}

// Open modal for create or edit
async function openAssignmentModal(assignmentId = null) {
    const modal = document.getElementById('assignment-modal');
    const form = document.getElementById('assignment-form');
    const titleInput = document.getElementById('assignment-title');
    const instructionsInput = document.getElementById('assignment-instructions');
    const materialSelect1 = document.getElementById('assignment-material-1');
    const materialSelect2 = document.getElementById('assignment-material-2');
    const classSelect = document.getElementById('assignment-class');
    const dueInput = document.getElementById('assignment-due');
    const saveBtn = document.getElementById('assignment-save-btn');
    const modalTitle = document.getElementById('assignment-modal-title');

    // Reset form fields (except class dropdown – populated later)
    titleInput.value = '';
    if (instructionsInput) instructionsInput.value = '';
    dueInput.value = '';
    materialSelect1.innerHTML = '<option value="">Loading materials...</option>';
    materialSelect2.innerHTML = '<option value="">Loading materials...</option>';
    classSelect.innerHTML = '<option value="">Loading classes...</option>';

    // Remove any existing "readonly" note from previous opens
    const parent = classSelect?.parentNode;
    const oldNote = parent?.querySelector('.field-readonly-note');
    if (oldNote) oldNote.remove();

    // Populate dropdowns
    await populateClassDropdown();
    await populateMaterialDropdowns(materialSelect1, materialSelect2);

    // If editing, fetch and fill assignment data
    if (assignmentId) {
        try {
            // Fetch assignment details (teacher_id auto-injected)
            const data = await fetchAssignmentApi(`/teacher/assignments/${assignmentId}`);
            const assignment = data.assignment;

            titleInput.value = assignment.title || '';
            if (instructionsInput) instructionsInput.value = assignment.instructions || '';
            dueInput.value = assignment.due_date ? assignment.due_date.slice(0, 10) : '';

            // Disable class and material selects for editing
            [classSelect, materialSelect1, materialSelect2].forEach(el => {
                if (el) el.disabled = true;
            });

            // Add a note explaining immutability
            const note = document.createElement('p');
            note.className = 'u-text-muted-xs u-mt-4 field-readonly-note';
            note.textContent = 'Class and materials cannot be changed after creation.';
            parent?.appendChild(note);

            // Set class
            if (classSelect) classSelect.value = assignment.class_id || '';

            // Set materials (1 or 2)
            const materials = assignment.materials || [];
            if (materials.length >= 1) materialSelect1.value = materials[0].material_id;
            if (materials.length >= 2) materialSelect2.value = materials[1].material_id;

            saveBtn.textContent = 'Update';
            modalTitle.textContent = 'Edit Assignment';
            form.dataset.assignmentId = assignmentId;

        } catch (error) {
            showToast('Failed to load assignment: ' + error.message, 'error');
            closeModal('assignment-modal');
            return;
        }
    } else {
        // Create mode – ensure selects are enabled
        [classSelect, materialSelect1, materialSelect2].forEach(el => {
            if (el) el.disabled = false;
        });

        form.dataset.assignmentId = '';
        saveBtn.textContent = 'Create';
        modalTitle.textContent = 'New Assignment';
    }

    showModal('assignment-modal');
}

async function populateClassDropdown() {
    const select = document.getElementById('assignment-class');
    if (!select) return;

    try {
        const data = await fetchAssignmentApi('/teacher/classes');
        const classes = data.classes || [];
        select.innerHTML = '<option value="">Select a class...</option>';
        classes.forEach(cls => {
            const option = document.createElement('option');
            option.value = cls.class_id;
            option.textContent = cls.display_name || `${cls.grade_level} - ${cls.section}`;
            select.appendChild(option);
        });
    } catch (error) {
        showToast('Failed to load classes: ' + error.message, 'error');
        select.innerHTML = '<option value="">Error loading classes</option>';
    }
}

async function populateMaterialDropdowns(select1, select2) {
    try {
        // Use the new /teacher/materials endpoint – teacher_id is auto‑injected
        const data = await fetchAssignmentApi('/teacher/materials');
        const materials = data.materials || [];
        const options = materials.map(m => 
            `<option value="${m.material_id}">${escapeAssessmentHtml(m.title)}</option>`
        ).join('');
        select1.innerHTML = `<option value="">Select material 1</option>${options}`;
        select2.innerHTML = `<option value="">Select material 2 (optional)</option>${options}`;
    } catch (error) {
        showToast('Failed to load materials: ' + error.message, 'error');
        // Optionally leave dropdowns empty or show a fallback
    }
}

// Populate material and student dropdowns
async function populateAssignmentDropdowns() {
    const materialSelect = document.getElementById('assignment-material');
    const studentSelect = document.getElementById('assignment-students');

    try {
        // Fetch materials
        const materials = await fetchAssignmentApi('/materials');
        materialSelect.innerHTML = '<option value="">Select a story...</option>';
        (materials.materials || []).forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = m.title;
            materialSelect.appendChild(opt);
        });

        // Fetch students (optional) – if you have a student list endpoint
        // We'll reuse your existing PHP endpoint? Or use Python API.
        // For now, we'll try to use the Python API if available, else fallback to PHP.
        try {
            const students = await fetchAssignmentApi('/students');
            studentSelect.innerHTML = '';
            (students.students || []).forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = `${s.first_name} ${s.last_name}`;
                studentSelect.appendChild(opt);
            });
        } catch (e) {
            // If no student endpoint, keep the select empty
            studentSelect.innerHTML = '<option value="">No students available</option>';
        }
    } catch (error) {
        showToast('Error loading materials: ' + error.message, 'error');
    }
}

// Delete assignment
async function deleteAssignment(id) {
    const confirmed = await showConfirm('Delete this assignment?', {
        title: 'Confirm Delete',
        confirmLabel: 'Delete',
        confirmTone: 'danger'
    });
    if (!confirmed) return;

    try {
        // teacher_id is auto-injected!
        await fetchAssignmentApi(`/teacher/assignments/${id}`, {
            method: 'DELETE'
        });
        showToast('Assignment archived.', 'info');
        await loadAssignmentList();
    } catch (error) {
        showToast('Delete failed: ' + error.message, 'error');
    }
}

async function viewAssignment(assignmentId) {
    try {
        // teacher_id is auto-injected!
        const data = await fetchAssignmentApi(`/teacher/assignments/${assignmentId}`);
        const assignment = data.assignment;
        
        // Store for detail view
        state.selectedAssignmentId = assignmentId;
        setActiveView('assignment-detail');
    } catch (error) {
        showToast('Error loading assignment: ' + error.message, 'error');
    }
}

async function renderAssignmentDetail(assignmentId) {
    viewContainer.innerHTML = `<div class="spinner">Loading assignment...</div>`;
    try {
        const assignmentData = await fetchAssignmentApi(`/teacher/assignments/${assignmentId}`);
        const resultsData = await fetchAssignmentApi(`/teacher/assignments/${assignmentId}/results`);
        const assignment = assignmentData.assignment;
        const results = resultsData.results || [];

        viewContainer.innerHTML = `
            <div class="assignment-detail-shell">
                <div class="u-row-between u-mb-16">
                    <button class="btn-secondary" id="back-to-assignments">← Back to Assignments</button>
                    <h2>${escapeAssessmentHtml(assignment.title)}</h2>
                </div>
                <div class="panel u-mb-16">
                    <p><strong>Instructions:</strong> ${escapeAssessmentHtml(assignment.instructions || '—')}</p>
                    <p><strong>Due:</strong> ${assignment.due_date ? new Date(assignment.due_date).toLocaleDateString() : 'No due date'}</p>
                    <p><strong>Materials:</strong> ${assignment.materials.map(m => escapeAssessmentHtml(m.material_title)).join(', ')}</p>
                </div>
                <div class="panel">
                    <h3>Student Progress</h3>
                    ${results.length ? `
                        <div class="u-scroll-x">
                            <table class="table-clean">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Reading Result</th>
                                        <th>Quiz Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${results.map(row => `
                                        <tr>
                                            <td>${escapeAssessmentHtml(row.first_name + ' ' + row.last_name)}</td>
                                            <td>
                                                ${row.reading_result ? `
                                                    Acc: ${safeNumber(row.reading_result.accuracy_percentage, 1)}%<br>
                                                    WCPM: ${safeNumber(row.reading_result.wcpm, 1)}
                                                ` : 'Not started'}
                                            </td>
                                            <td>
                                                ${row.quiz_result ? `
                                                    ${row.quiz_result.score}/${row.quiz_result.total_questions}
                                                    (${safeNumber(row.quiz_result.percentage, 1)}%)
                                                ` : 'Not taken'}
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : '<p class="u-text-muted">No student data yet.</p>'}
                </div>
            </div>
        `;

        document.getElementById('back-to-assignments').addEventListener('click', () => {
            state.activeView = 'assignments';
            renderTeacherAssignments();
        });
    } catch (error) {
        viewContainer.innerHTML = `<p class="u-text-danger">${error.message}</p>`;
    }
}

async function getAssignmentResults(assignmentId) {
    try {
        // teacher_id is auto-injected!
        const data = await fetchAssignmentApi(`/teacher/assignments/${assignmentId}/results`);
        return data.results || [];
    } catch (error) {
        showToast('Error loading results: ' + error.message, 'error');
        return [];
    }
}

function initClassPerformanceChart(data) {
    const ctx = document.getElementById('classPerformanceChart');
    if (!ctx) return;

    // Destroy existing chart instance
    if (state.chartInstances.classPerformance) {
        state.chartInstances.classPerformance.destroy();
        delete state.chartInstances.classPerformance;
    }

    // 1. Prepare data - map the time-series data from our backend
    const labels = data.map(item => {
        if (!item.date) return 'Unknown';
        // Format date nicely (e.g., "Sep 9")
        const d = new Date(item.date);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    });
    
    // 2. Read the new average keys from the PHP API
    const wcpmData = data.map(item => Number(item.avg_wcpm || 0).toFixed(1));
    const accuracyData = data.map(item => Number(item.avg_accuracy || 0).toFixed(1));

    state.chartInstances.classPerformance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Avg. WCPM',
                    data: wcpmData,
                    backgroundColor: 'rgba(74, 144, 217, 0.7)',
                    borderColor: '#4A90D9',
                    borderWidth: 1,
                    order: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Avg. Accuracy (%)',
                    data: accuracyData,
                    backgroundColor: 'rgba(52, 199, 89, 0.7)',
                    borderColor: '#34C759',
                    borderWidth: 1,
                    order: 2,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'WCPM'
                    },
                    grid: { color: 'rgba(148, 163, 184, 0.18)' }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    max: 100,
                    title: {
                        display: true,
                        text: 'Accuracy %'
                    },
                    grid: { display: false }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0
                    }
                }
            }
        }
    });
}

// ============================================================
// 3. VIEW ROUTING (Continued: Dashboard Search)
// ============================================================

async function searchStudent() {
    const input = document.getElementById('student-id-input');
    const resultPanel = document.getElementById('student-search-result');
    const nameEl = document.getElementById('found-student-name');
    const gradeEl = document.getElementById('found-student-grade');
    const sectionEl = document.getElementById('found-student-section');
    const searchBtn = document.getElementById('search-student-btn');

    if (!input || !resultPanel) return;

    const term = input.value.trim();
    if (!term) {
        resultPanel.classList.add('hidden');
        return;
    }

    try {
        // 1. Show loading state on the button
        const originalBtnText = searchBtn.innerHTML;
        searchBtn.innerHTML = 'Searching...';
        searchBtn.disabled = true;

        // 2. Call the PHP search endpoint we fixed earlier
        const response = await fetch(`php/api/shared/students.php?action=search&term=${encodeURIComponent(term)}`);
        const data = await response.json();

        // 3. Display the result
        if (data.success && data.students && data.students.length > 0) {
            const student = data.students[0]; // Grab the top match
            
            nameEl.textContent = `${student.first_name} ${student.last_name}`;
            gradeEl.textContent = student.grade_level || 'Unassigned';
            sectionEl.textContent = student.section || 'General';
            
            resultPanel.classList.remove('hidden');
            
            // Make the result card clickable!
            resultPanel.style.cursor = 'pointer';
            resultPanel.onclick = () => {
                state.selectedStudentId = student.student_id;
                setActiveView('student-detail'); 
            };
        } else {
            // Handle no results
            nameEl.textContent = 'No student found';
            gradeEl.textContent = '-';
            sectionEl.textContent = '-';
            
            resultPanel.classList.remove('hidden');
            resultPanel.style.cursor = 'default';
            resultPanel.onclick = null;
        }
    } catch (error) {
        console.error('Search error:', error);
        nameEl.textContent = 'Error searching. Try again.';
        resultPanel.classList.remove('hidden');
    } finally {
        // 4. Restore the button
        searchBtn.innerHTML = `
            <span class="icon-search" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
            </span>
            Search
        `;
        searchBtn.disabled = false;
    }
}

function startAssessment() {
    const input = document.getElementById('student-id-input');
    const studentTerm = (input?.value || '').trim();
    resetAssessmentState();
    assessmentState.step = 1;
    assessmentState.prefillSearchTerm = studentTerm;
    setActiveView('assessment');
}

// ============================================================
// 3. VIEW ROUTING (Continued: Dashboards)
// ============================================================

async function loadPrincipalDashboard() {
    try {
        const data = await fetchJson('php/api/modules/principal-dashboard.php');
        if (data.success && data.dashboard) {
            state.principalDashboard = data.dashboard;
            if (state.activeView === 'dashboard') {
                renderPrincipalDashboard();
            }
        }
    } catch (error) {
        console.error('Error loading principal dashboard:', error);
    }
}

function renderPrincipalDashboard() {
    // 1. DATA PREPARATION (Unchanged)
    const dashboard = state.principalDashboard || {
        total_students: 0,
        total_materials: 0,
        total_assessments: 0,
        avg_accuracy: 0,
        students_this_week: 0,
        materials_this_week: 0,
        assessments_this_month: 0,
        accuracy_trend: 0,
        students_below_target: 0,
        recent_assessments: [],
        weekly_activity: [],
        grade_performance: []
    };
    
    const recent = Array.isArray(dashboard.recent_assessments) ? dashboard.recent_assessments : [];
    const weekly = Array.isArray(dashboard.weekly_activity) ? dashboard.weekly_activity : [];
    const gradePerf = Array.isArray(dashboard.grade_performance) ? dashboard.grade_performance : [];
    const riskStudents = Array.isArray(dashboard.risk_students) ? dashboard.risk_students : [];
    const weeklyCount = weekly.reduce((sum, item) => sum + Number(item.count || 0), 0);
    
    const formatDisplayDate = (value) => {
        if (!value) return 'N/A';
        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    const studentsTrendText = `+${safeNumber(dashboard.students_this_week, 0)} this week`;
    const materialsTrendText = `${safeNumber(dashboard.materials_this_week, 0)} uploaded this week`;
    const assessmentsTrendText = `${safeNumber(dashboard.assessments_this_month, 0)} this month`;
    const accuracyTrendText = `${safeNumber(dashboard.accuracy_trend, 1)}% from last mo.`;

    // 2. GENERATE HTML BLOCKS OUTSIDE OF INNERHTML (Fixes the red IDE errors)
    const riskStudentsHtml = riskStudents.length ? riskStudents.slice(0, 5).map(student => {
        const fName = student.first_name || '';
        const lName = student.last_name || '';
        const initials = ((fName.charAt(0) || 'S') + (lName.charAt(0) || '')).toUpperCase();
        return `
        <div class="recent-item">
            <div class="avatar" style="background: #fef2f2; color: #ef4444;">${initials}</div>
            <div class="item-info">
                <div class="item-name">${fName} ${lName}</div>
                <div class="item-mat">Grade ${student.grade_level || 'N/A'}</div>
            </div>
            <div class="item-metrics">
                <span style="background: #fef2f2; color: #ef4444; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700;">${student.reading_level || student.grade_level || 'Frustration'}</span>
            </div>
        </div>
        `;
    }).join('') : '<p class="u-text-muted u-text-center u-py-20">No students flagged for intervention.</p>';

    const recentAssessmentsHtml = recent.length ? recent.slice(0, 5).map(item => {
        const fName = item.first_name || '';
        const lName = item.last_name || '';
        const studentName = item.student_name || (fName + ' ' + lName).trim() || 'Student';
        const initials = studentName.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
        const acc = Number(item.accuracy_percentage || 0);
        const accColor = acc >= 80 ? 'text-green' : (acc >= 60 ? 'text-orange' : 'text-red');
        
        return `
        <div class="recent-item">
            <div class="avatar">${initials}</div>
            <div class="item-info">
                <div class="item-name">${studentName}</div>
                <div class="item-mat">Fluency: ${safeNumber(item.fluency_score, 1)} WCPM</div>
            </div>
            <div class="item-metrics">
                <div class="item-acc ${accColor}">${acc.toFixed(0)}%</div>
                <div class="item-date">${formatDisplayDate(item.assessed_at || item.date)}</div>
            </div>
        </div>
        `;
    }).join('') : '<p class="u-text-muted u-text-center u-py-20">No assessments yet.</p>';


    // 3. APPLY UI (Cleanly interpolated)
    viewContainer.innerHTML = `
        <div class="principal-dashboard mobile-polished">
            
            <!-- Hero Card -->
            <div class="hero-assess-card" style="background: linear-gradient(135deg, #4f46e5 0%, #312e81 100%); box-shadow: 0 8px 24px rgba(79, 70, 229, 0.25);">
                <span class="hero-tag" style="color: #c7d2fe;">School Overview</span>
                <h2 class="hero-title">Principal Dashboard</h2>
                <p class="hero-desc">Monitor school-wide reading progress, track assessment activity, and identify students needing intervention.</p>
                <button id="hero-view-reports-btn" class="hero-btn" style="color: #4f46e5;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    View Full Reports
                </button>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-box" style="align-items: flex-start;">
                    <div class="stat-icon bg-blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                    </div>
                    <div class="stat-text">
                        <span class="stat-title">Total Students</span>
                        <span class="stat-val">${safeNumber(dashboard.total_students, 0)}</span>
                        <span style="font-size: 11px; color: #64748b; margin-top: 4px; line-height: 1.2;">${studentsTrendText}</span>
                    </div>
                </div>
                <div class="stat-box" style="align-items: flex-start;">
                    <div class="stat-icon bg-purple">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path></svg>
                    </div>
                    <div class="stat-text">
                        <span class="stat-title">Reading Materials</span>
                        <span class="stat-val">${safeNumber(dashboard.total_materials, 0)}</span>
                        <span style="font-size: 11px; color: #64748b; margin-top: 4px; line-height: 1.2;">${materialsTrendText}</span>
                    </div>
                </div>
                <div class="stat-box" style="align-items: flex-start;">
                    <div class="stat-icon bg-green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                    </div>
                    <div class="stat-text">
                        <span class="stat-title">Assessments Done</span>
                        <span class="stat-val">${safeNumber(dashboard.total_assessments, 0)}</span>
                        <span style="font-size: 11px; color: #64748b; margin-top: 4px; line-height: 1.2;">${assessmentsTrendText}</span>
                    </div>
                </div>
                <div class="stat-box" style="align-items: flex-start;">
                    <div class="stat-icon bg-orange">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                    </div>
                    <div class="stat-text">
                        <span class="stat-title">Avg. Accuracy</span>
                        <span class="stat-val">${safeNumber(dashboard.avg_accuracy, 0)}<span class="stat-unit">%</span></span>
                        <span style="font-size: 11px; color: #64748b; margin-top: 4px; line-height: 1.2;">
                            <span style="color: ${safeNumber(dashboard.accuracy_trend, 1) >= 0 ? '#10b981' : '#ef4444'}; font-weight: 700;">${safeNumber(dashboard.accuracy_trend, 1) >= 0 ? '↑' : '↓'}</span> ${accuracyTrendText}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Two Column Layout: Lists -->
            <div class="grid two">
                <div class="recent-assessments-panel" style="border-top: 4px solid #ef4444;">
                    <div class="panel-header u-mb-0" style="padding-bottom: 8px;">
                        <h3 class="panel-heading" style="margin:0; color: #ef4444; display: flex; align-items: center; gap: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                            At-Risk Students
                        </h3>
                    </div>
                    <div class="recent-list">
                        ${riskStudentsHtml}
                    </div>
                </div>

                <div class="recent-assessments-panel">
                    <div class="panel-header u-mb-0" style="padding-bottom: 8px;">
                        <h3 class="panel-heading" style="margin:0;">Recent Assessments</h3>
                    </div>
                    <div class="recent-list">
                        ${recentAssessmentsHtml}
                    </div>
                </div>
            </div>

            <!-- Charts Canvas -->
            <div class="principal-charts-row grid two u-mt-16">
                <div class="principal-chart-panel panel" style="padding: 24px;">
                    <div class="panel-header" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 24px;">
                        <h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0;">Performance by Grade Level</h3>
                        <span class="chart-subtitle" style="font-size: 13px; color: #64748b;">Accuracy · Fluency · Reading rate</span>
                    </div>
                    <div class="chart-container chart-h-250">
                        <canvas id="gradePerformanceChart"></canvas>
                    </div>
                </div>

                <div class="principal-chart-panel panel" style="padding: 24px;">
                    <div class="panel-header" style="flex-direction: column; align-items: flex-start; gap: 6px; margin-bottom: 24px;">
                        <h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0;">Weekly Activity</h3>
                        <span class="chart-subtitle" style="font-size: 13px; color: #64748b;">Assessments conducted per day</span>
                    </div>
                    <div class="chart-container chart-h-250">
                        <canvas id="weeklyActivityChart"></canvas>
                    </div>
                </div>
            </div>
            
        </div>
    `;

    // 4. EVENT LISTENERS
    document.getElementById('hero-view-reports-btn')?.addEventListener('click', () => {
        if (typeof setActiveView === 'function') setActiveView('reports');
    });

    setTimeout(() => {
        if (typeof initPrincipalCharts === 'function') {
            initPrincipalCharts(dashboard);
        }
    }, 200);
}

function initPrincipalCharts(data) {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js failed to load; principal dashboard charts are unavailable.');
        document.querySelectorAll('#gradePerformanceChart, #weeklyActivityChart').forEach((canvas) => {
            const container = canvas.parentElement;
            if (!container || container.querySelector('.chart-fallback')) {
                return;
            }

            canvas.style.display = 'none';
            const fallback = document.createElement('div');
            fallback.className = 'chart-fallback';
            fallback.textContent = 'Charts are unavailable right now.';
            container.appendChild(fallback);
        });
        return;
    }

    // Destroy existing charts
    if (state.chartInstances.principal) {
        Object.values(state.chartInstances.principal).forEach(chart => {
            if (chart && chart.destroy) chart.destroy();
        });
    }
    state.chartInstances.principal = {};

    // Grade Performance Chart
    const gradeCtx = document.getElementById('gradePerformanceChart');
    if (gradeCtx) {
        const gradeRows = Array.isArray(data.grade_performance) && data.grade_performance.length ? data.grade_performance : [
            { grade_level: 'Grade 2', accuracy: 0, fluency: 0, rate: 0 },
            { grade_level: 'Grade 3', accuracy: 0, fluency: 0, rate: 0 }
        ];
        const gradeLabels = gradeRows.map(item => item.grade_level || item.grade || 'Grade');
        const accuracyData = gradeRows.map(item => Number(item.accuracy || item.avg_accuracy || 0));
        const fluencyData = gradeRows.map(item => Number(item.fluency || item.avg_fluency || 0));
        const rateData = gradeRows.map(item => Number(item.rate || item.avg_rate || 0));

        state.chartInstances.principal.grade = new Chart(gradeCtx, {
            type: 'bar',
            data: {
                labels: gradeLabels,
                datasets: [
                    { label: 'Accuracy', data: accuracyData, backgroundColor: '#1e40af', borderColor: '#1e40af', borderWidth: 1 },
                    { label: 'Fluency', data: fluencyData, backgroundColor: '#60a5fa', borderColor: '#60a5fa', borderWidth: 1 },
                    { label: 'Reading Rate', data: rateData, backgroundColor: '#f59e0b', borderColor: '#f59e0b', borderWidth: 1 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: 'rgba(148, 163, 184, 0.18)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Weekly Activity Chart
    const weeklyCtx = document.getElementById('weeklyActivityChart');
    if (weeklyCtx) {
        const weeklyRows = Array.isArray(data.weekly_activity) && data.weekly_activity.length ? data.weekly_activity : [];
        const days = weeklyRows.length ? weeklyRows.map(item => item.day || '') : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        const values = weeklyRows.length ? weeklyRows.map(item => Number(item.count || 0)) : [0, 0, 0, 0, 0, 0, 0];
        const maxValue = Math.max(10, ...values) + 5;

        state.chartInstances.principal.weekly = new Chart(weeklyCtx, {
            type: 'line',
            data: {
                labels: days,
                datasets: [{
                    label: 'Assessments',
                    data: values,
                    borderColor: '#1e40af',
                    backgroundColor: '#1e40af',
                    pointBackgroundColor: '#1e40af',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    fill: false,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: maxValue,
                        grid: { color: 'rgba(148, 163, 184, 0.18)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }
}

// Add this function to fetch teacher_id from the backend
async function fetchTeacherId() {
    try {
        const data = await fetchJson('php/api/teacher/me.php');
        if (data.success && data.teacher_id) {
            return data.teacher_id;
        }
        return null;
    } catch (error) {
        console.error('Error fetching teacher_id:', error);
        return null;
    }
}

async function renderStudentDashboard() {
    const studentId = state.user?.student_id; // You'll need to store this on login
    if (!studentId) {
        viewContainer.innerHTML = `<div class="panel"><p class="u-text-muted">Student ID not found.</p></div>`;
        return;
    }

    try {
        const data = await fetchAssignmentApi(`/student/assignments?student_id=${studentId}`);
        const assignments = data.assignments || [];

        viewContainer.innerHTML = `
            <div class="panel">
                <h3>My Reading Assignments</h3>
                ${assignments.length ? `
                    <ul class="list">
                        ${assignments.map(a => `
                            <li class="assignment-list-item">
                                <strong>${escapeAssessmentHtml(a.title)}</strong>
                                <div class="u-text-muted-xs u-mt-4">
                                    Due: ${a.due_date ? new Date(a.due_date).toLocaleDateString() : 'No due date'}
                                    <span class="u-float-right">Status: ${a.status}</span>
                                </div>
                                <div class="u-mt-8">
                                    ${a.materials.map(m => `
                                        <span class="material-chip">${escapeAssessmentHtml(m.title)}</span>
                                    `).join(' ')}
                                </div>
                                <button class="btn-primary btn-sm u-mt-8 view-assignment-btn" data-id="${a.assignment_id}">View Details</button>
                            </li>
                        `).join('')}
                    </ul>
                ` : `
                    <p class="u-text-muted">No active assignments at the moment.</p>
                `}
            </div>
        `;

        document.querySelectorAll('.view-assignment-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                state.selectedAssignmentId = btn.dataset.id;
                // You can create a new view 'assignment-detail' or navigate to it
                setActiveView('assignment-detail');
            });
        });

    } catch (error) {
        viewContainer.innerHTML = `<div class="panel"><p class="u-text-danger">Error loading assignments: ${escapeAssessmentHtml(error.message)}</p></div>`;
    }
}

function renderParentDashboard() {
    viewContainer.innerHTML = `
        <div class="panel">
            <h3><span class="icon-people" aria-hidden="true"><svg>...</svg></span> Parent Dashboard</h3>
            <p>Welcome, ${state.user ? state.user.username : ''}!</p>
            <p>This dashboard will show:</p>
            <ul>
                <li>My Children</li>
                <li>Progress Overview</li>
            </ul>
            <p class="u-text-muted">Coming soon!</p>
        </div>
    `;
}

function renderDefaultDashboard() {
    viewContainer.innerHTML = `
        <div class="panel">
            <h3>Welcome to ArchiveVox!</h3>
            <p>Please login to access your dashboard.</p>
        </div>
    `;
}

// ============================================================
// 4. READING MATERIALS - Upload, Display, CRUD
// ============================================================

function renderMaterials() {
    const materials = state.materials || [];
    const today = new Date().toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });

    const getOcrState = (material) => {
        if ((material.ocr_text || '').trim()) return 'OCR Done';
        const rawStatus = (material.status || '').toLowerCase();
        if (rawStatus.includes('process')) return 'Processing';
        if (rawStatus.includes('archive')) return 'Archived';
        return 'Pending';
    };

    const getToken = (value) => String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    
    viewContainer.innerHTML = `
        <div class="materials-shell">
            
            <!-- HEADER MATCHING THE MOCKUP -->
            <div class="page-header u-mb-24">
                <div>
                    <h2 style="font-family: 'Inter', sans-serif; font-size: 24px; font-weight: 700; color: #0f172a;">Reading Materials</h2>
                    <p class="subtitle" style="margin-top: 4px; color: #64748b;">${today}</p>
                </div>
            </div>

            <!-- SEARCH & FILTERS ROW -->
            <div class="u-row-between u-mb-24" style="gap: 16px; flex-wrap: wrap;">
                <div style="position: relative; flex: 1; max-width: 500px;">
                    <span style="position: absolute; left: 12px; top: 10px; color: #94a3b8;">🔍</span>
                    <input type="text" id="search-material" placeholder="Search materials..." class="input-inline-fill" style="background: #fff; padding-left: 36px; width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; height: 40px; font-size: 14px;">
                </div>
                <div style="display: flex; gap: 12px;">
                    <select id="filter-language" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 0 16px; height: 40px; background: #fff; color: #475569; font-size: 14px; cursor: pointer;">
                        <option value="">All Languages</option>
                        <option value="English">English</option>
                        <option value="Filipino">Filipino</option>
                    </select>
                    <button id="upload-material-btn" class="btn-primary" style="background: #1e40af; display: flex; align-items: center; gap: 8px; height: 40px; padding: 0 16px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload Material
                    </button>
                    <button id="refresh-materials-btn" class="btn-secondary" style="height: 40px; padding: 0 12px;">↻</button>
                </div>
            </div>

            <!-- SLEEK CARD GRID -->
            <div class="grid three u-gap-20">
                ${materials && materials.length ? 
                    materials.map(material => {
                        const ocrState = getOcrState(material);
                        
                        // Dynamic Pill Styling
                        const lang = material.language || 'English';
                        const langStyle = lang === 'Filipino' 
                            ? 'color: #9333ea; background: #faf5ff; border: 1px solid #e9d5ff;'
                            : 'color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe;';
                            
                        let ocrStyle = 'color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0;';
                        let ocrIcon = '';
                        if (ocrState === 'OCR Done') {
                            ocrStyle = 'color: #16a34a; background: #f0fdf4; border: 1px solid #bbf7d0;';
                            ocrIcon = '✓ ';
                        } else if (ocrState === 'Processing') {
                            ocrStyle = 'color: #d97706; background: #fffbeb; border: 1px solid #fde68a;';
                            ocrIcon = '⏳ ';
                        }
                        
                        const uploadDate = material.upload_date ? new Date(material.upload_date).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}) : 'Recently added';
                        const uploader = material.uploaded_by_name || 'Teacher';

                        return `
                        <div class="material-card" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between; height: 100%;" onmouseover="this.style.boxShadow='0 10px 25px rgba(0,0,0,0.08)'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.boxShadow='0 2px 4px rgba(0,0,0,0.02)'; this.style.borderColor='#e2e8f0';" onclick="viewMaterial('${material.material_id}')">
                            <div>
                                <div style="display: flex; gap: 16px; margin-bottom: 20px;">
                                    <div style="background: #eff6ff; color: #1e40af; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;">
                                        📖
                                    </div>
                                    <div>
                                        <h4 style="margin: 0 0 4px 0; color: #0f172a; font-size: 16px; font-weight: 600; line-height: 1.3;">${escapeAssessmentHtml(material.title || 'Untitled Material')}</h4>
                                        <p style="margin: 0; color: #64748b; font-size: 13px;">${escapeAssessmentHtml(material.grade_level || 'General')} • ${escapeAssessmentHtml(String(material.total_words || 0))} words</p>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                                    <span style="padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; ${langStyle}">${escapeAssessmentHtml(lang)}</span>
                                    <span style="padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; ${ocrStyle}">${ocrIcon}${escapeAssessmentHtml(ocrState)}</span>
                                </div>
                            </div>
                            <div style="border-top: 1px solid #f1f5f9; padding-top: 16px; display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #94a3b8; font-size: 12px;">${escapeAssessmentHtml(uploader)} • ${uploadDate}</span>
                                <span style="color: #3b82f6; font-size: 13px; font-weight: 600;">Details →</span>
                            </div>
                        </div>
                        `;
                    }).join('')
                : `
                    <div class="empty-state u-col-span-full">
                        <p>No reading materials uploaded yet</p>
                        <p class="u-text-muted">Upload reading materials using the Upload button.</p>
                    </div>
                `}
            </div>
        </div>

        <!-- MODALS (Kept exact same structure for functionality) -->
        ${getLibraryModalsHTML()}
    `;

    // Reattach basic event listeners
    document.getElementById('upload-material-btn')?.addEventListener('click', (e) => {
        e.preventDefault(); e.stopPropagation(); showModal('upload-modal');
    });
    document.getElementById('refresh-materials-btn')?.addEventListener('click', async () => {
        await loadMaterials(); if (state.activeView === 'materials') renderMaterials();
    });
    
    // Filter events
    document.getElementById('filter-language')?.addEventListener('change', applyMaterialFilters);
    document.getElementById('search-material')?.addEventListener('input', applyMaterialFilters);
    
    // Upload form and Dropzone
    document.getElementById('upload-material-form')?.addEventListener('submit', handleMaterialUpload);
    setupUploadDropZone();

    // Modal Close buttons
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', async () => {
            const modal = btn.closest('.modal');
            if (modal?.id) await requestCloseModal(modal.id);
        });
    });
}

// Helper to keep the render string clean
function getLibraryModalsHTML() {
    return `
        <!-- Upload Material Modal -->
        <div id="upload-modal" class="modal hidden">
            <div class="modal-content modal-wide">
                <div class="modal-header">
                    <h3>Upload Reading Material</h3>
                    <button class="close-modal">x</button>
                </div>
                <form id="upload-material-form" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group form-group-full">
                            <label>Title *</label>
                            <input type="text" name="title" placeholder="Enter material title" required>
                        </div>
                        <div class="form-group form-group-full">
                            <label>Description</label>
                            <textarea name="description" placeholder="Brief description of the material" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Grade Level *</label>
                            <select name="grade_level" required>
                                <option value="Grade 1">Grade 1</option><option value="Grade 2">Grade 2</option>
                                <option value="Grade 3">Grade 3</option><option value="Grade 4">Grade 4</option>
                                <option value="Grade 5">Grade 5</option><option value="Grade 6">Grade 6</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Language *</label>
                            <select name="language" required>
                                <option value="English">English</option><option value="Filipino">Filipino</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Difficulty</label>
                            <select name="difficulty">
                                <option value="Easy">Easy</option><option value="Average" selected>Average</option><option value="Hard">Hard</option>
                            </select>
                        </div>
                    </div>
                    <div class="upload-area" id="upload-drop-zone">
                        <p>Drag & drop an image here, or click to browse</p>
                        <p class="u-text-muted-xs">Upload an image of the reading passage (JPG, PNG, PDF)</p>
                        <input type="file" name="image" accept="image/*,application/pdf" class="hidden" id="file-input">
                    </div>
                    <div id="ocr-result" class="hidden ocr-result-box">
                        <h4 class="u-m-0">OCR Result</h4><p id="ocr-message" class="u-my-4"></p>
                        <div class="ocr-result-preview"><p id="ocr-text-preview" class="u-m-0 u-pre-wrap"></p></div>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary">Save Material</button>
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- View Material Modal -->
        <div id="view-material-modal" class="modal hidden">
            <div class="modal-content modal-wide modal-scroll-80">
                <div class="modal-header">
                    <h3 id="view-material-title">Reading Material</h3>
                    <button class="close-modal">x</button>
                </div>
                <div id="view-material-content"></div>
            </div>
        </div>

        <!-- Manage Quiz Modal -->
        <div id="quiz-modal" class="modal hidden">
            <div class="modal-content modal-wide modal-scroll-80">
                <div class="modal-header">
                    <h3 id="quiz-modal-title">Manage Quiz</h3>
                    <button class="close-modal">x</button>
                </div>
                <form id="quiz-form">
                    <input type="hidden" id="quiz-material-id">
                    <div class="form-group">
                        <label>Quiz Title</label>
                        <input type="text" id="quiz-title" value="Comprehension Quiz" required>
                    </div>
                    <div id="quiz-questions-container" class="u-mt-16"></div>
                    <div class="modal-actions u-mt-16">
                        <button type="submit" class="btn-primary" id="quiz-save-btn">Save Quiz</button>
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;
}

// ============================================================
// 4. READING MATERIALS (Continued: Filtering)
// ============================================================

function applyMaterialFilters() {
    const grade = document.getElementById('filter-grade')?.value || '';
    const language = document.getElementById('filter-language')?.value || '';
    const type = document.getElementById('filter-type')?.value || '';
    const search = document.getElementById('search-material')?.value?.toLowerCase() || '';

    const cards = document.querySelectorAll('.material-card');
    cards.forEach(card => {
        let show = true;

        if (grade) {
            const cardGrade = card.querySelector('.material-meta span:first-child')?.textContent || '';
            if (!cardGrade.includes(grade)) show = false;
        }

        if (language) {
            const cardLang = card.querySelector('.material-language')?.textContent || '';
            if (cardLang !== language) show = false;
        }

        if (type) {
            const cardType = card.querySelector('.material-type')?.textContent || '';
            if (cardType !== type) show = false;
        }

        if (search) {
            const title = card.querySelector('h4')?.textContent?.toLowerCase() || '';
            const desc = card.querySelector('.material-description')?.textContent?.toLowerCase() || '';
            if (!title.includes(search) && !desc.includes(search)) show = false;
        }

        card.style.display = show ? '' : 'none';
    });
}

// ============================================================
// 4. READING MATERIALS (Continued: Upload Zone)
// ============================================================

function setupUploadDropZone() {
    const dropZone = document.getElementById('upload-drop-zone');
    const fileInput = document.getElementById('file-input');

    if (!dropZone || !fileInput) return;

    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--accent)';
        dropZone.style.background = 'var(--accent-soft)';
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.style.borderColor = 'var(--border)';
        dropZone.style.background = 'transparent';
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.style.borderColor = 'var(--border)';
        dropZone.style.background = 'transparent';
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });

    fileInput.addEventListener('change', async function() {
        if (this.files && this.files[0]) {
            const formData = new FormData();
            formData.append('image', this.files[0]);
            formData.append('language', document.querySelector('select[name="language"]')?.value || 'English');

            const resultDiv = document.getElementById('ocr-result');
            const messageEl = document.getElementById('ocr-message');
            const previewEl = document.getElementById('ocr-text-preview');

            resultDiv.classList.remove('hidden');
            messageEl.textContent = '⏳ Processing OCR...';
            previewEl.textContent = 'Please wait...';

            try {
                const response = await fetch('php/api/shared/reading-materials.php?action=preview-ocr', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                const data = await response.json();

                if (data.success && data.ocr_text) {
                    messageEl.textContent = '' + data.ocr_message;
                    previewEl.textContent = data.ocr_text;

                    const titleInput = document.querySelector('input[name="title"]');
                    if (titleInput && !titleInput.value) {
                        const firstLine = data.ocr_text.split('\n')[0].trim();
                        if (firstLine && firstLine.length > 3) {
                            titleInput.value = firstLine.substring(0, 100);
                        }
                    }
                } else {
                    messageEl.innerHTML = '<span class="icon-error" aria-hidden="true"><svg>...</svg></span>' + (data.ocr_message || 'OCR could not extract text from this image.');
                    previewEl.textContent = 'No text extracted. Please enter the text manually.';
                }
            } catch (error) {
                messageEl.textContent = 'Error: ' + error.message;
                previewEl.textContent = 'OCR service error. Please enter the text manually.';
            }
        }
    });
}

// ============================================================
// 4. READING MATERIALS (Continued: CRUD Operations)
// ============================================================

async function handleMaterialUpload(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    
    try {
        const response = await fetch('php/api/shared/reading-materials.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Material uploaded successfully!');
            closeModal('upload-modal');
            await loadMaterials();
            if (state.activeView === 'materials') {
                renderMaterials();
            }
            form.reset();
            document.getElementById('ocr-result').classList.add('hidden');
        } else {
            alert('' + data.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

async function viewMaterial(materialId) {
    if (!materialId) {
        alert('No material selected.');
        return;
    }

    try {
        const data = await fetchJson(`php/api/shared/reading-materials.php?id=${encodeURIComponent(materialId)}`);
        if (!data.success || !data.material) {
            alert('Material not found.');
            return;
        }

        const material = data.material;
        const esc = escapeAssessmentHtml;

        const titleEl = document.getElementById('view-material-title');
        if (titleEl) titleEl.textContent = material.title || 'Reading Material';

        const contentEl = document.getElementById('view-material-content');
        if (contentEl) {
            contentEl.innerHTML = `
                <form id="view-material-form">
                    <div class="editor-state-row">
                        <span id="material-editor-badge" class="editor-badge">Preview Mode</span>
                    </div>

                    ${material.file_path ? `
                        <div class="material-image-preview">
                            <img src="../uploads/materials/${encodeURIComponent(material.file_path)}"
                                alt="Material image"
                                class="material-full-image"
                                onerror="this.style.display='none';">
                        </div>
                    ` : ''}

                    <div class="u-grid-2 u-gap-8 u-text-md u-mb-12">
                        <p><strong>Words:</strong> ${Number(material.total_words || 0)}</p>
                        <p><strong>Uploaded:</strong> ${material.upload_date ? new Date(material.upload_date).toLocaleDateString() : 'N/A'}</p>
                    </div>

                    <div class="form-grid">
                        <div class="form-group form-group-full">
                            <label>Title</label>
                            <input id="material-edit-title" name="title" type="text" value="${esc(material.title || '')}" disabled>
                        </div>
                        <div class="form-group">
                            <label>Grade Level</label>
                            <select id="material-edit-grade" name="grade_level" disabled>
                                <option value="Grade 2" ${material.grade_level === 'Grade 2' ? 'selected' : ''}>Grade 2</option>
                                <option value="Grade 3" ${material.grade_level === 'Grade 3' ? 'selected' : ''}>Grade 3</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Language</label>
                            <select id="material-edit-language" name="language" disabled>
                                <option value="English" ${material.language === 'English' ? 'selected' : ''}>English</option>
                                <option value="Filipino" ${material.language === 'Filipino' ? 'selected' : ''}>Filipino</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Type</label>
                            <select id="material-edit-type" name="material_type" disabled>
                                <option value="Custom" ${material.material_type === 'Custom' ? 'selected' : ''}>Custom</option>
                                <option value="Phil-IRI" ${material.material_type === 'Phil-IRI' ? 'selected' : ''}>Phil-IRI</option>
                                <option value="CRLA" ${material.material_type === 'CRLA' ? 'selected' : ''}>CRLA</option>
                                <option value="Practice" ${material.material_type === 'Practice' ? 'selected' : ''}>Practice</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Difficulty</label>
                            <select id="material-edit-difficulty" name="difficulty" disabled>
                                <option value="Easy" ${material.difficulty === 'Easy' ? 'selected' : ''}>Easy</option>
                                <option value="Average" ${material.difficulty === 'Average' ? 'selected' : ''}>Average</option>
                                <option value="Hard" ${material.difficulty === 'Hard' ? 'selected' : ''}>Hard</option>
                            </select>
                        </div>
                        <div class="form-group form-group-full">
                            <label>Description</label>
                            <textarea id="material-edit-description" name="description" rows="3" disabled>${esc(material.description || '')}</textarea>
                        </div>
                        <div class="form-group form-group-full">
                            <label>Full Text</label>
                            <textarea id="material-edit-ocr" name="ocr_text" rows="10" disabled>${esc(material.ocr_text || '')}</textarea>
                        </div>
                    </div>

                    <!-- NEW: ACTIONS WRAPPER -->
                    <div class="modal-actions u-mt-12" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 16px;">
                        
                        <div id="view-mode-buttons" style="display: flex; gap: 8px; flex: 1;">
                            <button id="view-material-edit-btn" type="button" class="btn-primary">✏️ Edit Material</button>
                            <button id="view-material-quiz-btn" type="button" class="btn-secondary" style="border-color: #3b82f6; color: #3b82f6; background: #eff6ff;">📝 Manage Quiz</button>
                            <button id="view-material-delete-btn" type="button" class="btn-secondary" style="color: #ef4444; border-color: #fecaca; background: #fef2f2; margin-left: auto;">🗑️ Delete</button>
                        </div>

                        <div id="edit-mode-buttons" class="hidden" style="display: flex; gap: 8px; flex: 1; justify-content: flex-end;">
                            <button id="view-material-cancel-btn" type="button" class="btn-secondary">Cancel</button>
                            <button id="view-material-save-btn" type="submit" class="btn-primary">Save Changes</button>
                        </div>
                        
                    </div>
                </form>
            `;

            const form = document.getElementById('view-material-form');
            const modal = document.getElementById('view-material-modal');
            const fields = form?.querySelectorAll('input, select, textarea');
            
            // Buttons
            const editBtn = document.getElementById('view-material-edit-btn');
            const quizBtn = document.getElementById('view-material-quiz-btn');
            const deleteBtn = document.getElementById('view-material-delete-btn');
            const saveBtn = document.getElementById('view-material-save-btn');
            const cancelBtn = document.getElementById('view-material-cancel-btn');
            
            const viewModeDiv = document.getElementById('view-mode-buttons');
            const editModeDiv = document.getElementById('edit-mode-buttons');
            const badge = document.getElementById('material-editor-badge');

            if (modal) {
                modal.dataset.dirty = 'false';
            }

            const setEditing = (editing) => {
                fields?.forEach((field) => {
                    if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
                        field.disabled = !editing;
                    }
                });
                
                // Toggle Button Groups
                viewModeDiv.classList.toggle('hidden', editing);
                editModeDiv.classList.toggle('hidden', !editing);

                if (badge) {
                    badge.textContent = editing ? 'Editing' : 'Preview Mode';
                    badge.classList.toggle('editing', editing);
                }
                if (!editing && modal) modal.dataset.dirty = 'false';
            };

            // Hook up our NEW Quiz and Delete Buttons!
            quizBtn?.addEventListener('click', () => {
                closeModal('view-material-modal');
                openQuizModal(material.material_id);
            });

            deleteBtn?.addEventListener('click', () => {
                deleteMaterial(material.material_id);
                // The delete handler handles closing and refreshing the list
            });

            // Standard Edit Handlers
            editBtn?.addEventListener('click', () => setEditing(true));
            cancelBtn?.addEventListener('click', async () => {
                if (modal?.dataset.dirty === 'true') {
                    const shouldDiscard = await showConfirm('Discard your material changes?', {
                        title: 'Unsaved Changes', confirmLabel: 'Discard Changes', confirmTone: 'danger'
                    });
                    if (!shouldDiscard) return;
                }
                await viewMaterial(material.material_id);
            });

            form?.addEventListener('input', () => {
                if (!editModeDiv.classList.contains('hidden') && modal) {
                    modal.dataset.dirty = 'true';
                }
            });

            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                clearFormErrors(form);

                const titleField = document.getElementById('material-edit-title');
                const ocrField = document.getElementById('material-edit-ocr');

                const payload = {
                    title: String(titleField?.value || '').trim(),
                    description: String(document.getElementById('material-edit-description')?.value || '').trim(),
                    grade_level: String(document.getElementById('material-edit-grade')?.value || '').trim(),
                    language: String(document.getElementById('material-edit-language')?.value || '').trim(),
                    material_type: String(document.getElementById('material-edit-type')?.value || '').trim(),
                    difficulty: String(document.getElementById('material-edit-difficulty')?.value || '').trim(),
                    status: String(document.getElementById('material-edit-status')?.value || '').trim(), // Note: Disabled field removed from view, default to Active
                    ocr_text: String(ocrField?.value || '').trim(),
                    total_words: String(ocrField?.value || '').trim().split(/\s+/).filter(word => word.length > 0).length
                };

                let hasErrors = false;
                if (!payload.title) { setFieldError(titleField, 'Material title is required.'); hasErrors = true; }
                if (!payload.ocr_text) { setFieldError(ocrField, 'Full text is required for assessment use.'); hasErrors = true; }
                if (hasErrors) { showToast('Please correct the highlighted fields.', 'error'); return; }

                try {
                    const update = await fetchJson(`php/api/shared/reading-materials.php?id=${encodeURIComponent(material.material_id)}`, {
                        method: 'PUT', body: JSON.stringify(payload)
                    });
                    if (!update.success) throw new Error(update.message || 'Failed to update material');

                    showToast('Material updated successfully!', 'success');
                    await loadMaterials();
                    if (state.activeView === 'materials') renderMaterials();
                    await viewMaterial(material.material_id);
                } catch (error) {
                    showToast('Error updating material: ' + error.message, 'error');
                }
            });
        }

        showModal('view-material-modal');
    } catch (error) {
        alert('Error loading material: ' + error.message);
    }
}

async function editMaterial(materialId) {
    await viewMaterial(materialId);
}

async function deleteMaterial(materialId) {
    const shouldDelete = await showConfirm('Are you sure you want to delete this material?', {
        title: 'Delete Material',
        confirmLabel: 'Delete',
        confirmTone: 'danger'
    });
    if (!shouldDelete) return;
    
    try {
        const result = await fetchJson(`php/api/shared/reading-materials.php?id=${encodeURIComponent(materialId)}`, {
            method: 'DELETE'
        });
        
        if (result.success) {
            alert('✅ Material deleted successfully');
            await loadMaterials();
            if (state.activeView === 'materials') {
                renderMaterials();
            }
        } else {
            alert('' + result.message);
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// ============================================================
// QUIZ MANAGEMENT
// ============================================================

async function openQuizModal(materialId) {
    const container = document.getElementById('quiz-questions-container');
    document.getElementById('quiz-material-id').value = materialId;
    document.getElementById('quiz-title').value = "Comprehension Quiz";
    
    // 1. Generate the 5 blank questions HTML instantly
    let questionsHtml = '';
    for (let i = 1; i <= 5; i++) {
        questionsHtml += `
            <div class="panel u-mb-16 u-p-16" style="background: var(--bg-alt); border: 1px solid var(--border);">
                <h4 class="u-mt-0">Question ${i}</h4>
                <div class="form-group form-group-full">
                    <input type="text" name="q${i}_text" placeholder="Enter question text here..." required>
                </div>
                <div class="u-grid-2 u-gap-12">
                    ${['A', 'B', 'C', 'D'].map((label, idx) => `
                        <div class="u-flex u-align-center u-gap-8">
                            <input type="radio" name="q${i}_correct" value="${label}" ${idx === 0 ? 'checked' : ''} title="Mark as correct answer">
                            <span class="u-fw-600">${label}</span>
                            <input type="text" name="q${i}_choice_${label}" placeholder="Option ${label}" class="u-flex-grow-1" required>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    container.innerHTML = questionsHtml;

    // Show the modal immediately so the UI feels fast
    showModal('quiz-modal');

    // 2. Fetch existing quiz data (The Missing Link!)
    try {
        const saveBtn = document.getElementById('quiz-save-btn');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Loading saved quiz...';

        const result = await fetchAssignmentApi(`/teacher/materials/${materialId}/quiz`);
        
        if (result.success && result.quiz) {
            // Populate the Title
            document.getElementById('quiz-title').value = result.quiz.title;
            
            // Populate Questions and Choices
            result.quiz.questions.forEach((q) => {
                const qNum = q.question_number;
                
                // Set Question Text
                const qInput = document.querySelector(`input[name="q${qNum}_text"]`);
                if (qInput) qInput.value = q.question_text;

                // Set Choices
                q.choices.forEach((c) => {
                    const label = c.choice_label;
                    const cInput = document.querySelector(`input[name="q${qNum}_choice_${label}"]`);
                    if (cInput) cInput.value = c.choice_text;

                    // Check the correct radio button
                    if (c.is_correct === 1 || c.is_correct === true) {
                        const radio = document.querySelector(`input[name="q${qNum}_correct"][value="${label}"]`);
                        if (radio) radio.checked = true;
                    }
                });
            });
        }
    } catch (error) {
        console.error("Error fetching existing quiz:", error);
    } finally {
        const saveBtn = document.getElementById('quiz-save-btn');
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save Quiz';
    }
}

// Listen to the whole document to catch the form submit, no matter when it's drawn!
document.addEventListener('submit', async (e) => {
    // Only intercept if the form being submitted is the quiz form
    if (e.target && e.target.id === 'quiz-form') {
        e.preventDefault(); // STOP THE PAGE FROM RELOADING!
        
        const form = e.target;
        const materialId = document.getElementById('quiz-material-id').value;
        const saveBtn = document.getElementById('quiz-save-btn');
        
        const payload = {
            title: document.getElementById('quiz-title').value,
            questions: []
        };

        // Extract the 5 questions and their choices
        for (let i = 1; i <= 5; i++) {
            const questionText = form.elements[`q${i}_text`].value;
            const correctLabel = form.elements[`q${i}_correct`].value;
            
            const choices = ['A', 'B', 'C', 'D'].map(label => ({
                label: label,
                text: form.elements[`q${i}_choice_${label}`].value,
                is_correct: label === correctLabel ? 1 : 0
            }));

            // FIX: Use JavaScript's .push() instead of Python's .append() !
            payload.questions.push({
                number: i,
                text: questionText,
                choices: choices
            });
        }

        try {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
            
            const result = await fetchAssignmentApi(`/teacher/materials/${materialId}/quiz`, {
                method: 'POST',
                body: payload
            });

            if (result.success) {
                showToast('Quiz saved successfully!', 'success');
                closeModal('quiz-modal');
            }
        } catch (error) {
            showToast('Error saving quiz: ' + error.message, 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Quiz';
        }
    }
});

// ============================================================
// 5. ASSESSMENT WORKFLOW - 5-Step Reading Assessment
// ============================================================

function renderAssessment() {
    const steps = {
        1: renderStep1SelectStudent,
        2: renderStep2SelectMaterial,
        3: renderStep3Record,
        4: renderStep4Results
    };

    const renderFn = steps[assessmentState.step] || renderStep1SelectStudent;
    renderFn();
}
// ============================================================
// 5. ASSESSMENT - Step 1: Student Selection
// ============================================================

function renderStep1SelectStudent() {
    viewContainer.innerHTML = `
        <div class="assessment-container">
            <div class="assessment-header">
                <div class="step-indicator">
                    <span class="step active">1. Student</span>
                    <span class="step">2. Material</span>
                    <span class="step">3. Record</span>
                    <span class="step">4. Results</span>
                </div>
            </div>

            <div class="assessment-step panel">
                <h3>Select Student</h3>
                <p>Search for a student by LRN or name</p>

                <div class="search-area">
                    <div class="u-inline-form-row">
                        <input type="text" id="student-search-input" placeholder="Enter LRN or name..." class="input-inline-fill">
                        <button id="search-student-btn" class="btn-primary">Search</button>
                    </div>
                </div>

                <div id="student-results" class="u-mt-16"></div>

                <div class="u-actions-row is-end u-mt-20">
                    <button id="next-step-1" class="btn-primary" disabled>Next</button>
                </div>
            </div>
        </div>
    `;

    if (assessmentState.prefillSearchTerm) {
        const input = document.getElementById('student-search-input');
        if (input) input.value = assessmentState.prefillSearchTerm;
        delete assessmentState.prefillSearchTerm;
    }

    document.getElementById('search-student-btn')?.addEventListener('click', searchAssessmentStudents);
    document.getElementById('student-search-input')?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') searchAssessmentStudents();
    });

    document.getElementById('next-step-1')?.addEventListener('click', () => {
        if (assessmentState.student) {
            assessmentState.step = 2;
            renderAssessment();
        }
    });
}

async function searchAssessmentStudents() {
    const term = (document.getElementById('student-search-input')?.value || '').trim();
    if (!term) return;

    try {
        const data = await fetchJson(`php/api/shared/assessment.php?action=student&term=${encodeURIComponent(term)}`);
        const resultsDiv = document.getElementById('student-results');
        if (!resultsDiv) return;

        if (data.success && data.students && data.students.length > 0) {
            resultsDiv.innerHTML = `
                <div class="u-stack-sm">
                    ${data.students.map(student => `
                        <div class="student-result-item selector-item" data-id="${student.student_id}">
                            <strong>${escapeAssessmentHtml(student.first_name)} ${escapeAssessmentHtml(student.last_name)}</strong>
                            <span class="u-float-right">LRN: ${escapeAssessmentHtml(student.lrn || '')}</span>
                            <br><small class="u-text-muted">${escapeAssessmentHtml(student.grade_level || 'N/A')} - ${escapeAssessmentHtml(student.section || 'N/A')}</small>
                        </div>
                    `).join('')}
                </div>
            `;

            document.querySelectorAll('.student-result-item').forEach(el => {
                el.addEventListener('click', () => {
                    document.querySelectorAll('.student-result-item').forEach(item => {
                        item.classList.remove('selected');
                    });
                    el.classList.add('selected');

                    const student = data.students.find(s => String(s.student_id) === String(el.dataset.id));
                    assessmentState.student = student || null;
                    const nextBtn = document.getElementById('next-step-1');
                    if (nextBtn) nextBtn.disabled = !assessmentState.student;
                });
            });
        } else {
            resultsDiv.innerHTML = '<p class="u-text-muted">No students found. Try a different search term.</p>';
        }
    } catch (error) {
        alert('Error searching: ' + error.message);
    }
}

// ============================================================
// 5. ASSESSMENT - Step 2: Material Selection
// ============================================================

function renderStep2SelectMaterial() {
    const student = assessmentState.student;

    viewContainer.innerHTML = `
        <div class="assessment-container">
            <div class="assessment-header">
                <div class="step-indicator">
                    <span class="step done">1. Student</span>
                    <span class="step active">2. Material</span>
                    <span class="step">3. Record</span>
                    <span class="step">4. Results</span>
                </div>
            </div>

            <div class="assessment-step panel">
                <h3>Select Reading Material</h3>
                <p>Choose a passage for ${student ? escapeAssessmentHtml(student.first_name) : ''} to read</p>

                <div class="student-badge">
                    <strong>Selected:</strong> ${student ? `${escapeAssessmentHtml(student.first_name)} ${escapeAssessmentHtml(student.last_name)}` : ''}
                </div>

                <div class="filter-area u-row-wrap u-gap-12 u-my-16">
                    <select id="material-filter-grade" class="filter-select">
                        <option value="">All Grades</option>
                        <option value="Grade 2">Grade 2</option>
                        <option value="Grade 3">Grade 3</option>
                    </select>
                    <select id="material-filter-language" class="filter-select">
                        <option value="">All Languages</option>
                        <option value="English">English</option>
                        <option value="Filipino">Filipino</option>
                    </select>
                    <button id="load-materials-btn" class="btn-primary">Load Materials</button>
                </div>

                <div id="materials-list" class="u-stack-md u-max-h-320 u-scroll-y"></div>

                <div class="u-actions-row is-between u-mt-20">
                    <button id="back-step-2" class="btn-secondary">Back</button>
                    <button id="next-step-2" class="btn-primary" disabled>Next</button>
                </div>
            </div>
        </div>
    `;

    document.getElementById('load-materials-btn')?.addEventListener('click', loadAssessmentMaterials);
    document.getElementById('back-step-2')?.addEventListener('click', () => {
        assessmentState.step = 1;
        renderAssessment();
    });
    document.getElementById('next-step-2')?.addEventListener('click', () => {
        if (assessmentState.material) {
            assessmentState.step = 3;
            renderAssessment();
        }
    });

    loadAssessmentMaterials();
}

async function loadAssessmentMaterials() {
    const grade = document.getElementById('material-filter-grade')?.value || '';
    const language = document.getElementById('material-filter-language')?.value || '';

    let url = 'php/api/shared/assessment.php?action=materials';
    if (grade) url += `&grade=${encodeURIComponent(grade)}`;
    if (language) url += `&language=${encodeURIComponent(language)}`;

    try {
        const data = await fetchJson(url);
        const listDiv = document.getElementById('materials-list');
        if (!listDiv) return;

        if (data.success && data.materials && data.materials.length > 0) {
            listDiv.innerHTML = data.materials.map(material => `
                <div class="material-item selector-item u-p-16" data-id="${material.material_id}">
                    <div class="u-row-between u-gap-8">
                        <strong>${escapeAssessmentHtml(material.title)}</strong>
                        <span class="u-text-muted-xs">${escapeAssessmentHtml(material.language)}</span>
                    </div>
                    <div class="u-row-wrap u-gap-16 u-text-muted-xs u-mt-4">
                        <span>${escapeAssessmentHtml(material.grade_level)}</span>
                        <span>${escapeAssessmentHtml(material.difficulty)}</span>
                        <span>${material.ocr_text ? 'Text available' : 'No text'}</span>
                    </div>
                </div>
            `).join('');

            document.querySelectorAll('.material-item').forEach(el => {
                el.addEventListener('click', () => {
                    document.querySelectorAll('.material-item').forEach(item => {
                        item.classList.remove('selected');
                    });
                    el.classList.add('selected');

                    const material = data.materials.find(m => String(m.material_id) === String(el.dataset.id));
                    assessmentState.material = material || null;
                    const nextBtn = document.getElementById('next-step-2');
                    if (nextBtn) nextBtn.disabled = !assessmentState.material;
                });
            });
        } else {
            listDiv.innerHTML = '<p class="u-text-muted">No materials found. Upload some materials first.</p>';
        }
    } catch (error) {
        alert('Error loading materials: ' + error.message);
    }
}

// ============================================================
// 5. ASSESSMENT - Step 3: Recording & Audio Capture
// ============================================================

function renderStep3Record() {
    const material = assessmentState.material;
    const student = assessmentState.student;
    const isRecording = assessmentState.recording;

    viewContainer.innerHTML = `
        <div class="assessment-container">
            <div class="assessment-header">
                <div class="step-indicator">
                    <span class="step done">1. Student</span>
                    <span class="step done">2. Material</span>
                    <span class="step active">3. Record & Process</span>
                    <span class="step">4. Results</span>
                </div>
            </div>

            <div class="assessment-step panel">
                <div class="u-row-between u-gap-8">
                    <h3>Reading Activity</h3>
                    <span id="timer-display" class="timer-display">00:00</span>
                </div>

                <div class="student-badge">
                    <strong>Student:</strong> ${student ? `${escapeAssessmentHtml(student.first_name)} ${escapeAssessmentHtml(student.last_name)}` : ''}
                    <strong class="u-ml-20">Material:</strong> ${material ? escapeAssessmentHtml(material.title) : ''}
                </div>

                <div class="reading-passage reading-passage-shell">
                    <h4 class="u-mt-0">Read the passage below:</h4>
                    <p class="reading-passage-text">${material?.ocr_text ? escapeAssessmentHtml(material.ocr_text) : 'No text available'}</p>
                </div>

                <div class="recording-controls controls-wrap">
                <button id="play-passage-btn" class="btn-info btn-record">🔊 Hear Passage</button>
                <button id="start-record-btn" class="btn-primary btn-record ${isRecording ? 'hidden' : ''}">🎤 Start Recording</button>
                <button id="stop-record-btn" class="btn-danger btn-record ${isRecording ? '' : 'hidden'}">⏹️ Stop Recording</button>
                <button id="play-record-btn" class="btn-secondary" ${assessmentState.audioBlob ? '' : 'disabled'}>▶️ Playback</button>
                <button id="clear-record-btn" class="btn-secondary" ${assessmentState.audioBlob ? '' : 'disabled'}>🗑️ Clear</button>
                </div>

                <div id="recording-status" class="recording-status-msg">
                    ${isRecording ? '⏺️ Recording in progress...' : 'Recording complete. Ready to submit.'}
                </div>

                <div class="u-actions-row is-between u-mt-20">
                    <button id="back-step-3" class="btn-secondary">← Back</button>
                    <button id="submit-assessment-btn" class="btn-primary" ${assessmentState.audioBlob ? '' : 'disabled'}>Process Audio & View Results →</button>
                </div>
            </div>
        </div>
    `;

    updateAssessmentTimer();

    document.getElementById('play-passage-btn')?.addEventListener('click', playPassageAudio);
    document.getElementById('start-record-btn')?.addEventListener('click', startAssessmentRecording);
    document.getElementById('stop-record-btn')?.addEventListener('click', stopAssessmentRecording);
    document.getElementById('play-record-btn')?.addEventListener('click', playAssessmentRecording);
    document.getElementById('clear-record-btn')?.addEventListener('click', clearAssessmentRecording);
    
    document.getElementById('back-step-3')?.addEventListener('click', () => {
        stopAssessmentRecording(true);
        assessmentState.step = 2;
        renderAssessment();
    });
    document.getElementById('submit-assessment-btn')?.addEventListener('click', submitAssessment);
}

function playPassageAudio() {
    const text = assessmentState.material?.ocr_text || '';
    if (!text) { alert('No passage text available.'); return; }
    window.speechSynthesis.cancel();
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 0.9;
    utterance.pitch = 1.0;
    const englishVoice = window.speechSynthesis.getVoices().find(v => v.lang.includes('en'));
    if (englishVoice) utterance.voice = englishVoice;
    utterance.onstart = () => { const btn = document.getElementById('play-passage-btn'); if (btn) btn.textContent = '🔊 Stop'; };
    utterance.onend = () => { const btn = document.getElementById('play-passage-btn'); if (btn) btn.textContent = '🔊 Hear Passage'; };
    const btn = document.getElementById('play-passage-btn');
    if (btn && window.speechSynthesis.speaking) { window.speechSynthesis.cancel(); btn.textContent = '🔊 Hear Passage'; return; }
    window.speechSynthesis.speak(utterance);
}async function startAssessmentRecording() {
    try {
        const preferredMic = uiSettings.micDeviceId;
        const audioConstraint = preferredMic ? { deviceId: { exact: preferredMic } } : true;

        try {
            assessmentState.stream = await navigator.mediaDevices.getUserMedia({ audio: audioConstraint });
        } catch (error) {
            if (preferredMic && (error.name === 'OverconstrainedError' || error.name === 'NotFoundError')) {
                assessmentState.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                showToast('Preferred mic unavailable, using system default.', 'info');
            } else {
                throw error;
            }
        }

        assessmentState.mediaRecorder = new MediaRecorder(assessmentState.stream);
        assessmentState.audioChunks = [];
        assessmentState.seconds = 0;

        assessmentState.mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                assessmentState.audioChunks.push(event.data);
            }
        };

        assessmentState.mediaRecorder.onstop = () => {
            assessmentState.audioBlob = new Blob(assessmentState.audioChunks, { type: 'audio/webm' });
            const submitBtn = document.getElementById('submit-assessment-btn');
            const playBtn = document.getElementById('play-record-btn');
            const clearBtn = document.getElementById('clear-record-btn');
            const statusEl = document.getElementById('recording-status');

            if (submitBtn) submitBtn.disabled = false;
            if (playBtn) playBtn.disabled = false;
            if (clearBtn) clearBtn.disabled = false;
            if (statusEl) statusEl.textContent = 'Recording complete. Ready to submit.';
        };

        assessmentState.mediaRecorder.start();
        assessmentState.recording = true;

        if (assessmentState.timerInterval) {
            clearInterval(assessmentState.timerInterval);
        }

        assessmentState.timerInterval = setInterval(() => {
            assessmentState.seconds += 1;
            updateAssessmentTimer();
        }, 1000);

        const startBtn = document.getElementById('start-record-btn');
        const stopBtn = document.getElementById('stop-record-btn');
        const statusEl = document.getElementById('recording-status');
        if (startBtn) startBtn.classList.add('hidden');
        if (stopBtn) stopBtn.classList.remove('hidden');
        if (statusEl) statusEl.textContent = 'Recording in progress...';
    } catch (error) {
        alert('Error accessing microphone: ' + error.message);
    }
}

function stopAssessmentRecording(silent = false) {
    if (assessmentState.mediaRecorder && assessmentState.mediaRecorder.state === 'recording') {
        assessmentState.mediaRecorder.stop();
    }

    if (assessmentState.stream) {
        assessmentState.stream.getTracks().forEach(track => track.stop());
        assessmentState.stream = null;
    }

    assessmentState.recording = false;

    if (assessmentState.timerInterval) {
        clearInterval(assessmentState.timerInterval);
        assessmentState.timerInterval = null;
    }

    if (!silent) {
        const startBtn = document.getElementById('start-record-btn');
        const stopBtn = document.getElementById('stop-record-btn');
        if (startBtn) startBtn.classList.remove('hidden');
        if (stopBtn) stopBtn.classList.add('hidden');
    }
}

function playAssessmentRecording() {
    if (!assessmentState.audioBlob) return;
    if (assessmentState.playbackUrl) {
        URL.revokeObjectURL(assessmentState.playbackUrl);
    }
    assessmentState.playbackUrl = URL.createObjectURL(assessmentState.audioBlob);
    const audio = new Audio(assessmentState.playbackUrl);
    applyAudioOutputPreference(audio);
    audio.play();
}

function clearAssessmentRecording() {
    assessmentState.audioBlob = null;
    assessmentState.audioChunks = [];
    assessmentState.seconds = 0;
    updateAssessmentTimer();

    const playBtn = document.getElementById('play-record-btn');
    const clearBtn = document.getElementById('clear-record-btn');
    const submitBtn = document.getElementById('submit-assessment-btn');
    const statusEl = document.getElementById('recording-status');

    if (playBtn) playBtn.disabled = true;
    if (clearBtn) clearBtn.disabled = true;
    if (submitBtn) submitBtn.disabled = true;
    if (statusEl) statusEl.textContent = 'Recording cleared.';
}

function updateAssessmentTimer() {
    const mins = String(Math.floor(assessmentState.seconds / 60)).padStart(2, '0');
    const secs = String(assessmentState.seconds % 60).padStart(2, '0');
    const timerEl = document.getElementById('timer-display');
    if (timerEl) timerEl.textContent = `${mins}:${secs}`;
}

function ensureComprehensionModal() {
    let modal = document.getElementById('comprehension-modal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'comprehension-modal';
    modal.className = 'modal hidden';
    modal.innerHTML = `
        <div class="modal-content comprehension-modal-content">
            <div class="modal-header">
                <h3 id="comprehension-modal-title">Enter Comprehension Score</h3>
                <button type="button" class="close-modal" id="comprehension-modal-close"><span class="icon-error" aria-hidden="true"><svg>...</svg></span></button>
            </div>
            <div id="comprehension-modal-body"></div>
        </div>
    `;
    document.body.appendChild(modal);

    modal.querySelector('#comprehension-modal-close')?.addEventListener('click', () => {
        requestCloseModal('comprehension-modal');
    });

    return modal;
}

function renderComprehensionRows(rows, studentName = '') {
    if (!rows.length) {
        return `
            <div class="comprehension-empty-state">
                <p>No assessments are available for comprehension scoring yet.</p>
            </div>
        `;
    }

    return `
        <div class="comprehension-modal-intro">
            <p>${studentName ? `Update comprehension scores for <strong>${escapeAssessmentHtml(studentName)}</strong>.` : 'Enter the teacher-scored comprehension result after the ORF session.'}</p>
        </div>
        <div class="comprehension-entry-list">
            ${rows.map((row) => `
                <div class="comprehension-entry-card" data-activity-id="${row.activity_id || ''}" data-assessment-id="${row.assessment_id || ''}">
                    <div class="comprehension-entry-head">
                        <div>
                            <h4>${escapeAssessmentHtml(row.material_title || 'Assessment')}</h4>
                            <p>${row.assessed_at ? new Date(row.assessed_at).toLocaleDateString() : (row.activity_date ? new Date(row.activity_date).toLocaleDateString() : 'Assessment date unavailable')}</p>
                        </div>
                        <button type="button" class="btn-export export-assessment-btn" data-assessment-id="${row.assessment_id || ''}">Export CSV</button>
                    </div>
                    <div class="comprehension-entry-metrics">
                        <span>Accuracy: ${safeNumber(row.accuracy_percentage, 1)}%</span>
                        <span>WCPM: ${safeNumber(row.wcpm, 1)}</span>
                        <span>Level: ${escapeAssessmentHtml(row.final_reading_level || row.reading_level || 'Pending')}</span>
                    </div>
                    <div class="comprehension-entry-actions">
                        <label>
                            <span>Comprehension Score</span>
                            <input type="number" min="0" max="7" step="1" class="comprehension-score-input" value="${row.comprehension_score ?? ''}" data-activity-id="${row.activity_id || ''}">
                        </label>
                        <button type="button" class="btn-primary save-comprehension-btn" data-activity-id="${row.activity_id || ''}" data-assessment-id="${row.assessment_id || ''}">Save Score</button>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

async function refreshAssessmentProfile(assessmentId) {
    if (!assessmentId) return;

    const data = await fetchJson(`php/api/shared/assessment.php?action=profile&id=${encodeURIComponent(assessmentId)}`);
    if (!data.success) return;

    assessmentState.results = {
        ...assessmentState.results,
        assessment: data.assessment,
        assessment_id: data.assessment?.assessment_id || assessmentState.results?.assessment_id,
        activity_id: data.assessment?.activity_id || assessmentState.results?.activity_id,
        transcript: data.assessment?.transcript || assessmentState.results?.transcript,
        reading_profile: data.reading_profile,
        interpretation: data.interpretation,
        miscues: data.miscues || [],
        scores: {
            ...(assessmentState.results?.scores || {}),
            accuracy: data.reading_profile?.accuracy_percentage,
            wcpm: data.reading_profile?.wcpm,
            time_seconds: data.reading_profile?.reading_time_seconds,
            substitutions: data.reading_profile?.miscues?.substitutions,
            omissions: data.reading_profile?.miscues?.omissions,
            insertions: data.reading_profile?.miscues?.insertions,
            repetitions: data.reading_profile?.miscues?.repetitions
        }
    };

    if (state.activeView === 'assessment' && assessmentState.step === 4) {
        renderAssessment();
    }
}

async function saveComprehensionScore(activityId, score, options = {}) {
    const normalizedScore = Number(score);
    if (!Number.isInteger(normalizedScore) || normalizedScore < 0 || normalizedScore > 7) {
        showToast('Comprehension score must be a whole number from 0 to 7.', 'error');
        return false;
    }

    const result = await fetchJson('php/api/shared/assessment.php?action=update_comprehension', {
        method: 'POST',
        body: JSON.stringify({
            activity_id: activityId,
            comprehension_score: normalizedScore
        })
    });

    if (!result.success) {
        throw new Error(result.message || 'Failed to save comprehension score');
    }

    await loadAssessments();

    if (options.assessmentId) {
        await refreshAssessmentProfile(options.assessmentId);
    }

    showToast('Comprehension score saved.', 'success');
    return true;
}

function exportAssessmentResults(assessmentId = null, options = {}) {
    const params = new URLSearchParams({ action: 'export' });
    if (assessmentId) {
        params.set('assessment_id', String(assessmentId));
    }
    if (options.studentId) {
        params.set('student_id', String(options.studentId));
    }

    const link = document.createElement('a');
    link.href = `php/api/shared/assessment.php?${params.toString()}`;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
}

async function openComprehensionModal(options = {}) {
    const modal = ensureComprehensionModal();
    const titleEl = document.getElementById('comprehension-modal-title');
    const bodyEl = document.getElementById('comprehension-modal-body');
    if (!bodyEl) return;

    if (titleEl) {
        titleEl.textContent = options.studentName
            ? `Enter Comprehension Score: ${options.studentName}`
            : 'Enter Comprehension Score';
    }

    bodyEl.innerHTML = '<p class="comprehension-loading">Loading assessment details...</p>';
    showModal('comprehension-modal');

    try {
        let rows = [];
        if (options.studentId) {
            const data = await fetchJson(`php/api/shared/assessment.php?action=history&student_id=${encodeURIComponent(options.studentId)}`);
            rows = Array.isArray(data.history) ? data.history : [];
        } else if (options.activityId) {
            rows = [{
                activity_id: options.activityId,
                assessment_id: options.assessmentId,
                material_title: options.materialTitle,
                assessed_at: options.assessedAt,
                comprehension_score: options.comprehensionScore,
                accuracy_percentage: options.accuracyPercentage,
                wcpm: options.wcpm,
                reading_level: options.readingLevel,
                final_reading_level: options.finalReadingLevel
            }];
        }

        bodyEl.innerHTML = renderComprehensionRows(rows, options.studentName || '');

        bodyEl.querySelectorAll('.save-comprehension-btn').forEach((button) => {
            button.addEventListener('click', async () => {
                const card = button.closest('.comprehension-entry-card');
                const input = card?.querySelector('.comprehension-score-input');
                const activityId = button.dataset.activityId;
                const assessmentId = button.dataset.assessmentId;
                if (!activityId || !(input instanceof HTMLInputElement)) {
                    return;
                }

                try {
                    button.disabled = true;
                    await saveComprehensionScore(activityId, input.value, {
                        assessmentId: assessmentId || options.assessmentId || null
                    });

                    if (options.studentId) {
                        await openComprehensionModal({
                            studentId: options.studentId,
                            studentName: options.studentName
                        });
                        return;
                    }

                    const currentAssessment = assessmentState.results?.assessment;
                    await openComprehensionModal({
                        activityId: assessmentState.results?.activity_id || activityId,
                        assessmentId: assessmentState.results?.assessment_id || assessmentId,
                        materialTitle: currentAssessment?.material_title || options.materialTitle,
                        assessedAt: currentAssessment?.assessed_at || options.assessedAt,
                        comprehensionScore: currentAssessment?.comprehension_score,
                        accuracyPercentage: assessmentState.results?.reading_profile?.accuracy_percentage || options.accuracyPercentage,
                        wcpm: assessmentState.results?.reading_profile?.wcpm || options.wcpm,
                        readingLevel: currentAssessment?.reading_level || assessmentState.results?.scores?.reading_level,
                        finalReadingLevel: currentAssessment?.final_reading_level || assessmentState.results?.reading_profile?.reading_level
                    });
                } catch (error) {
                    showToast(error.message, 'error');
                } finally {
                    button.disabled = false;
                }
            });
        });

        bodyEl.querySelectorAll('.export-assessment-btn').forEach((button) => {
            button.addEventListener('click', () => {
                if (button.dataset.assessmentId) {
                    exportAssessmentResults(button.dataset.assessmentId);
                }
            });
        });
    } catch (error) {
        bodyEl.innerHTML = `<p class="comprehension-error">${escapeAssessmentHtml(error.message)}</p>`;
    }
}

// ============================================================
// 5. ASSESSMENT - Step 4: Results Display
// ============================================================

function renderStep4Results() {
    const results = assessmentState.results;
    const profile = results?.reading_profile || {};
    const scoreSummary = results?.scores || {};
    const assessmentRecord = results?.assessment || {};
    const hasComprehensionScore = assessmentRecord.comprehension_score !== null && assessmentRecord.comprehension_score !== undefined;
    const orfReadingLevel = scoreSummary.reading_level || assessmentRecord.reading_level || 'Pending';
    const assessmentId = results?.assessment_id || assessmentRecord.assessment_id || null;
    const activityId = results?.activity_id || assessmentRecord.activity_id || null;
    const readingSeconds = profile.reading_time_seconds || scoreSummary.time_seconds || 0;

    viewContainer.innerHTML = `
        <div class="assessment-container">
            <div class="assessment-header">
                <div class="step-indicator">
                    <span class="step done">1. Student</span>
                    <span class="step done">2. Material</span>
                    <span class="step done">3. Record</span>
                    <span class="step active">4. Results</span>
                </div>
            </div>

            <div class="assessment-step panel">
                <h3>Assessment Results</h3>

                ${results ? `
                        <div class="crla-results-grid crla-grid">
                            <div class="stat-card crla-card-level">
                                <h4 class="u-mt-0">Reading Level</h4>
                                <p class="stat-number stat-number-lg">${orfReadingLevel}</p>
                                <span class="stat-label u-text-md">Based on ASR + ORF comparison</span>
                            </div>

                            <div class="stat-card crla-card-comprehension">
                                <h4 class="u-mt-0">Comprehension Status</h4>
                                <p class="stat-number stat-number-lg">${hasComprehensionScore ? `Scored: ${assessmentRecord.comprehension_score}` : 'Pending'}</p>
                                <span class="stat-label u-text-md">Teacher enters score after the live reading</span>
                            </div>

                            <div class="stat-card crla-card-accuracy">
                                <h4 class="u-mt-0">Accuracy</h4>
                                <p class="stat-number">${safeNumber(profile.accuracy_percentage ?? scoreSummary.accuracy, 2)}%</p>
                                <span class="stat-label">Words correct</span>
                            </div>

                            <div class="stat-card crla-card-wcpm">
                                <h4 class="u-mt-0">WCPM</h4>
                                <p class="stat-number">${safeNumber(profile.wcpm ?? scoreSummary.wcpm, 2)}</p>
                                <span class="stat-label">Words per minute</span>
                            </div>

                            <div class="stat-card crla-card-time">
                                <h4 class="u-mt-0">Time</h4>
                                <p class="stat-number">${Math.floor(readingSeconds / 60)}:${String(readingSeconds % 60).padStart(2, '0')}</p>
                                <span class="stat-label">Reading time</span>
                            </div>
                        </div>

                        <div class="panel u-mt-16">
                            <h4 class="u-mt-0">Miscue Analysis</h4>
                            <div class="miscue-tiles">
                                <div class="miscue-tile miscue-substitution">
                                    <div class="miscue-count">${profile.miscues?.substitutions || scoreSummary.substitutions || 0}</div>
                                    <div class="u-text-muted-xs">Substitutions</div>
                                </div>
                                <div class="miscue-tile miscue-omission">
                                    <div class="miscue-count">${profile.miscues?.omissions || scoreSummary.omissions || 0}</div>
                                    <div class="u-text-muted-xs">Omissions</div>
                                </div>
                                <div class="miscue-tile miscue-insertion">
                                    <div class="miscue-count">${profile.miscues?.insertions || scoreSummary.insertions || 0}</div>
                                    <div class="u-text-muted-xs">Insertions</div>
                                </div>
                                <div class="miscue-tile miscue-repetition">
                                    <div class="miscue-count">${profile.miscues?.repetitions || scoreSummary.repetitions || 0}</div>
                                    <div class="u-text-muted-xs">Repetitions</div>
                                </div>
                            </div>
                        </div>

                        ${hasComprehensionScore && results.interpretation ? `
                            <div class="panel u-mt-16 crla-profile-panel">
                                <h4 class="u-mt-0">CRLA Reading Profile</h4>
                                <p class="u-my-8"><strong>Reading Level:</strong> ${profile.reading_level || assessmentRecord.final_reading_level || 'Pending'}</p>
                                <p class="u-my-8"><strong>Observation Level:</strong> ${profile.observation_level || assessmentRecord.observation_level || 'Pending'}</p>
                                <p class="u-my-8"><strong>Comprehension Score:</strong> ${assessmentRecord.comprehension_score} / 7</p>
                                <p class="u-my-8"><strong>Reading Level:</strong> ${results.interpretation.reading_level_interpretation}</p>
                                <p class="u-my-8"><strong>Fluency Observation:</strong> ${results.interpretation.observation_level_description}</p>
                                ${results.interpretation.intervention_needed ? `
                                    <div class="status-callout danger u-mt-12">
                                        <strong>Intervention Recommended</strong>
                                        <p class="u-mt-6 u-mb-0 u-text-md">This student may benefit from additional support and intervention strategies.</p>
                                    </div>
                                ` : `
                                    <div class="status-callout success u-mt-12">
                                        <strong>âœ“ On Track</strong>
                                        <p class="u-mt-6 u-mb-0 u-text-md">Continue with grade-level instruction and monitor progress.</p>
                                    </div>
                                `}
                            </div>
                        ` : `
                            <div class="panel u-mt-16 status-callout warning">
                                <h4 class="u-mt-0">Comprehension Score Needed</h4>
                                <p class="u-my-8">Reading assessment scoring is complete. Enter the teacher-scored comprehension result to finalize the CRLA reading level.</p>
                            </div>
                        `}

                    <div class="panel u-mt-16">
                        <h4 class="u-mt-0">Transcribed Text</h4>
                        <div class="transcript-box">
                            ${escapeAssessmentHtml(results.transcript || 'No transcript available')}
                        </div>
                    </div>
                ` : `
                    <p>No results available. Please complete the assessment.</p>
                `}

                <div class="assessment-result-actions u-row-wrap u-gap-12 u-mt-20">
                    <button id="new-assessment-btn" class="btn-primary">New Assessment</button>
                    <button id="enter-comprehension-btn" class="btn-secondary">Enter Comprehension Score</button>
                    <button id="view-history-btn" class="btn-secondary">View History</button>
                    <button id="export-assessment-btn" class="btn-export">Export CSV</button>
                    <button id="print-results-btn" class="btn-secondary">Print Results</button>
                </div>

                <div id="assessment-history" class="u-mt-14"></div>
            </div>
        </div>
    `;

    document.getElementById('new-assessment-btn')?.addEventListener('click', () => {
        resetAssessmentState();
        renderAssessment();
    });

    document.getElementById('enter-comprehension-btn')?.addEventListener('click', () => {
        openComprehensionModal({
            activityId,
            assessmentId,
            studentName: assessmentState.student ? `${assessmentState.student.first_name} ${assessmentState.student.last_name}` : '',
            materialTitle: assessmentState.material?.title || assessmentRecord.material_title || 'Assessment',
            assessedAt: assessmentRecord.assessed_at || null,
            comprehensionScore: assessmentRecord.comprehension_score,
            accuracyPercentage: profile.accuracy_percentage ?? scoreSummary.accuracy,
            wcpm: profile.wcpm ?? scoreSummary.wcpm,
            readingLevel: orfReadingLevel,
            finalReadingLevel: profile.reading_level || assessmentRecord.final_reading_level || null
        });
    });

    document.getElementById('view-history-btn')?.addEventListener('click', loadAssessmentHistory);
    document.getElementById('export-assessment-btn')?.addEventListener('click', () => exportAssessmentResults(assessmentId));
    document.getElementById('print-results-btn')?.addEventListener('click', printAssessmentResults);
}

function printAssessmentResults() {
    const results = assessmentState.results;
    if (!results) return;

    const printWindow = window.open('', '', 'height=600,width=800');
    const profile = results?.reading_profile || results?.scores;
    
    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Assessment Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h2 { color: #0c4a6e; border-bottom: 2px solid #0284c7; padding-bottom: 10px; }
                h3 { color: #334155; margin-top: 20px; }
                .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .info-table td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
                .info-table td:first-child { font-weight: bold; width: 30%; }
                .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
                .stat-box { padding: 15px; border: 1px solid #e2e8f0; border-radius: 8px; }
                .stat-label { font-size: 0.9rem; color: #64748b; }
                .stat-value { font-size: 2rem; font-weight: bold; color: #0c4a6e; }
                .miscue-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 15px 0; }
                .miscue-item { text-align: center; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; }
                .miscue-count { font-size: 1.8rem; font-weight: bold; }
                .alert-warning { background: #fef2f2; border-left: 4px solid #dc2626; padding: 12px; margin: 15px 0; }
                .alert-success { background: #f0fdf4; border-left: 4px solid #22c55e; padding: 12px; margin: 15px 0; }
                .transcript { background: #f8fafc; padding: 12px; border-radius: 6px; font-size: 0.95rem; line-height: 1.6; max-height: 300px; overflow-y: auto; }
            </style>
        </head>
        <body>
            <h2>Reading Assessment Report - CRLA Grade 3</h2>
            
            <h3>Student Information</h3>
            <table class="info-table">
                <tr>
                    <td>Student Name:</td>
                    <td>${assessmentState.student?.first_name} ${assessmentState.student?.last_name}</td>
                </tr>
                <tr>
                    <td>LRN:</td>
                    <td>${assessmentState.student?.lrn || 'N/A'}</td>
                </tr>
                <tr>
                    <td>Grade Level:</td>
                    <td>${assessmentState.student?.grade_level || 'N/A'}</td>
                </tr>
                <tr>
                    <td>Assessment Date:</td>
                    <td>${new Date().toLocaleDateString()}</td>
                </tr>
            </table>

            <h3>Reading Profile</h3>
            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-label">Reading Level</div>
                    <div class="stat-value">${profile?.reading_level || 'N/A'}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Observation Level</div>
                    <div class="stat-value">${profile?.observation_level || 'N/A'}</div>
                </div>
            </div>

            <h3>Performance Metrics</h3>
            <table class="info-table">
                <tr>
                    <td>Accuracy:</td>
                    <td>${profile?.accuracy_percentage || results.scores?.accuracy || 0}%</td>
                </tr>
                <tr>
                    <td>WCPM (Words Per Minute):</td>
                    <td>${profile?.wcpm || results.scores?.wcpm || 0}</td>
                </tr>
                <tr>
                    <td>Part 2 Score (Comprehension):</td>
                    <td>${profile?.part2_score || 0} / 25</td>
                </tr>
                <tr>
                    <td>Reading Time:</td>
                    <td>${Math.floor((profile?.reading_time_seconds || results.scores?.time_seconds || 0) / 60)}:${String((profile?.reading_time_seconds || results.scores?.time_seconds || 0) % 60).padStart(2, '0')}</td>
                </tr>
            </table>

            <h3>Miscue Analysis</h3>
            <div class="miscue-grid">
                <div class="miscue-item">
                    <div class="miscue-count">${profile?.miscues?.substitutions || results.scores?.substitutions || 0}</div>
                    <div class="stat-label">Substitutions</div>
                </div>
                <div class="miscue-item">
                    <div class="miscue-count">${profile?.miscues?.omissions || results.scores?.omissions || 0}</div>
                    <div class="stat-label">Omissions</div>
                </div>
                <div class="miscue-item">
                    <div class="miscue-count">${profile?.miscues?.insertions || results.scores?.insertions || 0}</div>
                    <div class="stat-label">Insertions</div>
                </div>
                <div class="miscue-item">
                    <div class="miscue-count">${profile?.miscues?.repetitions || results.scores?.repetitions || 0}</div>
                    <div class="stat-label">Repetitions</div>
                </div>
            </div>

            ${results.interpretation ? `
                <h3>Interpretation</h3>
                <p><strong>Reading Level:</strong> ${results.interpretation.reading_level_interpretation}</p>
                <p><strong>Fluency Observation:</strong> ${results.interpretation.observation_level_description}</p>
                ${results.interpretation.intervention_needed ? `
                    <div class="alert-warning">
                        <strong>Intervention Recommended</strong>
                        <p>This student may benefit from additional support and intervention strategies.</p>
                    </div>
                ` : `
                    <div class="alert-success">
                        <strong>âœ“ On Track</strong>
                        <p>Continue with grade-level instruction and monitor progress.</p>
                    </div>
                `}
            ` : ''}

            <h3>Transcribed Text</h3>
            <div class="transcript">${results.transcript || 'No transcript available'}</div>

            <div class="report-footer">
                <p>Report generated on ${new Date().toLocaleString()}</p>
            </div>
        </body>
        </html>
    `;
    
    printWindow.document.write(htmlContent);
    printWindow.document.close();
    setTimeout(() => {
        printWindow.print();
    }, 250);
}

async function loadAssessmentHistory() {
    if (!assessmentState.student?.student_id) return;
    const target = document.getElementById('assessment-history');
    if (!target) return;

    target.innerHTML = '<p class="u-text-muted">Loading history...</p>';

    try {
        const data = await fetchJson(`php/api/shared/assessment.php?action=history&student_id=${encodeURIComponent(assessmentState.student.student_id)}`);
        if (!data.success || !Array.isArray(data.history) || data.history.length === 0) {
            target.innerHTML = '<p class="u-text-muted">No assessment history found for this student yet.</p>';
            return;
        }

        target.innerHTML = `
            <div class="panel">
                <h4 class="u-mt-0">Recent History</h4>
                <div class="u-stack-sm">
                    ${data.history.map(item => `
                        <div class="history-item">
                            <strong>${escapeAssessmentHtml(item.material_title || 'Material')}</strong>
                            <div class="u-text-muted-xs u-mt-4">
                                Accuracy: ${Number(item.accuracy_percentage || 0).toFixed(2)}% | WCPM: ${Number(item.wcpm || 0).toFixed(2)} | Level: ${escapeAssessmentHtml(item.reading_level || 'N/A')}
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    } catch (error) {
        target.innerHTML = `<p class="u-text-danger">Failed to load history: ${escapeAssessmentHtml(error.message)}</p>`;
    }
}

// ============================================================
// 5. ASSESSMENT - Submission & State Management
// ============================================================

async function submitAssessment() {
    if (!assessmentState.audioBlob) {
        alert('Please record audio first.');
        return;
    }
    if (!assessmentState.student?.student_id || !assessmentState.material?.material_id) {
        alert('Student and material are required.');
        return;
    }

    const submitBtn = document.getElementById('submit-assessment-btn');
    const statusEl = document.getElementById('recording-status');
    if (submitBtn) submitBtn.disabled = true;
    if (statusEl) statusEl.textContent = 'Submitting audio and processing CRLA assessment...';

    try {
        const formData = new FormData();
        formData.append('audio', assessmentState.audioBlob, 'reading.webm');
        formData.append('student_id', String(assessmentState.student.student_id));
        formData.append('material_id', String(assessmentState.material.material_id));
        formData.append('duration_seconds', String(Math.max(1, assessmentState.seconds || 1)));
        formData.append('original_text', String(assessmentState.material.ocr_text || ''));

        const response = await fetch('php/api/shared/assessment.php?action=record', {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Assessment processing failed.');
        }

        assessmentState.results = data;
        assessmentState.step = 4;

        await loadAssessments();
        await loadDashboardData();
        renderAssessment();
    } catch (error) {
        if (statusEl) statusEl.textContent = 'Submission failed. Please try again.';
        alert('Assessment error: ' + error.message);
        if (submitBtn) submitBtn.disabled = false;
    }
}

function resetAssessmentState() {
    stopAssessmentRecording(true);
    if (assessmentState.playbackUrl) {
        URL.revokeObjectURL(assessmentState.playbackUrl);
        assessmentState.playbackUrl = null;
    }

    assessmentState.step = 1;
    assessmentState.student = null;
    assessmentState.material = null;
    assessmentState.audioBlob = null;
    assessmentState.recording = false;
    assessmentState.mediaRecorder = null;
    assessmentState.audioChunks = [];
    assessmentState.results = null;
    assessmentState.seconds = 0;
    delete assessmentState.crlaData;
}

function cleanupAssessmentOnLeave() {
    stopAssessmentRecording(true);
}

function escapeAssessmentHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// ============================================================
// 6. REPORTING & ANALYTICS
// ============================================================

// ============================================================
// 7. TEACHER MANAGEMENT (for principals/admins)
// ============================================================

async function loadTeachers() {
    if (state.user?.role !== 'principal' && state.user?.role !== 'admin') {
        return;
    }
    
    try {
        const data = await fetchJson('php/api/principal/teachers.php?action=list');
        if (data.success) {
            state.teachers = data.teachers || [];
            if (state.activeView === 'teachers') {
                renderTeachers();
            }
        }
    } catch (error) {
        console.error('Error loading teachers:', error);
        alert('Error loading teachers: ' + error.message);
    }
}

function renderTeachers() {
    const teachers = state.teachers || [];
    
    viewContainer.innerHTML = `
        <div class="teachers-shell">

            <div class="page-header u-mb-24">
                <div>
                    <h2 style="font-family: 'Inter', sans-serif; font-size: 24px; font-weight: 700; color: #0f172a;">Teachers List</h2>
                    <p class="subtitle" style="margin-top: 4px; color: #64748b;">Manage teaching staff and view their advisory classes.</p>
                </div>
            </div>

            <div class="u-row-between u-mb-24" style="gap: 16px; flex-wrap: wrap;">
                <div class="teachers-search-wrap" style="display: flex; gap: 12px; flex: 1; max-width: 600px;">
                    <div style="position: relative; flex: 1;">
                        <span style="position: absolute; left: 12px; top: 10px; color: #94a3b8;">🔍</span>
                        <input type="text" id="search-teacher" placeholder="Search by name or email" class="input-inline-fill" style="background: #fff; padding-left: 36px; width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; height: 40px; font-size: 14px;">
                    </div>
                    <select id="filter-teacher-grade" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 0 16px; height: 40px; background: #fff; color: #475569; font-size: 14px; cursor: pointer;">
                        <option value="">All Grades</option>
                        <option value="Grade 1">Grade 1</option>
                        <option value="Grade 2">Grade 2</option>
                        <option value="Grade 3">Grade 3</option>
                        <option value="Grade 4">Grade 4</option>
                        <option value="Grade 5">Grade 5</option>
                        <option value="Grade 6">Grade 6</option>
                    </select>
                </div>
                <div class="teachers-toolbar-actions">
                    <button id="add-teacher-btn" class="btn-primary" style="background: #1e40af; display: flex; align-items: center; gap: 8px; height: 40px;">➕ Add Teacher</button>
                    <button id="refresh-teachers-btn" class="btn-secondary" style="height: 40px;">Refresh</button>
                </div>
            </div>

            <div class="panel" style="padding: 0; overflow: hidden;">
                <table class="table-clean">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #f1f5f9;">
                            <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">NAME</th>
                            <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">EMAIL</th>
                            <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">DEPARTMENT</th>
                            <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px; text-align: center;">STUDENTS</th>
                            <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px; text-align: center;">STATUS</th>
                            <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px; text-align: center;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${teachers.length ? teachers.map(teacher => `
                            <tr class="table-row" data-grade="${escapeAssessmentHtml(teacher.grade_level || '')}" style="background: #fff; transition: background 0.2s;">
                                <td style="padding: 16px 20px;"><strong>${escapeAssessmentHtml(teacher.first_name)} ${escapeAssessmentHtml(teacher.last_name)}</strong></td>
                                <td style="padding: 16px 20px; color: #64748b;">${escapeAssessmentHtml(teacher.email || 'N/A')}</td>
                                <td style="padding: 16px 20px; color: #475569;">${escapeAssessmentHtml(teacher.department || 'N/A')}</td>
                                <td style="padding: 16px 20px; text-align: center; font-weight: 600;">${teacher.student_count || 0}</td>
                                <td style="padding: 16px 20px; text-align: center;">
                                    <span class="teacher-status-badge ${teacher.status === 'Active' ? 'is-active' : 'is-inactive'}">
                                        ${teacher.status || 'Inactive'}
                                    </span>
                                </td>
                                <td style="padding: 16px 20px; text-align: center;">
                                    <button class="action-btn action-btn-primary view-teacher" data-id="${teacher.teacher_id}" title="View details" style="color: #1e40af; border-color: #bfdbfe; background: #eff6ff;">View Classes</button>
                                    <button class="action-btn action-btn-secondary edit-teacher" data-id="${teacher.teacher_id}" title="Edit">Edit</button>
                                </td>
                            </tr>
                        `).join('') : `
                            <tr>
                                <td colspan="6" class="u-cell-20 u-ta-center u-text-muted">No teachers found</td>
                            </tr>
                        `}
                    </tbody>
                </table>
            </div>

            <!-- Add Teacher Modal -->
            <div id="add-teacher-modal" class="modal hidden">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Add New Teacher</h3>
                        <button class="close-modal">×</button>
                    </div>
                    <form id="add-teacher-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name *</label>
                                <input type="text" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name *</label>
                                <input type="text" name="last_name" required>
                            </div>
                            <div class="form-group">
                                <label>Grade Level *</label>
                                <select name="grade_level" required>
                                    <option value="">Select Grade</option>
                                    <option value="Grade 1">Grade 1</option>
                                    <option value="Grade 2">Grade 2</option>
                                    <option value="Grade 3">Grade 3</option>
                                    <option value="Grade 4">Grade 4</option>
                                    <option value="Grade 5">Grade 5</option>
                                    <option value="Grade 6">Grade 6</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Section *</label>
                                <input type="text" name="section" placeholder="e.g. Section A" required>
                            </div>
                            <div class="form-group">
                                <label>Department (optional)</label>
                                <input type="text" name="department" placeholder="e.g. Elementary">
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="btn-primary">Create Teacher</button>
                            <button type="button" class="btn-secondary close-modal">Cancel</button>
                        </div>
                    </form>
                    <div id="add-teacher-result" class="u-mt-12"></div>
                </div>
            </div>
        </div>
    `;

    // 1. Search & Filter Logic
    const applyTeacherFilters = () => {
        const term = document.getElementById('search-teacher')?.value.toLowerCase() || '';
        const grade = document.getElementById('filter-teacher-grade')?.value || '';
        
        document.querySelectorAll('.teachers-shell tbody tr').forEach(row => {
            if (row.children.length === 1) return; // Skip empty state row
            
            const text = row.textContent.toLowerCase();
            const rowGrade = row.dataset.grade || '';
            
            const matchesSearch = text.includes(term);
            const matchesGrade = grade === '' || rowGrade.includes(grade);
            
            row.style.display = (matchesSearch && matchesGrade) ? '' : 'none';
        });
    };

    document.getElementById('search-teacher')?.addEventListener('input', applyTeacherFilters);
    document.getElementById('filter-teacher-grade')?.addEventListener('change', applyTeacherFilters);

    // 2. Button Listeners
    document.getElementById('add-teacher-btn')?.addEventListener('click', () => {
        showModal('add-teacher-modal');
        document.getElementById('add-teacher-result').innerHTML = '';
        document.getElementById('add-teacher-form').reset();
    });

    document.getElementById('refresh-teachers-btn')?.addEventListener('click', loadTeachers);

    // 3. View/Edit teacher actions
    document.querySelectorAll('.view-teacher').forEach(btn => {
        btn.addEventListener('click', () => {
            state.selectedTeacherId = btn.dataset.id;
            state.selectedTeacherClass = null; // Reset class selection
            setActiveView('teacher-detail');
        });
    });

    document.querySelectorAll('.edit-teacher').forEach(btn => {
        btn.addEventListener('click', () => {
            state.selectedTeacherId = btn.dataset.id;
            setActiveView('teacher-edit');
        });
    });

    // 4. Modal and Form Handlers
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', async () => {
            const modal = btn.closest('.modal');
            if (modal?.id) await requestCloseModal(modal.id);
        });
    });

    document.getElementById('add-teacher-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const data = {
            first_name: form.first_name.value.trim(),
            last_name: form.last_name.value.trim(),
            grade_level: form.grade_level.value,
            section: form.section.value.trim(),
            department: form.department.value.trim() || 'Elementary'
        };

        const resultDiv = document.getElementById('add-teacher-result');

        if (!data.first_name || !data.last_name || !data.grade_level || !data.section) {
            resultDiv.innerHTML = '<p class="u-text-danger">Please fill in all required fields.</p>';
            return;
        }

        resultDiv.innerHTML = '<p class="u-text-muted">Creating teacher...</p>';

        try {
            const response = await fetchJson('php/api/principal/teachers.php?action=create', {
                method: 'POST', body: JSON.stringify(data)
            });

            if (response.success) {
                resultDiv.innerHTML = `<p class="u-text-success">✅ ${response.message}<br>
                    Username: <strong>${response.teacher.username}</strong><br>
                    Email: <strong>${response.teacher.email}</strong><br>
                    Default password: <strong>stacruzCen3lem</strong></p>`;
                await loadTeachers();
                setTimeout(() => closeModal('add-teacher-modal'), 3000);
            } else {
                resultDiv.innerHTML = `<p class="u-text-danger">${response.message || 'Failed to create teacher.'}</p>`;
            }
        } catch (error) {
            resultDiv.innerHTML = `<p class="u-text-danger">Error: ${error.message}</p>`;
        }
    });
}

async function renderTeacherDetail(teacherId) {
    if (!teacherId) {
        viewContainer.innerHTML = '<p class="u-text-danger">Teacher not selected.</p>';
        return;
    }

    // Only show spinner if not already rendering the view (prevents flickering)
    if (!document.querySelector('.teacher-detail-shell')) {
        viewContainer.innerHTML = '<div class="spinner u-text-center u-mt-20">⏳ Loading teacher data...</div>';
    }

    try {
        const [teacherData, studentsData] = await Promise.all([
            fetchJson(`php/api/principal/teachers.php?action=get&id=${encodeURIComponent(teacherId)}`),
            fetchJson(`php/api/principal/teachers.php?action=students&id=${encodeURIComponent(teacherId)}`)
        ]);
        
        if (!teacherData.success || !teacherData.teacher) {
            viewContainer.innerHTML = '<p class="u-text-danger">Teacher not found.</p>';
            return;
        }
        
        const teacher = teacherData.teacher;
        const students = studentsData.success && studentsData.students ? studentsData.students : [];

        // Group students by Class and Section
        const classesMap = {};
        students.forEach(s => {
            const grade = s.grade_level || 'Unassigned';
            const section = s.section || 'General';
            const classKey = `${grade} - ${section}`;
            
            if (!classesMap[classKey]) {
                classesMap[classKey] = { grade, section, students: [] };
            }
            classesMap[classKey].students.push(s);
        });

        const classesList = Object.values(classesMap).sort((a, b) => a.grade.localeCompare(b.grade));

        // ----------------------------------------------------
        // VIEW A: Teacher Profile + Class Cards
        // ----------------------------------------------------
        if (!state.selectedTeacherClass) {
            viewContainer.innerHTML = `
                <div class="teacher-detail-shell">
                    <!-- Header -->
                    <div class="u-row-between u-mb-24">
                        <div style="display: flex; align-items: center; gap: 16px;">
                            <button class="btn-secondary" id="back-to-teachers" style="padding: 8px 12px;">← Back to Directory</button>
                            <div>
                                <h2 class="u-m-0" style="font-family: 'Inter', sans-serif; font-size: 24px; font-weight: 700; color: #0f172a;">${escapeAssessmentHtml(teacher.first_name)} ${escapeAssessmentHtml(teacher.last_name)}</h2>
                                <span class="u-text-muted">${escapeAssessmentHtml(teacher.email || 'No email')} • ${escapeAssessmentHtml(teacher.department || 'General Dept')}</span>
                            </div>
                        </div>
                    </div>

                    <h3 style="color: #0f172a; margin-bottom: 12px;">Advisory Classes</h3>
                    
                    <div class="grid three">
                        ${classesList.map(cls => `
                            <div class="stat-card" style="cursor: pointer; padding: 24px;" onclick="state.selectedTeacherClass = '${cls.grade} - ${cls.section}'; renderTeacherDetail('${teacherId}');">
                                <div class="stat-card-icon" style="background: #eff6ff; color: #1e40af; width: 54px; height: 54px;">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </div>
                                <div class="stat-card-content">
                                    <h4 style="color: #0f172a; font-size: 1.25rem; font-weight: 700; margin-bottom: 4px;">${escapeAssessmentHtml(cls.grade)}</h4>
                                    <div class="stat-label" style="font-size: 0.95rem; color: #64748b;">Section: ${escapeAssessmentHtml(cls.section)}</div>
                                    <div class="stat-label u-mt-12"><strong style="color: #1e40af; font-size: 1rem;">${cls.students.length}</strong> Enrolled Students</div>
                                </div>
                            </div>
                        `).join('')}
                        ${classesList.length === 0 ? `
                            <div class="empty-state u-col-span-full panel">
                                <p>No students assigned to this teacher yet.</p>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;

            document.getElementById('back-to-teachers')?.addEventListener('click', () => {
                state.selectedTeacherId = null;
                setActiveView('teachers');
            });
        }
        // ----------------------------------------------------
        // VIEW B: Student Spreadsheet for Selected Class
        // ----------------------------------------------------
        else {
            const currentClass = classesMap[state.selectedTeacherClass];
            if (!currentClass) {
                state.selectedTeacherClass = null; 
                renderTeacherDetail(teacherId);
                return;
            }

            viewContainer.innerHTML = `
                <div class="teacher-detail-shell">
                    <div class="page-header u-mb-16">
                        <div class="u-row-between" style="width: 100%;">
                            <div style="display: flex; align-items: center; gap: 16px;">
                                <button class="btn-secondary" onclick="state.selectedTeacherClass = null; renderTeacherDetail('${teacherId}');" style="padding: 8px 12px;">← Back to Classes</button>
                                <div>
                                    <h2 style="font-family: 'Inter', sans-serif; font-size: 24px; font-weight: 700; color: #0f172a;">${escapeAssessmentHtml(currentClass.grade)} - ${escapeAssessmentHtml(currentClass.section)}</h2>
                                    <p class="subtitle" style="margin-top: 4px;">Teacher: ${escapeAssessmentHtml(teacher.first_name)} ${escapeAssessmentHtml(teacher.last_name)}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="search-bar u-mb-16">
                        <div style="position: relative; max-width: 400px; width: 100%;">
                            <span style="position: absolute; left: 12px; top: 10px; color: #94a3b8;">🔍</span>
                            <input type="text" id="roster-search-input" placeholder="Search students in class..." class="input-inline-fill" style="background: #fff; padding-left: 36px;">
                        </div>
                    </div>

                    <div class="panel" style="padding: 0; overflow: hidden; border-radius: 12px;">
                        <div class="u-scroll-x">
                            <table class="table-clean" style="margin: 0; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #fff; border-bottom: 2px solid #f1f5f9;">
                                        <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">ID</th>
                                        <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">NAME</th>
                                        <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px; text-align: center;">READING LEVEL</th>
                                        <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px; text-align: center;">ASSESSMENTS</th>
                                        <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px; text-align: center;">AVG ACCURACY</th>
                                        <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px; text-align: center;">AVG WCPM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${currentClass.students.map(student => {
                                        const firstI = student.first_name ? student.first_name.charAt(0).toUpperCase() : '';
                                        const lastI = student.last_name ? student.last_name.charAt(0).toUpperCase() : '';
                                        
                                        // --- NEW: Client Spec Reading Level Logic ---
                                        const rawLvl = student.reading_level || 'Pending';
                                        let lvl = rawLvl;
                                        let badgeClass = 'status-default'; // Gray
                                        const lowerLvl = rawLvl.toLowerCase();

                                        if (lowerLvl.includes('reading at grade level') || lowerLvl === 'grade level') {
                                            lvl = 'Reading At Grade Level';
                                            badgeClass = 'status-success'; // Green
                                        } 
                                        else if (lowerLvl.includes('transitioning')) {
                                            lvl = 'Transitioning Reader';
                                            badgeClass = 'status-info'; // Blue
                                        } 
                                        else if (lowerLvl.includes('developing')) {
                                            lvl = 'Developing Reader';
                                            badgeClass = 'status-warning'; // Yellow
                                        } 
                                        else if (lowerLvl.includes('high emerging')) {
                                            lvl = 'High Emerging Reader';
                                            badgeClass = 'status-orange'; // Orange
                                        } 
                                        else if (lowerLvl.includes('low emerging') || lowerLvl.includes('emerging')) {
                                            lvl = 'Low Emerging Reader';
                                            badgeClass = 'status-danger'; // Red
                                        }

                                        const rawAcc = student.accuracy_percentage || student.avg_accuracy || 0;
                                        const accColor = rawAcc >= 80 ? '#059669' : (rawAcc >= 65 ? '#d97706' : '#dc2626');

                                        return `
                                        <tr class="table-row student-row" style="background: #fff; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'" onclick="state.selectedStudentId = '${student.student_id}'; setActiveView('student-detail');">
                                            <td style="padding: 16px 20px; color: #94a3b8; font-family: 'JetBrains Mono', monospace; font-size: 13px;">${escapeAssessmentHtml(student.lrn || '-')}</td>
                                            <td style="padding: 16px 20px;">
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #eff6ff; color: #1e40af; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px;">${escapeAssessmentHtml(firstI)}${escapeAssessmentHtml(lastI)}</div>
                                                    <strong style="color: #0f172a; font-weight: 600;">${escapeAssessmentHtml(student.first_name)} ${escapeAssessmentHtml(student.last_name)}</strong>
                                                </div>
                                            </td>
                                            <td style="padding: 16px 20px; text-align: center;">
                                                <!-- NEW: Using the CSS classes instead of inline styles! -->
                                                <span class="status-badge ${badgeClass}">${escapeAssessmentHtml(lvl)}</span>
                                            </td>
                                            <td style="padding: 16px 20px; text-align: center; color: #475569; font-weight: 600;">${escapeAssessmentHtml(String(student.assessment_count || 0))}</td>
                                            <td style="padding: 16px 20px; text-align: center; color: ${rawAcc > 0 ? accColor : '#94a3b8'}; font-weight: 700;">${rawAcc > 0 ? Math.round(rawAcc) + '%' : '-'}</td>
                                            <td style="padding: 16px 20px; text-align: center; color: #0f172a; font-family: 'JetBrains Mono', monospace; font-weight: 600;">${Number(student.avg_wcpm || 0).toFixed(1)}</td>
                                        </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            // Search logic inside the specific class
            document.getElementById('roster-search-input')?.addEventListener('input', (e) => {
                const term = e.target.value.toLowerCase();
                document.querySelectorAll('.student-row').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });
        }

    } catch (error) {
        viewContainer.innerHTML = `<p class="u-text-danger">Error loading teacher: ${escapeAssessmentHtml(error.message)}</p>`;
    }
}

async function viewTeacherDetails(teacherId) {
    try {
        // Get teacher basic info
        const teacherData = await fetchJson(`php/api/principal/teachers.php?action=get&id=${encodeURIComponent(teacherId)}`);
        if (!teacherData.success || !teacherData.teacher) {
            alert('Teacher not found');
            return;
        }

        const teacher = teacherData.teacher;
        const titleEl = document.getElementById('view-teacher-title');
        if (titleEl) titleEl.textContent = `${teacher.first_name} ${teacher.last_name}`;

        const contentEl = document.getElementById('view-teacher-content');
        if (!contentEl) return;

        // Show loading state
        contentEl.innerHTML = `
            <div class="u-text-center u-py-20">
                <p class="u-text-muted">Loading teacher data...</p>
            </div>
        `;

        // Fetch students for this specific teacher
        const studentsResponse = await fetchJson(`php/api/principal/teachers.php?action=students&id=${encodeURIComponent(teacherId)}`);
        const students = studentsResponse.success && studentsResponse.students ? studentsResponse.students : [];

        // Fetch assessments for this teacher's students
        const assessmentsData = await fetchJson(`php/api/shared/assessment.php?action=teacher&teacher_id=${encodeURIComponent(teacherId)}`);
        const assessments = assessmentsData.success && assessmentsData.assessments ? assessmentsData.assessments : [];

        // Calculate statistics from the students data
        const totalStudents = students.length;
        const totalAssessments = students.reduce((sum, s) => sum + (s.assessment_count || 0), 0);
        const avgWcpm = students.length > 0 
            ? students.reduce((sum, s) => sum + (Number(s.avg_wcpm) || 0), 0) / students.length 
            : 0;

        // Build the content
        contentEl.innerHTML = `
            <div class="u-stack-16">
                <!-- Teacher Info -->
                <div class="u-grid-2 u-gap-12">
                    <div>
                        <p class="u-text-muted-xs u-mb-4 u-m-0">Full Name</p>
                        <p class="u-m-0 u-fw-600">${teacher.first_name} ${teacher.last_name}</p>
                    </div>
                    <div>
                        <p class="u-text-muted-xs u-mb-4 u-m-0">Email</p>
                        <p class="u-m-0">${teacher.email || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="u-text-muted-xs u-mb-4 u-m-0">Department</p>
                        <p class="u-m-0">${teacher.department || 'N/A'}</p>
                    </div>
                    <div>
                        <p class="u-text-muted-xs u-mb-4 u-m-0">Status</p>
                        <p class="u-m-0"><span class="teacher-status-badge ${teacher.status === 'Active' ? 'is-active' : 'is-inactive'}">${teacher.status}</span></p>
                    </div>
                </div>

                <!-- Statistics Summary -->
                <div class="u-border-top u-pt-12">
                    <h4 class="u-m-0 u-mb-8">Class Statistics</h4>
                    <div class="u-grid-3 u-gap-8">
                        <div class="metric-chip">
                            <p class="u-m-0 u-text-muted-xs">Students</p>
                            <p class="u-m-0 metric-number">${totalStudents}</p>
                        </div>
                        <div class="metric-chip">
                            <p class="u-m-0 u-text-muted-xs">Assessments</p>
                            <p class="u-m-0 metric-number">${totalAssessments}</p>
                        </div>
                        <div class="metric-chip">
                            <p class="u-m-0 u-text-muted-xs">Average WCPM</p>
                            <p class="u-m-0 metric-number">${avgWcpm.toFixed(1)}</p>
                        </div>
                    </div>
                </div>

                <!-- Students List (Only this teacher's students) -->
                <div class="u-border-top u-pt-12">
                    <h4 class="u-m-0 u-mb-8">Students (${totalStudents})</h4>
                    ${students.length ? `
                        <div class="u-max-h-300 u-scroll-y">
                            <table class="table-clean table-sm">
                                <thead>
                                    <tr>
                                        <th class="u-ta-left">Name</th>
                                        <th class="u-ta-left">LRN</th>
                                        <th class="u-ta-center">Grade</th>
                                        <th class="u-ta-center">Section</th>
                                        <th class="u-ta-center">Assessments</th>
                                        <th class="u-ta-center">Avg WCPM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${students.map(student => `
                                        <tr>
                                            <td class="u-ta-left"><strong>${student.first_name || ''} ${student.last_name || ''}</strong></td>
                                            <td class="u-ta-left">${student.lrn || 'N/A'}</td>
                                            <td class="u-ta-center">${student.grade_level || 'N/A'}</td>
                                            <td class="u-ta-center">${student.section || 'N/A'}</td>
                                            <td class="u-ta-center">${student.assessment_count || 0}</td>
                                            <td class="u-ta-center">${Number(student.avg_wcpm || 0).toFixed(1)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : `
                        <p class="u-text-muted">No students assigned to this teacher.</p>
                    `}
                </div>

                <!-- Recent Assessments -->
                <div class="u-border-top u-pt-12">
                    <h4 class="u-m-0 u-mb-8">Recent Assessments (${assessments.length})</h4>
                    ${assessments.length ? `
                        <div class="u-max-h-300 u-scroll-y">
                            <table class="table-clean table-sm">
                                <thead>
                                    <tr>
                                        <th class="u-ta-left">Student</th>
                                        <th class="u-ta-left">Material</th>
                                        <th class="u-ta-center">Date</th>
                                        <th class="u-ta-center">WCPM</th>
                                        <th class="u-ta-center">Accuracy</th>
                                        <th class="u-ta-center">Level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${assessments.slice(0, 20).map(assessment => `
                                        <tr>
                                            <td class="u-ta-left"><strong>${assessment.student_name || `${assessment.first_name || ''} ${assessment.last_name || ''}`}</strong></td>
                                            <td class="u-ta-left">${assessment.material_title || 'N/A'}</td>
                                            <td class="u-ta-center">${assessment.assessed_at ? new Date(assessment.assessed_at).toLocaleDateString() : 'N/A'}</td>
                                            <td class="u-ta-center">${Number(assessment.wcpm || 0).toFixed(1)}</td>
                                            <td class="u-ta-center">${Number(assessment.accuracy_percentage || 0).toFixed(1)}%</td>
                                            <td class="u-ta-center"><span class="status-badge ${(assessment.reading_level || '').toLowerCase() === 'frustration' ? 'status-danger' : (assessment.reading_level || '').toLowerCase() === 'instructional' ? 'status-warning' : 'status-success'}">${assessment.reading_level || 'N/A'}</span></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                            ${assessments.length > 20 ? `<p class="u-text-muted-xs u-mt-8">Showing 20 of ${assessments.length} assessments</p>` : ''}
                        </div>
                    ` : `
                        <p class="u-text-muted">No assessments recorded for this teacher's students.</p>
                    `}
                </div>
            </div>

            <div class="modal-actions u-mt-16">
                <button class="btn-primary" onclick="editTeacher(${teacher.teacher_id})">Edit Teacher</button>
                <button class="btn-secondary close-modal">Close</button>
            </div>
        `;

        // Re-bind close modal buttons
        contentEl.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', () => {
                requestCloseModal('view-teacher-modal');
            });
        });

        showModal('view-teacher-modal');

    } catch (error) {
        console.error('Error loading teacher details:', error);
        const contentEl = document.getElementById('view-teacher-content');
        if (contentEl) {
            contentEl.innerHTML = `<p class="u-text-danger">Error loading teacher details: ${escapeAssessmentHtml(error.message)}</p>`;
        }
    }
}

async function renderTeacherEdit(teacherId) {
    if (!teacherId) {
        viewContainer.innerHTML = '<p class="u-text-danger">Teacher not selected.</p>';
        return;
    }

    try {
        const data = await fetchJson(`php/api/principal/teachers.php?action=get&id=${encodeURIComponent(teacherId)}`);
        if (!data.success || !data.teacher) {
            viewContainer.innerHTML = '<p class="u-text-danger">Teacher not found.</p>';
            return;
        }
        const teacher = data.teacher;

        viewContainer.innerHTML = `
            <div class="teacher-edit-shell">
                <div class="u-row-between u-mb-16">
                    <button class="btn-secondary" id="back-to-teachers-edit">← Back to Teachers</button>
                    <h2 class="u-m-0">Edit Teacher</h2>
                </div>

                <div class="panel">
                    <form id="edit-teacher-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" value="${escapeAssessmentHtml(teacher.first_name || '')}" required>
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" value="${escapeAssessmentHtml(teacher.last_name || '')}" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="${escapeAssessmentHtml(teacher.email || '')}" required>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status">
                                    <option value="Active" ${teacher.status === 'Active' ? 'selected' : ''}>Active</option>
                                    <option value="Inactive" ${teacher.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-actions u-mt-16">
                            <button type="submit" class="btn-primary">Save Changes</button>
                            <button type="button" class="btn-secondary" id="cancel-edit-teacher">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        document.getElementById('back-to-teachers-edit')?.addEventListener('click', () => {
            state.selectedTeacherId = null;
            setActiveView('teachers');
        });

        document.getElementById('cancel-edit-teacher')?.addEventListener('click', () => {
            state.selectedTeacherId = null;
            setActiveView('teachers');
        });

        document.getElementById('edit-teacher-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const payload = {
                first_name: form.first_name.value,
                last_name: form.last_name.value,
                email: form.email.value,
                status: form.status.value
            };

            try {
                const result = await fetchJson(`php/api/principal/teachers.php?id=${encodeURIComponent(teacherId)}`, {
                    method: 'PUT',
                    body: JSON.stringify(payload)
                });

                if (result.success) {
                    showToast('Teacher updated successfully!', 'success');
                    await loadTeachers();
                    state.selectedTeacherId = null;
                    setActiveView('teachers');
                } else {
                    showToast(result.message || 'Update failed.', 'error');
                }
            } catch (error) {
                showToast('Error updating teacher: ' + error.message, 'error');
            }
        });

    } catch (error) {
        viewContainer.innerHTML = `<p class="u-text-danger">Error loading teacher: ${escapeAssessmentHtml(error.message)}</p>`;
    }
}

async function editTeacher(teacherId) {
    try {
        const data = await fetchJson(`php/api/principal/teachers.php?action=get&id=${encodeURIComponent(teacherId)}`);
        if (!data.success || !data.teacher) {
            alert('Teacher not found');
            return;
        }

        const teacher = data.teacher;
        const contentEl = document.getElementById('view-teacher-content');
        if (contentEl) {
            contentEl.innerHTML = `
                <form id="edit-teacher-form">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="${teacher.first_name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="${teacher.last_name || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="${teacher.email || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" value="${teacher.department || ''}">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="Active" ${teacher.status === 'Active' ? 'selected' : ''}>Active</option>
                            <option value="Inactive" ${teacher.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary">Save Changes</button>
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                    </div>
                </form>
            `;

            document.getElementById('edit-teacher-form')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const form = e.target;
                const payload = {
                    first_name: form.first_name.value,
                    last_name: form.last_name.value,
                    email: form.email.value,
                    department: form.department.value,
                    status: form.status.value
                };

                try {
                    const result = await fetchJson(`php/api/principal/teachers.php?id=${encodeURIComponent(teacherId)}`, {
                        method: 'PUT',
                        body: JSON.stringify(payload)
                    });

                    if (result.success) {
                        alert('Teacher updated successfully');
                        await loadTeachers();
                        closeModal('view-teacher-modal');
                    } else {
                        alert('' + result.message);
                    }
                } catch (error) {
                    alert('Error updating teacher: ' + error.message);
                }
            });
        }
    } catch (error) {
        alert('Error loading teacher for edit: ' + error.message);
    }
}

async function populateExportClassDropdown() {
    const select = document.getElementById('export-class-select');
    if (!select) return;

    // Fetch classes for the current teacher (or all for principal)
    try {
        const data = await fetchJson('php/api/teacher/classes.php?action=list');
        if (data.success && data.classes) {
            select.innerHTML = '<option value="">All Classes</option>' +
                data.classes.map(cls => `<option value="${cls.class_id}">${cls.class_name}</option>`).join('');
        } else {
            select.innerHTML = '<option value="">No classes found</option>';
        }
    } catch (error) {
        console.warn('Could not load classes:', error);
        select.innerHTML = '<option value="">Error loading classes</option>';
    }
}

// ============================================================
// 6. REPORTING & ANALYTICS (Continued)
// ============================================================

function renderReports() {
    const role = String(state.user?.role || '').toLowerCase();

    // Unified report view with export class report button
    viewContainer.innerHTML = `
        <div class="reports-shell">
            <div class="reports-toolbar">
                <div class="u-row-wrap u-gap-8">
                    <button id="report-school-wide" class="btn-primary">School Overview</button>
                    <button id="report-by-grade" class="btn-secondary">By Grade Level</button>
                    <button id="report-materials" class="btn-secondary">Material Usage</button>
                    <button id="export-class-report-btn" class="btn-export">📊 Export Class Report</button>
                    <button id="refresh-reports-btn" class="btn-secondary">Refresh</button>
                </div>
            </div>

            <div id="report-content" class="u-mt-16">
                <div class="panel">
                    <p class="u-text-muted">Select a report type to view</p>
                </div>
            </div>
        </div>

        <!-- Export Class Report Modal -->
        <div id="export-class-modal" class="modal hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Export Class Report</h3>
                    <button class="close-modal">x</button>
                </div>
                <form id="export-class-form">
                    <!-- Scope Toggle -->
                    <div class="form-group" style="background: #f8fafc; padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                        <label style="margin-bottom: 8px; font-weight: 600;">Report Scope</label>
                        <div style="display: flex; gap: 16px;">
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer;">
                                <input type="radio" name="export_scope" value="class" checked> Specific Class
                            </label>
                            <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer;">
                                <input type="radio" name="export_scope" value="grade"> Entire Grade
                            </label>
                        </div>
                    </div>
                    <!-- Grade Dropdown (Hidden by default) -->
                    <div class="form-group hidden" id="group-grade-select" style="display: none;">
                        <label for="export-grade-select">Select Grade Level</label>
                        <select id="export-grade-select" name="grade_level">
                            <option value="">All Grades</option>
                            <option value="Grade 1">Grade 1</option>
                            <option value="Grade 2">Grade 2</option>
                            <option value="Grade 3">Grade 3</option>
                            <option value="Grade 4">Grade 4</option>
                            <option value="Grade 5">Grade 5</option>
                            <option value="Grade 6">Grade 6</option>
                        </select>
                    </div>
                    <!-- Language Filter (Always visible) -->
                    <div class="form-group">
                        <label for="export-language-select">Target Language</label>
                        <select id="export-language-select" name="language">
                            <option value="">All Languages</option>
                            <option value="English">English</option>
                            <option value="Filipino">Filipino</option>
                        </select>
                    </div>

                    <div class="modal-actions">
                        <button type="submit" class="btn-primary">Generate & Download CSV</button>
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;

    // Event listeners for existing buttons
    document.getElementById('report-school-wide')?.addEventListener('click', () => loadAndDisplayReport('school-wide'));
    document.getElementById('report-by-grade')?.addEventListener('click', () => loadAndDisplayReport('class-performance'));
    document.getElementById('report-materials')?.addEventListener('click', () => loadAndDisplayReport('material-usage'));
    document.getElementById('refresh-reports-btn')?.addEventListener('click', () => renderReports());

// Export button
    document.getElementById('export-class-report-btn')?.addEventListener('click', () => {
        populateExportClassDropdown();
        showModal('export-class-modal');
    });

    // Close modal
    document.querySelectorAll('#export-class-modal .close-modal').forEach(btn => {
        btn.addEventListener('click', () => closeModal('export-class-modal'));
    });

    // Handle scope toggle (Class vs Grade)
    document.querySelectorAll('input[name="export_scope"]').forEach(radio => {
        radio.addEventListener('change', (e) => {
            const classGroup = document.getElementById('group-class-select');
            const gradeGroup = document.getElementById('group-grade-select');
            
            if (e.target.value === 'class') {
                classGroup.style.display = 'block';
                gradeGroup.style.display = 'none';
                document.getElementById('export-grade-select').value = ""; // Reset hidden field
            } else {
                classGroup.style.display = 'none';
                gradeGroup.style.display = 'block';
                document.getElementById('export-class-select').value = ""; // Reset hidden field
            }
        });
    });

    // Form submit
    document.getElementById('export-class-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const classId = document.getElementById('export-class-select').value;
        const gradeLevel = document.getElementById('export-grade-select').value;
        const language = document.getElementById('export-language-select').value;

        // Build URL with parameters
        let url = 'php/api/teacher/export-class-report.php?';
        if (classId) url += `class_id=${encodeURIComponent(classId)}&`;
        if (gradeLevel) url += `grade_level=${encodeURIComponent(gradeLevel)}&`;
        if (language) url += `language=${encodeURIComponent(language)}&`;
        // Remove trailing & if any
        url = url.replace(/&$/, '');

        // Trigger download via hidden iframe or window.open
        // Using window.open to start download
        window.open(url, '_blank');
        closeModal('export-class-modal');
    });
}

function renderDefaultReports() {
    viewContainer.innerHTML = `
        <div class="panel">
            <h3>📃 Reports</h3>
            <p>Reports not available for your role</p>
        </div>
    `;
}

// ============================================================
// STUDENT DETAIL (Python Assignments ONLY)
// ============================================================

async function renderStudentDetail(studentId) {
    if (!studentId) {
        viewContainer.innerHTML = '<p class="u-text-danger">Student not selected.</p>';
        return;
    }

    viewContainer.innerHTML = '<div class="spinner u-text-center u-mt-20">Loading student profile...</div>';

    try {
        // 1. Fetch Student Info (PHP)
        const studentData = await fetchJson(`php/api/shared/students.php?action=get&id=${encodeURIComponent(studentId)}`);
        if (!studentData.success || !studentData.student) {
            viewContainer.innerHTML = '<p class="u-text-danger">Student not found.</p>';
            return;
        }
        const student = studentData.student;

        // 2. Fetch Assignments & Quizzes (Python API)
        let assignments = [];
        try {
            const assignData = await fetchAssignmentApi(`/student/assignments?student_id=${studentId}`);
            assignments = assignData.assignments || [];
        } catch (e) {
            console.warn("Could not load Python assignments:", e);
        }

        // 3. Compute aggregate stats purely from completed Python Assignments
        let allMaterials = [];
        assignments.forEach(a => {
            if (a.materials && Array.isArray(a.materials)) {
                allMaterials = allMaterials.concat(a.materials);
            }
        });
        
        const completedReadings = allMaterials.filter(m => m.reading_result);
        const totalAssessments = completedReadings.length;
        const avgWcpm = totalAssessments ? completedReadings.reduce((sum, m) => sum + Number(m.reading_result.wcpm || 0), 0) / totalAssessments : 0;
        const avgAccuracy = totalAssessments ? completedReadings.reduce((sum, m) => sum + Number(m.reading_result.accuracy_percentage || 0), 0) / totalAssessments : 0;

        // Render the UI
        viewContainer.innerHTML = `
            <div class="student-detail-shell">
                <div class="u-row-between u-mb-16">
                    <button class="btn-secondary" id="back-to-roster" style="padding: 8px 16px;">← Back</button>
                    <div style="text-align: right;">
                        <h2 class="u-m-0" style="color: #0f172a; font-family: 'Inter', sans-serif;">${escapeAssessmentHtml(student.first_name)} ${escapeAssessmentHtml(student.last_name)}</h2>
                        <span class="u-text-muted">LRN: ${escapeAssessmentHtml(student.lrn || '-')}</span>
                    </div>
                </div>

                <!-- Basic Info Bar -->
                <div class="panel u-mb-16" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px 24px;">
                    <div class="u-grid-3 u-gap-16">
                        <div><strong style="color: #64748b;">Grade:</strong> <span style="color: #0f172a; font-weight: 600; font-size: 16px;">${escapeAssessmentHtml(student.grade_level || '-')}</span></div>
                        <div><strong style="color: #64748b;">Section:</strong> <span style="color: #0f172a; font-weight: 600; font-size: 16px;">${escapeAssessmentHtml(student.section || '-')}</span></div>
                        <div><strong style="color: #64748b;">Birthdate:</strong> <span style="color: #0f172a; font-weight: 600; font-size: 16px;">${student.birthdate ? new Date(student.birthdate).toLocaleDateString() : '-'}</span></div>
                    </div>
                </div>

                <!-- Aggregate Stat Cards (Powered by Python API) -->
                <div class="grid three u-mb-16">
                    <div class="stat-card" style="padding: 16px;">
                        <h4 style="font-size: 13px;">Completed Assignments</h4>
                        <div class="stat-number" style="font-size: 24px;">${totalAssessments}</div>
                    </div>
                    <div class="stat-card" style="padding: 16px; border-top-color: #3b82f6;">
                        <h4 style="font-size: 13px;">Avg WCPM</h4>
                        <div class="stat-number" style="font-size: 24px; color: #1d4ed8;">${avgWcpm.toFixed(1)}</div>
                    </div>
                    <div class="stat-card" style="padding: 16px; border-top-color: #10b981;">
                        <h4 style="font-size: 13px;">Avg Accuracy</h4>
                        <div class="stat-number" style="font-size: 24px; color: #059669;">${avgAccuracy.toFixed(1)}%</div>
                    </div>
                </div>

                <!-- Python API Assignments & Quizzes -->
                <div class="panel u-mb-16">
                    <div class="panel-header" style="border-bottom: 1px solid #e2e8f0; padding-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <h3 style="margin: 0;">Assignments & Quizzes</h3>
                        </div>
                    </div>
                    ${assignments.length ? `
                        <div class="u-scroll-x">
                            <table class="table-clean">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="padding: 12px; color: #64748b; font-size: 12px;">ASSIGNMENT TITLE</th>
                                        <th style="padding: 12px; color: #64748b; font-size: 12px;">DUE DATE</th>
                                        <th style="padding: 12px; color: #64748b; font-size: 12px;">READING METRICS</th>
                                        <th style="padding: 12px; color: #64748b; font-size: 12px;">QUIZ SCORE</th>
                                        <th style="padding: 12px; color: #64748b; font-size: 12px;">STATUS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${assignments.map(a => {
                                        // Look inside the materials array for results
                                        const materialWithReading = a.materials?.find(m => m.reading_result);
                                        const materialWithQuiz = a.materials?.find(m => m.quiz_attempt && m.quiz_attempt.status === 'completed');

                                        // Process Reading Result
                                        let readHtml = '<span style="color: #94a3b8; font-size: 13px;">Pending</span>';
                                        if (materialWithReading) {
                                            const rr = materialWithReading.reading_result;
                                            readHtml = `<strong style="color: #059669;">${Math.round(rr.accuracy_percentage)}% Acc</strong><br><span style="font-size: 12px; color: #475569;">${Math.round(rr.wcpm)} WCPM</span>`;
                                        }

                                        // Process Quiz Result (Notice we use quiz_attempt now)
                                        let quizHtml = '<span style="color: #94a3b8; font-size: 13px;">Pending</span>';
                                        if (materialWithQuiz) {
                                            const qa = materialWithQuiz.quiz_attempt;
                                            const perc = Math.round((qa.score / qa.total_questions) * 100);
                                            const color = perc >= 80 ? '#059669' : (perc >= 60 ? '#d97706' : '#dc2626');
                                            quizHtml = `<strong style="color: ${color}; font-size: 15px;">${qa.score}/${qa.total_questions}</strong><br><span style="font-size: 12px; color: #475569;">${perc}%</span>`;
                                        }

                                        // Smart Status Badges
                                        let statusBadge = '<span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600;">Assigned</span>';
                                        
                                        // If backend says completed, or if they finished both parts
                                        if (a.status === 'completed' || (materialWithReading && materialWithQuiz)) { 
                                            statusBadge = '<span style="background: #dcfce7; color: #15803d; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600;">Completed</span>';
                                        } 
                                        // If backend says in_progress, or if they finished at least one part
                                        else if (a.status === 'in_progress' || materialWithReading || materialWithQuiz) {
                                            statusBadge = '<span style="background: #fef9c3; color: #854d0e; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600;">In Progress</span>';
                                        }

                                        return `
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 16px 12px;"><strong>${escapeAssessmentHtml(a.title)}</strong></td>
                                            <td style="padding: 16px 12px; color: #64748b; font-size: 14px;">${a.due_date ? new Date(a.due_date).toLocaleDateString() : 'No Due Date'}</td>
                                            <td style="padding: 16px 12px;">${readHtml}</td>
                                            <td style="padding: 16px 12px;">${quizHtml}</td>
                                            <td style="padding: 16px 12px;">${statusBadge}</td>
                                        </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : `
                        <div class="empty-state">
                            <p style="margin: 0; font-size: 15px;">No assignments found for this student.</p>
                            <p class="u-text-muted-xs" style="margin-top: 4px;">Assign them reading materials from the Assignments tab.</p>
                        </div>
                    `}
                </div>
            </div>
        `;

        // Smart Back Routing
        document.getElementById('back-to-roster')?.addEventListener('click', () => {
            state.selectedStudentId = null;
            if (state.selectedTeacherId) {
                setActiveView('teacher-detail');
            } else {
                setActiveView('students');
            }
        });

    } catch (error) {
        viewContainer.innerHTML = `<div class="panel"><p class="u-text-danger">Error loading student data: ${escapeAssessmentHtml(error.message)}</p></div>`;
    }
}   

function renderPrincipalReports() {
    viewContainer.innerHTML = `
        <div class="reports-shell">

            <div class="reports-toolbar">
                <div class="u-row-wrap u-gap-8">
                    <button id="report-school-wide" class="btn-primary">School Overview</button>
                    <button id="report-by-grade" class="btn-secondary">By Grade Level</button>
                    <button id="report-materials" class="btn-secondary">Material Usage</button>
                    <button id="export-assessments-btn" class="btn-export">Export CSV</button>
                    <button id="refresh-reports-btn" class="btn-secondary">Refresh</button>
                </div>
            </div>

            <div id="report-content" class="u-mt-16">
                <div class="panel">
                    <p class="u-text-muted">Select a report type to view</p>
                </div>
            </div>
        </div>
    `;

    document.getElementById('report-school-wide')?.addEventListener('click', () => loadAndDisplayReport('school-wide'));
    document.getElementById('report-by-grade')?.addEventListener('click', () => loadAndDisplayReport('class-performance'));
    document.getElementById('report-materials')?.addEventListener('click', () => loadAndDisplayReport('material-usage'));
    document.getElementById('export-assessments-btn')?.addEventListener('click', () => exportAssessmentResults());
    document.getElementById('refresh-reports-btn')?.addEventListener('click', () => renderPrincipalReports());
}

function renderTeacherReports() {
    viewContainer.innerHTML = `
        <div class="reports-shell">

            <div class="reports-toolbar">
                <div class="u-row-wrap u-gap-8">
                    <button id="report-class-performance" class="btn-primary">Class Performance</button>
                    <button id="refresh-reports-btn" class="btn-secondary">Refresh</button>
                </div>
            </div>

            <div id="report-content" class="u-mt-16">
                <div class="panel">
                    <p class="u-text-muted">Click above to view your class performance report</p>
                </div>
            </div>
        </div>
    `;

    document.getElementById('report-class-performance')?.addEventListener('click', () => loadAndDisplayReport('class-performance'));
    document.getElementById('refresh-reports-btn')?.addEventListener('click', () => renderTeacherReports());
}

async function loadAndDisplayReport(reportType) {
    const contentDiv = document.getElementById('report-content');
    if (!contentDiv) return;

    contentDiv.innerHTML = '<div class="panel"><p class="u-text-muted u-text-center">Loading report...</p></div>';

    try {
        const data = await fetchJson(`php/api/shared/report-analytics.php?action=${encodeURIComponent(reportType)}`);
        
        if (data.success) {
            state.reportData = data;
            displayReport(reportType, data);
        } else {
            contentDiv.innerHTML = `<div class="panel"><p class="u-text-danger">${data.message}</p></div>`;
        }
    } catch (error) {
        contentDiv.innerHTML = `<div class="panel"><p class="u-text-danger">Error loading report: ${error.message}</p></div>`;
    }
}

function displayReport(reportType, data) {
    const contentDiv = document.getElementById('report-content');
    if (!contentDiv) return;

    if (reportType === 'school-wide') {
        const gradeStats = data.grade_statistics || [];
        const topPerformers = data.top_performers || [];
        const intervention = data.intervention_needed || [];

        contentDiv.innerHTML = `
            <div class="report-grid-3">
                <!-- Grade Statistics -->
                <div class="panel">
                    <h3>By Grade Level</h3>
                    <div class="u-max-h-400 u-scroll-y">
                        ${gradeStats.length ? gradeStats.map(stat => `
                            <div class="list-row-sm">
                                <p class="u-m-0 u-fw-600">${stat.grade_level}</p>
                                <div class="u-text-muted-xs u-mt-4">
                                    Students: ${stat.student_count} | Avg WCPM: ${Number(stat.avg_wcpm || 0).toFixed(1)}
                                </div>
                            </div>
                        `).join('') : '<p class="u-text-muted">No data</p>'}
                    </div>
                </div>

                <!-- Top Performers -->
                <div class="panel">
                    <h3>Top Performers</h3>
                    <div class="u-max-h-400 u-scroll-y">
                        ${topPerformers.length ? topPerformers.map(student => `
                            <div class="list-row-sm">
                                <p class="u-m-0 u-fw-600">${student.first_name} ${student.last_name}</p>
                                <div class="u-text-muted-xs u-mt-4">
                                    Grade: ${student.grade_level} | Avg WCPM: ${Number(student.avg_wcpm || 0).toFixed(1)}
                                </div>
                            </div>
                        `).join('') : '<p class="u-text-muted">No data</p>'}
                    </div>
                </div>

                <!-- Intervention Needed -->
                <div class="panel status-callout warning report-warning-panel">
                    <h3>Needs Intervention</h3>
                    <div class="u-max-h-400 u-scroll-y">
                        ${intervention.length ? intervention.map(student => `
                            <div class="list-row-soft">
                                <p class="u-m-0 u-fw-600">${student.first_name} ${student.last_name}</p>
                                <div class="u-text-danger-xs u-mt-4">
                                    Grade: ${student.grade_level} | Avg WCPM: ${Number(student.avg_wcpm || 0).toFixed(1)}
                                </div>
                            </div>
                        `).join('') : '<p class="u-text-muted">No data</p>'}
                    </div>
                </div>
            </div>
        `;
    } else if (reportType === 'material-usage') {
        const materials = data.materials || [];
        contentDiv.innerHTML = `
            <div class="panel">
                <h3>Material Usage Report</h3>
                <table class="table-clean">
                    <thead>
                        <tr class="table-head-accent">
                            <th class="u-cell-12 u-ta-left">Title</th>
                            <th class="u-cell-12 u-ta-center">Grade</th>
                            <th class="u-cell-12 u-ta-center">Usage</th>
                            <th class="u-cell-12 u-ta-center">Avg WCPM</th>
                            <th class="u-cell-12 u-ta-center">Avg Accuracy</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${materials.length ? materials.map(mat => `
                            <tr class="table-row">
                                <td class="u-cell-12"><strong>${mat.title || 'Untitled'}</strong></td>
                                <td class="u-cell-12 u-ta-center">${mat.grade_level}</td>
                                <td class="u-cell-12 u-ta-center">${mat.usage_count || 0}</td>
                                <td class="u-cell-12 u-ta-center">${Number(mat.avg_wcpm || 0).toFixed(1)}</td>
                                <td class="u-cell-12 u-ta-center">${Number(mat.avg_accuracy || 0).toFixed(1)}%</td>
                            </tr>
                        `).join('') : '<tr><td colspan="5" class="u-cell-20 u-ta-center u-text-muted">No data</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    } else if (reportType === 'class-performance') {
        const students = data.students || [];
        contentDiv.innerHTML = `
            <div class="panel">
                <h3>Class Performance Report</h3>
                <table class="table-clean">
                    <thead>
                        <tr class="table-head-accent">
                            <th class="u-cell-12 u-ta-left">Student Name</th>
                            <th class="u-cell-12 u-ta-center">Grade</th>
                            <th class="u-cell-12 u-ta-center">Assessments</th>
                            <th class="u-cell-12 u-ta-center">Avg WCPM</th>
                            <th class="u-cell-12 u-ta-center">Avg Accuracy</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${students.length ? students.map(student => `
                            <tr class="table-row">
                                <td class="u-cell-12"><strong>${student.first_name} ${student.last_name}</strong></td>
                                <td class="u-cell-12 u-ta-center">${student.grade_level}</td>
                                <td class="u-cell-12 u-ta-center">${student.assessment_count || 0}</td>
                                <td class="u-cell-12 u-ta-center">${Number(student.avg_wcpm || 0).toFixed(1)}</td>
                                <td class="u-cell-12 u-ta-center">${Number(student.avg_accuracy || 0).toFixed(1)}%</td>
                            </tr>
                        `).join('') : '<tr><td colspan="5" class="u-cell-20 u-ta-center u-text-muted">No data</td></tr>'}
                    </tbody>
                </table>
            </div>
        `;
    }
}



// ============================================================
// 9. DATA LOADING - Dashboard, Materials, Assessments
// ============================================================

async function loadDashboardData() {
    const user = state.user;
    if (!user) return;
    const role = String(user.role || '').toLowerCase();
    
    try {
        let data;
        if (role === 'teacher') {
            data = await fetchJson('php/api/shared/reports.php?action=teacher');
        } else if (role === 'principal' || role === 'admin') {
            data = await fetchJson('php/api/shared/reports.php?action=principal');
        } else {
            return;
        }
        
        if (data.success && data.dashboard) {
            // Merge with existing dashboard state to preserve any fields
            state.dashboard = {
                ...state.dashboard,
                ...data.dashboard
            };
            
            // Ensure we have valid numbers for stats
            state.dashboard.my_students = Number(state.dashboard.my_students || 0);
            state.dashboard.class_avg_wcpm = Number(state.dashboard.class_avg_wcpm || 0);
            state.dashboard.my_assessments = Number(state.dashboard.my_assessments || 0);
            state.dashboard.students_below = Number(state.dashboard.students_below || 0);
            
            // Ensure arrays are valid
            state.dashboard.recent_assessments = Array.isArray(state.dashboard.recent_assessments) ? state.dashboard.recent_assessments : [];
            state.dashboard.class_performance = Array.isArray(state.dashboard.class_performance) ? state.dashboard.class_performance : [];
            state.dashboard.recent_students = Array.isArray(state.dashboard.recent_students) ? state.dashboard.recent_students : [];
            
            if (state.activeView === 'dashboard') {
                renderView();
            }
        } else {
            // If no data from API, use defaults but show a warning
            console.warn('No dashboard data received from API');
            state.dashboard = {
                ...state.dashboard,
                my_students: 0,
                class_avg_wcpm: 0,
                my_assessments: 0,
                students_below: 0,
                recent_assessments: [],
                class_performance: [],
                recent_students: []
            };
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        // Don't show alert to user, just use defaults
        state.dashboard = {
            ...state.dashboard,
            my_students: 0,
            class_avg_wcpm: 0,
            my_assessments: 0,
            students_below: 0,
            recent_assessments: [],
            class_performance: [],
            recent_students: []
        };
    }
}

// ============================================================
// 9. DATA LOADING (Continued)
// ============================================================

async function loadDashboard() {
    await loadDashboardData();
}

// ============================================================
// 8. STUDENT MANAGEMENT - List, Search, CRUD, Import
// ============================================================

async function loadStudents() {
    try {
        const data = await fetchJson('php/api/shared/students.php?action=list');
        if (data.success) {
            state.students = data.students || [];
            if (state.activeView === 'students') {
                renderStudents();
            }
        }
    } catch (error) {
        console.error('Error loading students:', error);
    }
}

// ============================================================
// OVERHAULED STUDENT MANAGEMENT (Class Cards -> Spreadsheet)
// ============================================================

function renderStudents() {
    const students = state.students || [];
    const role = String(state.user?.role || '').toLowerCase();
    const isAdminOrPrincipal = ['principal', 'admin'].includes(role);

    // 1. Group students by Grade and Section automatically
    const classesMap = {};
    students.forEach(s => {
        const grade = s.grade_level || 'Unassigned';
        const section = s.section || 'General';
        const classKey = `${grade} - ${section}`;
        
        if (!classesMap[classKey]) {
            classesMap[classKey] = { grade, section, students: [] };
        }
        classesMap[classKey].students.push(s);
    });

    const classesList = Object.values(classesMap).sort((a, b) => a.grade.localeCompare(b.grade));

    // ----------------------------------------------------
    // VIEW A: Show Class Cards (If no class is selected)
    // ----------------------------------------------------
    if (!state.selectedClass) {
        viewContainer.innerHTML = `
            <div class="page-header u-mb-16">
                <div>
                    <h2 style="font-family: 'Inter', sans-serif; font-size: 24px; font-weight: 700; color: #0f172a;">Classes</h2>
                    <p class="subtitle" style="margin-top: 4px;">Select a class to view your students' reading progress</p>
                </div>
                <div class="page-header-right">
                    ${!isAdminOrPrincipal ? `<button id="add-student-btn" class="btn-primary">+ Add Student</button>` : ''}
                    <button id="import-students-btn" class="btn-secondary">Import</button>
                    <button id="refresh-students-btn" class="btn-secondary">Refresh</button>
                </div>
            </div>

            <div class="grid three u-mt-20">
                ${classesList.map(cls => `
                    <div class="stat-card" style="cursor: pointer; padding: 24px;" onclick="state.selectedClass = '${cls.grade} - ${cls.section}'; renderStudents();">
                        <div class="stat-card-icon" style="background: var(--primary); color: white; width: 54px; height: 54px;">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <div class="stat-card-content">
                            <h4 style="color: #0f172a; font-size: 1.25rem; font-weight: 700; margin-bottom: 4px;">${cls.grade}</h4>
                            <div class="stat-label" style="font-size: 0.95rem; color: #64748b;">Section: ${cls.section}</div>
                            <div class="stat-label u-mt-12"><strong style="color: var(--primary); font-size: 1rem;">${cls.students.length}</strong> Enrolled Students</div>
                        </div>
                    </div>
                `).join('')}
                ${classesList.length === 0 ? '<p class="u-text-muted">No classes or students found. Add a student to create a class.</p>' : ''}
            </div>
        ` + getStudentModalsHTML(); // Inject modals at bottom
    } 
    // ----------------------------------------------------
    // VIEW B: Show Spreadsheet for Selected Class
    // ----------------------------------------------------
    else {
        const currentClass = classesMap[state.selectedClass];
        if (!currentClass) {
            state.selectedClass = null; // Failsafe
            renderStudents();
            return;
        }

        viewContainer.innerHTML = `
            <div class="page-header u-mb-16">
                <div class="u-row-between" style="width: 100%;">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <button class="btn-secondary" style="padding: 8px 12px;" onclick="state.selectedClass = null; renderStudents();">← Back</button>
                        <div>
                            <h2 style="font-family: 'Inter', sans-serif; font-size: 24px; font-weight: 700; color: #0f172a;">Students</h2>
                            <p class="subtitle" style="margin-top: 4px;">${currentClass.grade} - ${currentClass.section}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="search-bar u-mb-16 u-row-between">
                <div style="position: relative; max-width: 400px; width: 100%;">
                    <span style="position: absolute; left: 12px; top: 10px; color: #94a3b8;">🔍</span>
                    <input type="text" id="roster-search-input" placeholder="Search students..." class="input-inline-fill" style="background: #fff; padding-left: 36px;">
                </div>
                <div style="display: flex; gap: 8px;">
                    <!-- NEW EXPORT BUTTON -->
                    <button class="btn-secondary" style="display: flex; align-items: center; gap: 6px;" onclick="window.open('php/api/teacher/export-class-report.php?grade_level=' + encodeURIComponent('${currentClass.grade}'), '_blank')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Export
                    </button>
                    <button class="btn-secondary" style="display: flex; align-items: center; gap: 6px;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg> Filter</button>
                    <button class="btn-primary" style="background: #1e40af;" onclick="showModal('add-student-modal')">+ Add Student</button>
                </div>
            </div>

            <div class="panel" style="padding: 0; overflow: hidden; border-radius: 12px;">
                <div class="u-scroll-x">
                    <table class="table-clean" style="margin: 0; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #fff; border-bottom: 2px solid #f1f5f9;">
                                <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">ID</th>
                                <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">NAME</th>
                                <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">GRADE</th>
                                <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">SECTION</th>
                                <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px; text-align: center;">READING LEVEL</th>
                                <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">LAST ASSESSED</th>
                                <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px; text-align: center;">ACCURACY</th>
                                <th style="padding: 16px 20px; color: #64748b; font-size: 11px; letter-spacing: 0.5px;">PROGRESS</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${currentClass.students.map(student => {
                                // 1. Generate Avatar Initials
                                const firstI = student.first_name ? student.first_name.charAt(0).toUpperCase() : '';
                                const lastI = student.last_name ? student.last_name.charAt(0).toUpperCase() : '';
                                
                                // 2. Determine Reading Level Badge Colors (Client Spec)
                                const rawLvl = student.reading_level || 'Pending';
                                let lvl = rawLvl;
                                let badgeClass = 'status-default'; // Gray

                                const lowerLvl = rawLvl.toLowerCase();

                                if (lowerLvl.includes('reading at grade level') || lowerLvl === 'grade level') {
                                    lvl = 'Reading At Grade Level';
                                    badgeClass = 'status-success'; // Green
                                } 
                                else if (lowerLvl.includes('transitioning')) {
                                    lvl = 'Transitioning Reader';
                                    badgeClass = 'status-info'; // Blue
                                } 
                                else if (lowerLvl.includes('developing')) {
                                    lvl = 'Developing Reader';
                                    badgeClass = 'status-warning'; // Yellow
                                } 
                                else if (lowerLvl.includes('high emerging')) {
                                    lvl = 'High Emerging Reader';
                                    badgeClass = 'status-orange'; // Orange
                                } 
                                else if (lowerLvl.includes('low emerging') || lowerLvl.includes('emerging')) {
                                    lvl = 'Low Emerging Reader';
                                    badgeClass = 'status-danger'; // Red
                                }

                                // 3. Format Accuracy
                                const rawAcc = student.accuracy_percentage || student.avg_accuracy || 0;
                                const acc = Math.round(rawAcc);
                                const accColor = acc >= 80 ? '#059669' : (acc >= 65 ? '#d97706' : '#dc2626');

                                // 4. Determine Progress Trend
                                let progHtml = '<span style="color: #94a3b8; font-size: 13px;">Stable</span>';
                                if (acc >= 80) progHtml = '<span style="color: #059669; font-size: 13px;">↗ Improving</span>';
                                else if (acc < 65 && rawAcc > 0) progHtml = '<span style="color: #dc2626; font-size: 13px;">⚠ Declining</span>';

                                return `
                                <tr class="table-row" style="background: #fff; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='#fff'" onclick="state.selectedStudentId = '${student.student_id}'; setActiveView('student-detail');">
                                    <td style="padding: 16px 20px; color: #94a3b8; font-family: 'JetBrains Mono', monospace; font-size: 13px;">${student.lrn || `STU-${student.student_id.toString().padStart(3, '0')}`}</td>
                                    <td style="padding: 16px 20px;">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #eff6ff; color: #1e40af; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px;">${firstI}${lastI}</div>
                                            <strong style="color: #0f172a; font-weight: 600;">${student.first_name} ${student.last_name}</strong>
                                        </div>
                                    </td>
                                    <td style="padding: 16px 20px; color: #475569;">${student.grade_level}</td>
                                    <td style="padding: 16px 20px; color: #94a3b8;">${student.section || '-'}</td>
                                    <td style="padding: 16px 20px; text-align: center;">
                                        <!-- NEW: Using the CSS classes instead of inline styles! -->
                                        <span class="status-badge ${badgeClass}">${lvl}</span>
                                    </td>
                                    <td style="padding: 16px 20px; color: #64748b;">${student.last_assessed ? new Date(student.last_assessed).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}) : '-'}</td>
                                    <td style="padding: 16px 20px; text-align: center; color: ${rawAcc > 0 ? accColor : '#94a3b8'}; font-weight: 700;">${rawAcc > 0 ? acc + '%' : '-'}</td>
                                    <td style="padding: 16px 20px;">${rawAcc > 0 ? progHtml : '-'}</td>
                                </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        ` + getStudentModalsHTML(); // Inject modals at bottom
    }

    // Finally, re-attach all your existing event listeners for the Modals!
    attachStudentEventListeners();
}

// ============================================================
// HELPER: Keeps Modals Clean and Out of the Way
// ============================================================

function getStudentModalsHTML() {
    return `
        <!-- Add Student Modal (Kept exactly as it was) -->
        <div id="add-student-modal" class="modal hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Add Student</h3>
                    <button class="close-modal">x</button>
                </div>
                <form id="add-student-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>LRN (6 digits) *</label>
                            <input type="text" name="lrn" placeholder="e.g. 123456" maxlength="6" required>
                        </div>
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label>Grade Level</label>
                            <select name="grade_level" required>
                                <option value="Grade 1">Grade 1</option>
                                <option value="Grade 2" selected>Grade 2</option>
                                <option value="Grade 3">Grade 3</option>
                                <option value="Grade 4">Grade 4</option>
                                <option value="Grade 5">Grade 5</option>
                                <option value="Grade 6">Grade 6</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Section</label>
                            <input type="text" name="section" placeholder="e.g. Section A">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary">Save Student</button>
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Import Modal -->
        <div id="import-modal" class="modal hidden">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Import Students</h3>
                    <button class="close-modal">x</button>
                </div>
                <form id="import-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Select File (CSV or Excel)</label>
                        <input type="file" name="file" accept=".csv,.xlsx" required>
                    </div>
                    <div class="modal-actions">
                        <button type="submit" class="btn-primary">Import</button>
                        <button type="button" class="btn-secondary close-modal">Cancel</button>
                    </div>
                </form>
                <div id="import-result"></div>
            </div>
        </div>
    `;
}

// ============================================================
// HELPER: Re-attaches events after HTML swap
// ============================================================

function attachStudentEventListeners() {
    document.getElementById('add-student-btn')?.addEventListener('click', () => showModal('add-student-modal'));
    document.getElementById('import-students-btn')?.addEventListener('click', () => showModal('import-modal'));
    document.getElementById('refresh-students-btn')?.addEventListener('click', loadStudents);

    // Search functionality inside the roster
    document.getElementById('roster-search-input')?.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        document.querySelectorAll('.table-row').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
    
    // Close modal generic listener
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal');
            if (modal?.id) closeModal(modal.id);
        });
    });

    // Form handlers
    document.getElementById('add-student-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        try {
            const result = await fetchJson('php/api/shared/students.php', {
                method: 'POST', body: JSON.stringify(data)
            });
            if (result.success) {
                showToast('Student added successfully!', 'success');
                closeModal('add-student-modal');
                await loadStudents();
            } else alert(result.message);
        } catch (error) { alert(error.message); }
    });

    // Handle the Import Form Submission
    document.getElementById('import-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const resultDiv = document.getElementById('import-result');
        
        resultDiv.innerHTML = '<p class="u-text-muted">⏳ Reading file...</p>';
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        try {
            // Step 1: Send file to the preview endpoint
            const previewRes = await fetch('php/modules/student_import.php?action=preview', {
                method: 'POST',
                body: formData
            });
            const previewData = await previewRes.json();

            if (!previewData.success) {
                throw new Error(previewData.message || 'Failed to read file.');
            }

            if (previewData.preview.errors.length > 0) {
                resultDiv.innerHTML = `<p class="u-text-danger">Found ${previewData.preview.errors.length} errors in your file. Please fix missing names and try again.</p>`;
                submitBtn.disabled = false;
                return;
            }

            resultDiv.innerHTML = '<p class="u-text-muted">✅ File looks good! Importing students...</p>';

            // Step 2: Send the validated rows to the import endpoint
            const importRes = await fetch('php/modules/student_import.php?action=import', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ rows: previewData.rows })
            });
            const importData = await importRes.json();

            if (importData.success) {
                showToast(`Successfully imported ${importData.summary.imported} students!`, 'success');
                closeModal('import-modal');
                await loadStudents();
            } else {
                throw new Error(importData.message || 'Import failed during processing.');
            }
        } catch (error) {
            resultDiv.innerHTML = `<p class="u-text-danger">Error: ${error.message}</p>`;
        } finally {
            submitBtn.disabled = false;
        }
    });
}

function showModal(modalId) {
    // Hide all modals first
    document.querySelectorAll('.modal').forEach(m => {
        m.classList.add('hidden');
        m.style.display = 'none';
        m.style.opacity = '0';
    });

    // Show target modal
    const modal = document.getElementById(modalId);
    if (!modal) {
        console.warn('Modal not found:', modalId);
        return;
    }

    // Force popup overlay behavior regardless of stylesheet corruption.
    modal.style.position = 'fixed';
    modal.style.inset = '0';
    modal.style.width = '100%';
    modal.style.height = '100%';
    modal.style.alignItems = 'center';
    modal.style.justifyContent = 'center';
    modal.style.padding = '24px';
    modal.style.background = 'rgba(0, 0, 0, 0.6)';
    modal.style.zIndex = '9999';

    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    modal.style.opacity = '1';
    modal.dataset.previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement.id || '' : '';
    modal.onclick = async (event) => {
        if (event.target === modal) {
            await requestCloseModal(modalId);
        }
    };

    document.body.style.overflow = 'hidden';

    const content = modal.querySelector('.modal-content');
    if (content instanceof HTMLElement) {
        content.style.maxWidth = '700px';
        content.style.width = '95%';
        content.style.maxHeight = '90vh';
        content.style.overflowY = 'auto';
        content.scrollTop = 0;
    }

    setupModalFocusTrap(modal);
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        teardownModalFocusTrap(modal);
        modal.classList.add('hidden');
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';

    const previousFocusId = modal?.dataset.previousFocus;
    if (previousFocusId) {
        const previousFocus = document.getElementById(previousFocusId);
        if (previousFocus instanceof HTMLElement) {
            previousFocus.focus();
        }
    }
}

function isModalVisible(modalId) {
    const modal = document.getElementById(modalId);
    return !!modal && !modal.classList.contains('hidden');
}

async function searchStudents() {
    const term = document.getElementById('student-search-input')?.value || '';
    if (term.length < 2) {
        await loadStudents();
        return;
    }
    
    try {
        const data = await fetchJson(`php/api/shared/students.php?action=search&term=${encodeURIComponent(term)}`);
        state.students = data.students || [];
        renderStudents();
    } catch (error) {
        console.error('Search error:', error);
    }
}

async function editStudent(studentId) {
    try {
        const data = await fetchJson(`php/api/shared/students.php?action=get&id=${encodeURIComponent(studentId)}`);
        if (!data.success || !data.student) {
            alert('Student not found.');
            return;
        }

        const student = data.student;
        const setValue = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.value = value ?? '';
        };

        setValue('edit-student-id', student.student_id);
        setValue('edit-lrn', student.lrn);
        setValue('edit-first-name', student.first_name);
        setValue('edit-middle-name', student.middle_name);
        setValue('edit-last-name', student.last_name);
        setValue('edit-gender', student.gender);
        setValue('edit-birthdate', student.birthdate);
        setValue('edit-grade-level', student.grade_level);

        showModal('edit-student-modal');
    } catch (error) {
        alert('Error loading student: ' + error.message);
    }
}

async function archiveStudent(studentId) {
    const shouldArchive = await showConfirm('Are you sure you want to archive this student?', {
        title: 'Archive Student',
        confirmLabel: 'Archive',
        confirmTone: 'danger'
    });
    if (!shouldArchive) return;
    
    try {
        const result = await fetchJson(`php/api/shared/students.php?id=${studentId}`, {
            method: 'DELETE'
        });
        
        if (result.success) {
            alert('Student archived successfully');
            await loadStudents();
        } else {
            alert('' + result.message);
        }
    } catch (error) {
        alert('' + error.message);
    }
}

async function loadMaterials() {
    try {
        const data = await fetchJson('php/api/shared/reading-materials.php?action=list');
        state.materials = data.materials || [];
    } catch (error) {
        console.error('Error loading materials:', error);
    }
}

async function loadAssessments() {
    try {
        const data = await fetchJson('php/api/shared/assessment.php?action=list');
        state.assessments = data.assessments || [];
    } catch (error) {
        console.error('Error loading assessments:', error);
    }
}

// ============================================================
// 10. SESSION & AUTHENTICATION
// ============================================================

async function checkSession() {
    try {
        const data = await fetchJson('php/api/auth/session.php');
        state.user = data.user || null;
        if (state.user) {
            
            // UPDATE: Save the user data to localStorage so student.js can read it!
            localStorage.setItem("archivevox_user", JSON.stringify(state.user));

            // UPDATE: If they are a student, immediately redirect them to their portal
            if (state.user.role === 'student') {
                window.location.href = 'student/student.html';
                return; // Stop execution here
            }

            showApp();
            renderNavigation();
            await loadDashboardData();
            renderView();
        } else {
            showLogin();
        }
    } catch (error) {
        showLogin();
    }
}

// ============================================================
// 10. SESSION & AUTHENTICATION (Continued)
// ============================================================

// Login
loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(loginForm);
    try {
        const data = await fetchJson('php/api/auth/login.php', {
            method: 'POST',
            body: JSON.stringify({
                username: formData.get('username'),
                password: formData.get('password')
            })
        });
        
        state.user = data.user || null;

        // UPDATE: Save the user data to localStorage upon successful login
        localStorage.setItem("archivevox_user", JSON.stringify(state.user));

        // UPDATE: Route the student to their dedicated dashboard
        if (state.user && state.user.role === 'student') {
            window.location.href = 'student/student.html';
            return; // Stop execution so the main app shell doesn't load
        }
        
        // Fetch teacher_id if user is a teacher
        if (state.user && state.user.role === 'teacher') {
            const teacherId = await fetchTeacherId();
            if (teacherId) {
                state.user.teacher_id = teacherId;
            }
        }
        
        showApp();
        renderNavigation();
        await loadDashboardData();
        renderView();
    } catch (error) {
        alert('' + error.message);
    }
});

// Logout
async function handleLogout() {
    try {
        // UPDATE: Added { method: 'POST' }
        await fetchJson('php/api/auth/logout.php', { method: 'POST' }); 
        
        state.user = null;
        
        // Clear the localStorage
        localStorage.removeItem("archivevox_user");
        
        showLogin();
    } catch (error) {
        console.error('Logout error:', error);
    }
}

// ============================================================
// 10. SESSION & AUTHENTICATION (Continued: Initialization)
// ============================================================

document.addEventListener('keydown', async (event) => {
    if (event.key === 'Escape' && !isDesktopSidebarMode() && sidebarState.mobileOpen) {
        closeMobileSidebar();
        return;
    }

    if (event.key === 'Escape' && settingsPopover && !settingsPopover.classList.contains('hidden')) {
        closeSettingsPopover();
        return;
    }

    if (event.key !== 'Escape') return;

    const modal = getTopVisibleModal();
    if (!modal?.id) return;

    if (modal.id === 'confirm-modal') {
        resolveConfirm(false);
        return;
    }

    await requestCloseModal(modal.id);
});

window.addEventListener('beforeunload', cleanupAssessmentOnLeave);
window.addEventListener('DOMContentLoaded', () => {
    loadUiSettings();
    loadSidebarPreference();
    applyFontSizeSetting();
    initSidebarToggle();
    applySidebarState();
    checkSession();
});
