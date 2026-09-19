<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PHPMailer\PHPMailer\PHPMailer;

final class AdminUserController extends Controller
{
    public function index(): void
    {
        require_permission('staff.manage');

        $db = app('db');

        $users = $db->fetchAll(
            'SELECT 
                u.*,
                r.name AS role_name,
                r.slug AS role_slug,
                c.name AS client_name,
                c.phone AS client_phone,
                c.email AS client_email,
                c.client_type,
                c.company_name AS client_company_name,
                c.gst_number AS client_gst_number,
                c.pan_number AS client_pan_number,
                c.city AS client_city,
                c.status AS client_status
             FROM users u
             LEFT JOIN roles r ON r.id = u.role_id
             LEFT JOIN clients c ON c.id = u.client_id
             ORDER BY u.id DESC'
        );

        $roles = $db->fetchAll(
            'SELECT id, name, slug, is_system
             FROM roles
             ORDER BY is_system DESC, name ASC'
        );

        $stats = [
            'total_users'    => (int) $db->scalar('SELECT COUNT(*) FROM users'),
            'active_users'   => (int) $db->scalar('SELECT COUNT(*) FROM users WHERE is_active = 1'),
            'phone_verified' => (int) $db->scalar('SELECT COUNT(*) FROM users WHERE is_phone_verified = 1'),
            'email_verified' => (int) $db->scalar('SELECT COUNT(*) FROM users WHERE is_email_verified = 1'),
        ];

        $this->view('admin/manage_users', [
            'title' => 'Manage Users – Tax Saathi',
            'stats' => $stats,
            'users' => $users,
            'roles' => $roles,
        ], 'layouts/dashboard');
    }

    public function saveUser(): void
    {
        require_permission('staff.manage');
        verify_csrf();

        $db = app('db');

        $id = (int) input('id', 0);
        $isNew = $id <= 0;
        $now = date('Y-m-d H:i:s');

        $name = trim((string) input('name'));
        $phoneInput = trim((string) input('phone'));
        $phone = function_exists('normalize_phone') ? normalize_phone($phoneInput) : $phoneInput;
        $email = strtolower(trim((string) input('email')));
        $password = trim((string) input('password'));

        $roleRaw = trim((string) input('role_id', ''));
        $roleId = null;
        $roleRecord = null;

        $clientName = trim((string) input('client_name'));
        $clientPhoneInput = trim((string) input('client_phone'));
        $clientPhone = function_exists('normalize_phone') ? normalize_phone($clientPhoneInput) : $clientPhoneInput;
        $clientEmail = strtolower(trim((string) input('client_email')));
        $clientType = trim((string) input('client_type', 'individual'));
        $companyName = trim((string) input('company_name'));
        $gstNumber = strtoupper(trim((string) input('gst_number')));
        $panNumber = strtoupper(trim((string) input('pan_number')));
        $city = trim((string) input('city'));
        $clientStatus = trim((string) input('client_status', 'active'));

        $existingUser = null;

        if ($id > 0) {
            $existingUser = $db->fetch(
                'SELECT *
                 FROM users
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $id]
            );

            if (!$existingUser) {
                flash('error', 'User not found.');
                redirect('admin/users');
            }
        }

        if ($name === '') {
            flash('error', 'User full name is required.');
            redirect('admin/users');
        }

        if ($phone === '') {
            flash('error', 'User phone number is required.');
            redirect('admin/users');
        }

        if ($isNew && $email === '') {
            flash('error', 'User email is required for sending login details.');
            redirect('admin/users');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid user email address.');
            redirect('admin/users');
        }

        if ($clientName === '') {
            $clientName = $name;
        }

        if ($clientPhone === '') {
            $clientPhone = $phone;
        }

        if ($clientEmail === '') {
            $clientEmail = $email;
        }

        if ($clientName === '') {
            flash('error', 'Client name is required.');
            redirect('admin/users');
        }

        if ($clientPhone === '') {
            flash('error', 'Client phone is required.');
            redirect('admin/users');
        }

        if ($clientEmail !== '' && !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid client email address.');
            redirect('admin/users');
        }

