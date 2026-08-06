<?php

$pageTitle = is_string($pageTitle ?? null) && $pageTitle !== '' ? $pageTitle : 'Uthyrning';
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

        .public-header {
            align-items: center;
            background: #ffffff;
            border-bottom: 1px solid #dce3ee;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            padding: 1rem;
        }

        .public-brand {
            color: #172033;
            font-size: 1.15rem;
            font-weight: 700;
            text-decoration: none;
        }

        .public-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .public-nav span {
            color: #172033;
            font-weight: 700;
        }

        .public-main {
            margin: 0 auto;
            max-width: 1120px;
            padding: 2rem 1rem;
        }

        .public-page-header {
            margin-bottom: 1.5rem;
        }

        .public-page-header h1 {
            font-size: clamp(2rem, 5vw, 3.5rem);
            line-height: 1.1;
            margin: 0 0 0.75rem;
        }

        .public-page-header p {
            color: #526072;
            max-width: 680px;
        }

        .public-item-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        }

        .public-item-card,
        .public-empty-state {
            background: #ffffff;
            border: 1px solid #dce3ee;
            border-radius: 8px;
            padding: 1.25rem;
        }

        .public-item-card h2 {
            font-size: 1.25rem;
            margin: 0 0 0.5rem;
        }

        .public-item-meta {
            color: #526072;
            font-size: 0.95rem;
            margin: 0 0 0.75rem;
        }

        .public-item-description {
            margin: 0 0 1rem;
        }

        .public-item-price {
            color: #172033;
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0;
        }

        @media (max-width: 640px) {
            .public-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="public-header">
        <a class="public-brand" href="/items">Uthyrning</a>

        <nav class="public-nav" aria-label="Publik navigation">
            <span>Objekt</span>
        </nav>
    </header>

    <main class="public-main">
        <?= $content ?>
    </main>
</body>
</html>
