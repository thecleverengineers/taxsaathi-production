<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;

final class AuthController extends Controller
{
    public function showAuth(): void
    {
        require_guest();

        $pending = $_SESSION['register_otp'] ?? null;

        $this->view('auth/phone', [
            'title'   => 'Login / Register – Tax Saathi',
            'pending' => $pending,
        ], 'layouts/auth');
    }

    public function sendRegisterOtp(): void
    {
        require_guest();
        verify_csrf();

        $name            = trim((string) input('name'));
        $email           = strtolower(trim((string) input('email')));
        $phone           = trim((string) input('phone'));
        $password        = (string) input('password');
        $confirmPassword = (string) input('confirm_password');

        with_old([
            'name'  => $name,
            'email' => $email,
            'phone' => $phone,
        ]);

        if ($name === '' || $email === '' || $phone === '' || $password === '' || $confirmPassword === '') {
            flash('error', 'Please fill in all registration fields.');
            redirect('auth');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect('auth');
        }

        $normalizedPhone = normalize_phone($phone);
        $mobile          = $this->mobileForFast2Sms($phone);

        if ($normalizedPhone === '' || $mobile === '') {
            flash('error', 'Please enter a valid 10 digit Indian mobile number.');
            redirect('auth');
        }

        if ($password !== $confirmPassword) {
            flash('error', 'Password and confirm password do not match.');
            redirect('auth');
        }

        if (strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters.');
            redirect('auth');
        }

        if (User::findByEmail($email)) {
            flash('error', 'This email is already registered. Please login.');
            redirect('auth');
        }

        if (User::findByPhone($normalizedPhone)) {
            flash('error', 'This phone number is already registered. Please login.');
            redirect('auth');
        }

        $otp = (string) random_int(100000, 999999);

        $_SESSION['register_otp'] = [
            'name'            => $name,
            'email'           => $email,
            'phone'           => $normalizedPhone,
            'fast2sms_mobile' => $mobile,
            'password_hash'   => password_hash($password, PASSWORD_DEFAULT),
            'otp_hash'        => password_hash($otp, PASSWORD_DEFAULT),
            'attempts'        => 0,
            'resend_count'    => 0,
            'expires_at'      => time() + ($this->otpExpiryMinutes() * 60),
            'sent_at'         => date('Y-m-d H:i:s'),
            'last_resend_at'  => null,
        ];

        if ((bool) config('app.debug', false)) {
            $_SESSION['register_otp']['debug_otp'] = $otp;
        }

        $result = $this->sendMobileOtpViaFast2Sms($mobile, $otp, $name);

        activity_log(null, null, 'auth.register_otp.sent', 'Registration OTP sent to mobile ' . $mobile, [
            'email'  => $email,
            'phone'  => $normalizedPhone,
            'mobile' => $mobile,
            'result' => $result['message'] ?? null,
        ]);

        if (!(bool) ($result['ok'] ?? false)) {
            $message = (string) ($result['message'] ?? 'Mobile OTP delivery failed.');

            if ((bool) config('app.debug', false)) {
                $message .= ' Development OTP: ' . $otp;
                flash('warning', $message);
                redirect('auth/verify');
            }

            unset($_SESSION['register_otp']);
            flash('error', $message);
            redirect('auth');
        }

        flash('success', 'OTP sent to your mobile number.');
        redirect('auth/verify');
    }

