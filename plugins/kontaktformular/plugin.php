<?php
/**
 * Plugin Name: Kontaktformular
 */

function plugin_output_kontaktformular()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Einstellungen laden
    $settingsRaw = json_decode(file_get_contents(__DIR__ . '/settings.json'), true);

    $settings = [];
    if (!empty($settingsRaw['fields']) && is_array($settingsRaw['fields'])) {
        foreach ($settingsRaw['fields'] as $field) {
            if (isset($field['value'])) {
                $settings[$field['key']] = $field['value'];
            } else {
                $settings[$field['key']] = $field['default'] ?? null;
            }
        }
    } else {
        $settings = $settingsRaw; // Direktes Mapping bei flacher Struktur
    }
    
// Pfad anpassen, je nach Plugin-Struktur
$coreSettingsPath = __DIR__ . '/../../config/settings.json';




// JSON laden und decodieren
$coreSettings = json_decode(file_get_contents($coreSettingsPath), true);

$base_url = rtrim($coreSettings['base_url'], '/');

$datenschutz_slug = $settings['datenschutzseite'] ?? 'datenschutz';

$datenschutz_url = $base_url . '/' . ltrim($datenschutz_slug, '/');




    $empfaenger = $settings['empfaenger'] ?? '';
    $standard_betreff = $settings['standard_betreff'] ?? 'Kontaktformular';
    $enable_copy_to_sender = $settings['enable_copy_to_sender'] ?? true;
    $enable_honeypot = $settings['enable_honeypot'] ?? true;

    // Formularfelder
    $form_fields = [
        ['field_name' => 'name', 'field_label' => 'Name', 'required' => true],
        ['field_name' => 'email', 'field_label' => 'E-Mail', 'required' => true],
        ['field_name' => 'subject', 'field_label' => 'Betreff', 'required' => false],
        ['field_name' => 'message', 'field_label' => 'Nachricht', 'required' => false],
    ];

    // CSRF Token + CAPTCHA
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['captcha_a'] = rand(1, 10);
        $_SESSION['captcha_b'] = rand(1, 10);
    }
    $csrf_token = $_SESSION['csrf_token'] ?? '';
    $captcha_a = $_SESSION['captcha_a'] ?? 0;
    $captcha_b = $_SESSION['captcha_b'] ?? 0;

    $success = false;
    $error = '';
    $copy_to_sender = false;

    $form_values = [];
    foreach ($form_fields as $f) {
        $key = $f['field_name'];
        $form_values[$key] = $_SERVER['REQUEST_METHOD'] === 'POST' ? trim($_POST[$key] ?? '') : '';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_contact_plugin'])) {
        if ($enable_honeypot && !empty($_POST['honeypot'])) {
            $error = 'Spam erkannt.';
        } elseif (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            $error = 'Ungültiges Token.';
        } else {
            // Pflichtfelder prüfen
            $fehlendeFelder = [];
            foreach ($form_fields as $f) {
                $key = $f['field_name'];
                $label = $f['field_label'] ?? $key;
                if (!empty($f['required']) && empty($form_values[$key])) {
                    $fehlendeFelder[] = $label;
                }
            }

            // E-Mail validieren
            if (!empty($form_values['email']) && !filter_var($form_values['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Ungültige E-Mail-Adresse.';
            }

            // CAPTCHA prüfen
            $captcha_input = (int)($_POST['captcha'] ?? 0);
            if ($captcha_input !== ($captcha_a + $captcha_b)) {
                $error = 'Falsches CAPTCHA-Ergebnis.';
            }

            // Datenschutz prüfen
            if (empty($_POST['privacy_accepted'])) {
                $error = 'Bitte akzeptieren Sie die Datenschutzerklärung.';
            }

            if (empty($error)) {
                // Nachricht zusammenbauen
                $mailtext = "";
                foreach ($form_fields as $f) {
                    $key = $f['field_name'];
                    $label = $f['field_label'] ?? $key;
                    $value = $form_values[$key] ?? '';
                    $mailtext .= "$label: $value\n";
                }

                $betreff = $form_values['subject'] ?: $standard_betreff;
                $safe_email = filter_var($form_values['email'] ?? '', FILTER_SANITIZE_EMAIL);

                $headers = "From: webmaster@example.com\r\n";
                if ($safe_email) {
                    $headers .= "Reply-To: $safe_email\r\n";
                }

                $copy_to_sender = $enable_copy_to_sender && !empty($_POST['copy']);
                if ($copy_to_sender && $safe_email) {
                    $headers .= "Cc: $safe_email\r\n";
                }

                if (mail($empfaenger, $betreff, $mailtext, $headers)) {
                    $success = true;
                    foreach ($form_fields as $f) {
                        $form_values[$f['field_name']] = '';
                    }
                    $copy_to_sender = false;
                } else {
                    $error = 'Nachricht konnte nicht gesendet werden.';
                }
            }
        }
    }

    ob_start(); ?>
    <form method="post" class="contact-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="_contact_plugin" value="1">

        <?php if ($enable_honeypot): ?>
            <input type="text" name="honeypot" style="display:none" aria-hidden="true">
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="contact-success">
                <?= htmlspecialchars($settings['erfolgsmeldung'] ?? 'Vielen Dank für Ihre Nachricht!') ?>
            </div>
        <?php elseif ($error): ?>
            <div class="contact-error">
                <?= htmlspecialchars($error ?: ($settings['fehlermeldung'] ?? 'Ein Fehler ist aufgetreten.')) ?>
            </div>
        <?php endif; ?>

        <?php
        foreach ($form_fields as $f):
            $key = $f['field_name'];
            $label = $f['field_label'] ?? $key;
            $required = !empty($f['required']);
            $value = $form_values[$key] ?? '';

            $inputType = 'text';
            if ($key === 'email') $inputType = 'email';
            if ($key === 'subject') $inputType = 'text';

            if ($key === 'message'): ?>
                <div class="form-group">
                    <label for="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?><?= $required ? '*' : '' ?>:</label>
                    <textarea name="<?= htmlspecialchars($key) ?>" id="<?= htmlspecialchars($key) ?>" rows="6" <?= $required ? 'required' : '' ?>><?= htmlspecialchars($value) ?></textarea>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label for="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?><?= $required ? '*' : '' ?>:</label>
                    <input type="<?= $inputType ?>" name="<?= htmlspecialchars($key) ?>" id="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>" <?= $required ? 'required' : '' ?>>
                </div>
            <?php endif;
        endforeach; ?>

        <div class="form-group">
            <label for="captcha">
                Was ist <?= $captcha_a ?> + <?= $captcha_b ?>?*
            </label>
            <input type="number" name="captcha" id="captcha" required>
        </div>

        <div class="form-group">
            <label>
    <input type="checkbox" name="privacy_accepted" required>
    Ich akzeptiere die <a href="<?= htmlspecialchars($datenschutz_url) ?>" target="_blank">Datenschutzerklärung</a>*
</label>


        </div>

        <?php if ($enable_copy_to_sender): ?>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="copy" <?= $copy_to_sender ? 'checked' : '' ?>>
                    Kopie an mich senden
                </label>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <button type="submit" class="btn-submit">Absenden</button>
        </div>
    </form>
    <?php
    return ob_get_clean();
}
