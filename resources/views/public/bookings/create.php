<?php

$item = is_array($item ?? null) ? $item : [];
$data = is_array($data ?? null) ? $data : [];
$errors = is_array($errors ?? null) ? $errors : [];
$pricePreview = is_array($pricePreview ?? null) ? $pricePreview : null;
$availabilityCalendar = is_array($availabilityCalendar ?? null) ? $availabilityCalendar : [];
$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$stringValue = static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '';
$formatAmount = static function (mixed $amount): string {
    $numericAmount = is_numeric($amount) ? (float) $amount : 0.0;
    $decimals = abs($numericAmount - round($numericAmount)) > 0.00001 ? 2 : 0;

    return number_format($numericAmount, $decimals, ',', ' ');
};
$currencyCode = static function (mixed $currency) use ($stringValue): string {
    return $stringValue($currency) === '' ? 'SEK' : strtoupper($stringValue($currency));
};
$fieldError = static function (array $errors, string $field) use ($escape): string {
    return isset($errors[$field])
        ? '<p class="public-field-error">' . $escape($errors[$field]) . '</p>'
        : '';
};

$name = $stringValue($item['name'] ?? '');
$publicId = $stringValue($item['public_id'] ?? '');
$slug = $stringValue($item['slug'] ?? '');
$actionUrl = '/items/' . rawurlencode($publicId) . '/' . rawurlencode($slug) . '/book';
$dailyRateCurrency = $currencyCode($item['daily_rate_currency'] ?? 'SEK');
$dailyRate = $formatAmount($item['daily_rate_amount'] ?? null) . ' ' . $dailyRateCurrency . '/dag';
$depositAmount = $item['deposit_amount'] ?? null;
$hasDeposit = is_numeric($depositAmount) && (float) $depositAmount > 0.0;
$calendarMonths = is_array($availabilityCalendar['months'] ?? null) ? $availabilityCalendar['months'] : [];
$calendarWeekdays = is_array($availabilityCalendar['weekdays'] ?? null) ? $availabilityCalendar['weekdays'] : [];
$activeMonthIndex = is_numeric($availabilityCalendar['active_month_index'] ?? null)
    ? (int) $availabilityCalendar['active_month_index']
    : 0;
