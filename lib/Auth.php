<?php

function auth_attempt_login($email, $password)
{
    $user = user_find_by_email($email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    audit_log_add($user['email'], 'auth.login', 'Successful login.');

    return $user;
}

function auth_logout($user)
{
    if ($user) {
        audit_log_add($user['email'], 'auth.logout', 'JWT token cleared on client.');
    }
}
