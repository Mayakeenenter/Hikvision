<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Hikvision Access Control Event Dashboard — KEEN Project employee attendance monitoring system.">
    <title>KEEN | Hikvision Event Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ═══════════════════════════════════════════════
           DESIGN TOKENS
        ═══════════════════════════════════════════════ */
        :root {
            --bg-base:        #0b0e1a;
            --bg-surface:     #111626;
            --bg-card:        #161d2e;
            --bg-card-hover:  #1c2540;
            --bg-row-hover:   #1a2238;
            --border:         rgba(255,255,255,0.07);
            --border-strong:  rgba(255,255,255,0.12);

            --accent:         #4f8ef7;
            --accent-glow:    rgba(79,142,247,0.25);
            --accent-light:   #7daeff;

            --green:          #22c55e;
            --green-bg:       rgba(34,197,94,0.12);
            --green-glow:     rgba(34,197,94,0.20);

            --yellow:         #f59e0b;
            --yellow-bg:      rgba(245,158,11,0.12);

            --red:            #ef4444;
            --red-bg:         rgba(239,68,68,0.12);
            --red-glow:       rgba(239,68,68,0.20);

            --purple:         #a855f7;
            --purple-bg:      rgba(168,85,247,0.12);

            --cyan:           #06b6d4;
            --cyan-bg:        rgba(6,182,212,0.12);

            --orange:         #f97316;
            --orange-bg:      rgba(249,115,22,0.12);

            --text-primary:   #e8edf8;
            --text-secondary: #8b95b0;
            --text-muted:     #4d5878;

            --font:           'Inter', sans-serif;
            --radius:         12px;
            --radius-lg:      16px;
            --radius-sm:      8px;
            --shadow:         0 4px 24px rgba(0,0,0,0.45);
            --shadow-card:    0 8px 32px rgba(0,0,0,0.55);
        }

        /* ═══════════════════════════════════════════════
           RESET & BASE
        ═══════════════════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        a { text-decoration: none; color: inherit; }

        /* ═══════════════════════════════════════════════
           SCROLLBAR
        ═══════════════════════════════════════════════ */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-base); }
        ::-webkit-scrollbar-thumb { background: var(--border-strong); border-radius: 999px; }

        /* ═══════════════════════════════════════════════
           LAYOUT
        ═══════════════════════════════════════════════ */
        .app-layout {
            display: flex;
            min-height: 100vh;
        }

        /* ═══════════════════════════════════════════════
           SIDEBAR
        ═══════════════════════════════════════════════ */
        .sidebar {
            width: 260px;
            min-height: 100vh;
            background: var(--bg-surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            padding: 28px 0 24px;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px 28px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--accent), #7c3aed);
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 12px var(--accent-glow);
            flex-shrink: 0;
        }

        .logo-text { font-size: 18px; font-weight: 700; letter-spacing: -0.3px; }
        .logo-sub  { font-size: 11px; color: var(--text-secondary); letter-spacing: 0.5px; text-transform: uppercase; }

        .sidebar-nav { flex: 1; padding: 0 12px; }

        .nav-section-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 0 12px;
            margin: 16px 0 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 2px;
        }

        .nav-item:hover { background: var(--bg-card-hover); color: var(--text-primary); }

        .nav-item.active {
            background: rgba(79,142,247,0.12);
            color: var(--accent-light);
            box-shadow: inset 2px 0 0 var(--accent);
        }

        .nav-icon { font-size: 16px; width: 20px; text-align: center; }

        .sidebar-footer {
            padding: 20px 24px 0;
            border-top: 1px solid var(--border);
        }

        .device-status {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .status-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 8px var(--green-glow);
            animation: pulse-dot 2s infinite;
            flex-shrink: 0;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.4; }
        }

        /* ═══════════════════════════════════════════════
           MAIN CONTENT
        ═══════════════════════════════════════════════ */
        .main-content {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ═══════════════════════════════════════════════
           TOP BAR
        ═══════════════════════════════════════════════ */
        .topbar {
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-left h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .topbar-left p {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .topbar-right { display: flex; align-items: center; gap: 12px; }

        .topbar-time {
            font-size: 13px;
            color: var(--text-secondary);
            padding: 6px 14px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-variant-numeric: tabular-nums;
        }

        /* ═══════════════════════════════════════════════
           BUTTONS
        ═══════════════════════════════════════════════ */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            font-family: var(--font);
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 4px 14px var(--accent-glow);
        }
        .btn-primary:hover {
            background: var(--accent-light);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px var(--accent-glow);
        }

        .btn-ghost {
            background: var(--bg-card);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover {
            background: var(--bg-card-hover);
            color: var(--text-primary);
            border-color: var(--border-strong);
        }

        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* ═══════════════════════════════════════════════
           PAGE BODY
        ═══════════════════════════════════════════════ */
        .page-body { padding: 28px 32px; flex: 1; }

        /* ═══════════════════════════════════════════════
           STATS CARDS
        ═══════════════════════════════════════════════ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: var(--border-strong);
            transform: translateY(-2px);
            box-shadow: var(--shadow-card);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .stat-card.blue::before  { background: linear-gradient(90deg, var(--accent), #7c3aed); }
        .stat-card.green::before { background: linear-gradient(90deg, var(--green), #059669); }
        .stat-card.purple::before{ background: linear-gradient(90deg, var(--purple), var(--accent)); }
        .stat-card.orange::before{ background: linear-gradient(90deg, var(--orange), var(--yellow)); }

        .stat-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .stat-icon {
            width: 40px; height: 40px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
        }

        .stat-icon.blue   { background: rgba(79,142,247,0.15); }
        .stat-icon.green  { background: var(--green-bg); }
        .stat-icon.purple { background: var(--purple-bg); }
        .stat-icon.orange { background: var(--orange-bg); }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -1px;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .stat-sub {
            font-size: 11px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* ═══════════════════════════════════════════════
           FILTER BAR
        ═══════════════════════════════════════════════ */
        .filter-bar {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .filter-bar form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 180px;
        }

        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .form-control {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-family: var(--font);
            font-size: 13px;
            padding: 8px 12px;
            width: 100%;
            transition: border-color 0.2s;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 2px var(--accent-glow);
        }

        .form-control option { background: var(--bg-surface); }

        .search-wrapper {
            flex: 2;
            min-width: 220px;
        }

        .search-wrapper .form-control {
            padding-left: 36px;
        }

        .search-icon-wrap {
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            pointer-events: none;
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        /* ═══════════════════════════════════════════════
           TABLE SECTION
        ═══════════════════════════════════════════════ */
        .table-section {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            gap: 12px;
        }

        .table-title {
            font-size: 15px;
            font-weight: 700;
        }

        .table-meta {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 1px;
        }

        .table-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 10px;
            background: var(--accent-glow);
            color: var(--accent-light);
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }

        .table-wrapper { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }

        thead th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--text-muted);
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        thead th:first-child { padding-left: 24px; }
        thead th:last-child  { padding-right: 24px; }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }

        tbody tr:last-child { border-bottom: none; }

        tbody tr:hover { background: var(--bg-row-hover); }

        tbody td {
            padding: 14px 16px;
            color: var(--text-primary);
            vertical-align: middle;
        }

        tbody td:first-child { padding-left: 24px; }
        tbody td:last-child  { padding-right: 24px; }

        .row-no {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .emp-id {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 22px;
            padding: 0 8px;
            background: rgba(79,142,247,0.10);
            color: var(--accent-light);
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
        }

        .emp-name-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .emp-avatar {
            width: 30px; height: 30px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px;
            font-weight: 700;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        .emp-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .emp-anon {
            color: var(--text-muted);
            font-style: italic;
        }

        .card-no {
            font-size: 12px;
            color: var(--text-muted);
            font-variant-numeric: tabular-nums;
        }

        /* ═══════════════════════════════════════════════
           BADGES
        ═══════════════════════════════════════════════ */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .badge-checkIn       { background: rgba(16,185,129,0.12); color: #10b981; border: 1px solid rgba(16,185,129,0.25); }
        .badge-checkIn .badge-dot { background: #10b981; }

        .badge-checkOut      { background: rgba(99,102,241,0.12); color: #818cf8; border: 1px solid rgba(99,102,241,0.25); }
        .badge-checkOut .badge-dot { background: #818cf8; }

        .badge-authenticated { background: var(--green-bg);  color: var(--green);  border: 1px solid rgba(34,197,94,0.2); }
        .badge-authenticated .badge-dot { background: var(--green); }

        .badge-failed        { background: var(--red-bg);    color: var(--red);    border: 1px solid rgba(239,68,68,0.2); }
        .badge-failed .badge-dot { background: var(--red); }

        .badge-doorOpen      { background: var(--cyan-bg);   color: var(--cyan);   border: 1px solid rgba(6,182,212,0.2); }
        .badge-doorOpen .badge-dot { background: var(--cyan); }

        .badge-doorClosed    { background: rgba(100,116,139,0.12); color: #94a3b8; border: 1px solid rgba(100,116,139,0.2); }
        .badge-doorClosed .badge-dot { background: #94a3b8; }

        .badge-exitButton    { background: var(--orange-bg); color: var(--orange); border: 1px solid rgba(249,115,22,0.2); }
        .badge-exitButton .badge-dot { background: var(--orange); }

        .badge-break         { background: var(--yellow-bg); color: var(--yellow); border: 1px solid rgba(245,158,11,0.25); }
        .badge-break .badge-dot { background: var(--yellow); }

        .badge-alarm         { background: rgba(244,63,94,0.12); color: #f43f5e; border: 1px solid rgba(244,63,94,0.25); }
        .badge-alarm .badge-dot { background: #f43f5e; }

        .badge-access        { background: var(--purple-bg); color: var(--purple); border: 1px solid rgba(168,85,247,0.2); }
        .badge-access .badge-dot { background: var(--purple); }

        /* Quick Filter Chips */
        .filter-chips {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 6px;
            margin-bottom: 20px;
        }
        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 999px;
            font-size: 12.5px;
            font-weight: 500;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .filter-chip:hover {
            background: var(--bg-card-hover);
            color: var(--text-primary);
            border-color: var(--border-strong);
        }
        .filter-chip.active {
            background: rgba(79,142,247,0.15);
            color: var(--accent-light);
            border-color: var(--accent);
            font-weight: 600;
        }
        .filter-chip-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 18px;
            padding: 0 6px;
            border-radius: 999px;
            background: rgba(255,255,255,0.08);
            font-size: 11px;
            font-weight: 700;
        }
        .filter-chip.active .filter-chip-count {
            background: var(--accent);
            color: #fff;
        }

        /* ═══════════════════════════════════════════════
           DATE / TIME
        ═══════════════════════════════════════════════ */
        .datetime-cell { white-space: nowrap; }

        .event-date {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            font-variant-numeric: tabular-nums;
        }

        .event-time {
            font-size: 12px;
            color: var(--text-secondary);
            font-variant-numeric: tabular-nums;
            margin-top: 2px;
        }

        .remote-host {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* ═══════════════════════════════════════════════
           EMPTY STATE
        ═══════════════════════════════════════════════ */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
        }

        .empty-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .empty-desc {
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* ═══════════════════════════════════════════════
           PAGINATION
        ═══════════════════════════════════════════════ */
        .pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 24px;
            border-top: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 12px;
        }

        .page-info {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .page-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 8px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            background: var(--bg-surface);
            border: 1px solid var(--border);
            transition: all 0.15s;
            cursor: pointer;
        }

        .page-link:hover {
            background: var(--bg-card-hover);
            color: var(--text-primary);
            border-color: var(--border-strong);
        }

        .page-link.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
            box-shadow: 0 2px 8px var(--accent-glow);
        }

        .page-link.disabled {
            opacity: 0.35;
            pointer-events: none;
        }

        /* ═══════════════════════════════════════════════
           ANIMATIONS
        ═══════════════════════════════════════════════ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .stat-card { animation: fadeInUp 0.4s ease both; }
        .stat-card:nth-child(1) { animation-delay: 0.05s; }
        .stat-card:nth-child(2) { animation-delay: 0.10s; }
        .stat-card:nth-child(3) { animation-delay: 0.15s; }
        .stat-card:nth-child(4) { animation-delay: 0.20s; }

        .table-section { animation: fadeInUp 0.4s 0.25s ease both; }

        /* ═══════════════════════════════════════════════
           RESPONSIVE
        ═══════════════════════════════════════════════ */
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .page-body { padding: 20px 16px; }
            .topbar { padding: 14px 16px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }

        /* ═══════════════════════════════════════════════
           AVATAR COLORS (cycle by hash)
        ═══════════════════════════════════════════════ */
        .av-blue   { background: rgba(79,142,247,0.18); color: var(--accent-light); }
        .av-green  { background: var(--green-bg);       color: var(--green); }
        .av-purple { background: var(--purple-bg);      color: var(--purple); }
        .av-orange { background: var(--orange-bg);      color: var(--orange); }
        .av-cyan   { background: var(--cyan-bg);        color: var(--cyan); }
    </style>
</head>
<body>

<div class="app-layout">

    {{-- ─────── SIDEBAR ─────── --}}
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">🎯</div>
            <div>
                <div class="logo-text">KEEN</div>
                <div class="logo-sub">Access Control</div>
            </div>
        </div>
        <div class="sidebar-footer">
            <div class="device-status">
                <div class="status-dot"></div>
                <div>
                    <div style="font-size:12px;font-weight:600">Device Online</div>
                    <div style="font-size:11px;color:var(--text-muted);">
                        {{ $latestEvent ? 'Last: ' . $latestEvent->recorded_at?->diffForHumans() : 'No events yet' }}
                    </div>
                </div>
            </div>
        </div>
    </aside>

    {{-- ─────── MAIN CONTENT ─────── --}}
    <div class="main-content">

        {{-- TOP BAR --}}
        <header class="topbar">
            <div class="topbar-left">
                <h1>Hikvision Event Dashboard</h1>
                <p>Real-time Access Control, Attendance & Door Events</p>
            </div>
            <div class="topbar-right">
                <span class="topbar-time" id="live-clock"></span>
                <a href="{{ route('events.index') }}" class="btn btn-ghost btn-sm">
                    🔄 Refresh
                </a>
            </div>
        </header>

        {{-- PAGE BODY --}}
        <main class="page-body">

            {{-- STATS GRID --}}
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">{{ number_format($totalEvents) }}</div>
                            <div class="stat-label">Total Events</div>
                            <div class="stat-sub">All recorded access & door events</div>
                        </div>
                        <div class="stat-icon blue">📊</div>
                    </div>
                </div>

                <div class="stat-card green">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">{{ number_format($authenticatedIn) }}</div>
                            <div class="stat-label">Authentications</div>
                            <div class="stat-sub">Face / Card / Fingerprint passes</div>
                        </div>
                        <div class="stat-icon green">🔐</div>
                    </div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">{{ number_format($checkInCount + $checkOutCount) }}</div>
                            <div class="stat-label">Check In / Out</div>
                            <div class="stat-sub">{{ number_format($checkInCount) }} In &bull; {{ number_format($checkOutCount) }} Out</div>
                        </div>
                        <div class="stat-icon purple">⏱️</div>
                    </div>
                </div>

                <div class="stat-card orange">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value">{{ number_format($doorOpenCount + $doorClosedCount + $exitButtonCount) }}</div>
                            <div class="stat-label">Door & Button Activity</div>
                            <div class="stat-sub">{{ number_format($doorOpenCount) }} Open &bull; {{ number_format($doorClosedCount) }} Closed</div>
                        </div>
                        <div class="stat-icon orange">🚪</div>
                    </div>
                </div>
            </div>

            {{-- QUICK FILTER CHIPS --}}
            <div class="filter-chips">
                <a href="{{ route('events.index') }}" class="filter-chip {{ !request()->hasAny(['badge', 'event_type']) ? 'active' : '' }}">
                    All Events <span class="filter-chip-count">{{ number_format($totalEvents) }}</span>
                </a>
                <a href="{{ route('events.index', array_merge(request()->except(['badge', 'page']), ['badge' => 'authenticated'])) }}" class="filter-chip {{ request('badge') === 'authenticated' ? 'active' : '' }}">
                    🔐 Authenticated <span class="filter-chip-count">{{ number_format($authenticatedIn) }}</span>
                </a>
                <a href="{{ route('events.index', array_merge(request()->except(['badge', 'page']), ['badge' => 'checkIn'])) }}" class="filter-chip {{ request('badge') === 'checkIn' ? 'active' : '' }}">
                    📥 Check In <span class="filter-chip-count">{{ number_format($checkInCount) }}</span>
                </a>
                <a href="{{ route('events.index', array_merge(request()->except(['badge', 'page']), ['badge' => 'checkOut'])) }}" class="filter-chip {{ request('badge') === 'checkOut' ? 'active' : '' }}">
                    📤 Check Out <span class="filter-chip-count">{{ number_format($checkOutCount) }}</span>
                </a>
                <a href="{{ route('events.index', array_merge(request()->except(['badge', 'page']), ['badge' => 'doorOpen'])) }}" class="filter-chip {{ request('badge') === 'doorOpen' ? 'active' : '' }}">
                    🚪 Door Open <span class="filter-chip-count">{{ number_format($doorOpenCount) }}</span>
                </a>
                <a href="{{ route('events.index', array_merge(request()->except(['badge', 'page']), ['badge' => 'doorClosed'])) }}" class="filter-chip {{ request('badge') === 'doorClosed' ? 'active' : '' }}">
                    🔒 Door Closed <span class="filter-chip-count">{{ number_format($doorClosedCount) }}</span>
                </a>
                <a href="{{ route('events.index', array_merge(request()->except(['badge', 'page']), ['badge' => 'exitButton'])) }}" class="filter-chip {{ request('badge') === 'exitButton' ? 'active' : '' }}">
                    🔘 Exit Button <span class="filter-chip-count">{{ number_format($exitButtonCount) }}</span>
                </a>
                @if($failedCount > 0 || $alarmCount > 0)
                    <a href="{{ route('events.index', array_merge(request()->except(['badge', 'page']), ['badge' => 'failed'])) }}" class="filter-chip {{ request('badge') === 'failed' ? 'active' : '' }}">
                        ⚠️ Failed / Alarms <span class="filter-chip-count">{{ number_format($failedCount + $alarmCount) }}</span>
                    </a>
                @endif
            </div>

            {{-- FILTER BAR --}}
            <div class="filter-bar">
                <form method="GET" action="{{ route('events.index') }}">
                    @if(request('badge'))
                        <input type="hidden" name="badge" value="{{ request('badge') }}">
                    @endif

                    {{-- Search --}}
                    <div class="filter-group search-wrapper">
                        <div class="search-icon-wrap" style="position:relative;flex:1">
                            <span class="search-icon">🔍</span>
                            <input
                                id="search-input"
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search by name, employee ID, event type..."
                                value="{{ request('search') }}"
                            >
                        </div>
                    </div>

                    {{-- Event Type --}}
                    <div class="filter-group" style="min-width:200px">
                        <label class="filter-label" for="filter-event-type">Type</label>
                        <select id="filter-event-type" name="event_type" class="form-control">
                            <option value="">All Event Types</option>
                            @foreach($eventTypes as $type)
                                <option value="{{ $type }}" @selected(request('event_type') === $type)>
                                    {{ $type }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Date From --}}
                    <div class="filter-group" style="min-width:170px">
                        <label class="filter-label" for="filter-date-from">From</label>
                        <input id="filter-date-from" type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                    </div>

                    {{-- Date To --}}
                    <div class="filter-group" style="min-width:170px">
                        <label class="filter-label" for="filter-date-to">To</label>
                        <input id="filter-date-to" type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                    </div>

                    {{-- Per page --}}
                    <div class="filter-group" style="min-width:80px;flex:0">
                        <select id="filter-per-page" name="per_page" class="form-control" onchange="this.form.submit()">
                            @foreach([12, 24, 50, 100] as $n)
                                <option value="{{ $n }}" @selected((int) request('per_page', 24) === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-actions">
                        <button id="filter-apply-btn" type="submit" class="btn btn-primary btn-sm">Apply</button>
                        @if(request()->hasAny(['search','event_type','badge','date_from','date_to']))
                            <a href="{{ route('events.index') }}" class="btn btn-ghost btn-sm">Clear</a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- TABLE --}}
            <div class="table-section">
                <div class="table-header">
                    <div>
                        <div class="table-title">
                            Access Events
                            <span class="table-count">{{ number_format($events->total()) }}</span>
                        </div>
                        <div class="table-meta">
                            @if(request()->hasAny(['search','event_type','badge','date_from','date_to']))
                                Filtered results &bull; sorted by most recent
                            @else
                                All records, sorted by most recent
                            @endif
                        </div>
                    </div>
                </div>

                <div class="table-wrapper">
                    @if($events->isEmpty())
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <div class="empty-title">No events found</div>
                            <div class="empty-desc">
                                Try adjusting your filters, or run
                                <code style="background:var(--bg-surface);padding:2px 6px;border-radius:4px;font-size:12px;">php artisan hikvision:seed</code>
                                to generate sample data.
                            </div>
                        </div>
                    @else
                        <table id="events-table">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Card No.</th>
                                    <th>Card Reader ID</th>
                                    <th>Event Type</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Remote Host</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $avatarColors = ['av-blue','av-green','av-purple','av-orange','av-cyan'];
                                    $rowOffset    = ($events->currentPage() - 1) * $events->perPage();
                                @endphp
                                @foreach($events as $index => $event)
                                    @php
                                        $colorClass = $avatarColors[crc32($event->employee_name ?? '') % count($avatarColors)];
                                        $initials   = $event->employee_name
                                            ? mb_substr($event->employee_name, 0, 1)
                                            : '?';
                                        $badgeClass = 'badge-' . ($event->status_badge ?? 'access');
                                    @endphp
                                    <tr>
                                        {{-- Row number --}}
                                        <td><span class="row-no">{{ $rowOffset + $index + 1 }}</span></td>

                                        {{-- Employee ID --}}
                                        <td>
                                            @if($event->employee_id)
                                                <span class="emp-id">{{ $event->employee_id }}</span>
                                            @else
                                                <span class="card-no">--</span>
                                            @endif
                                        </td>

                                        {{-- Employee Name --}}
                                        <td>
                                            <div class="emp-name-wrap">
                                                <div class="emp-avatar {{ $colorClass }}">{{ $initials }}</div>
                                                @if($event->employee_name)
                                                    <span class="emp-name">{{ $event->employee_name }}</span>
                                                @else
                                                    <span class="emp-anon">--</span>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- Card No --}}
                                        <td><span class="card-no">{{ $event->card_number ?? '--' }}</span></td>

                                        {{-- Card Reader ID --}}
                                        <td><span class="card-no">{{ $event->card_reader_id ?? '--' }}</span></td>

                                        {{-- Event Type Badge --}}
                                        <td>
                                            <span class="badge {{ $badgeClass }}">
                                                <span class="badge-dot"></span>
                                                {{ $event->event_type }}
                                            </span>
                                        </td>

                                        {{-- Date --}}
                                        <td>
                                            <span class="event-date">
                                                {{ $event->recorded_at?->format('m-d-Y') ?? ($event->event_date ? \Carbon\Carbon::parse($event->event_date)->format('m-d-Y') : '--') }}
                                            </span>
                                        </td>

                                        {{-- Time --}}
                                        <td>
                                            <span class="event-date">
                                                {{ $event->recorded_at?->format('H:i:s') ?? ($event->event_time ?? '--') }}
                                            </span>
                                        </td>

                                        {{-- Remote Host --}}
                                        <td><span class="remote-host">{{ $event->remote_host ?? '--' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                {{-- PAGINATION --}}
                @if($events->hasPages())
                    <div class="pagination-bar">
                        <div class="page-info">
                            Showing {{ $events->firstItem() }}–{{ $events->lastItem() }} of {{ number_format($events->total()) }} events
                        </div>
                        <div class="page-links">
                            {{-- Previous --}}
                            @if($events->onFirstPage())
                                <span class="page-link disabled">‹</span>
                            @else
                                <a href="{{ $events->previousPageUrl() }}" class="page-link">‹</a>
                            @endif

                            {{-- Page numbers --}}
                            @foreach($events->getUrlRange(max(1, $events->currentPage() - 3), min($events->lastPage(), $events->currentPage() + 3)) as $page => $url)
                                <a href="{{ $url }}" class="page-link {{ $page == $events->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                            @endforeach

                            {{-- Next --}}
                            @if($events->hasMorePages())
                                <a href="{{ $events->nextPageUrl() }}" class="page-link">›</a>
                            @else
                                <span class="page-link disabled">›</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

        </main>
    </div>
</div>

<script>
    // Live clock
    function updateClock() {
        const el = document.getElementById('live-clock');
        if (!el) return;
        const now  = new Date();
        const date = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        const time = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        el.textContent = date + '  ' + time;
    }
    updateClock();
    setInterval(updateClock, 1000);

    // Auto-submit on per-page change (already handled by onchange above)
    // Highlight active filters
    document.addEventListener('DOMContentLoaded', function () {
        const inputs = document.querySelectorAll('.filter-bar .form-control');
        inputs.forEach(function (input) {
            if (input.value && input.value !== '') {
                input.style.borderColor = 'var(--accent)';
            }
            input.addEventListener('change', function () {
                if (this.value) {
                    this.style.borderColor = 'var(--accent)';
                } else {
                    this.style.borderColor = '';
                }
            });
        });
    });
</script>

</body>
</html>