$minDate = $stringValue($availabilityCalendar['min_date'] ?? '');
$maxDate = $stringValue($availabilityCalendar['max_date'] ?? '');
?>
<section>
    <p class="public-back-link">
        <a href="/items/<?= $escape(rawurlencode($publicId)) ?>/<?= $escape(rawurlencode($slug)) ?>">Tillbaka till objekt</a>
    </p>

    <div class="public-page-header">
        <h1>Boka objekt</h1>
        <p>Skicka en bokningsf&ouml;rfr&aring;gan f&ouml;r <?= $escape($name) ?>. Bokningen &auml;r inte bekr&auml;ftad f&ouml;rr&auml;n uthyraren har godk&auml;nt den.</p>
    </div>

    <div class="public-detail-grid">
        <form class="public-form" action="<?= $escape($actionUrl) ?>" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

            <?php if (isset($errors['form'])): ?>
                <p class="public-form-error"><?= $escape($errors['form']) ?></p>
            <?php endif; ?>

            <?php if ($calendarMonths !== []): ?>
                <div
                    class="public-calendar"
                    data-booking-calendar
                    data-min-date="<?= $escape($minDate) ?>"
                    data-max-date="<?= $escape($maxDate) ?>"
                    data-active-month="<?= $escape((string) $activeMonthIndex) ?>"
                    aria-label="Tillg&auml;nglighetskalender"
                >
                    <div class="public-calendar-header">
                        <div>
                            <h2>Tillg&auml;nglighet</h2>
                            <p>
                                <?= $escape($minDate) ?>
                                -
                                <?= $escape($maxDate) ?>
                            </p>
                        </div>

                        <div class="public-calendar-nav" aria-label="Kalendernavigation">
                            <button type="button" data-calendar-prev>F&ouml;reg&aring;ende</button>
                            <button type="button" data-calendar-next>N&auml;sta</button>
                        </div>
                    </div>

                    <div class="public-calendar-legend" aria-label="F&ouml;rklaring">
                        <span><span class="public-calendar-dot available"></span> Ledigt</span>
                        <span><span class="public-calendar-dot unavailable"></span> Ej tillg&auml;ngligt</span>
                        <span><span class="public-calendar-dot selected"></span> Valt datum</span>
                    </div>

                    <p
                        id="booking-calendar-feedback"
                        class="public-calendar-feedback"
                        data-calendar-feedback
                        role="status"
                        aria-live="polite"
                    ></p>

                    <?php foreach ($calendarMonths as $monthIndex => $month): ?>
                        <?php
                        $monthDays = is_array($month['days'] ?? null) ? $month['days'] : [];
                        $isActiveMonth = $monthIndex === $activeMonthIndex;
                        $leadingEmptyDays = is_numeric($month['leading_empty_days'] ?? null)
                            ? (int) $month['leading_empty_days']
                            : 0;
                        ?>
                        <div
                            class="public-calendar-month"
                            data-calendar-month="<?= $escape((string) $monthIndex) ?>"
                            <?= $isActiveMonth ? '' : 'hidden' ?>
                        >
                            <h3><?= $escape($month['label'] ?? '') ?></h3>

                            <div class="public-calendar-weekdays" aria-hidden="true">
                                <?php foreach ($calendarWeekdays as $weekday): ?>
                                    <span class="public-calendar-weekday"><?= $escape($weekday) ?></span>
                                <?php endforeach; ?>
                            </div>

                            <div class="public-calendar-grid" role="list">
                                <?php for ($emptyDay = 0; $emptyDay < $leadingEmptyDays; $emptyDay++): ?>
                                    <span class="public-calendar-empty-day" aria-hidden="true"></span>
                                <?php endfor; ?>

                                <?php foreach ($monthDays as $day): ?>
                                    <?php
                                    $isAvailable = (bool) ($day['is_available'] ?? false);
                                    $isSelected = (bool) ($day['is_selected'] ?? false);
                                    $classes = 'public-calendar-day '
                                        . ($isAvailable ? 'is-available' : 'is-unavailable')
                                        . ((bool) ($day['is_today'] ?? false) ? ' is-today' : '')
                                        . ($isSelected ? ' is-selected' : '')
                                        . ((bool) ($day['is_selected_start'] ?? false) ? ' is-selected-start' : '')
                                        . ((bool) ($day['is_selected_end'] ?? false) ? ' is-selected-end' : '');
                                    $statusText = $isAvailable ? 'Ledigt' : 'Ej tillg&auml;ngligt';
                                    ?>
                                    <button
                                        type="button"
                                        class="<?= $escape($classes) ?>"
                                        role="listitem"
                                        data-calendar-date="<?= $escape($day['date'] ?? '') ?>"
                                        data-calendar-available="<?= $isAvailable ? '1' : '0' ?>"
                                        aria-disabled="<?= $isAvailable ? 'false' : 'true' ?>"
                                        aria-label="<?= $escape($day['aria_label'] ?? '') ?>"
                                    >
                                        <strong><?= $escape($day['day_label'] ?? '') ?></strong>
                                        <span data-calendar-day-status><?= $statusText ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="public-calendar-selection">
                        <p data-calendar-selection>Inget datum valt.</p>
                        <button class="public-calendar-clear" type="button" data-calendar-clear>Rensa datumval</button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="public-form-grid">
                <div class="public-form-field">
                    <label for="booking-start-date">Startdatum</label>
                    <input
                        id="booking-start-date"
                        name="start_date"
                        type="date"
                        min="<?= $escape($minDate) ?>"
                        max="<?= $escape($maxDate) ?>"
                        value="<?= $escape($data['start_date'] ?? '') ?>"
                        aria-describedby="booking-calendar-feedback"
                        required
                    >
                    <?= $fieldError($errors, 'start_date') ?>
                </div>

                <div class="public-form-field">
                    <label for="booking-end-date">Slutdatum</label>
                    <input
                        id="booking-end-date"
                        name="end_date"
                        type="date"
                        min="<?= $escape($minDate) ?>"
                        max="<?= $escape($maxDate) ?>"
                        value="<?= $escape($data['end_date'] ?? '') ?>"
                        aria-describedby="booking-calendar-feedback"
                        required
                    >
                    <?= $fieldError($errors, 'end_date') ?>
                </div>

                <div class="public-form-field">
                    <label for="booking-customer-name">Namn</label>
                    <input
                        id="booking-customer-name"
                        name="customer_name"
                        type="text"
                        maxlength="255"
                        value="<?= $escape($data['customer_name'] ?? '') ?>"
                        required
                    >
                    <?= $fieldError($errors, 'customer_name') ?>
                </div>

                <div class="public-form-field">
                    <label for="booking-customer-email">E-post</label>
                    <input
                        id="booking-customer-email"
                        name="customer_email"
                        type="email"
                        maxlength="255"
                        value="<?= $escape($data['customer_email'] ?? '') ?>"
                        required
                    >
                    <?= $fieldError($errors, 'customer_email') ?>
                </div>

                <div class="public-form-field">
                    <label for="booking-customer-phone">Telefon</label>
                    <input
                        id="booking-customer-phone"
                        name="customer_phone"
                        type="tel"
                        maxlength="50"
                        value="<?= $escape($data['customer_phone'] ?? '') ?>"
                        required
                    >
                    <?= $fieldError($errors, 'customer_phone') ?>
                </div>

                <div class="public-form-field">
                    <label for="booking-company-name">F&ouml;retag</label>
                    <input
                        id="booking-company-name"
                        name="company_name"
                        type="text"
                        maxlength="255"
                        value="<?= $escape($data['company_name'] ?? '') ?>"
                    >
                </div>

                <div class="public-form-field full">
                    <label for="booking-customer-comment">Kommentar</label>
                    <textarea
                        id="booking-customer-comment"
                        name="customer_comment"
                        maxlength="1000"
                    ><?= $escape($data['customer_comment'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="public-form-actions">
                <button type="submit">Skicka f&ouml;rfr&aring;gan</button>
                <a class="public-detail-link" href="/items/<?= $escape(rawurlencode($publicId)) ?>/<?= $escape(rawurlencode($slug)) ?>">Avbryt</a>
            </div>
        </form>

        <aside class="public-detail-panel" aria-label="Sammanfattning">
            <p class="public-detail-label">Objekt</p>
            <p class="public-detail-price"><?= $escape($name) ?></p>

            <div class="public-summary-list">
                <div class="public-summary-row">
                    <span>Dagspris</span>
                    <span><?= $escape($dailyRate) ?></span>
                </div>

                <?php if ($hasDeposit): ?>
                    <div class="public-summary-row">
                        <span>Deposition</span>
                        <span><?= $escape($formatAmount($depositAmount) . ' ' . $dailyRateCurrency) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($pricePreview !== null): ?>
                    <div class="public-summary-row">
                        <span>Antal dagar</span>
                        <span><?= $escape($pricePreview['number_of_units'] ?? '') ?></span>
                    </div>
                    <div class="public-summary-row">
                        <span>Prelimin&auml;rt pris</span>
                        <span>
                            <?= $escape($formatAmount($pricePreview['subtotal_amount'] ?? null) . ' ' . $currencyCode($pricePreview['currency'] ?? $dailyRateCurrency)) ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>
