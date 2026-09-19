<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;

final class ClientProfileController extends Controller
{
    public function index(): void
    {
        require_auth();

        $userId = $this->currentUserId();

        if ($userId <= 0) {
            flash('error', 'Please login to continue.');
            redirect('auth');
            return;
        }

        $user = $this->findUserById($userId);

        if (!$user) {
            flash('error', 'User account not found.');
            redirect('auth');
            return;
        }

        $client = $this->ensureClientForUser($user);

        /*
         * Render the profile once inside the shared dashboard layout.
         * Do not render dashboard/index here and do not call undefined
         * roleKey()/dashboardDataForRole() methods.
         */
        $this->view(
            'client/profile',
            [
                'title'    => 'My Profile – Tax Saathi',
                'userData' => $user,
                'client'   => $client,
            ],
            'layouts/dashboard'
        );
    }

    public function update(): void
    {
        require_auth();
        verify_csrf();

        $userId = $this->currentUserId();

        if ($userId <= 0) {
            flash('error', 'Please login to continue.');
            redirect('auth');
            return;
        }

        $user = $this->findUserById($userId);

        if (!$user) {
            flash('error', 'User account not found.');
            redirect('auth');
            return;
        }

        $client = $this->ensureClientForUser($user);

        $name        = trim((string) input('name'));
        $email       = strtolower(trim((string) input('email')));
        $clientType  = trim((string) input('client_type'));
        $companyName = trim((string) input('company_name'));
        $gstNumber   = strtoupper(trim((string) input('gst_number')));
        $panNumber   = strtoupper(trim((string) input('pan_number')));
        $city        = trim((string) input('city'));

        with_old([
            'name'         => $name,
            'email'        => $email,
            'client_type'  => $clientType,
            'company_name' => $companyName,
            'gst_number'   => $gstNumber,
            'pan_number'   => $panNumber,
            'city'         => $city,
        ]);

        if ($name === '') {
            flash('error', 'Please enter your full name.');
            redirect('client/profile');
            return;
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            flash('error', 'Please enter a valid email address.');
            redirect('client/profile');
            return;
        }

        $allowedClientTypes = ['individual', 'business', 'company'];

        if (!in_array($clientType, $allowedClientTypes, true)) {
            $clientType = 'individual';
        }

        if ($panNumber !== '' && preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $panNumber) !== 1) {
            flash('error', 'Please enter a valid PAN number.');
            redirect('client/profile');
            return;
        }

