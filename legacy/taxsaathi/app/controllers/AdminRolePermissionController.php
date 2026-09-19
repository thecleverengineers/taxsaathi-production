<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Role;
use Throwable;

final class AdminRolePermissionController extends Controller
{
    private const REDIRECT_TO = 'admin/role-permissions';

    public function index(): void
    {
        $this->guard();

        $this->view('admin/role-permissions/index', [
            'title' => 'Manage Role Permissions – Tax Saathi',
            'roles' => Role::allOrdered(),
        ], 'layouts/dashboard');
    }

    public function create(): void
    {
        $this->guard();

        $this->view('admin/role-permissions/form', [
            'title' => 'Create Role Permission – Tax Saathi',
            'role' => null,
            'selectedPermissions' => [],
        ], 'layouts/dashboard');
    }

    public function edit(): void
    {
        $this->guard();

        $id = (int) input('id', 0);
        $role = Role::find($id);

        if (!$role) {
            flash('error', 'Role not found.');
            redirect(self::REDIRECT_TO);
        }

        $selected = json_decode((string) ($role['permissions_json'] ?? '[]'), true);
        if (!is_array($selected)) {
            $selected = [];
        }

        $this->view('admin/role-permissions/form', [
            'title' => 'Edit Role Permission – Tax Saathi',
            'role' => $role,
            'selectedPermissions' => array_values(array_map('strval', $selected)),
        ], 'layouts/dashboard');
    }

    public function store(): void
    {
        $this->guard();
        verify_csrf();

        $payload = $this->rolePayload();
        $payload['created_at'] = date('Y-m-d H:i:s');

        try {
            Role::insert($payload);
            flash('success', 'Role permission created successfully.');
        } catch (Throwable $e) {
            flash('error', 'Unable to create role. Please make sure the role slug is unique.');
        }

        redirect(self::REDIRECT_TO);
    }

    public function update(): void
    {
        $this->guard();
        verify_csrf();

        $id = (int) input('id', 0);
        $role = Role::find($id);

        if (!$role) {
            flash('error', 'Role not found.');
            redirect(self::REDIRECT_TO);
        }

        $payload = $this->rolePayload();

        // Keep system role identity stable. Permissions can still be changed.
        if ((int) ($role['is_system'] ?? 0) === 1) {
            $payload['name'] = (string) ($role['name'] ?? $payload['name']);
            $payload['slug'] = (string) ($role['slug'] ?? $payload['slug']);
            $payload['is_system'] = 1;
        }

        try {
            Role::update($id, $payload);
            flash('success', 'Role permission updated successfully.');
        } catch (Throwable $e) {
            flash('error', 'Unable to update role. Please make sure the role slug is unique.');
        }

        redirect(self::REDIRECT_TO);
    }

    public function delete(): void
    {
        $this->guard();
        verify_csrf();

        $id = (int) input('id', 0);
        $role = Role::find($id);

        if (!$role) {
            flash('error', 'Role not found.');
            redirect(self::REDIRECT_TO);
        }

        if ((int) ($role['is_system'] ?? 0) === 1) {
            flash('error', 'System roles cannot be deleted.');
            redirect(self::REDIRECT_TO);
        }

        try {
            Role::delete($id);
            flash('success', 'Role deleted successfully.');
        } catch (Throwable $e) {
            flash('error', 'This role cannot be deleted because it is assigned to one or more users.');
        }

        redirect(self::REDIRECT_TO);
    }

    private function rolePayload(): array
    {
        $name = trim((string) input('name'));
        $slug = $this->cleanSlug((string) input('slug'));

        if ($name === '' || $slug === '') {
            flash('error', 'Role name and slug are required.');
            redirect(self::REDIRECT_TO);
        }

        $permissions = $this->cleanPermissions($_POST['permissions'] ?? []);

        return [
            'name' => $name,
            'slug' => $slug,
            'permissions_json' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
            'is_system' => (int) input('is_system', 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function cleanSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
        return trim($slug, '-');
    }

    private function cleanPermissions(mixed $permissions): array
    {
        if (!is_array($permissions)) {
            return [];
        }

        $allowed = [];
        foreach (permissions_catalog() as $items) {
            foreach ((array) $items as $item) {
                $allowed[] = (string) $item;
            }
        }

        $allowed = array_unique($allowed);
        $selected = array_map(static fn ($value): string => trim((string) $value), $permissions);
        $selected = array_filter($selected, static fn (string $value): bool => $value !== '');

        return array_values(array_intersect(array_unique($selected), $allowed));
    }

    private function guard(): void
    {
        // Uses the existing permission already protecting your old combined Staff & Roles page.
        // After adding roles.manage to your catalog/admin role, you may change this to require_permission('roles.manage').
        require_permission('staff.manage');
    }
}