        $phoneExists = $db->fetch(
            'SELECT id
             FROM users
             WHERE phone = :phone AND id != :id
             LIMIT 1',
            [
                'phone' => $phone,
                'id'    => $id,
            ]
        );

        if ($phoneExists) {
            flash('error', 'This phone number is already used by another user.');
            redirect('admin/users');
        }

        if ($email !== '') {
            $emailExists = $db->fetch(
                'SELECT id
                 FROM users
                 WHERE email = :email AND id != :id
                 LIMIT 1',
                [
                    'email' => $email,
                    'id'    => $id,
                ]
            );

            if ($emailExists) {
                flash('error', 'This email is already used by another user.');
                redirect('admin/users');
            }
        }

        if ($roleRaw !== '') {
            $roleId = (int) $roleRaw;

            $roleRecord = $db->fetch(
                'SELECT id, name, slug
                 FROM roles
                 WHERE id = :id
                 LIMIT 1',
                ['id' => $roleId]
            );

            if (!$roleRecord) {
                flash('error', 'Selected role does not exist.');
                redirect('admin/users');
            }
        }

        $clientPayload = [
            'name'         => $clientName,
            'phone'        => $clientPhone,
            'email'        => $clientEmail !== '' ? $clientEmail : null,
            'client_type'  => $clientType !== '' ? $clientType : 'individual',
            'company_name' => $companyName !== '' ? $companyName : null,
            'gst_number'   => $gstNumber !== '' ? $gstNumber : null,
            'pan_number'   => $panNumber !== '' ? $panNumber : null,
            'city'         => $city !== '' ? $city : null,
            'status'       => $clientStatus !== '' ? $clientStatus : 'active',
            'updated_at'   => $now,
        ];

        $plainPasswordForEmail = '';
        if ($isNew) {
            $plainPasswordForEmail = $password !== '' ? $password : $this->generateTemporaryPassword();
        } elseif ($password !== '') {
            $plainPasswordForEmail = $password;
        }

        $userPayload = [
            'role_id'           => $roleId,
            'name'              => $name,
            'phone'             => $phone,
            'email'             => $email !== '' ? $email : null,
            'is_phone_verified' => isset($_POST['is_phone_verified']) ? 1 : 0,
            'is_email_verified' => isset($_POST['is_email_verified']) ? 1 : 0,
            'is_active'         => isset($_POST['is_active']) ? 1 : 0,
            'updated_at'        => $now,
        ];

        if ($plainPasswordForEmail !== '') {
            $userPayload['password_hash'] = password_hash($plainPasswordForEmail, PASSWORD_DEFAULT);
        }

        $transactionStarted = false;

        try {
            $transactionStarted = $this->beginDbTransaction();

            if ($isNew) {
                $clientPayload['created_at'] = $now;
                $clientId = $this->dbInsertRow('clients', $clientPayload);

                $userPayload['client_id'] = $clientId;
                $userPayload['last_login_at'] = null;
                $userPayload['created_at'] = $now;

                if (!isset($userPayload['password_hash'])) {
                    $userPayload['password_hash'] = null;
                }

                $newUserId = $this->dbInsertRow('users', $userPayload);

                if ($transactionStarted) {
                    $this->commitDbTransaction();
                }

                $mailNote = '';
                if (($userPayload['email'] ?? null) !== null) {
                    try {
                        $this->sendUserCreatedEmail(
                            $name,
                            (string) $userPayload['email'],
                            $plainPasswordForEmail,
                            $roleRecord['name'] ?? 'User',
                            $companyName !== '' ? $companyName : $clientName
                        );
                        $mailNote = ' Login email sent successfully.';
                    } catch (\Throwable $mailError) {
                        $mailNote = ' User created, but email could not be sent.';
                    }
                }

                flash('success', 'User and client created successfully.' . $mailNote);
                redirect('admin/users');
            }

            $clientId = (int) ($existingUser['client_id'] ?? 0);

            if ($clientId > 0) {
                $this->dbUpdateRow('clients', $clientPayload, ['id' => $clientId]);
            } else {
                $clientPayload['created_at'] = $now;
                $clientId = $this->dbInsertRow('clients', $clientPayload);
            }

            $userPayload['client_id'] = $clientId;

            $this->dbUpdateRow('users', $userPayload, ['id' => $id]);

            if ($transactionStarted) {
                $this->commitDbTransaction();
            }

            flash('success', 'User and linked client updated successfully.');
            redirect('admin/users');
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                $this->rollbackDbTransaction();
            }