        if (
            $gstNumber !== ''
            && preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][A-Z0-9]Z[A-Z0-9]$/', $gstNumber) !== 1
        ) {
            flash('error', 'Please enter a valid GST number.');
            redirect('client/profile');
            return;
        }

        $emailOwner = $this->findUserByEmail($email);

        if ($emailOwner && (int) ($emailOwner['id'] ?? 0) !== $userId) {
            flash('error', 'This email address is already used by another account.');
            redirect('client/profile');
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->db()->query(
            "UPDATE users
             SET name = :name,
                 email = :email,
                 updated_at = :updated_at
             WHERE id = :id
             LIMIT 1",
            [
                'name'       => $name,
                'email'      => $email,
                'updated_at' => $now,
                'id'         => $userId,
            ]
        );

        $this->db()->query(
            "UPDATE clients
             SET name = :name,
                 email = :email,
                 client_type = :client_type,
                 company_name = :company_name,
                 gst_number = :gst_number,
                 pan_number = :pan_number,
                 city = :city,
                 updated_at = :updated_at
             WHERE id = :id
             LIMIT 1",
            [
                'name'         => $name,
                'email'        => $email,
                'client_type'  => $clientType,
                'company_name' => $companyName !== '' ? $companyName : null,
                'gst_number'   => $gstNumber !== '' ? $gstNumber : null,
                'pan_number'   => $panNumber !== '' ? $panNumber : null,
                'city'         => $city !== '' ? $city : null,
                'updated_at'   => $now,
                'id'           => (int) ($client['id'] ?? 0),
            ]
        );

        activity_log(
            $userId,
            null,
            'client.profile.updated',
            'Client profile updated.',
            [
                'email'     => $email,
                'client_id' => (int) ($client['id'] ?? 0),
            ]
        );

        flash('success', 'Profile updated successfully.');
        redirect('client/profile');
    }

    public function password(): void
    {
        require_auth();
        verify_csrf();

        $userId = $this->currentUserId();

        if ($userId <= 0) {
            flash('error', 'Please login to continue.');
            redirect('auth');
            return;
        }

        $user = $this->findUserById($userId);

        if (!$user) {
            flash('error', 'User account not found.');
            redirect('auth');
            return;
        }

        $currentPassword = (string) input('current_password');
        $password        = (string) input('password');
        $confirmPassword = (string) input('confirm_password');

        if ($currentPassword === '' || $password === '' || $confirmPassword === '') {
            flash('error', 'Please fill in all password fields.');
            redirect('client/profile');
            return;
        }

        $storedPasswordHash = trim((string) ($user['password_hash'] ?? ''));

        if ($storedPasswordHash === '' || !password_verify($currentPassword, $storedPasswordHash)) {
            flash('error', 'Current password is incorrect.');
            redirect('client/profile');
            return;
        }

        if ($password !== $confirmPassword) {
            flash('error', 'New password and confirm password do not match.');
            redirect('client/profile');
            return;
        }

        if (strlen($password) < 6) {
            flash('error', 'Password must be at least 6 characters.');
            redirect('client/profile');
            return;
        }

        $this->db()->query(
            "UPDATE users
             SET password_hash = :password_hash,
                 updated_at = :updated_at
             WHERE id = :id
             LIMIT 1",
            [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'updated_at'    => date('Y-m-d H:i:s'),
                'id'            => $userId,
            ]
        );

        activity_log(
            $userId,
            null,
            'client.profile.password_updated',
            'Client password updated.'
        );

        flash('success', 'Password updated successfully.');
        redirect('client/profile');
    }

    private function currentUserId(): int
    {
        $user = auth_user();

        return (int) ($user['id'] ?? 0);
    }

    private function findUserById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db()->query(
            "SELECT *
             FROM users
             WHERE id = :id
             LIMIT 1",
            ['id' => $id]
        )->fetch();

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function findUserByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        $row = $this->db()->query(
            "SELECT *
             FROM users
             WHERE email = :email
             LIMIT 1",
            ['email' => $email]
        )->fetch();

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function findClientById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db()->query(
            "SELECT *
             FROM clients
             WHERE id = :id
             LIMIT 1",
            ['id' => $id]
        )->fetch();

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function findClientByPhone(string $phone): ?array
    {
        $phone = trim($phone);

        if ($phone === '') {
            return null;
        }

        $row = $this->db()->query(
            "SELECT *
             FROM clients
             WHERE phone = :phone
             LIMIT 1",
            ['phone' => $phone]
        )->fetch();

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function ensureClientForUser(array $user): array
    {
        $userId   = (int) ($user['id'] ?? 0);
        $clientId = (int) ($user['client_id'] ?? 0);
        $phone    = trim((string) ($user['phone'] ?? $user['mobile'] ?? ''));
        $email    = strtolower(trim((string) ($user['email'] ?? '')));
        $name     = trim((string) ($user['name'] ?? $user['full_name'] ?? 'Client'));

        if ($userId <= 0) {
            throw new RuntimeException('Invalid user account.');
        }

        if ($clientId > 0) {
            $client = $this->findClientById($clientId);

            if ($client) {
                return $client;
            }
        }

        if ($phone !== '') {
            $client = $this->findClientByPhone($phone);

            if ($client) {
                $this->db()->query(
                    "UPDATE users
                     SET client_id = :client_id,
                         updated_at = :updated_at
                     WHERE id = :id
                     LIMIT 1",
                    [
                        'client_id'  => (int) ($client['id'] ?? 0),
                        'updated_at' => date('Y-m-d H:i:s'),
                        'id'         => $userId,
                    ]
                );

                return $client;
            }
        }

        $now = date('Y-m-d H:i:s');

        $this->db()->query(
            "INSERT INTO clients
                (
                    name,
                    phone,
                    email,
                    client_type,
                    company_name,
                    gst_number,
                    pan_number,
                    city,
                    status,
                    created_at,
                    updated_at
                )
             VALUES
                (
                    :name,
                    :phone,
                    :email,
                    'individual',
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    'active',
                    :created_at,
                    :updated_at
                )",
            [
                'name'       => $name !== '' ? $name : 'Client',
                'phone'      => $phone,
                'email'      => $email !== '' ? $email : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $newClientId = (int) $this->db()
            ->query('SELECT LAST_INSERT_ID() AS id')
            ->fetchColumn();

        if ($newClientId <= 0) {
            throw new RuntimeException('Unable to create client profile.');
        }

        $this->db()->query(
            "UPDATE users
             SET client_id = :client_id,
                 updated_at = :updated_at
             WHERE id = :id
             LIMIT 1",
            [
                'client_id'  => $newClientId,
                'updated_at' => $now,
                'id'         => $userId,
            ]
        );

        $client = $this->findClientById($newClientId);

        if (!$client) {
            throw new RuntimeException('Unable to load the newly created client profile.');
        }

        return $client;
    }

    private function db(): Database
    {
        $database = app('db');

        if (!$database instanceof Database) {
            throw new RuntimeException('Database service is unavailable.');
        }

        return $database;
    }
}