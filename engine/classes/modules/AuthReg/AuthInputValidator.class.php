<?php

declare(strict_types=1);

final class AuthInputValidator
{
    public function __construct(
        private int $maximumLoginLength = 64,
        private int $minimumPasswordLength = 10,
    ) {
        $this->maximumLoginLength = max(3, min(64, $maximumLoginLength));
        $this->minimumPasswordLength = max(8, min(72, $minimumPasswordLength));
    }

    /** @return array{login:string,password:string} */
    public function authenticationCredentials(string $login, string $password): array
    {
        return [
            'login' => $this->login($login, false),
            'password' => $this->authenticationPassword($password),
        ];
    }

    /**
     * @return array{login:string,email:string,password:string,realname:string,registrationCode:string}
     */
    public function registration(
        string $login,
        string $email,
        string $password,
        string $confirmation,
        string $realname,
        string $registrationCode,
    ): array {
        $login = $this->login($login, true);
        $email = $this->email($email);
        $password = $this->registrationPassword($password);
        if (!hash_equals($password, $confirmation)) {
            throw new AuthFailure(
                'Пароли не совпадают.',
                'passwords_do_not_match',
                422,
                'password2',
                expected: ['passwordConfirmationMatches' => true],
                actual: ['passwordConfirmationMatches' => false],
            );
        }

        return [
            'login' => $login,
            'email' => $email,
            'password' => $password,
            'realname' => $this->realName($realname, $login),
            'registrationCode' => $this->registrationCode($registrationCode),
        ];
    }

    private function login(string $login, bool $registration): string
    {
        $login = trim($login);
        if ($login === '') {
            throw new AuthFailure(
                'Введите логин.',
                'login_required',
                422,
                'login',
                expected: ['loginPresent' => true],
                actual: ['loginPresent' => false],
            );
        }

        $length = mb_strlen($login, 'UTF-8');
        $minimum = $registration ? 3 : 1;
        if ($length < $minimum) {
            throw new AuthFailure(
                'Логин должен содержать не менее ' . $minimum . ' символов.',
                'login_too_short',
                422,
                'login',
                expected: ['minimumLength' => $minimum],
                actual: ['length' => $length],
            );
        }
        if ($length > $this->maximumLoginLength) {
            throw new AuthFailure(
                'Логин не должен превышать ' . $this->maximumLoginLength . ' символов.',
                'login_too_long',
                422,
                'login',
                expected: ['maximumLength' => $this->maximumLoginLength],
                actual: ['length' => $length],
            );
        }
        if (preg_match('/^[A-Za-z0-9_.-]+$/D', $login) !== 1) {
            throw new AuthFailure(
                'Логин содержит недопустимые символы. Разрешены латинские буквы, цифры, точка, дефис и подчёркивание.',
                'login_contains_forbidden_characters',
                422,
                'login',
                expected: ['allowedCharacters' => 'A-Z a-z 0-9 . _ -'],
                actual: ['containsForbiddenCharacters' => true],
            );
        }
        return $login;
    }

    private function email(string $email): string
    {
        $email = mb_strtolower(trim($email), 'UTF-8');
        if ($email === '') {
            throw new AuthFailure(
                'Введите электронную почту.',
                'email_required',
                422,
                'email',
                expected: ['emailPresent' => true],
                actual: ['emailPresent' => false],
            );
        }
        $length = mb_strlen($email, 'UTF-8');
        if ($length > 254) {
            throw new AuthFailure(
                'Электронная почта слишком длинная. Максимум — 254 символа.',
                'email_too_long',
                422,
                'email',
                expected: ['maximumLength' => 254],
                actual: ['length' => $length],
            );
        }
        if (preg_match('/[\x00-\x20\x7F]/u', $email) === 1) {
            throw new AuthFailure(
                'Электронная почта содержит пробелы или недопустимые управляющие символы.',
                'email_contains_forbidden_characters',
                422,
                'email',
                expected: ['containsWhitespaceOrControlCharacters' => false],
                actual: ['containsWhitespaceOrControlCharacters' => true],
            );
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new AuthFailure(
                'Введите корректный адрес электронной почты.',
                'email_invalid',
                422,
                'email',
                expected: ['emailFormatValid' => true],
                actual: ['emailFormatValid' => false],
            );
        }
        return $email;
    }

