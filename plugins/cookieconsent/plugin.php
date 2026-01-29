<?php
function plugin_output_cookieconsent() {
    $settingsFile = __DIR__ . '/settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
    }

    $privacyUrl = $settings['privacy_url'] ?? '/datenschutz';
    $messageTemplate = $settings['message'] ?? 'Wir verwenden Cookies, um dir das beste Erlebnis zu bieten. Mehr Infos findest du in unserer <a href="{{privacy_url}}" target="_blank" rel="noopener">Datenschutzerklärung</a>.';
    $buttonAcceptText = $settings['button_accept_text'] ?? 'Akzeptieren';
    $buttonRejectText = $settings['button_reject_text'] ?? 'Ablehnen';
    $cookieDurationDays = $settings['cookie_duration_days'] ?? 365;

    $message = str_replace('{{privacy_url}}', htmlspecialchars($privacyUrl, ENT_QUOTES), $messageTemplate);

    $cookieName = 'cookie_consent_status'; // Werte: accepted, rejected, undefined
    $consentStatus = $_COOKIE[$cookieName] ?? 'undefined';

    ob_start();
    if ($consentStatus === 'undefined'):
?>
<style>
#cookie-consent {
  position: fixed;
  bottom: 1em; left: 1em; right: 1em;
  background: #f8f9fa;
  border: 1px solid #ccc;
  padding: 1em;
  z-index: 10000;
  box-shadow: 0 0 10px rgba(0,0,0,0.1);
  font-family: Arial, sans-serif;
}
#cookie-consent button {
  margin-right: 0.5em;
  padding: 0.4em 1em;
  cursor: pointer;
}
</style>

<div id="cookie-consent" role="alert" aria-live="polite" aria-label="Cookie Hinweis">
  <div><?= $message ?></div>
  <button id="cookie-accept-btn"><?= htmlspecialchars($buttonAcceptText) ?></button>
  <button id="cookie-reject-btn"><?= htmlspecialchars($buttonRejectText) ?></button>
</div>

<script>
(function(){
  var consentName = <?= json_encode($cookieName) ?>;
  var cookieDuration = <?= json_encode($cookieDurationDays) ?>;
  var consentDiv = document.getElementById('cookie-consent');

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

  document.getElementById('cookie-accept-btn').addEventListener('click', function(){
    setCookie(consentName, 'accepted', cookieDuration);
    consentDiv.style.display = 'none';
  });

  document.getElementById('cookie-reject-btn').addEventListener('click', function(){
    setCookie(consentName, 'rejected', cookieDuration);
    consentDiv.style.display = 'none';
  });
})();
</script>
<?php
    endif;

    return ob_get_clean();
}