    public function resendRegisterOtp(): void
    {
        require_guest();
        verify_csrf();

        $pending = $_SESSION['register_otp'] ?? null;

        if (!$pending) {
            flash('error', 'Please complete registration first.');
            redirect('auth');
        }

        $lastResendAt = (int) ($pending['last_resend_at'] ?? 0);

        if ($lastResendAt > 0 && (time() - $lastResendAt) < 30) {
            flash('error', 'Please wait before requesting another OTP.');
            redirect('auth/verify');
        }

        $mobile = (string) ($pending['fast2sms_mobile'] ?? '');

        if ($mobile === '') {
            $mobile = $this->mobileForFast2Sms((string) ($pending['phone'] ?? ''));
        }

        if ($mobile === '') {
            unset($_SESSION['register_otp']);
            flash('error', 'Mobile number is invalid. Please register again.');
            redirect('auth');
        }

        /*
         * Fast2SMS resend endpoint accepts only mobile.
         * Do not generate a new local OTP here.
         * Resend the currently active OTP from Fast2SMS.
         */
        $result = $this->resendMobileOtpViaFast2Sms($mobile);

        if (!(bool) ($result['ok'] ?? false)) {
            flash('error', (string) ($result['message'] ?? 'Unable to resend OTP. Please try again.'));
            redirect('auth/verify');
        }

        $_SESSION['register_otp']['expires_at']     = time() + ($this->otpExpiryMinutes() * 60);
        $_SESSION['register_otp']['sent_at']        = date('Y-m-d H:i:s');
        $_SESSION['register_otp']['last_resend_at'] = time();
        $_SESSION['register_otp']['attempts']       = 0;
        $_SESSION['register_otp']['resend_count']   = ((int) ($pending['resend_count'] ?? 0)) + 1;

        activity_log(null, null, 'auth.register_otp.resent', 'Registration OTP resent to mobile ' . $mobile, [
            'email'  => $pending['email'] ?? null,
            'phone'  => $pending['phone'] ?? null,
            'mobile' => $mobile,
        ]);

        flash('success', 'OTP resent to your mobile number.');
        redirect('auth/verify');
    }

    public function showVerify(): void
    {
        require_guest();

        $pending = $_SESSION['register_otp'] ?? null;

        if (!$pending) {
            flash('error', 'Please complete registration first.');
            redirect('auth');
        }

        $this->view('auth/verify', [
            'title'   => 'Verify Mobile OTP – Tax Saathi',
            'pending' => $pending,
        ], 'layouts/auth');
    }

    public function verifyRegisterOtp(): void
    {
        require_guest();
        verify_csrf();

        $pending = $_SESSION['register_otp'] ?? null;

        if (!$pending) {
            flash('error', 'OTP session expired. Please register again.');
            redirect('auth');
        }

        if ((int) ($pending['expires_at'] ?? 0) < time()) {
            flash('error', 'OTP expired. Please resend OTP.');
            redirect('auth/verify');
        }

        $code = trim((string) input('otp'));

        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            flash('error', 'Please enter a valid 6 digit OTP.');
            redirect('auth/verify');
        }

        $attempts = (int) ($pending['attempts'] ?? 0) + 1;
        $_SESSION['register_otp']['attempts'] = $attempts;

        if ($attempts > (int) config('security.max_otp_attempts', 5)) {
            unset($_SESSION['register_otp']);
            flash('error', 'Too many invalid attempts. Please register again.');
            redirect('auth');
        }

        $mobile = (string) ($pending['fast2sms_mobile'] ?? '');

        if ($mobile === '') {
            $mobile = $this->mobileForFast2Sms((string) ($pending['phone'] ?? ''));
        }

        $apiResult = $this->verifyMobileOtpViaFast2Sms($mobile, $code);
        $apiOk     = (bool) ($apiResult['ok'] ?? false);
        $localOk   = password_verify($code, (string) ($pending['otp_hash'] ?? ''));

        if (!$apiOk && !$localOk) {
            flash('error', 'Invalid OTP. Please try again.');
            redirect('auth/verify');
        }

        $name         = (string) ($pending['name'] ?? '');
        $email        = (string) ($pending['email'] ?? '');
        $phone        = (string) ($pending['phone'] ?? '');
        $passwordHash = (string) ($pending['password_hash'] ?? '');

        if ($name === '' || $email === '' || $phone === '' || $passwordHash === '') {
            unset($_SESSION['register_otp']);
            flash('error', 'Registration data is incomplete. Please register again.');
            redirect('auth');
        }

        if (User::findByEmail($email)) {
            unset($_SESSION['register_otp']);
            flash('error', 'This email is already registered. Please login.');
            redirect('auth');
        }

        if (User::findByPhone($phone)) {
            unset($_SESSION['register_otp']);
            flash('error', 'This phone number is already registered. Please login.');
            redirect('auth');
        }

