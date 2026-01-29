<?php
http_response_code(404);
$pageTitle = 'Seite nicht gefunden';
?>
<style>
  .error-404-container {
    text-align: center;
    padding: 80px 20px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    color: #333;
  }
  .error-404-container h1 {
    font-size: 6rem;
    margin-bottom: 0;
    color: #e74c3c;
  }
  .error-404-container h2 {
    font-size: 2rem;
    margin: 20px 0;
  }
  .error-404-container p {
    font-size: 1.1rem;
    margin-bottom: 30px;
    color: #666;
  }
  .error-404-container a.button {
    display: inline-block;
    background-color: #3498db;
    color: white;
    text-decoration: none;
    padding: 12px 28px;
    border-radius: 5px;
    font-weight: 600;
    transition: background-color 0.3s ease;
  }
  .error-404-container a.button:hover {
    background-color: #2980b9;
  }
</style>

<div class="error-404-container">
  <h1>404</h1>
  <h2>Oops! Seite nicht gefunden</h2>
  <p>Die von dir gesuchte Seite existiert leider nicht oder wurde verschoben.</p>
  <a href="<?= htmlspecialchars($baseUrl) ?>/" class="button">Zur Startseite</a>

  <form action="<?= htmlspecialchars($baseUrl) ?>/suche" method="get" style="margin-top: 30px;">
    <input type="text" name="q" placeholder="Suchbegriff eingeben..." 
           style="padding: 10px; width: 250px; border: 1px solid #ccc; border-radius: 4px;" required>
    <button type="submit" 
            style="padding: 10px 15px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer;">
      Suchen
    </button>
  </form>
</div>


