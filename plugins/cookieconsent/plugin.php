<?php
function plugin_output_cookieconsent() {
    $settingsFile = __DIR__ . '/settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
    }

    $privacyUrl = $settings['privacy_url'] ?? '/datenschutz';
    $messageTemplate = $settings['message'] ?? 'Wir verwenden Cookies, um dir das beste Erlebnis zu bieten. Mehr Infos findest du in unserer <a href="{{privacy_url}}" target="_blank" rel="noopener" aria-label="Datenschutzerklärung (öffnet in neuem Tab)">Datenschutzerklärung</a>.';
    $buttonAcceptText = $settings['button_accept_text'] ?? 'Akzeptieren';
    $buttonRejectText = $settings['button_reject_text'] ?? 'Ablehnen';
    $cookieDurationDays = $settings['cookie_duration_days'] ?? 365;

    $message = str_replace('{{privacy_url}}', htmlspecialchars($privacyUrl, ENT_QUOTES), $messageTemplate);

    $cookieName = 'cookie_consent_status';
    $consentStatus = $_COOKIE[$cookieName] ?? 'undefined';

    ob_start();
    if ($consentStatus === 'undefined'):
?>
<div id="cookie-consent"
     role="dialog"
     aria-modal="true"
     aria-labelledby="cookie-consent-title">

  <h2 id="cookie-consent-title" class="visually-hidden">
    Cookie-Einstellungen
  </h2>

  <div><?= $message ?></div>

  <div class="cookie-buttons">
    <button id="cookie-accept-btn">
      <?= htmlspecialchars($buttonAcceptText) ?>
    </button>
    <button id="cookie-reject-btn">
      <?= htmlspecialchars($buttonRejectText) ?>
    </button>
  </div>
</div>

<script>
(function(){
  var consentName = <?= json_encode($cookieName) ?>;
  var cookieDuration = <?= json_encode($cookieDurationDays) ?>;
  var consentDiv = document.getElementById('cookie-consent');
  var acceptBtn = document.getElementById('cookie-accept-btn');
  var rejectBtn = document.getElementById('cookie-reject-btn');
  var previousFocus = document.activeElement;

  function setCookie(name, value, days) {
    var expires = "";
    if (days) {
      var date = new Date();
      date.setTime(date.getTime() + (days*24*60*60*1000));
      expires = "; expires=" + date.toUTCString();
    }
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = name + "=" + (value || "") + expires + "; path=/" + secure + "; SameSite=Lax";
  }

  function closeConsent(status) {
    setCookie(consentName, status, cookieDuration);
    consentDiv.style.display = 'none';
    if (previousFocus) {
      previousFocus.focus();
    }
  }

  // Fokus beim Öffnen setzen
  acceptBtn.focus();

  acceptBtn.addEventListener('click', function(){
    closeConsent('accepted');
  });

  rejectBtn.addEventListener('click', function(){
    closeConsent('rejected');
  });

  // ESC-Taste zum Schließen
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      closeConsent('rejected');
    }
  });

})();
</script>
<?php
    endif;

    return ob_get_clean();
}