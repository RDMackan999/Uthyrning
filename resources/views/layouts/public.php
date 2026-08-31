<?php

$pageTitle = is_string($pageTitle ?? null) && $pageTitle !== '' ? $pageTitle : 'Uthyrning';
$content = is_string($content ?? null) ? $content : '';
$publicScripts = is_array($publicScripts ?? null) ? $publicScripts : [];

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

        .public-nav a,
        .public-nav span {
            color: #172033;
            font-weight: 700;
            text-decoration: none;
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

        .public-primary-link,
        .public-filter-actions a,
        .public-detail-link {
            color: #172033;
            font-weight: 700;
            text-decoration-color: #8aa0bd;
            text-underline-offset: 0.18em;
        }

        .public-primary-link {
            background: #172033;
            border: 1px solid #172033;
            border-radius: 6px;
            color: #ffffff;
            display: inline-block;
            margin-top: 1rem;
            padding: 0.65rem 1rem;
            text-decoration: none;
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

        .public-item-card-image {
            aspect-ratio: 4 / 3;
            background: #eef2f7;
            border-radius: 6px;
            display: block;
            margin: 0 0 1rem;
            object-fit: cover;
            width: 100%;
        }

        .public-item-card-placeholder,
        .public-detail-image-placeholder {
            align-items: center;
            aspect-ratio: 4 / 3;
            background: #eef2f7;
            border: 1px dashed #b9c6d8;
            border-radius: 6px;
            color: #526072;
            display: flex;
            font-weight: 700;
            justify-content: center;
            margin: 0 0 1rem;
            min-height: 12rem;
            text-align: center;
            width: 100%;
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

        .public-detail-main-image {
            margin: 0 0 1rem;
        }

        .public-detail-main-image img {
            aspect-ratio: 4 / 3;
            background: #eef2f7;
            border-radius: 6px;
            display: block;
            object-fit: cover;
            width: 100%;
        }

        .public-detail-gallery {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            margin: 0 0 1.5rem;
        }

        .public-detail-gallery-link {
            border: 1px solid #dce3ee;
            border-radius: 6px;
            color: #172033;
            display: grid;
            gap: 0.4rem;
            padding: 0.4rem;
            text-decoration: none;
        }

        .public-detail-gallery-link:focus-visible,
        .public-nav a:focus-visible,
        .public-primary-link:focus-visible,
        .public-detail-link:focus-visible,
        .public-filter-actions button:focus-visible,
        .public-filter-actions a:focus-visible,
        .public-item-card h2 a:focus-visible,
        .public-back-link a:focus-visible {
            outline: 3px solid #7296c7;
            outline-offset: 3px;
        }

        .public-detail-gallery-link span {
            color: #526072;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .public-detail-gallery-link img {
            aspect-ratio: 4 / 3;
            background: #eef2f7;
            border-radius: 6px;
            display: block;
            object-fit: cover;
            width: 100%;
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

        .public-form {
            background: #ffffff;
            border: 1px solid #dce3ee;
            border-radius: 8px;
            display: grid;
            gap: 1rem;
            padding: 1.25rem;
        }

        .public-form-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .public-form-field {
            display: grid;
            gap: 0.35rem;
        }

        .public-form-field.full {
            grid-column: 1 / -1;
        }

        .public-form-field label {
            color: #526072;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .public-form-field input,
        .public-form-field textarea {
            border: 1px solid #b9c6d8;
            border-radius: 6px;
            color: #172033;
            font: inherit;
            min-height: 2.6rem;
            padding: 0.55rem 0.65rem;
        }

        .public-form-field textarea {
            min-height: 7rem;
            resize: vertical;
        }

        .public-form-error,
        .public-field-error {
            color: #9f1d1d;
        }

        .public-form-error {
            background: #fff4f4;
            border: 1px solid #f1b7b7;
            border-radius: 6px;
            margin: 0;
            padding: 0.75rem;
        }

        .public-calendar {
            border: 1px solid #dce3ee;
            border-radius: 8px;
            display: grid;
            gap: 1rem;
            padding: 1rem;
        }

        .public-calendar-header {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1rem;
            justify-content: space-between;
        }

        .public-calendar-header h2,
        .public-calendar-header p {
            margin: 0;
        }

        .public-calendar-header p,
        .public-calendar-legend {
            color: #526072;
            font-size: 0.9rem;
        }

        .public-calendar-nav {
            align-items: center;
            display: flex;
            gap: 0.5rem;
        }

        .public-calendar-nav button,
        .public-calendar-clear {
            background: #ffffff;
            border: 1px solid #b9c6d8;
            border-radius: 6px;
            color: #172033;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            min-height: 2.4rem;
            padding: 0.45rem 0.7rem;
        }

        .public-calendar-nav button[disabled] {
            cursor: not-allowed;
            opacity: 0.45;
        }

        .public-calendar-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .public-calendar-legend span {
            align-items: center;
            display: inline-flex;
            gap: 0.35rem;
        }

        .public-calendar-dot {
            border-radius: 999px;
            display: inline-block;
            height: 0.7rem;
            width: 0.7rem;
        }

        .public-calendar-dot.available {
            background: #2f7d50;
        }

        .public-calendar-dot.unavailable {
            background: #9f1d1d;
        }

        .public-calendar-dot.selected {
            background: #172033;
        }

        .public-calendar-month {
            display: grid;
            gap: 0.65rem;
        }

        .public-calendar-month[hidden] {
            display: none;
        }

        .public-calendar-month h3 {
            font-size: 1.1rem;
            margin: 0;
        }

        .public-calendar-weekdays,
        .public-calendar-grid {
            display: grid;
            gap: 0.45rem;
            grid-template-columns: repeat(7, minmax(0, 1fr));
        }

        .public-calendar-weekday {
            color: #526072;
            font-size: 0.78rem;
            font-weight: 700;
            text-align: center;
        }

        .public-calendar-day {
            border: 1px solid #dce3ee;
            border-radius: 6px;
            color: #172033;
            cursor: pointer;
            display: grid;
            gap: 0.15rem;
            min-height: 3.2rem;
            padding: 0.4rem;
            text-align: center;
        }

        .public-calendar-empty-day {
            min-height: 3.2rem;
        }

        button.public-calendar-day {
            font: inherit;
        }

        .public-calendar-day:focus-visible,
        .public-calendar-nav button:focus-visible,
        .public-calendar-clear:focus-visible {
            outline: 3px solid #8aa0bd;
            outline-offset: 2px;
        }

        .public-calendar-day span {
            font-size: 0.72rem;
        }

        .public-calendar-day.is-available {
            background: #eef6f1;
            border-color: #c9e6d3;
        }

        .public-calendar-day.is-unavailable {
            background: #fff4f4;
            border-color: #f1b7b7;
            cursor: not-allowed;
            color: #7a1520;
        }

        .public-calendar-day.is-today {
            box-shadow: inset 0 0 0 2px #8aa0bd;
        }

        .public-calendar-day.is-selected,
        .public-calendar-day.is-selected-start,
        .public-calendar-day.is-selected-end {
            outline: 2px solid #172033;
            outline-offset: 2px;
        }

        .public-calendar-day.is-selected-range {
            background: #e8edf5;
            border-color: #8aa0bd;
        }

        .public-calendar-day.is-selected-start,
        .public-calendar-day.is-selected-end {
            background: #172033;
            color: #ffffff;
        }

        .public-calendar-feedback {
            background: #fff7ed;
            border: 1px solid #f3c27a;
            border-radius: 6px;
            color: #704510;
            margin: 0;
            padding: 0.65rem 0.75rem;
        }

        .public-calendar-feedback:empty {
            display: none;
        }

        .public-calendar-selection {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: space-between;
        }

        .public-calendar-selection p {
            color: #526072;
            margin: 0;
        }

        .public-field-error {
            font-size: 0.9rem;
            margin: 0;
        }

        .public-form-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .public-form-actions button {
            background: #172033;
            border: 1px solid #172033;
            border-radius: 6px;
            color: #ffffff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            min-height: 2.6rem;
            padding: 0.65rem 1rem;
        }

        .public-summary-list {
            display: grid;
            gap: 0.75rem;
            margin: 1rem 0 0;
        }

        .public-summary-row {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }

        .public-summary-row span:first-child {
            color: #526072;
        }

        .public-summary-row span:last-child {
            font-weight: 700;
            text-align: right;
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

            .public-form-grid {
                grid-template-columns: 1fr;
            }

            .public-calendar-weekdays,
            .public-calendar-grid {
                gap: 0.25rem;
            }

            .public-calendar-day {
                min-height: 3rem;
                padding: 0.25rem;
            }

            .public-calendar-day span {
                font-size: 0.68rem;
            }
        }
    </style>
</head>
<body>
    <header class="public-header">
        <a class="public-brand" href="/">Uthyrning</a>

        <nav class="public-nav" aria-label="Publik navigation">
            <a href="/">Startsida</a>
            <a href="/items">Objekt</a>
        </nav>
    </header>

    <main class="public-main">
        <?= $content ?>
    </main>

    <?php foreach ($publicScripts as $script): ?>
        <?php if (is_string($script) && $script !== ''): ?>
            <script src="<?= $escape($script) ?>" defer></script>
        <?php endif; ?>
    <?php endforeach; ?>
</body>
</html>
