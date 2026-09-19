<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Role;
use App\Models\User;
use Throwable;

final class AdminStaffController extends Controller
{
    private const REDIRECT_TO = 'admin/staff';

    public function index(): void
    {
        $this->guard();

        $this->view('admin/staff/index', [
            'title' => 'Manage Staff – Tax Saathi',
            'users' => User::allWithRoles(),
            'roles' => $this->staffRoles(),
        ], 'layouts/dashboard');
    }

    public function create(): void
    {
        $this->guard();

        $this->view('admin/staff/form', [
            'title' => 'Create Staff – Tax Saathi',
            'user' => null,
            'roles' => $this->staffRoles(),
        ], 'layouts/dashboard');
    }

    public function edit(): void
    {
        $this->guard();

        $id = (int) input('id', 0);
        $user = User::find($id);

        if (!$user) {
            flash('error', 'Staff user not found.');
            redirect(self::REDIRECT_TO);
        }

        $this->view('admin/staff/form', [
            'title' => 'Edit Staff – Tax Saathi',
            'user' => $user,
            'roles' => $this->staffRoles(),
        ], 'layouts/dashboard');
    }

    public function store(): void
    {
        $this->guard();
        verify_csrf();

        $payload = $this->staffPayload();
        $payload['password_hash'] = null;
        $payload['last_login_at'] = null;
        $payload['created_at'] = date('Y-m-d H:i:s');

        try {
            User::insert($payload);
            flash('success', 'Staff user created successfully.');
        } catch (Throwable $e) {
            flash('error', 'Unable to create staff user. Please check duplicate phone/email.');
        }

        redirect(self::REDIRECT_TO);
    }

    public function update(): void
    {
        $this->guard();
        verify_csrf();

        $id = (int) input('id', 0);
        $user = User::find($id);

        if (!$user) {
            flash('error', 'Staff user not found.');
            redirect(self::REDIRECT_TO);
        }

        $payload = $this->staffPayload();

        try {
            User::update($id, $payload);
            flash('success', 'Staff user updated successfully.');
        } catch (Throwable $e) {
            flash('error', 'Unable to update staff user. Please check duplicate phone/email.');
        }

        redirect(self::REDIRECT_TO);
    }

    public function toggle(): void
    {
        $this->guard();
        verify_csrf();

        $id = (int) input('id', 0);
        $user = User::find($id);

        if (!$user) {
            flash('error', 'Staff user not found.');
            redirect(self::REDIRECT_TO);
        }

        if ($this->currentUserId() === $id) {
            flash('error', 'You cannot disable your own account.');
            redirect(self::REDIRECT_TO);
        }

        User::update($id, [
            'is_active' => (int) ($user['is_active'] ?? 0) === 1 ? 0 : 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        flash('success', 'Staff status updated successfully.');
        redirect(self::REDIRECT_TO);
    }

    public function delete(): void
    {
        $this->guard();
        verify_csrf();

        $id = (int) input('id', 0);
        $user = User::find($id);

        if (!$user) {
            flash('error', 'Staff user not found.');
            redirect(self::REDIRECT_TO);
        }

        if ($this->currentUserId() === $id) {
            flash('error', 'You cannot delete your own account.');
            redirect(self::REDIRECT_TO);
        }

        try {
            User::delete($id);
            flash('success', 'Staff user deleted successfully.');
        } catch (Throwable $e) {
            flash('error', 'Unable to delete this staff user because related records exist. Disable the user instead.');
        }

        redirect(self::REDIRECT_TO);
    }

    private function staffPayload(): array
    {
        $name = trim((string) input('name'));
        $phone = normalize_phone(trim((string) input('phone')));
        $email = trim((string) input('email'));
        $roleId = (int) input('role_id', 0);

        if ($name === '' || $phone === '' || $roleId <= 0) {
            flash('error', 'Name, phone and role are required.');
            redirect(self::REDIRECT_TO);
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect(self::REDIRECT_TO);
        }

        if (!$this->isAllowedStaffRole($roleId)) {
            flash('error', 'Please select a valid staff role.');
            redirect(self::REDIRECT_TO);
        }

        return [
            'role_id' => $roleId,
            'client_id' => null,
            'name' => $name,
            'phone' => $phone,
            'email' => $email !== '' ? $email : null,
            'is_phone_verified' => isset($_POST['is_phone_verified']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function staffRoles(): array
    {
        $roles = Role::allOrdered();

        return array_values(array_filter($roles, static function (array $role): bool {
            return strtolower((string) ($role['slug'] ?? '')) !== 'client';
        }));
    }

    private function isAllowedStaffRole(int $roleId): bool
    {
        foreach ($this->staffRoles() as $role) {
            if ((int) ($role['id'] ?? 0) === $roleId) {
                return true;
            }
        }

        return false;
    }

    private function currentUserId(): int
    {
        return (int) (
            $_SESSION['user']['id']
            ?? $_SESSION['auth_user']['id']
            ?? $_SESSION['user_id']
            ?? 0
        );
    }

    private function guard(): void
    {
        require_permission('staff.manage');
    }
}