    private function authenticationPassword(string $password, string $field = 'password'): string
    {
        if ($password === '') {
            throw new AuthFailure(
                'Введите пароль.',
                'password_required',
                422,
                $field,
                expected: ['passwordPresent' => true],
                actual: ['passwordPresent' => false],
            );
        }
        if (strlen($password) > 4_096) {
            throw new AuthFailure(
                'Пароль имеет недопустимую длину.',
                'password_input_too_long',
                422,
                $field,
                severity: 'warning',
                expected: ['maximumInputBytes' => 4096],
                actual: ['inputBytes' => strlen($password)],
            );
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $password) === 1) {
            throw new AuthFailure(
                'Пароль содержит недопустимые управляющие символы.',
                'password_contains_control_characters',
                422,
                $field,
                expected: ['containsControlCharacters' => false],
                actual: ['containsControlCharacters' => true],
            );
        }
        return $password;
    }

    private function registrationPassword(string $password): string
    {
        $password = $this->authenticationPassword($password, 'password1');
        $length = strlen($password);
        if ($length < $this->minimumPasswordLength) {
            throw new AuthFailure(
                'Пароль должен содержать не менее ' . $this->minimumPasswordLength . ' символов.',
                'password_too_short',
                422,
                'password1',
                expected: ['minimumLength' => $this->minimumPasswordLength],
                actual: ['length' => $length],
            );
        }
        if ($length > 72) {
            throw new AuthFailure(
                'Пароль не должен превышать 72 символа.',
                'password_too_long',
                422,
                'password1',
                expected: ['maximumLength' => 72],
                actual: ['length' => $length],
            );
        }
        if (preg_match('/[^\x21-\x7E]/', $password) === 1) {
            throw new AuthFailure(
                'Пароль содержит недопустимые символы. Используйте латинские буквы, цифры и специальные символы.',
                'password_contains_forbidden_characters',
                422,
                'password1',
                expected: ['allowedCharacters' => 'printable ASCII without spaces'],
                actual: ['containsForbiddenCharacters' => true],
            );
        }
        return $password;
    }

    private function realName(string $realname, string $fallback): string
    {
        $realname = trim($realname);
        if ($realname === '') {
            return $fallback;
        }
        $length = mb_strlen($realname, 'UTF-8');
        if ($length > 64) {
            throw new AuthFailure(
                'Имя не должно превышать 64 символа.',
                'realname_too_long',
                422,
                'realname',
                expected: ['maximumLength' => 64],
                actual: ['length' => $length],
            );
        }
        if (preg_match('/[\x00-\x1F\x7F]/u', $realname) === 1) {
            throw new AuthFailure(
                'Имя содержит недопустимые управляющие символы.',
                'realname_contains_control_characters',
                422,
                'realname',
                expected: ['containsControlCharacters' => false],
                actual: ['containsControlCharacters' => true],
            );
        }
        return $realname;
    }

    private function registrationCode(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }
        if (strlen($code) < 4 || strlen($code) > 64) {
            throw new AuthFailure(
                'Код регистрации должен содержать от 4 до 64 символов.',
                'registration_code_length_invalid',
                422,
                'regCode',
                expected: ['minimumLength' => 4, 'maximumLength' => 64],
                actual: ['length' => strlen($code)],
            );
        }
        if (preg_match('/^[A-Za-z0-9_-]+$/D', $code) !== 1) {
            throw new AuthFailure(
                'Код регистрации содержит недопустимые символы. Разрешены латинские буквы, цифры, дефис и подчёркивание.',
                'registration_code_contains_forbidden_characters',
                422,
                'regCode',
                expected: ['allowedCharacters' => 'A-Z a-z 0-9 _ -'],
                actual: ['containsForbiddenCharacters' => true],
            );
        }
        return $code;
    }
}
