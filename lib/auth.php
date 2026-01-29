<?php
function check_login($username, $password) {
    $users = json_decode(file_get_contents(__DIR__ . '/../config/users.json'), true);
    foreach ($users as $user) {
        if ($user['user'] === $username && password_verify($password, $user['pass'])) {
            return true;
        }
    }
    return false;
}
