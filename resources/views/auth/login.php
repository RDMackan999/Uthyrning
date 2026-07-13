<?php

$csrfToken = is_string($csrfToken ?? null) ? $csrfToken : '';
$errorMessage = is_string($errorMessage ?? null) ? $errorMessage : null;

$escapedCsrfToken = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
$escapedErrorMessage = $errorMessage === null ? null : htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="sv">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logga in - Uthyrning</title>
</head>
<body>
    <main>
        <h1>Logga in</h1>

        <?php if ($escapedErrorMessage !== null): ?>
            <p role="alert"><?= $escapedErrorMessage ?></p>
        <?php endif; ?>

        <form method="post" action="/login">
            <input type="hidden" name="csrf_token" value="<?= $escapedCsrfToken ?>">

            <div>
                <label for="email">E-post</label>
                <input id="email" name="email" type="email" autocomplete="email" required>
            </div>

            <div>
                <label for="password">Lösenord</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>

            <button type="submit">Logga in</button>
        </form>
    </main>
</body>
</html>
