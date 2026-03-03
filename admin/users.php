<?php
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/assets/icons/icons.php';

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
                addMessage($messages, __('user_created'), 'success');
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
                addMessage($messages, __('user_deleted'), 'success');
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

<?php if (!empty($messages)): ?>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const successBox = document.querySelector('.message.success');
            if (successBox) {
                setTimeout(() => {
                    successBox.style.transition = 'opacity 1s ease-out';
                    successBox.style.opacity = 0;
                    setTimeout(() => successBox.remove(), 1000);
                }, 4000);
            }
        });
    </script>
<?php endif; ?>

<?php if ($users[$currentUser]['role'] !== 'editor'): ?>
<h2><?= __('create_user') ?></h2>
<form method="post" name="create_user_form" class="maru-card create-card" novalidate aria-label="Create new user">
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
<div class="maru-list users-list">
<?php foreach ($users as $username => $info): ?>
    <div class="user-card" aria-label="User <?= htmlspecialchars($username) ?> with role <?= htmlspecialchars($info['role']) ?>">
        <div class="user-info">
            <strong><?= htmlspecialchars($username) ?></strong>
            <span class="role"><?= htmlspecialchars($info['role']) ?></span>
        </div>
        <div class="actions">

        <?php if ($users[$currentUser]['role'] === 'admin' || $username === $currentUser): ?>
            <button
                type="button"
                class="icon-btn edit-user"
                data-username="<?= htmlspecialchars($username) ?>"
                aria-label="<?= __('edit_user') ?>">
                <?= getIcon('edit') ?>
            </button>
        <?php endif; ?>

        <?php if ($username !== $currentUser && $users[$currentUser]['role'] === 'admin'): ?>
            <button class="maru-delete delete-user" 
                data-type="user" 
                data-message="<?= htmlspecialchars(__('delete_confirm_user'), ENT_QUOTES) ?>"
                data-name="<?= htmlspecialchars($username) ?>" 
                data-title="<?= __('delete') ?>">
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

    const errorModal = document.getElementById('errorModal');
    const errorText = document.getElementById('errorText');
    const closeErrorBtn = document.getElementById('closeErrorModal');

    function showErrorModal(message) {
        errorText.textContent = message;
        errorModal.classList.add('active');
        errorModal.style.display = 'flex';
        closeErrorBtn.focus();
    }

    closeErrorBtn.addEventListener('click', () => {
        errorModal.classList.remove('active');
        setTimeout(() => { errorModal.style.display = 'none'; }, 300);
    });

    errorModal.addEventListener('click', function(e) {
        if (e.target === errorModal) {
            errorModal.classList.remove('active');
            setTimeout(() => { errorModal.style.display = 'none'; }, 300);
        }
    });

    document.querySelectorAll('.change-password-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const newPwd = form.querySelector('input[name="new_password"]').value;
            const confirmPwd = form.querySelector('input[name="confirm_new_password"]').value;

            if (newPwd !== confirmPwd) {
                e.preventDefault();
                showErrorModal('Passwörter stimmen nicht überein.');
                return;
            }

            if (!validatePassword(newPwd)) {
                e.preventDefault();
                showErrorModal('Passwort muss mindestens 8 Zeichen lang sein, einen Großbuchstaben und eine Zahl enthalten und darf keine Sonderzeichen enthalten.');
            }
        });
    });
});
</script>

<div id="errorModal" class="modal-overlay" style="display:none;" role="alertdialog" aria-modal="true" aria-labelledby="errorText" tabindex="-1">
    <div class="modal">
        <p id="errorText"></p>
        <div class="modal-buttons" style="justify-content:center;">
            <button id="closeErrorModal" type="button">OK</button>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '_layout.php';
?>