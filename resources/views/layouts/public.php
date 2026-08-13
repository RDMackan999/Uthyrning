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

        .public-filter-form {
            align-items: end;
            background: #ffffff;
            border: 1px solid #dce3ee;
            border-radius: 8px;
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(220px, 1fr) minmax(180px, 260px) auto;
            margin: 0 0 1.25rem;
            padding: 1rem;
        }

        .public-filter-field {
            display: grid;
            gap: 0.35rem;
        }

        .public-filter-field label {
            color: #526072;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .public-filter-field input,
        .public-filter-field select {
            border: 1px solid #b9c6d8;
            border-radius: 6px;
            color: #172033;
            font: inherit;
            min-height: 2.6rem;
            padding: 0.55rem 0.65rem;
        }

        .public-filter-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .public-filter-actions button {
            background: #172033;
            border: 1px solid #172033;
            border-radius: 6px;
            color: #ffffff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            min-height: 2.6rem;
            padding: 0.55rem 1rem;
        }

        .public-filter-actions a,
        .public-detail-link {
            color: #172033;
            font-weight: 700;
            text-decoration-color: #8aa0bd;
            text-underline-offset: 0.18em;
        }

        .public-filter-summary {
            color: #526072;
            margin: 0 0 1rem;
        }

        .public-detail-link {
            display: inline-block;
            margin-top: 1rem;
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

        .public-item-card h2 a,
        .public-back-link a {
            color: #172033;
            text-decoration-color: #8aa0bd;
            text-underline-offset: 0.18em;
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

        .public-back-link {
            margin: 0 0 1rem;
        }

        .public-detail {
            background: #ffffff;
            border: 1px solid #dce3ee;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .public-detail-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: minmax(0, 1fr) minmax(240px, 320px);
        }

        .public-detail-panel {
            background: #f6f8fb;
            border: 1px solid #dce3ee;
            border-radius: 8px;
            padding: 1rem;
        }

        .public-detail-label {
            color: #526072;
            font-size: 0.9rem;
            margin: 0 0 0.35rem;
        }

        .public-detail-price {
            color: #172033;
            font-size: 1.35rem;
            font-weight: 700;
            margin: 0 0 0.75rem;
        }

        .public-detail-deposit {
            color: #526072;
            margin: 0;
        }

        @media (max-width: 640px) {
            .public-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .public-filter-form {
                align-items: stretch;
                grid-template-columns: 1fr;
            }

            .public-detail-grid {
                grid-template-columns: 1fr;
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
