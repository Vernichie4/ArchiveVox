// ============================================================
// TEACHER DASHBOARD MODULE (teacher-dashboard.js)
// ============================================================

let currentTeacherDashTab = 'summary';
let currentTeacherDashLanguage = 'all';
let currentTeacherDashDateFilter = 'custom';
let currentTeacherDashStartDate = '';
let currentTeacherDashEndDate = '';

async function fetchTeacherDashboardData(language, dateFilter, startDate, endDate) {
    try {
        const url = `php/api/shared/reports.php?action=teacher&language=${language}&dateFilter=${dateFilter}&startDate=${startDate}&endDate=${endDate}`;
        const data = await fetchJson(url);
        if (data && data.success && data.dashboard) {
            return data.dashboard;
        }
        return null;
    } catch (error) {
        console.error('Failed to load teacher dashboard data:', error);
        return null;
    }
}

async function renderTeacherDashboard() {
    const viewContainer = document.getElementById('view-container');
    if (!viewContainer) return;

    viewContainer.style.padding = '0';
    viewContainer.style.backgroundColor = '#f4f6fb';

    viewContainer.innerHTML = `
        <div style="text-align: center; padding: 4rem 0; font-family: 'Inter', sans-serif;">
            <div class="spinner"></div>
            <p style="color: #6b7280; margin-top: 1rem; font-size: 0.875rem;">Loading teacher dashboard...</p>
        </div>
    `;

    const rawData = await fetchTeacherDashboardData(currentTeacherDashLanguage, currentTeacherDashDateFilter, currentTeacherDashStartDate, currentTeacherDashEndDate);
    console.log("Backend Data:", rawData);

    const dashData = {
        teacherName: state?.user?.username || "Teacher",
        grade: rawData?.grade || "N/A",
        language: currentTeacherDashLanguage === 'english' ? 'English' : 
                  currentTeacherDashLanguage === 'filipino' ? 'Filipino Language' : 
                  'Overall',
        section: rawData?.section || "N/A",
        storedRecords: rawData?.my_assessments ?? 0,
        classifiedAssessments: rawData?.my_assessments ?? 0,
        needsFollowUp: rawData?.students_below ?? 0,
        assessed: rawData?.my_students ?? 0,
        avgWcpm: Number(rawData?.class_avg_wcpm ?? 0).toFixed(1),
        avgAccuracy: Number(rawData?.avg_accuracy ?? 0).toFixed(1),
        avgComp: Number((rawData?.avg_comprehension ?? 0) * 20).toFixed(1),
        recentAssessments: rawData?.recent_assessments || [],
        recentStudents: rawData?.recent_students || [],
        profileStats: rawData?.profile_stats || [],
        part1Stats: rawData?.part1_stats || []
    };

    viewContainer.innerHTML = `
        <style>
            /* Scoped Styles */
            #td-wrapper {
                max-width: 1200px;
                margin: 0 auto;
                padding: 32px 24px;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                color: #1e293b;
            }
            #td-wrapper * { box-sizing: border-box; }
            #td-wrapper h1, #td-wrapper h2, #td-wrapper h3 { font-family: 'Playfair Display', serif !important; }
            .td-mono { font-family: 'JetBrains Mono', monospace !important; font-weight: 700; }
            
            /* Cards */
            .td-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; }
            .td-card-warning { background: #fefce8 !important; border: 1px solid #fde047 !important; }
            
            /* Typography Colors */
            .td-text-muted { color: #64748b; }
            .td-text-navy { color: #1b4382 !important; }
            .td-text-amber { color: #ea580c !important; }
            .td-text-blue { color: #2563eb !important; }
            .td-text-emerald { color: #16a34a !important; }
            .td-text-violet { color: #7c3aed !important; }
            .td-text-red { color: #ef4444 !important; }

            /* Buttons & Toolbar */
            .td-toolbar { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px 16px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
            .td-tab-btn { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; border: none; cursor: pointer; transition: all 0.2s; background: transparent; color: #64748b; }
            .td-tab-btn.active { background-color: #1b4382 !important; color: #ffffff !important; }
            
            .td-btn-outline-navy { display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; border: 1px solid #1b4382; background: transparent; color: #1b4382; cursor: pointer; }
            .td-btn-outline-navy:hover { background: #f0f4f8; }
            .td-btn-outline-grey { display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; border: 1px solid #e2e8f0; background: transparent; color: #1e293b; cursor: pointer; }
            .td-btn-outline-grey:hover { background: #f8fafc; }
            .td-btn-ghost { display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; border: none; background: transparent; color: #64748b; cursor: pointer; }
            .td-btn-ghost:hover { background: #f1f5f9; }
            .td-btn-solid-navy { background: #1b4382; color: #ffffff; border: none; border-radius: 8px; padding: 8px 24px; font-size: 14px; font-weight: 500; cursor: pointer; }

            /* Segments */
            .td-segment-group { display: flex; background: transparent; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; }
            .td-segment-btn { border: none; border-right: 1px solid #e2e8f0; background: transparent; padding: 8px 16px; font-size: 14px; color: #64748b; cursor: pointer; }
            .td-segment-btn:last-child { border-right: none; }
            .td-segment-btn.active { background: #1b4382 !important; color: #ffffff !important; }
            
            /* Status Pills */
            .td-badge { padding: 4px 12px; border-radius: 16px; font-size: 12px; font-weight: 500; display: inline-block; }
            .td-badge-grey { background: #f1f5f9; color: #1b4382; border: 1px solid #2563eb; }
            .td-badge-red { background: #fef2f2; color: #ef4444; }
            .td-badge-amber { background: #fffbeb; color: #ea580c; }
            .td-badge-blue { background: #eff6ff; color: #2563eb; }
            .td-badge-emerald { background: #ecfdf5; color: #16a34a; }

            /* Tables */
            .td-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
            .td-table th { padding: 12px 20px; font-size: 11px; text-transform: uppercase; font-weight: 600; background: #fafafa; color: #64748b; border-bottom: 1px solid #e2e8f0; letter-spacing: 0.5px; }
            .td-table td { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
            .td-table tr:last-child td { border-bottom: none; }
        </style>
        
        <div id="td-wrapper">
            <!-- Header Info Banner -->
            <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <span style="font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700; color: #1e4d8c;">
                    ${dashData.teacherName}
                </span>
                <span style="color: #cbd5e1; font-size: 1.25rem;">|</span>
                <span style="font-size: 1rem; font-weight: 500; color: #475569;">
                    ${dashData.grade}
                </span>
                <span style="color: #cbd5e1; font-size: 1rem;">•</span>
                <span style="font-size: 1rem; font-weight: 500; color: #475569;">
                    ${dashData.section}
                </span>
                
                <!-- Language Filter Segment -->
                <div class="td-segment-group" style="margin-left: 0.5rem;" id="td-header-lang-filter">
                    <button class="td-segment-btn ${currentTeacherDashLanguage === 'all' ? 'active' : ''}" data-lang="all">Overall</button>
                    <button class="td-segment-btn ${currentTeacherDashLanguage === 'english' ? 'active' : ''}" data-lang="english">English</button>
                    <button class="td-segment-btn ${currentTeacherDashLanguage === 'filipino' ? 'active' : ''}" data-lang="filipino">Filipino</button>
                </div>
            </div>

            <!-- Toolbar / Tab Control -->
            <div class="td-toolbar">
                <div style="display: flex; gap: 0.5rem;" id="teacher-dash-tabs">
                    <button class="td-tab-btn ${currentTeacherDashTab === 'summary' ? 'active' : ''}" data-tab="summary">Assessment Summary</button>
                    <button class="td-tab-btn ${currentTeacherDashTab === 'record' ? 'active' : ''}" data-tab="record">Class Record</button>
                    <button class="td-tab-btn ${currentTeacherDashTab === 'assignments' ? 'active' : ''}" data-tab="assignments">Assignments</button>
                </div>

                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <button id="td-btn-export" class="td-btn-outline-grey">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Export Report
                    </button>
                    <button id="td-btn-refresh" class="td-btn-ghost">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Dynamic Tab Content -->
            <div id="teacher-dash-content">
                ${getTeacherDashTabContent(currentTeacherDashTab, dashData)}
            </div>
        </div>

        ${getExportModalHTML(dashData)}
    `;

    attachTeacherDashboardListeners();
}

