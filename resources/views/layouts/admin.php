<?php

$pageTitle = is_string($pageTitle ?? null) && $pageTitle !== '' ? $pageTitle : 'Admin';
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$content = is_string($content ?? null) ? $content : '';

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape($pageTitle) ?> - Uthyrning</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Arial, sans-serif;
            line-height: 1.5;
        }

        body {
            background: #f6f8fb;
            color: #172033;
            margin: 0;
        }

        .admin-shell {
            min-height: 100vh;
        }

        .admin-header {
            align-items: center;
            background: #ffffff;
            border-bottom: 1px solid #dce3ee;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            padding: 1rem;
        }

        .admin-brand {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .admin-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .admin-nav a,
        .admin-nav span {
            color: #172033;
            text-decoration: none;
        }

        .admin-nav span {
            color: #6b7280;
        }

        .admin-logout button {
            background: #172033;
            border: 0;
            border-radius: 6px;
            color: #ffffff;
            cursor: pointer;
            padding: 0.55rem 0.8rem;
        }

        .admin-main {
            margin: 0 auto;
            max-width: 960px;
            padding: 2rem 1rem;
        }

        .admin-panel {
            background: #ffffff;
            border: 1px solid #dce3ee;
            border-radius: 8px;
            padding: 1.25rem;
        }

        .admin-page-header {
            align-items: flex-start;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .admin-button {
            background: #172033;
            border: 0;
            border-radius: 6px;
            color: #ffffff;
            cursor: pointer;
            display: inline-block;
            font: inherit;
            padding: 0.6rem 0.85rem;
            text-decoration: none;
        }

        .admin-button-secondary {
            background: #e8edf5;
            color: #172033;
        }

        .admin-button-danger {
            background: #9f1d2a;
        }

        .admin-button:disabled {
            cursor: not-allowed;
            opacity: 0.55;
        }

        .admin-button:focus-visible,
        .admin-link-button:focus-visible,
        .admin-nav a:focus-visible {
            outline: 3px solid #7296c7;
            outline-offset: 3px;
        }

        .admin-inline-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .admin-inline-actions form {
            margin: 0;
        }

        .admin-link-button {
            background: transparent;
            border: 0;
            color: #9f1d2a;
            cursor: pointer;
            font: inherit;
            padding: 0;
            text-decoration: underline;
        }

        .admin-table-wrap {
            overflow-x: auto;
        }

        .admin-table {
            border-collapse: collapse;
            min-width: 760px;
            width: 100%;
        }

        .admin-table th,
        .admin-table td {
            border-bottom: 1px solid #dce3ee;
            padding: 0.7rem;
            text-align: left;
            vertical-align: top;
        }

        .admin-form {
            display: grid;
            gap: 1rem;
        }

        .admin-form-grid,
        .admin-readonly-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .admin-form label,
        .admin-readonly-grid div {
            display: grid;
            gap: 0.35rem;
        }

        .admin-form input,
        .admin-form select,
        .admin-form textarea {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
            font: inherit;
            padding: 0.65rem;
            width: 100%;
        }

        .admin-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .admin-checkboxes label {
            align-items: center;
            display: flex;
            gap: 0.45rem;
        }

        .admin-checkboxes input {
            width: auto;
        }

        .admin-error,
        .admin-form em {
            color: #9f1d2a;
        }

        .admin-message {
            background: #eef6f1;
            border: 1px solid #c9e6d3;
            border-radius: 6px;
            padding: 0.75rem;
        }

        .admin-archive-form {
            border-top: 1px solid #dce3ee;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
        }

        .admin-publication-actions {
            border-top: 1px solid #dce3ee;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
        }

        .admin-media-section {
            border-top: 1px solid #dce3ee;
            display: grid;
            gap: 1rem;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
        }

        .admin-media-upload {
            align-items: end;
            display: grid;
            gap: 0.75rem;
            grid-template-columns: minmax(220px, 1fr) auto;
        }

        .admin-media-hint,
        .admin-media-empty,
        .admin-media-meta {
            color: #526072;
            font-size: 0.92rem;
            margin: 0;
        }

        .admin-media-hint {
            grid-column: 1 / -1;
        }

        .admin-media-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        }

        .admin-media-card {
            border: 1px solid #dce3ee;
            border-radius: 8px;
            display: grid;
            gap: 0.75rem;
            padding: 0.75rem;
        }

        .admin-media-card.is-primary {
            border-color: #5b7fae;
            box-shadow: 0 0 0 1px #5b7fae;
        }

        .admin-media-card img {
            aspect-ratio: 4 / 3;
            background: #eef2f7;
            border-radius: 6px;
            display: block;
            object-fit: cover;
            width: 100%;
        }

        .admin-media-card p {
            margin: 0.25rem 0 0;
            overflow-wrap: anywhere;
        }

        .admin-media-badge {
            background: #eef6f1;
            border: 1px solid #c9e6d3;
            border-radius: 999px;
            color: #1f6b3d;
            display: inline-block;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
        }

        .admin-media-card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .admin-media-card input[type="number"] {
            max-width: 7rem;
        }

        .admin-form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .status-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin: 1rem 0;
        }

        .status-box {
            background: #eef6f1;
            border: 1px solid #c9e6d3;
            border-radius: 8px;
            padding: 1rem;
        }

        @media (max-width: 640px) {
            .admin-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .admin-media-upload {
                align-items: stretch;
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-shell">
        <header class="admin-header">
            <div class="admin-brand">Uthyrning Admin</div>

            <nav class="admin-nav" aria-label="Adminnavigation">
                <a href="/admin">Dashboard</a>
                <a href="/admin/items">Objekt och priser</a>
                <a href="/admin/bookings">Bokningar</a>
                <a href="/admin/notifications">Notifieringar</a>
                <a href="/admin/customers">Kunder</a>
                <span aria-disabled="true">Service</span>
                <span aria-disabled="true">Inställningar</span>
            </nav>

            <form class="admin-logout" method="post" action="/logout">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <button type="submit">Logga ut</button>
            </form>
        </header>

        <main class="admin-main">
            <?= $content ?>
        </main>
    </div>
</body>
</html>
