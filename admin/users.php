<?php
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/init.php';
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

$error = '';
$success = '';

function is_valid_password($password) {
    return strlen($password) >= 8 &&
           preg_match('/[A-Z]/', $password) &&
           preg_match('/[0-9]/', $password) &&
           !preg_match('/[^a-zA-Z0-9]/', $password);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'editor';

        if (strlen($username) < 5 || isset($users[$username])) {
            $error = __('username_invalid');
        } elseif (!is_valid_password($password)) {
            $error = __('password_invalid');
        } else {
            $users[$username] = [
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role
            ];
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
                $error = __('user_save_error');
            } else {
                $success = __('user_created');
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        $username = $_POST['delete_user'];
        if ($username !== $currentUser && isset($users[$username])) {
            unset($users[$username]);
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
                $error = __('user_save_error');
            } else {
                $success = __('user_deleted');
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $username = $_POST['change_user'];
        $newPassword = $_POST['new_password'];

        if ($users[$currentUser]['role'] === 'editor' && $username !== $currentUser) {
            $error = __('editor_change_own_password_only');
        } else {
            if (isset($users[$username])) {
                if (!is_valid_password($newPassword)) {
                    $error = __('password_invalid');
                } else {
                    $users[$username]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
                        $error = __('user_save_error');
                    } else {
                        $success = __('password_changed');
                    }
                }
            }
        }
    } elseif (isset($_POST['change_role'])) {
        $username = $_POST['change_user'];
        $newRole = $_POST['new_role'];
        if (isset($users[$username])) {
            $users[$username]['role'] = $newRole;
            if (file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT)) === false) {
                $error = __('user_save_error');
            } else {
                $success = __('role_updated');
            }
        }
    }
}

ob_start();
?>

<h1><?= __('manage_users') ?></h1>

<?php if ($error): ?>
<div class="error" role="alert">❌ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="success" role="alert">✅ <?= htmlspecialchars($success) ?></div>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const successBox = document.querySelector('.success');
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
<form method="post" name="create_user_form" class="user-card create-card" novalidate aria-label="Create new user">
    <label for="username"><?= __('username') ?>:</label>
    <input type="text" id="username" name="username" required minlength="5">

    <label for="password"><?= __('password') ?>:</label>
    <input type="password" id="password" name="password" required>

    <label for="confirm_password"><?= __('confirm_password') ?>:</label>
    <input type="password" id="confirm_password" name="confirm_password" required>

    <label for="role"><?= __('role') ?>:</label>
    <select id="role" name="role">
        <option value="editor"><?= __('editor') ?></option>
        <option value="admin"><?= __('admin') ?></option>
    </select>

    <button type="submit" name="create_user"><?= __('create_user') ?></button>
</form>
<?php endif; ?>

<h2><?= __('existing_users') ?></h2>
<div class="users-list">
    <?php foreach ($users as $username => $info): ?>
    <div class="user-card" aria-label="User <?= htmlspecialchars($username) ?> with role <?= htmlspecialchars($info['role']) ?>">
        <div class="user-info">
            <strong><?= htmlspecialchars($username) ?></strong>
            <span class="role"><?= htmlspecialchars($info['role']) ?></span>
        </div>
        <div class="actions">
            <?php if ($username !== $currentUser && $users[$currentUser]['role'] !== 'editor'): ?>
            <form method="post" class="delete-user-form" style="display:inline" aria-label="Delete user <?= htmlspecialchars($username) ?>">
                <button type="submit" id="delete_<?= htmlspecialchars($username) ?>" name="delete_user" data-username="<?= htmlspecialchars($username) ?>" value="<?= htmlspecialchars($username) ?>">
                    <?= __('delete') ?>
                </button>
            </form>
            <?php endif; ?>

            <?php if ($users[$currentUser]['role'] == 'admin' || $username === $currentUser): ?>
            <form method="post" class="change-password-form" style="display:inline" aria-label="Change password for <?= htmlspecialchars($username) ?>">
                <input type="hidden" name="change_user" value="<?= htmlspecialchars($username) ?>">

                <label for="new_password_<?= htmlspecialchars($username) ?>"><?= __('new_password') ?>:</label>
                <input type="password" id="new_password_<?= htmlspecialchars($username) ?>" name="new_password" placeholder="<?= __('new_password') ?>" required>

                <label for="confirm_new_password_<?= htmlspecialchars($username) ?>"><?= __('confirm_password') ?>:</label>
                <input type="password" id="confirm_new_password_<?= htmlspecialchars($username) ?>" name="confirm_new_password" placeholder="<?= __('confirm_password') ?>" required>

                <button type="submit" name="change_password"><?= __('change_password') ?></button>
            </form>
            <?php endif; ?>

            <?php if ($users[$currentUser]['role'] === 'admin'): ?>
            <form method="post" class="change-role-form" style="display:inline" aria-label="Change role for <?= htmlspecialchars($username) ?>">
                <input type="hidden" name="change_user" value="<?= htmlspecialchars($username) ?>">

                <label for="new_role_<?= htmlspecialchars($username) ?>"><?= __('role') ?>:</label>
                <select id="new_role_<?= htmlspecialchars($username) ?>" name="new_role">
                    <option value="editor" <?= $info['role'] === 'editor' ? 'selected' : '' ?>><?= __('editor') ?></option>
                    <option value="admin" <?= $info['role'] === 'admin' ? 'selected' : '' ?>><?= __('admin') ?></option>
                </select>

                <button type="submit" name="change_role"><?= __('change_role') ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Modals unverändert außer Text und Barrierefreiheit -->
