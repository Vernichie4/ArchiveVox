<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArchiveVox - Reading Assessment Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <!-- ============================================ -->
    <!-- LOGIN VIEW -->
    <!-- ============================================ -->
    <div id="login-view" class="login-shell">
        <div class="card login-card">
            <div class="login-header">
                <div class="logo">ArchiveVox</div>
                <p class="subtitle">Reading Assessment Portal</p>
            </div>
            <form id="login-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" placeholder="Enter your username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn-primary">Sign In</button>
                <a href="#" class="forgot-password">Forgot password?</a>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- APP SHELL (Hidden until login) -->
    <!-- ============================================ -->
    <div id="app-shell" class="hidden">
        <!-- ========================================== -->
        <!-- TOPBAR - Fixed at top of app shell        -->
        <!-- ========================================== -->
        <header class="topbar" id="topbar">
            <div id="topbar-content" class="topbar-content">
                <!-- Dynamic content rendered by JavaScript -->
            </div>
        </header>

        <!-- ========================================== -->
        <!-- SIDEBAR - Left side navigation            -->
        <!-- ========================================== -->
        <div class="sidebar" id="sidebar">
            <!-- Sidebar Header -->
            <div class="sidebar-header">
                <div class="sidebar-brand-row">
                    <div class="brand-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            <polyline points="14 2 14 8 18 8"></polyline>
                        </svg>
                    </div>
                    <div class="sidebar-brand-copy">
                        <div class="logo-small">ArchiveVox</div>
                    </div>
                    <button id="sidebar-toggle" class="sidebar-toggle sidebar-toggle-mobile" aria-label="Toggle Navigation" aria-expanded="true">
                        <span></span><span></span><span></span>
                    </button>
                </div>
            </div>

            <!-- Sidebar Navigation -->
            <nav class="nav-tabs" id="nav-container">
                <!-- Dynamically rendered by JavaScript -->
            </nav>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <div class="user-profile-wrap">
                    <div class="user-avatar" id="user-avatar">ME</div>
                    <div class="user-info">
                        <span id="user-badge">Guest</span>
                        <span id="role-badge" class="role-badge-text">Teacher</span>
                    </div>
                </div>
                <button id="settings-btn" class="icon-btn" aria-label="Settings">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M12 15.2a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4Zm8.8-3.2a7.8 7.8 0 0 0-.1-1.2l2-1.6-2-3.5-2.5 1a8.9 8.9 0 0 0-2-1.2l-.4-2.7h-4l-.4 2.7a8.9 8.9 0 0 0-2 1.2l-2.5-1-2 3.5 2 1.6a7.8 7.8 0 0 0-.1 1.2c0 .4 0 .8.1 1.2l-2 1.6 2 3.5 2.5-1c.6.5 1.3.9 2 1.2l.4 2.7h4l.4-2.7c.7-.3 1.4-.7 2-1.2l2.5 1 2-3.5-2-1.6c.1-.4.1-.8.1-1.2Z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Sidebar Overlay (mobile) -->
        <div id="sidebar-overlay" class="sidebar-overlay" aria-hidden="true"></div>

        <!-- ========================================== -->
        <!-- MAIN CONTENT - Right of sidebar          -->
        <!-- ========================================== -->
        <main id="view-container" class="view-container"></main>
    </div>

    <script src="assets/chart.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>