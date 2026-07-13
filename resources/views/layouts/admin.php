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
        }
    </style>
</head>
<body>
    <div class="admin-shell">
        <header class="admin-header">
            <div class="admin-brand">Uthyrning Admin</div>

            <nav class="admin-nav" aria-label="Adminnavigation">
                <a href="/admin">Dashboard</a>
                <span aria-disabled="true">Objekt</span>
                <span aria-disabled="true">Bokningar</span>
                <span aria-disabled="true">Kunder</span>
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