 $client = Client::findByEmail($email);

if (!$client) {
    $clientId = Client::insert([
        'name'         => $name,
        'phone'        => $phone,
        'email'        => $email,
        'client_type'  => 'individual',
        'company_name' => null,
        'gst_number'   => null,
        'pan_number'   => null,
        'city'         => null,
        'status'       => 'active',
        'created_at'   => date('Y-m-d H:i:s'),
        'updated_at'   => date('Y-m-d H:i:s'),
    ]);
} else {
    $clientId = (int) $client['id'];

    Client::update($clientId, [
        'name'       => $name,
        'phone'      => $phone,
        'email'      => $email,
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
}

        $role = Role::findBySlug('client');

        $userId = User::insert([
            'role_id'            => (int) ($role['id'] ?? 5),
            'client_id'          => $clientId,
            'name'               => $name,
            'phone'              => $phone,
            'email'              => $email,
            'password_hash'      => $passwordHash,
            'is_phone_verified'  => 1,
            'is_email_verified'  => 0,
            'is_active'          => 1,
            'last_login_at'      => date('Y-m-d H:i:s'),
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ]);

        $user = User::findByIdDetailed($userId);

        unset($_SESSION['register_otp']);

        activity_log($userId, null, 'auth.registered', 'Client account registered via mobile OTP.', [
            'email' => $email,
            'phone' => $phone,
        ]);

        login_user($user ?? []);
        flash('success', 'Registration successful. Welcome.');
        $this->redirectAfterLogin($user ?? []);
    }

    public function login(): void
    {
        require_guest();
        verify_csrf();

        $email    = strtolower(trim((string) input('email')));
        $password = (string) input('password');

        with_old([
            'login_email' => $email,
        ]);

        if ($email === '' || $password === '') {
            flash('error', 'Please enter email and password.');
            redirect('auth');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect('auth');
        }

        $user = User::findByEmail($email);

        if (!$user || empty($user['password_hash']) || !password_verify($password, (string) $user['password_hash'])) {
            flash('error', 'Invalid email or password.');
            redirect('auth');
        }

        if (!(int) ($user['is_active'] ?? 0)) {
            flash('error', 'Your account is inactive. Please contact support.');
            redirect('auth');
        }

        User::update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $user = User::findByIdDetailed((int) $user['id']);

        activity_log((int) $user['id'], null, 'auth.login', 'Logged in via email/password.', [
            'email' => $email,
        ]);

        login_user($user ?? []);
        flash('success', 'Welcome back.');
        $this->redirectAfterLogin($user ?? []);
    }


    /**
     * Strict role-based redirect.
     *
     * No generic /dashboard redirect is used.
     *
     * The redirect is resolved from user_roles.role_id assignments only.
     */
    private function redirectAfterLogin(array $user): void
    {
        $roleIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($user['role_ids'] ?? [])),
            static fn (int $roleId): bool => $roleId > 0
        )));

        if (array_intersect([1, 2], $roleIds) !== []) {
            redirect('admin/dashboard');
        }

        if (in_array(3, $roleIds, true)) {
            redirect('executive/dashboard');
        }

        if (in_array(4, $roleIds, true)) {
            redirect('partner/dashboard');
        }

        if (in_array(5, $roleIds, true)) {
            redirect('client/dashboard');
        }

        redirect('dashboard');
    }


    public function showForgotPassword(): void
    {
        require_guest();

        $pending = $_SESSION['forgot_password_otp'] ?? null;

        $this->view('auth/forgot-password', [
            'title' => 'Forgot Password – Tax Saathi',
            'pending' => is_array($pending) ? $pending : null,
        ], 'layouts/auth');
    }

    public function sendForgotPasswordOtp(): void
    {
        require_guest();
        verify_csrf();

        $identifier = trim((string) input('identifier'));

        with_old([
            'forgot_identifier' => $identifier,
        ]);

        if ($identifier === '') {
            flash('error', 'Please enter your registered email address or mobile number.');
            redirect('auth/forgot-password');
        }

        $user = $this->findUserForPasswordRecovery($identifier);

        if (!$user) {
            flash('error', 'No active account was found with those details.');
            redirect('auth/forgot-password');
        }

        if (!(int) ($user['is_active'] ?? 1)) {
            flash('error', 'This account is inactive. Please contact support.');
            redirect('auth/forgot-password');
        }

        $phone = trim((string) ($user['phone'] ?? $user['mobile'] ?? ''));
        $mobile = $this->mobileForFast2Sms($phone);

        if ($mobile === '') {
            flash('error', 'No valid mobile number is linked to this account. Please contact support.');
            redirect('auth/forgot-password');
        }

        $otp = (string) random_int(100000, 999999);
        $name = trim((string) ($user['name'] ?? 'User'));

        $_SESSION['forgot_password_otp'] = [
            'user_id' => (int) ($user['id'] ?? 0),
            'name' => $name,
            'email' => strtolower(trim((string) ($user['email'] ?? ''))),
            'phone' => normalize_phone($phone),
            'fast2sms_mobile' => $mobile,
            'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
            'attempts' => 0,
            'resend_count' => 0,
            'expires_at' => time() + ($this->otpExpiryMinutes() * 60),
            'sent_at' => date('Y-m-d H:i:s'),
            'last_resend_at' => null,
            'verified_at' => null,
            'reset_expires_at' => null,
        ];

        if ((bool) config('app.debug', false)) {
            $_SESSION['forgot_password_otp']['debug_otp'] = $otp;
        }

        $result = $this->sendMobileOtpViaFast2Sms($mobile, $otp, $name);

        activity_log((int) ($user['id'] ?? 0), null, 'auth.password_reset_otp.sent', 'Password reset OTP sent.', [
            'email' => $user['email'] ?? null,
            'phone' => $phone,
            'mobile' => $mobile,
            'result' => $result['message'] ?? null,
        ]);

        if (!(bool) ($result['ok'] ?? false)) {
            $message = (string) ($result['message'] ?? 'Unable to send password reset OTP.');

            if ((bool) config('app.debug', false)) {
                flash('warning', $message . ' Development OTP: ' . $otp);
                redirect('auth/forgot-password/verify');
            }

            unset($_SESSION['forgot_password_otp']);
            flash('error', $message);
            redirect('auth/forgot-password');
        }

        flash('success', 'A password reset OTP has been sent to your registered mobile number.');
        redirect('auth/forgot-password/verify');
    }

    public function showForgotPasswordVerify(): void
    {
        require_guest();

        $pending = $_SESSION['forgot_password_otp'] ?? null;

        if (!is_array($pending) || (int) ($pending['user_id'] ?? 0) <= 0) {
            flash('error', 'Please request a password reset OTP first.');
            redirect('auth/forgot-password');
        }

        $this->view('auth/forgot-password-verify', [
            'title' => 'Verify Password Reset OTP – Tax Saathi',
            'pending' => $pending,
        ], 'layouts/auth');
    }

    public function verifyForgotPasswordOtp(): void
    {
        require_guest();
        verify_csrf();

        $pending = $_SESSION['forgot_password_otp'] ?? null;

        if (!is_array($pending) || (int) ($pending['user_id'] ?? 0) <= 0) {
            flash('error', 'Password reset session expired. Please start again.');
            redirect('auth/forgot-password');
        }

        if ((int) ($pending['expires_at'] ?? 0) < time()) {
            flash('error', 'OTP expired. Please request or resend the OTP.');
            redirect('auth/forgot-password/verify');
        }

        $code = trim((string) input('otp'));

        if (!preg_match('/^\d{6}$/', $code)) {
            flash('error', 'Please enter a valid 6 digit OTP.');
            redirect('auth/forgot-password/verify');
        }

        $attempts = (int) ($pending['attempts'] ?? 0) + 1;
        $_SESSION['forgot_password_otp']['attempts'] = $attempts;

        if ($attempts > (int) config('security.max_otp_attempts', 5)) {
            unset($_SESSION['forgot_password_otp']);
            flash('error', 'Too many invalid attempts. Please request a new OTP.');
            redirect('auth/forgot-password');
        }

        $mobile = (string) ($pending['fast2sms_mobile'] ?? '');
        $apiResult = $this->verifyMobileOtpViaFast2Sms($mobile, $code);
        $apiOk = (bool) ($apiResult['ok'] ?? false);
        $localOk = password_verify($code, (string) ($pending['otp_hash'] ?? ''));

        if (!$apiOk && !$localOk) {
            flash('error', 'Invalid OTP. Please try again.');
            redirect('auth/forgot-password/verify');
        }

        $_SESSION['forgot_password_otp']['verified_at'] = time();
        $_SESSION['forgot_password_otp']['reset_expires_at'] = time() + (15 * 60);
        $_SESSION['forgot_password_otp']['otp_hash'] = null;
        $_SESSION['forgot_password_otp']['attempts'] = 0;

        activity_log((int) ($pending['user_id'] ?? 0), null, 'auth.password_reset_otp.verified', 'Password reset OTP verified.');

        flash('success', 'OTP verified. Create your new password.');
        redirect('auth/reset-password');
    }

    public function resendForgotPasswordOtp(): void
    {
        require_guest();
        verify_csrf();

        $pending = $_SESSION['forgot_password_otp'] ?? null;

        if (!is_array($pending) || (int) ($pending['user_id'] ?? 0) <= 0) {
            flash('error', 'Please request a password reset OTP first.');
            redirect('auth/forgot-password');
        }

        $lastResendAt = (int) ($pending['last_resend_at'] ?? 0);

        if ($lastResendAt > 0 && (time() - $lastResendAt) < 30) {
            flash('error', 'Please wait 30 seconds before requesting another OTP.');
            redirect('auth/forgot-password/verify');
        }

        if ((int) ($pending['resend_count'] ?? 0) >= 5) {
            unset($_SESSION['forgot_password_otp']);
            flash('error', 'OTP resend limit reached. Please start the password reset again.');
            redirect('auth/forgot-password');
        }

        $mobile = (string) ($pending['fast2sms_mobile'] ?? '');

        if ($mobile === '') {
            unset($_SESSION['forgot_password_otp']);
            flash('error', 'Registered mobile number is unavailable. Please start again.');
            redirect('auth/forgot-password');
        }

        $result = $this->resendMobileOtpViaFast2Sms($mobile);

        if (!(bool) ($result['ok'] ?? false)) {
            flash('error', (string) ($result['message'] ?? 'Unable to resend OTP. Please try again.'));
            redirect('auth/forgot-password/verify');
        }

        $_SESSION['forgot_password_otp']['expires_at'] = time() + ($this->otpExpiryMinutes() * 60);
        $_SESSION['forgot_password_otp']['sent_at'] = date('Y-m-d H:i:s');
        $_SESSION['forgot_password_otp']['last_resend_at'] = time();
        $_SESSION['forgot_password_otp']['attempts'] = 0;
        $_SESSION['forgot_password_otp']['resend_count'] = (int) ($pending['resend_count'] ?? 0) + 1;

        activity_log((int) ($pending['user_id'] ?? 0), null, 'auth.password_reset_otp.resent', 'Password reset OTP resent.');

        flash('success', 'OTP resent to your registered mobile number.');
        redirect('auth/forgot-password/verify');
    }

    public function showResetPassword(): void
    {
        require_guest();

        $pending = $_SESSION['forgot_password_otp'] ?? null;

        if (!$this->passwordResetSessionIsVerified($pending)) {
            flash('error', 'Please verify your password reset OTP first.');
            redirect('auth/forgot-password');
        }

        $this->view('auth/reset-password', [
            'title' => 'Create New Password – Tax Saathi',
            'pending' => $pending,
        ], 'layouts/auth');
    }

    public function resetPassword(): void
    {
        require_guest();
        verify_csrf();

        $pending = $_SESSION['forgot_password_otp'] ?? null;

        if (!$this->passwordResetSessionIsVerified($pending)) {
            unset($_SESSION['forgot_password_otp']);
            flash('error', 'Password reset authorization expired. Please start again.');
            redirect('auth/forgot-password');
        }

        $password = (string) input('password');
        $confirmPassword = (string) input('confirm_password');

        if ($password === '' || $confirmPassword === '') {
            flash('error', 'Please enter and confirm your new password.');
            redirect('auth/reset-password');
        }

        if (strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters.');
            redirect('auth/reset-password');
        }

        if ($password !== $confirmPassword) {
            flash('error', 'New password and confirm password do not match.');
            redirect('auth/reset-password');
        }

        $userId = (int) ($pending['user_id'] ?? 0);
        $user = User::findByIdDetailed($userId);

        if (!$user) {
            unset($_SESSION['forgot_password_otp']);
            flash('error', 'User account was not found.');
            redirect('auth');
        }

        if (!empty($user['password_hash']) && password_verify($password, (string) $user['password_hash'])) {
            flash('error', 'Your new password must be different from your current password.');
            redirect('auth/reset-password');
        }

        User::update($userId, [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        unset($_SESSION['forgot_password_otp']);

        activity_log($userId, null, 'auth.password_reset.completed', 'Password reset completed via mobile OTP.');

        flash('success', 'Password reset successfully. You can now login with your new password.');
        redirect('auth');
    }

    public function showChangePassword(): void
    {
        require_auth();

        $authUser = auth_user();
        $userId = (int) ($authUser['id'] ?? 0);
        $user = $userId > 0 ? User::findByIdDetailed($userId) : null;

        if (!$user) {
            flash('error', 'User account not found.');
            redirect('auth');
        }

        $this->view('auth/change-password', [
            'title' => 'Change Password – Tax Saathi',
            'userData' => $user,
            'dashboardPath' => $this->dashboardPathForUser($user),
        ], 'layouts/dashboard');
    }

    public function changePassword(): void
    {
        require_auth();
        verify_csrf();

        $authUser = auth_user();
        $userId = (int) ($authUser['id'] ?? 0);
        $user = $userId > 0 ? User::findByIdDetailed($userId) : null;

        if (!$user) {
            flash('error', 'User account not found.');
            redirect('auth');
        }

        $currentPassword = (string) input('current_password');
        $password = (string) input('password');
        $confirmPassword = (string) input('confirm_password');

        if ($currentPassword === '' || $password === '' || $confirmPassword === '') {
            flash('error', 'Please fill in all password fields.');
            redirect('auth/change-password');
        }

        if (empty($user['password_hash']) || !password_verify($currentPassword, (string) $user['password_hash'])) {
            flash('error', 'Current password is incorrect.');
            redirect('auth/change-password');
        }

        if (strlen($password) < 6) {
            flash('error', 'New password must be at least 6 characters.');
            redirect('auth/change-password');
        }

        if ($password !== $confirmPassword) {
            flash('error', 'New password and confirm password do not match.');
            redirect('auth/change-password');
        }

        if (password_verify($password, (string) $user['password_hash'])) {
            flash('error', 'Your new password must be different from your current password.');
            redirect('auth/change-password');
        }

        User::update($userId, [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $freshUser = User::findByIdDetailed($userId);
        if ($freshUser) {
            login_user($freshUser);
        }

        activity_log($userId, null, 'auth.password.changed', 'Password changed by logged-in user.');

        flash('success', 'Password changed successfully.');
        redirect('auth/change-password');
    }

    public function logout(): void
    {
        verify_csrf();

        $userId = (int) (auth_user()['id'] ?? 0);

        logout_user();
        activity_log($userId ?: null, null, 'auth.logout', 'Logged out.');
        flash('success', 'You have been logged out.');
        redirect('auth');
    }



    private function findUserForPasswordRecovery(string $identifier): ?array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        $user = null;

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = User::findByEmail(strtolower($identifier));
        } else {
            $normalizedPhone = normalize_phone($identifier);

            if ($normalizedPhone !== '') {
                $user = User::findByPhone($normalizedPhone);
            }
        }

        if (!$user || (int) ($user['id'] ?? 0) <= 0) {
            return null;
        }

        return User::findByIdDetailed((int) $user['id']) ?: $user;
    }

    private function passwordResetSessionIsVerified(mixed $pending): bool
    {
        if (!is_array($pending)) {
            return false;
        }

        return (int) ($pending['user_id'] ?? 0) > 0
            && (int) ($pending['verified_at'] ?? 0) > 0
            && (int) ($pending['reset_expires_at'] ?? 0) >= time();
    }

    private function dashboardPathForUser(array $user): string
    {
        $roleIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($user['role_ids'] ?? [])),
            static fn (int $roleId): bool => $roleId > 0
        )));

        if (array_intersect([1, 2], $roleIds) !== []) {
            return 'admin/dashboard';
        }

        if (in_array(3, $roleIds, true)) {
            return 'executive/dashboard';
        }

        if (in_array(4, $roleIds, true)) {
            return 'partner/dashboard';
        }

        return in_array(5, $roleIds, true) ? 'client/dashboard' : 'dashboard';
    }

    private function sendMobileOtpViaFast2Sms(string $mobile, string $otp, string $name = ''): array
    {
        return $this->fast2SmsPost('/dev/otp/send', [
            'mobile'           => $mobile,
            'otp_id'           => $this->fast2SmsOtpId(),
            'otp_expiry'       => $this->otpExpiryMinutes(),
            'otp_length'       => $this->fast2SmsOtpLength(),
            'otp'              => $otp,
            'variables_values' => $this->fast2SmsVariablesValues($name),
        ]);
    }

    private function verifyMobileOtpViaFast2Sms(string $mobile, string $otp): array
    {
        return $this->fast2SmsPost('/dev/otp/verify', [
            'mobile' => $mobile,
            'otp'    => $otp,
        ]);
    }

    private function resendMobileOtpViaFast2Sms(string $mobile): array
    {
        return $this->fast2SmsPost('/dev/otp/resend', [
            'mobile' => $mobile,
        ]);
    }

    private function fast2SmsPost(string $endpoint, array $payload): array
    {
        $authKey = $this->fast2SmsAuthKey();

        if ($authKey === '') {
            return [
                'ok'      => false,
                'message' => 'Fast2SMS auth key is missing in app/config/config.php.',
            ];
        }

        $curl = curl_init();

        if ($curl === false) {
            return [
                'ok'      => false,
                'message' => 'Unable to initialize cURL.',
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://www.fast2sms.com' . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'authorization: ' . $authKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        $errno    = curl_errno($curl);
        $error    = curl_error($curl);
        $status   = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($response === false || $errno > 0) {
            return [
                'ok'      => false,
                'message' => 'Fast2SMS request failed: ' . ($error ?: 'Unknown cURL error.'),
            ];
        }

        $json = json_decode((string) $response, true);

        $ok = false;

        if ($status >= 200 && $status < 300 && is_array($json)) {
            if (array_key_exists('return', $json)) {
                $ok = (bool) $json['return'];
            }

            if (!$ok && isset($json['status'])) {
                $statusText = strtolower((string) $json['status']);
                $ok = in_array($statusText, ['success', 'true', 'ok'], true);
            }
        }

        return [
            'ok'          => $ok,
            'status_code' => $status,
            'message'     => $this->fast2SmsMessage($json, $ok ? 'OTP request successful.' : 'OTP request failed.'),
            'raw'         => $json ?: $response,
        ];
    }

    private function fast2SmsMessage(mixed $json, string $fallback): string
    {
        if (is_array($json)) {
            if (isset($json['message'])) {
                if (is_array($json['message'])) {
                    return implode(' ', array_map('strval', $json['message']));
                }

                return (string) $json['message'];
            }

            if (isset($json['reason'])) {
                return (string) $json['reason'];
            }

            if (isset($json['error'])) {
                if (is_array($json['error'])) {
                    return implode(' ', array_map('strval', $json['error']));
                }

                return (string) $json['error'];
            }
        }

        return $fallback;
    }

    private function mobileForFast2Sms(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && substr($digits, 0, 1) === '0') {
            $digits = substr($digits, 1);
        }

        return strlen($digits) === 10 ? $digits : '';
    }

    private function fast2SmsAuthKey(): string
    {
        return trim((string) config('fast2sms.auth_key', ''));
    }

    private function fast2SmsOtpId(): string
    {
        return trim((string) config('fast2sms.otp_id', ''));
    }

    private function otpExpiryMinutes(): int
    {
        $minutes = (int) config('fast2sms.otp_expiry', config('security.otp_expiry_minutes', 15));

        return $minutes > 0 ? $minutes : 15;
    }

    private function fast2SmsOtpLength(): int
    {
        $length = (int) config('fast2sms.otp_length', 6);

        return $length > 0 ? $length : 6;
    }

    private function fast2SmsVariablesValues(string $name = ''): string
    {
        $configured = trim((string) config('fast2sms.variables_values', ''));

        if ($configured !== '') {
            return $configured;
        }

        return ($name !== '' ? $name : 'User') . '|Tax Saathi';
    }
}
