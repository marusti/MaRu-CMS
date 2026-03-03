<?php
// includes/messages.php
require_once __DIR__ . '/../assets/icons/icons.php'; 

/**
 * Meldung hinzufügen
 */
function addMessage(array &$messages, string $text, string $type = 'success') {
    $allowedTypes = ['success', 'error', 'info', 'warning'];
    if (!in_array($type, $allowedTypes)) $type = 'info';
    $messages[] = ['type' => $type, 'text' => $text];
}

/**
 * Barrierefreie Meldungen ausgeben mit Icons
 */
function renderMessages(array $messages) {
    if (empty($messages)) return;

    $firstMessage = true;

    foreach ($messages as $msg) {
        $type = $msg['type'];
        $ariaRole = in_array($type, ['error', 'warning']) ? 'alert' : 'status';
        $ariaLive = $ariaRole === 'alert' ? 'assertive' : 'polite';
        $tabindex = $firstMessage ? ' tabindex="-1"' : '';

        // Icon aus icons.php holen
        $iconSvg = getIcon($type);

        echo '<div class="message ' . htmlspecialchars($type) . '" role="' . $ariaRole . '" aria-live="' . $ariaLive . '"' . $tabindex . '>'
             . $iconSvg
             . '<span class="sr-only">' . ucfirst($type) . ': </span>'
             . htmlspecialchars($msg['text'])
             . '</div>';

        $firstMessage = false;
    }

    // Fokus auf die erste Meldung setzen
    echo '<script>
        const firstMsg = document.querySelector(".message[tabindex=\'-1\']");
        if(firstMsg){ firstMsg.focus(); }
    </script>';
}
?>