            flash('error', 'Could not save user/client. Please verify the values and try again.');
            redirect('admin/users');
        }
    }

    public function deleteUser(): void
    {
        require_permission('staff.manage');
        verify_csrf();

        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid user.');
            redirect('admin/users');
        }

        $this->dbDeleteRow('users', ['id' => $id]);

        flash('success', 'User deleted successfully.');
        redirect('admin/users');
    }

    public function toggleUser(): void
    {
        require_permission('staff.manage');
        verify_csrf();

        $db = app('db');
        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid user.');
            redirect('admin/users');
        }

        $user = $db->fetch(
            'SELECT id, is_active
             FROM users
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );

        if (!$user) {
            flash('error', 'User not found.');
            redirect('admin/users');
        }

        $this->dbUpdateRow('users', [
            'is_active'  => (int) $user['is_active'] === 1 ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        flash('success', 'User status updated.');
        redirect('admin/users');
    }

    private function sendUserCreatedEmail(
        string $name,
        string $email,
        string $plainPassword,
        string $roleName,
        string $clientName
    ): void {
        if ($email === '') {
            return;
        }

        if (!class_exists(PHPMailer::class)) {
            throw new \RuntimeException('PHPMailer is not installed or not autoloaded.');
        }

        $smtp = $this->smtpConfig();

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) $smtp['host'];
        $mail->Port = (int) $smtp['port'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $smtp['username'];
        $mail->Password = (string) $smtp['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout = (int) ($smtp['timeout'] ?? 30);
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('emailer@taxsaathi.in', 'Tax Saathi');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Your Tax Saathi account has been created';

        $loginUrl = function_exists('base_url') ? base_url('login') : '#';

        $mail->Body = $this->buildWelcomeEmailHtml(
            $name,
            $email,
            $plainPassword,
            $roleName,
            $clientName,
            $loginUrl
        );

        $mail->AltBody = $this->buildWelcomeEmailText(
            $name,
            $email,
            $plainPassword,
            $roleName,
            $clientName,
            $loginUrl
        );

        $mail->send();
    }

    private function buildWelcomeEmailHtml(
        string $name,
        string $email,
        string $plainPassword,
        string $roleName,
        string $clientName,
        string $loginUrl
    ): string {
        $nameEsc = $this->esc($name);
        $emailEsc = $this->esc($email);
        $passwordEsc = $this->esc($plainPassword);
        $roleEsc = $this->esc($roleName);
        $clientEsc = $this->esc($clientName);
        $loginUrlEsc = $this->esc($loginUrl);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Welcome to Tax Saathi</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f8fafc;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;">
                    <tr>
                        <td style="background:linear-gradient(135deg,#0f172a,#1e293b);padding:28px 32px;color:#ffffff;">
                            <div style="font-size:11px;letter-spacing:0.18em;text-transform:uppercase;opacity:0.8;font-weight:700;">Tax Saathi</div>
                            <h1 style="margin:10px 0 0;font-size:24px;line-height:1.3;">Your account has been created</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;">Hello {$nameEsc},</p>
                            <p style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#334155;">
                                Your Tax Saathi login is ready. Your account details are below.
                            </p>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
                                <tr>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;width:160px;font-size:13px;font-weight:700;color:#475569;">Email</td>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;">{$emailEsc}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700;color:#475569;">Password</td>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;">{$passwordEsc}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:700;color:#475569;">Role</td>
                                    <td style="padding:14px 16px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;">{$roleEsc}</td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 16px;font-size:13px;font-weight:700;color:#475569;">Client</td>
                                    <td style="padding:14px 16px;font-size:14px;color:#0f172a;">{$clientEsc}</td>
                                </tr>
                            </table>

                            <div style="margin-top:24px;">
                                <a href="{$loginUrlEsc}" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:12px;font-size:14px;font-weight:700;">
                                    Login to Tax Saathi
                                </a>
                            </div>

                            <p style="margin:24px 0 0;font-size:13px;line-height:1.7;color:#64748b;">
                                Please log in and change your password after your first successful login.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;">
                            This is an automated email from Tax Saathi.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function buildWelcomeEmailText(
        string $name,
        string $email,
        string $plainPassword,
        string $roleName,
        string $clientName,
        string $loginUrl
    ): string {
        return
            "Hello {$name},\n\n" .
            "Your Tax Saathi account has been created.\n\n" .
            "Email: {$email}\n" .
            "Password: {$plainPassword}\n" .
            "Role: {$roleName}\n" .
            "Client: {$clientName}\n" .
            "Login: {$loginUrl}\n\n" .
            "Please log in and change your password after your first login.\n\n" .
            "Tax Saathi";
    }

    private function smtpConfig(): array
    {
        return [
            'host'       => 'smtp.mailer91.com',
            'port'       => 587,
            'username'   => 'emailer@taxsaathi.in',
            'password'   => 'ZED7Elr00WnUXa3p',
            'encryption' => 'tls',
            'timeout'    => 30,
        ];
    }

    private function generateTemporaryPassword(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
        $max = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }

    private function beginDbTransaction(): bool
    {
        $pdo = $this->resolvePdo();
        if (!$pdo) {
            return false;
        }

        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            return true;
        }

        return false;
    }

    private function commitDbTransaction(): void
    {
        $pdo = $this->resolvePdo();
        if ($pdo && $pdo->inTransaction()) {
            $pdo->commit();
        }
    }

    private function rollbackDbTransaction(): void
    {
        $pdo = $this->resolvePdo();
        if ($pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    private function resolvePdo(): ?\PDO
    {
        $db = app('db');

        if (method_exists($db, 'pdo')) {
            $pdo = $db->pdo();
            if ($pdo instanceof \PDO) {
                return $pdo;
            }
        }

        if (property_exists($db, 'pdo') && $db->pdo instanceof \PDO) {
            return $db->pdo;
        }

        return null;
    }

    private function esc(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function dbWrite(string $sql, array $params = []): void
    {
        $db = app('db');

        if (method_exists($db, 'execute')) {
            $db->execute($sql, $params);
            return;
        }

        if (method_exists($db, 'statement')) {
            $db->statement($sql, $params);
            return;
        }

        if (method_exists($db, 'query')) {
            $db->query($sql, $params);
            return;
        }

        if (method_exists($db, 'pdo')) {
            $stmt = $db->pdo()->prepare($sql);
            $stmt->execute($params);
            return;
        }

        if (property_exists($db, 'pdo') && $db->pdo instanceof \PDO) {
            $stmt = $db->pdo->prepare($sql);
            $stmt->execute($params);
            return;
        }

        throw new \RuntimeException('Database write method not supported.');
    }

    private function dbInsertRow(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn(string $col): string => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->dbWrite($sql, $data);

        $db = app('db');

        if (method_exists($db, 'lastInsertId')) {
            return (int) $db->lastInsertId();
        }

        if (method_exists($db, 'pdo')) {
            return (int) $db->pdo()->lastInsertId();
        }

        if (property_exists($db, 'pdo') && $db->pdo instanceof \PDO) {
            return (int) $db->pdo->lastInsertId();
        }

        if (method_exists($db, 'scalar')) {
            return (int) $db->scalar('SELECT LAST_INSERT_ID()');
        }

        return 0;
    }

    private function dbUpdateRow(string $table, array $data, array $where): void
    {
        $setParts = [];
        $params = [];

        foreach ($data as $column => $value) {
            $param = 'set_' . $column;
            $setParts[] = $column . ' = :' . $param;
            $params[$param] = $value;
        }

        $whereParts = [];
        foreach ($where as $column => $value) {
            $param = 'where_' . $column;
            $whereParts[] = $column . ' = :' . $param;
            $params[$param] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );

        $this->dbWrite($sql, $params);
    }

    private function dbDeleteRow(string $table, array $where): void
    {
        $whereParts = [];
        $params = [];

        foreach ($where as $column => $value) {
            $param = 'where_' . $column;
            $whereParts[] = $column . ' = :' . $param;
            $params[$param] = $value;
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $whereParts)
        );

        $this->dbWrite($sql, $params);
    }
}