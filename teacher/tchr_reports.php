<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../assets/dashboardcss/tchr_reports.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
<title>ArchiveVox - Teacher Reports</title>
</head>
<body>

<div class="dashboard-container">
    
    <!-- Header -->
    <div class="header-section">
        <h1>Reports</h1>
        <p>Monitor reading progress and assessment outcomes across your school.</p>
    </div>

    <!-- Navigation -->
    <div class="top-nav">
        <div class="nav-tabs">
            <button class="active">Assessment Summary</button>
            <button>School Overview</button>
            <button>By Grade Level</button>
            <button>Material Usage</button>
        </div>
        <div class="nav-actions">
            <button class="btn-outline">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export Class Report
            </button>
            <button class="btn-outline">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
        <div class="filter-groups">
            <div>
                <div class="filter-group-label">Language</div>
                <div class="toggle-group">
                    <button>All Languages</button>
                    <button class="active">English</button>
                    <button>Filipino</button>
                </div>
            </div>
            <div>
                <div class="filter-group-label">Date Period</div>
                <div class="toggle-group">
                    <button>Day</button>
                    <button>Week</button>
                    <button class="active">Month</button>
                    <button>Custom</button>
                </div>
            </div>
            <button class="btn-primary">Apply Filters</button>
        </div>
        <div class="active-filters">
            Showing: 
            <span class="filter-badge">English</span>
            <span class="filter-badge">October 2026</span>
        </div>
    </div>

    <!-- Top Metrics -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-title">Stored Assessment Records</div>
            <div class="metric-value">248</div>
            <div class="metric-desc">All languages; records dated in the selected period.</div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Classified Assessments</div>
            <div class="metric-value">231</div>
            <div class="metric-desc">Linked to an active student and known reading material.</div>
        </div>
        <div class="metric-card warning">
            <div class="metric-title">
                <svg width="16" height="16" fill="none" stroke="var(--warning-orange)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                No Matching Reading Activity
            </div>
            <div class="metric-value">17</div>
            <div class="metric-desc">All languages; excluded from English and Filipino breakdowns.</div>
        </div>
    </div>

    <!-- Overall Performance -->
    <div class="performance-card">
        <div class="performance-header">Overall Performance</div>
        <div class="performance-stats">
            <div class="stat-col">
                <div class="stat-val">167</div>
                <div class="stat-label">Students Assessed</div>
            </div>
            <div class="stat-col">
                <div class="stat-val color-blue">67 <span>wpm</span></div>
                <div class="stat-label">Avg. WCPM</div>
            </div>
            <div class="stat-col">
                <div class="stat-val color-green">91.2<span>%</span></div>
                <div class="stat-label">Avg. Accuracy</div>
            </div>
            <div class="stat-col">
                <div class="stat-val color-purple">88<span>%</span></div>
                <div class="stat-label">Avg. Fluency Score</div>
            </div>
        </div>
    </div>

    <!-- Table: Results by Language -->
    <div class="table-card">
        <div class="table-header">Results by Language</div>
        <table>
            <thead>
                <tr>
                    <th>Language</th>
                    <th class="text-center">Assessments</th>
                    <th class="text-center">Students</th>
                    <th class="text-center">Avg WCPM</th>
                    <th class="text-center">Avg Accuracy</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="lang-badge">English</span></td>
                    <td class="text-center">107</td>
                    <td class="text-center">78</td>
                    <td class="text-center val-blue">74</td>
                    <td class="text-center"><span class="accuracy-pill">92.1%</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Table: By Grade Level and Language -->
    <div class="table-card">
        <div class="table-header">By Grade Level and Language</div>
        <table>
            <thead>
                <tr>
                    <th>Grade</th>
                    <th>Language</th>
                    <th class="text-center">Assessments</th>
                    <th class="text-center">Students</th>
                    <th class="text-center">Avg WCPM</th>
                    <th class="text-center">Avg Accuracy</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Grade 4</td>
                    <td><span class="lang-badge">English</span></td>
                    <td class="text-center">94</td>
                    <td class="text-center">94</td>
                    <td class="text-center val-blue">65</td>
                    <td class="text-center"><span class="accuracy-pill">90.1%</span></td>
                </tr>
                <tr>
                    <td>Grade 6</td>
                    <td><span class="lang-badge">English</span></td>
                    <td class="text-center">56</td>
                    <td class="text-center">56</td>
                    <td class="text-center val-blue">87</td>
                    <td class="text-center"><span class="accuracy-pill">95.0%</span></td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>