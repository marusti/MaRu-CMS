<?php
define('USER_FILE', __DIR__ . '/../config/users.json');

function load_users(): array {
    if (!file_exists(USER_FILE)) {
        return [];
    }
    $json = file_get_contents(USER_FILE);
    return json_decode($json, true) ?? [];
}

function save_users(array $users): void {
    file_put_contents(USER_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function delete_user(string $username): bool {
    $users = load_users();
    foreach ($users as $index => $user) {
        if ($user['username'] === $username) {
            unset($users[$index]);
            save_users(array_values($users));
            return true;
        }
    }
    return false;
}

function user_exists(string $username): bool {
    foreach (load_users() as $user) {
        if ($user['username'] === $username) {
            return true;
        }
    }
    return false;
}