function getTeacherDashTabContent(tab, dashData) {
    if (tab === 'summary') {
        return `
            <!-- Filter Panel -->
            <div class="td-card" style="padding: 1rem; margin-bottom: 1.5rem;">
                <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1rem;">
                    <div class="td-segment-group" id="td-date-filter-group">
                        <button class="td-segment-btn ${currentTeacherDashDateFilter === 'day' ? 'active' : ''}" data-filter="day">Day</button>
                        <button class="td-segment-btn ${currentTeacherDashDateFilter === 'week' ? 'active' : ''}" data-filter="week">Week</button>
                        <button class="td-segment-btn ${currentTeacherDashDateFilter === 'month' ? 'active' : ''}" data-filter="month">Month</button>
                        <button class="td-segment-btn ${currentTeacherDashDateFilter === 'custom' ? 'active' : ''}" data-filter="custom">Custom</button>
                    </div>
                    <input type="date" id="td-start-date" value="${currentTeacherDashStartDate}" style="border: 1px solid rgba(26,31,54,0.2); border-radius: 0.375rem; padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #0f172a; outline: none;">
                    <span class="td-text-muted" style="font-size: 0.75rem;">to</span>
                    <input type="date" id="td-end-date" value="${currentTeacherDashEndDate}" style="border: 1px solid rgba(26,31,54,0.2); border-radius: 0.375rem; padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #0f172a; outline: none;">
                    <button id="td-btn-apply-filters" class="td-btn-solid-navy" style="padding: 0.35rem 1rem;">Apply Filters</button>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <span class="td-badge td-badge-grey">${dashData.language}</span>
                    <span class="td-badge td-badge-grey">
                        ${currentTeacherDashDateFilter === 'custom' && currentTeacherDashStartDate && currentTeacherDashEndDate 
                            ? `${currentTeacherDashStartDate} to${currentTeacherDashEndDate}` 
                            : currentTeacherDashDateFilter === 'custom' ? 'All Time' 
                            : currentTeacherDashDateFilter.charAt(0).toUpperCase() + currentTeacherDashDateFilter.slice(1)}
                    </span>
                    <span class="td-badge td-badge-grey">${dashData.section}</span>
                </div>
            </div>

            <!-- KPI Cards -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="td-card">
                    <div class="td-mono" style="font-size: 2.5rem; line-height: 1; margin-bottom: 0.5rem;">${dashData.storedRecords}</div>
                    <div class="td-text-muted" style="font-size: 0.875rem;">Stored Assessment Records</div>
                </div>
                <div class="td-card">
                    <div class="td-mono" style="font-size: 2.5rem; line-height: 1; margin-bottom: 0.5rem;">${dashData.classifiedAssessments}</div>
                    <div class="td-text-muted" style="font-size: 0.875rem;">Classified Assessments</div>
                </div>
                <div class="td-card td-card-warning" style="display: flex; align-items: flex-start; gap: 1rem;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 0.25rem;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                    <div>
                        <div class="td-mono td-text-amber" style="font-size: 2.5rem; line-height: 1; margin-bottom: 0.5rem;">${dashData.needsFollowUp}</div>
                        <div style="color: #b45309; font-size: 0.875rem; line-height: 1.4;">Learners classified as Low Emerging — require intervention</div>
                    </div>
                </div>
            </div>

            <!-- Overall Class Performance Summary -->
            <div class="td-card" style="margin-bottom: 1.5rem; display: grid; grid-template-columns: repeat(4, 1fr); text-align: center; padding: 2rem 0;">
                <div style="border-right: 1px solid rgba(26,31,54,0.09);">
                    <div class="td-mono" style="font-size: 2.5rem; margin-bottom: 0.5rem;">${dashData.assessed}</div>
                    <div class="td-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Learners Assessed</div>
                </div>
                <div style="border-right: 1px solid rgba(26,31,54,0.09);">
                    <div class="td-mono td-text-blue" style="font-size: 2.5rem; margin-bottom: 0.5rem;">${dashData.avgWcpm}</div>
                    <div class="td-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Avg. WCPM</div>
                </div>
                <div style="border-right: 1px solid rgba(26,31,54,0.09);">
                    <div class="td-mono td-text-emerald" style="font-size: 2.5rem; margin-bottom: 0.5rem;">${dashData.avgAccuracy}%</div>
                    <div class="td-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Avg. Accuracy</div>
                </div>
                <div>
                    <div class="td-mono td-text-violet" style="font-size: 2.5rem; margin-bottom: 0.5rem;">${dashData.avgComp}%</div>
                    <div class="td-text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Avg. Comprehension</div>
                </div>
            </div>

            <!-- Tables Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                
                <!-- Reading Profile Table -->
                <div class="td-card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 1.25rem; border-bottom: 1px solid rgba(26,31,54,0.09); font-weight: 600;">Results by Reading Profile</div>
                    <table class="td-table">
                        <thead>
                            <tr>
                                <th>Profile</th>
                                <th>Students</th>
                                <th>Avg WCPM</th>
                                <th>Avg Accuracy</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${dashData.profileStats.length > 0 ? dashData.profileStats.map(stat => {
                                const p = String(stat.profile).toLowerCase();
                                const badgeColor = p.includes('low') || p.includes('frustration') ? 'td-badge-red' : p.includes('developing') || p.includes('high') ? 'td-badge-amber' : p.includes('transitioning') ? 'td-badge-blue' : 'td-badge-emerald';
                                const textColor = p.includes('low') || p.includes('frustration') ? 'td-text-red' : p.includes('developing') || p.includes('high') ? 'td-text-amber' : 'td-text-emerald';
                                
                                return `
                                <tr>
                                    <td><span class="td-badge ${badgeColor}">${stat.profile}</span></td>
                                    <td>${stat.students}</td>
                                    <td class="td-mono">${Number(stat.avg_wcpm).toFixed(0)}</td>
                                    <td class="td-mono ${textColor}">${Number(stat.avg_accuracy).toFixed(1)}%</td>
                                </tr>
                                `;
                            }).join('') : `<tr><td colspan="4" class="td-text-muted" style="text-align:center; padding: 2rem;">No profile data available</td></tr>`}
                        </tbody>
                    </table>
                </div>

                <!-- Part 1 Level Table -->
                <div class="td-card" style="padding: 0; overflow: hidden;">
                    <div style="padding: 1.25rem; border-bottom: 1px solid rgba(26,31,54,0.09); font-weight: 600;">Results by Part 1 — Word Recognition Level</div>
                    <table class="td-table">
                        <thead>
                            <tr>
                                <th>P1 Level</th>
                                <th>Students</th>
                                <th>Avg WCPM</th>
                                <th>Avg Accuracy</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${dashData.part1Stats.length > 0 ? dashData.part1Stats.map(stat => {
                                const l = String(stat.level).toLowerCase();
                                const badgeColor = l.includes('refresher') || l.includes('fail') ? 'td-badge-red' : 'td-badge-emerald';
                                const textColor = l.includes('refresher') || l.includes('fail') ? 'td-text-muted' : 'td-text-emerald';
                                
                                return `
                                <tr>
                                    <td><span class="td-badge ${badgeColor}">${stat.level}</span></td>
                                    <td>${stat.students}</td>
                                    <td class="td-mono ${textColor}">${Number(stat.avg_wcpm) > 0 ? Number(stat.avg_wcpm).toFixed(0) : 'N/A'}</td>
                                    <td class="td-mono ${textColor}">${Number(stat.avg_accuracy) > 0 ? Number(stat.avg_accuracy).toFixed(1) + '%' : 'N/A'}</td>
                                </tr>
                                `;
                            }).join('') : `<tr><td colspan="4" class="td-text-muted" style="text-align:center; padding: 2rem;">No Part 1 data available</td></tr>`}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    } 

if (tab === 'record') {
        // Look for materials data instead of student data
        const materials = dashData.materialReports || dashData.materials || [];

        return `
            <!-- Materials Report Search & Meta -->
            <div class="td-card" style="padding: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; gap: 0.75rem; align-items: center; flex: 1; max-width: 400px;">
                    <div style="position: relative; width: 100%;">
                        <input type="text" id="td-record-search" placeholder="Search reading material title..." style="width: 100%; padding: 0.5rem 0.75rem 0.5rem 2.25rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.875rem; outline: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%);">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </div>
                </div>
                
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span class="td-text-muted" style="font-size: 0.875rem;">Total Materials Assessed: <strong>${materials.length}</strong></span>
                </div>
            </div>

            <!-- Materials Report Table -->
            <div class="td-card" style="padding: 0; overflow-x: auto;">
                <table class="td-table" id="td-class-record-table">
                    <thead>
                        <tr>
                            <th>Reading Material</th>
                            <th>Language & Grade</th>
                            <th>Completion Rate</th>
                            <th>Avg. WCPM</th>
                            <th>Avg. Accuracy</th>
                            <th>Avg. Comprehension</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${materials.length > 0 ? materials.map(m => {
                            const title = m.title || m.material_title || 'Untitled Material';
                            const language = m.language || 'Unknown';
                            const grade = m.grade_level || m.grade || 'N/A';
                            
                            // Metrics
                            const totalAssigned = m.total_assigned || 0;
                            const totalCompleted = m.total_completed || 0;
                            const completionPerc = totalAssigned > 0 ? Math.round((totalCompleted / totalAssigned) * 100) : 0;
                            
                            const avgWcpm = m.avg_wcpm || 0;
                            const avgAcc = m.avg_accuracy || 0;
                            const avgComp = m.avg_comprehension || 0;
                            
                            const searchStr = title.toLowerCase();

                            return `
                            <tr class="td-record-row" data-search="${searchStr}">
                                <td>
                                    <div style="font-weight: 600; color: #1e293b;">${escapeAssessmentHtml(title)}</div>
                                </td>
                                <td>
                                    <div class="td-text-muted" style="font-size: 0.875rem;">
                                        <span class="td-badge td-badge-grey" style="margin-right: 4px;">${escapeAssessmentHtml(language)}</span> 
                                        Grade ${escapeAssessmentHtml(grade)}
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="td-mono">${totalCompleted} /${totalAssigned}</span>
                                        <span class="td-text-muted" style="font-size: 0.75rem;">(${completionPerc}%)</span>
                                    </div>
                                </td>
                                <td class="td-mono td-text-blue">${Number(avgWcpm).toFixed(0)}</td>
                                <td class="td-mono td-text-emerald">${Number(avgAcc).toFixed(1)}%</td>
                                <td class="td-mono td-text-amber">${Number(avgComp).toFixed(0)}%</td>
                                <td style="text-align: center;">
                                    <button class="td-btn-outline-navy btn-view-material" data-id="${m.id || m.material_id}" style="padding: 4px 10px; font-size: 0.75rem; margin: 0 auto;">
                                        View Breakdown
                                    </button>
                                </td>
                            </tr>
                            `;
                        }).join('') : `
                            <tr>
                                <td colspan="7" class="td-text-muted" style="text-align: center; padding: 3rem;">
                                    No reading material reports found for this class section.
                                </td>
                            </tr>
                        `}
                    </tbody>
                </table>
            </div>
        `;
    }
    
    if (tab === 'assignments') { 
        return `<div class="td-card" style="text-align:center; padding: 3rem;">Assignments View</div>`; 
    }

    return '';
}

function getExportModalHTML(dashData) {
    return `
        <!-- Export Modal -->
        <div id="td-export-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); z-index: 9999; align-items: center; justify-content: center; padding: 1.25rem;">
            <div style="background: #ffffff; width: 100%; max-width: 28rem; border-radius: 0.75rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); overflow: hidden;">
                
                <div style="padding: 1.5rem; border-bottom: 1px solid rgba(26,31,54,0.09); display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <h2 style="font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700; color: #1e4d8c; margin: 0 0 0.25rem 0;">Export Class Report</h2>
                        <p style="color: #6b7280; font-size: 0.875rem; margin: 0;">Download assessment records for your class</p>
                    </div>
                    <button id="td-close-export-btn" style="background: transparent; border: none; color: #6b7280; cursor: pointer; padding: 0.25rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>

                <div style="padding: 1.5rem;">
                    <div style="background: #f4f6fb; border: 1px solid rgba(26,31,54,0.09); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem;">
                        <div style="font-size: 0.875rem; color: #111827; font-weight: 500;">Grade: ${dashData?.grade || 'Class'} — Section: ${dashData?.section || 'Section'}</div>
                        <div style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Teacher · Class Records</div>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.875rem; font-weight: 500; color: #111827; margin-bottom: 0.5rem;">Language Filter</label>
                        <div style="display: flex; background: #f4f6fb; border-radius: 0.5rem; padding: 0.25rem; border: 1px solid rgba(26,31,54,0.09);" id="export-modal-lang-group">
                            <button class="export-lang-btn ${currentTeacherDashLanguage === 'all' ? 'active' : ''}" data-lang="all" style="flex: 1; border: none; background: ${currentTeacherDashLanguage === 'all' ? '#ffffff' : 'transparent'}; padding: 0.5rem; font-size: 0.875rem; color: #111827; font-weight: 500; border-radius: 0.375rem; cursor: pointer;">All</button>
                            <button class="export-lang-btn ${currentTeacherDashLanguage === 'filipino' ? 'active' : ''}" data-lang="filipino" style="flex: 1; border: none; background: ${currentTeacherDashLanguage === 'filipino' ? '#ffffff' : 'transparent'}; padding: 0.5rem; font-size: 0.875rem; color: #111827; font-weight: 500; border-radius: 0.375rem; cursor: pointer;">Filipino</button>
                            <button class="export-lang-btn ${currentTeacherDashLanguage === 'english' ? 'active' : ''}" data-lang="english" style="flex: 1; border: none; background: ${currentTeacherDashLanguage === 'english' ? '#ffffff' : 'transparent'}; padding: 0.5rem; font-size: 0.875rem; color: #111827; font-weight: 500; border-radius: 0.375rem; cursor: pointer;">English</button>
                        </div>
                    </div>
                </div>

                <div style="padding: 1.25rem 1.5rem; background: #f9fafb; border-top: 1px solid rgba(26,31,54,0.09); display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button id="td-cancel-export-btn" style="padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; border: 1px solid rgba(26,31,54,0.15); background: #ffffff; color: #111827; cursor: pointer;">Cancel</button>
                    <button id="td-confirm-download-btn" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; border: none; background: #1e4d8c; color: #ffffff; cursor: pointer;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                        Download Report
                    </button>
                </div>
            </div>
        </div>
    `;
}

function attachTeacherDashboardListeners() {
    // 1. Tab Switching Handler
    const tabContainer = document.getElementById('teacher-dash-tabs');
    if (tabContainer) {
        tabContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('.td-tab-btn');
            if (btn) {
                const tab = btn.getAttribute('data-tab');
                if (tab && tab !== currentTeacherDashTab) {
                    currentTeacherDashTab = tab;
                    renderTeacherDashboard();
                }
            }
        });
    }

    // 2. Refresh Button Handler
    const refreshBtn = document.getElementById('td-btn-refresh');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => renderTeacherDashboard());
    }

    // 3. Header Language Filter
    const langFilterContainer = document.getElementById('td-header-lang-filter');
    if (langFilterContainer) {
        langFilterContainer.addEventListener('click', (e) => {
            const btn = e.target.closest('.td-segment-btn');
            if (btn) {
                const lang = btn.getAttribute('data-lang');
                if (lang && lang !== currentTeacherDashLanguage) {
                    currentTeacherDashLanguage = lang;
                    renderTeacherDashboard();
                }
            }
        });
    }

    // 4. Date Filter Group
    const dateFilterGroup = document.getElementById('td-date-filter-group');
    if (dateFilterGroup) {
        dateFilterGroup.addEventListener('click', (e) => {
            const btn = e.target.closest('.td-segment-btn');
            if (btn) {
                const filter = btn.getAttribute('data-filter');
                currentTeacherDashDateFilter = filter;
                
                if (filter !== 'custom') {
                    currentTeacherDashStartDate = '';
                    currentTeacherDashEndDate = '';
                    renderTeacherDashboard();
                } else {
                    document.querySelectorAll('#td-date-filter-group .td-segment-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                }
            }
        });
    }

    // 5. Apply Custom Date Filters
    const btnApplyFilters = document.getElementById('td-btn-apply-filters');
    if (btnApplyFilters) {
        btnApplyFilters.addEventListener('click', () => {
            const start = document.getElementById('td-start-date')?.value || '';
            const end = document.getElementById('td-end-date')?.value || '';
            currentTeacherDashDateFilter = 'custom';
            currentTeacherDashStartDate = start;
            currentTeacherDashEndDate = end;
            renderTeacherDashboard();
        });
    }

    // 6. Export Modal Event Handlers
    const exportBtn = document.getElementById('td-btn-export');
    const exportModal = document.getElementById('td-export-modal');
    const closeExportBtn = document.getElementById('td-close-export-btn');
    const cancelExportBtn = document.getElementById('td-cancel-export-btn');
    const confirmDownloadBtn = document.getElementById('td-confirm-download-btn');

    if (exportBtn && exportModal) {
        exportBtn.addEventListener('click', () => {
            exportModal.style.display = 'flex';
        });
    }

    const hideModal = () => {
        if (exportModal) exportModal.style.display = 'none';
    };

    if (closeExportBtn) closeExportBtn.addEventListener('click', hideModal);
    if (cancelExportBtn) cancelExportBtn.addEventListener('click', hideModal);

    if (confirmDownloadBtn) {
        confirmDownloadBtn.addEventListener('click', () => {
            window.location.href = `export_excel.php?language=${currentTeacherDashLanguage}`;
            hideModal();
        });
    }

    // 7. Class Record Live Search Handler
    const searchInput = document.getElementById('td-record-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.td-record-row');
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                row.style.display = searchData.includes(query) ? '' : 'none';
            });
        });
    }

    // 8. View Details Button Handler
    const recordTable = document.getElementById('td-class-record-table');
    if (recordTable) {
        recordTable.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-view-student');
            if (btn) {
                const studentId = btn.getAttribute('data-id');
                console.log(`Opening individual assessment log for student ID: ${studentId}`);
            }
        });
    }
}