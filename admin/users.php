<?php
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/assets/icons/icons.php';

// Signal, dass diese Seite Filter braucht
$pageHasFilter = true;
$pageHasDialog = true;

session_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = __('manage_users');
$usersFile = __DIR__ . '/../config/users.json';
$users = file_exists($usersFile) ? json_decode(file_get_contents($usersFile), true) : [];
$currentUser = $_SESSION['admin'];

// CSRF-Token initialisieren
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Zentrale Messages
$messages = [];

function is_valid_password($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[0-9]/', $password) &&
           !preg_match('/[^a-zA-Z0-9]/', $password);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    // === User erstellen ===
    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? 'editor';

        if (strlen($username) < 5 || isset($users[$username])) {
            addMessage($messages, __('username_invalid'), 'error');
        } elseif (!is_valid_password($password)) {
            addMessage($messages, __('password_invalid'), 'error');
        } elseif ($password !== $confirmPassword) {
            addMessage($messages, __('password_mismatch'), 'error');
        } else {
            $users[$username] = [
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role
            ];
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
                addMessage($messages, __('user_save_error'), 'error');
            } else {
                addMessage($messages, sprintf(__('user_created'), $username), 'success');
            }
        }
    // === User löschen ===
    } elseif (isset($_POST['delete_user'])) {
        $username = $_POST['delete_user'];
        if ($username !== $currentUser && isset($users[$username])) {
            unset($users[$username]);
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
                addMessage($messages, __('user_save_error'), 'error');
            } else {
                addMessage($messages, sprintf(__('user_deleted'), $username), 'success'); // <-- hier Username einfügen
        }
        }
    // === Passwort ändern ===
    } elseif (isset($_POST['change_password'])) {
        $username = $_POST['change_user'];
        $newPassword = $_POST['new_password'];

        if ($users[$currentUser]['role'] === 'editor' && $username !== $currentUser) {
            addMessage($messages, __('editor_change_own_password_only'), 'error');
        } else {
            if (isset($users[$username])) {
                if (!is_valid_password($newPassword)) {
                    addMessage($messages, __('password_invalid'), 'error');
                } else {
                    $users[$username]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
                        addMessage($messages, __('user_save_error'), 'error');
                    } else {
                        addMessage($messages, __('password_changed'), 'success');
                    }
                }
            }
        }

    // === Rolle ändern ===
    } elseif (isset($_POST['change_role'])) {
        $username = $_POST['change_user'];
        $newRole = $_POST['new_role'];

        if (!in_array($newRole, ['admin','editor'])) {
            addMessage($messages, __('role_invalid'), 'error');
        } elseif (isset($users[$username])) {
            $users[$username]['role'] = $newRole;
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
                addMessage($messages, __('user_save_error'), 'error');
            } else {
                addMessage($messages, __('role_updated'), 'success');
            }
        }
    }
}

ob_start();
?>

<h1><?= __('manage_users') ?></h1>
<?php if ($users[$currentUser]['role'] !== 'editor'): ?>
<h2><?= __('create_user') ?></h2>
<form method="post" name="create_user_form" class="maru-card create-card" novalidate aria-label="<?= __('create_user') ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div>
        <label for="username"><?= __('username') ?>:</label>
        <input type="text" id="username" name="username" required minlength="5"  value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>
    <div>
        <label for="role"><?= __('role') ?>:</label>
        <select id="role" name="role">
            <option value="editor" <?= (($_POST['role'] ?? '') === 'editor') ? 'selected' : '' ?>><?= __('editor') ?></option>
            <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>><?= __('admin') ?></option>
        </select>
    </div>
    <div>
        <label for="password"><?= __('password') ?>:</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div>
        <label for="confirm_password"><?= __('confirm_password') ?>:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
    </div>
    <div>
        <button type="submit" name="create_user"><?= __('create_user') ?></button>
    </div>
</form>
<?php endif; ?>

<h2><?= __('existing_users') ?></h2>
<!-- Search filter -->
<div class="maru-toolbar">
<div class="filter">
<label for="filter"><?= __('search_user') ?>:</label>
<input  type="text" id="filter" class="admin-search" placeholder="<?= htmlspecialchars(__('search_user_placeholder')) ?>">
</div>
<div class="type-filter">
<label for="roleFilter"><?= __('role') ?>:</label>

<select id="roleFilter">
    <option value="all">Alle Rollen</option>
    <option value="admin"><?= __('admin') ?></option>
    <option value="editor"><?= __('editor') ?></option>
</select>
</div>
</div>
<div class="maru-list users-list">
<?php foreach ($users as $username => $info): ?>
    <div class="entry-block list-item" aria-label="User <?= htmlspecialchars($username) ?> with role <?= htmlspecialchars($info['role']) ?>" data-role="<?= htmlspecialchars($info['role']) ?>">
        <div class="user-info">
            <span class="entry-name"><?= htmlspecialchars($username) ?></span>
            <span class="role"><?= htmlspecialchars($info['role']) ?></span>
        </div>
        <div class="actions">

        <?php if ($users[$currentUser]['role'] === 'admin' || $username === $currentUser): ?>
            <button type="button" class="icon-btn edit-user" data-username="<?= htmlspecialchars($username) ?>" aria-label="<?= __('edit_user') ?>">
                <?= getIcon('edit') ?>
            </button>
        <?php endif; ?>

        <?php if ($username !== $currentUser && $users[$currentUser]['role'] === 'admin'): ?>
            <button class="maru-delete js-delete" aria-label="<?= __('delete') ?>" data-title="<?= __('delete') ?>" data-message="<?= htmlspecialchars(__('delete_confirm_user'), ENT_QUOTES) ?>"
        data-form="deleteUserForm"
        data-input="deleteUserInput"
        data-value="<?= htmlspecialchars($username, ENT_QUOTES) ?>"
        data-type="user">
    <?= getIcon('delete') ?>
</button>
        <?php endif; ?>

        </div>
    </div>
<?php endforeach; ?>
</div>

<form method="post" id="deleteUserForm" hidden>
    <input type="hidden" name="delete_user" id="deleteUserInput">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
</form>

<script>
document.addEventListener("DOMContentLoaded", function () {
    function validatePassword(pwd) {
        if (pwd.length < 8) return false;
        if (!/[A-Z]/.test(pwd)) return false;
        if (!/[0-9]/.test(pwd)) return false;
        if (/[^a-zA-Z0-9]/.test(pwd)) return false;
        return true;
    }

});
</script>

<?php
$content = ob_get_clean();
if (!empty($messages)) {
    $_SESSION['messages'] = array_merge($_SESSION['messages'] ?? [], $messages);
}
include '_layout.php';
?>