<?php
declare(strict_types=1);

final class Auth
{
    public static function attemptLogin(string $email, string $password): ?array
    {
        $user = UserRepository::findByEmail($email);
        if ($user === null) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        AuditRepository::log($user['email'], 'auth.login', 'Successful login.');

        return $user;
    }

    public static function logout(?array $user): void
    {
        if ($user !== null) {
            AuditRepository::log($user['email'], 'auth.logout', 'Session ended.');
        }

        $_SESSION = [];
        if (session_id() !== '') {
            session_regenerate_id(true);
        }
    }
}

