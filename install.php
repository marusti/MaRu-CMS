<?php
$configPath = __DIR__ . '/config/settings.json';
$usersPath = __DIR__ . '/config/users.json';
if (file_exists($configPath) && file_exists($usersPath)) {
    echo "<h2>Flatfile CMS</h2>";
    echo "<p>Das CMS wurde bereits eingerichtet.</p>";
    echo "<a href='admin/login.php'>Zum Admin-Login</a>";
    exit;
}
header("Location: admin/setup.php");
exit;