<div id="confirmModal" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="confirmText" tabindex="-1">
    <div class="modal">
        <p id="confirmText"><?= __('confirm_delete_user') ?></p>
        <div class="modal-buttons">
            <button id="cancelDelete" type="button"><?= __('cancel') ?></button>
            <button id="confirmDelete" type="button"><?= __('delete') ?></button>
        </div>
    </div>
</div>

<div id="errorModal" class="modal-overlay" style="display:none;" role="alertdialog" aria-modal="true" aria-labelledby="errorText" tabindex="-1">
    <div class="modal">
        <p id="errorText"></p>
        <div class="modal-buttons" style="justify-content:center;">
            <button id="closeErrorModal" type="button">OK</button>
        </div>
    </div>
</div>


<script>
document.addEventListener("DOMContentLoaded", function () {
    function validatePassword(pwd) {
        if (pwd.length < 8) return false;
        if (!/[A-Z]/.test(pwd)) return false;
        if (!/[0-9]/.test(pwd)) return false;
        if (/[^a-zA-Z0-9]/.test(pwd)) return false;
        return true;
    }

    // === Fehler Modal ===
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

    // === Passwortänderung Validierung ===
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

    // === Lösch-Modal ===
    const modal = document.getElementById('confirmModal');
    const confirmText = document.getElementById('confirmText');
    const cancelBtn = document.getElementById('cancelDelete');
    const confirmBtn = document.getElementById('confirmDelete');

    let formToSubmit = null;

    document.querySelectorAll('.delete-user-form button[type="submit"]').forEach(button => {
        button.addEventListener('click', e => {
            e.preventDefault();
            formToSubmit = button.closest('form');
            const username = button.getAttribute('data-username');
            confirmText.textContent = `Bist du sicher, dass du den Benutzer "${username}" löschen möchtest?`;
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('active'), 10);
            cancelBtn.focus();
        });
    });

    cancelBtn.addEventListener('click', () => {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
        formToSubmit = null;
    });

    confirmBtn.addEventListener('click', () => {
        if (formToSubmit) {
            if (!formToSubmit.querySelector('input[name="delete_user"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_user';
                input.value = formToSubmit.querySelector('button[type="submit"]').value;
                formToSubmit.appendChild(input);
            }
            modal.classList.remove('active');
            setTimeout(() => formToSubmit.submit(), 200);
        }
    });

    // ESC schließt Modal
    document.addEventListener('keydown', e => {
        if (e.key === "Escape") {
            if (modal.classList.contains('active')) {
                modal.classList.remove('active');
                setTimeout(() => modal.style.display = 'none', 300);
                formToSubmit = null;
            }
            if (errorModal.classList.contains('active')) {
                errorModal.classList.remove('active');
                setTimeout(() => errorModal.style.display = 'none', 300);
            }
        }
    });
});
</script>

<?php
$content = ob_get_clean();
include '_layout.php';
