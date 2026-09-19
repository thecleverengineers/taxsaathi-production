<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;

final class PartnerProfileController extends Controller
{
    private ?Database $database = null;

    private function db(): Database
    {
        if (!$this->database instanceof Database) {
            $this->database = new Database($this->databaseConfig());
        }
        return $this->database;
    }

    private function databaseConfig(): array
    {
        $config = [];
        if (function_exists('config')) {
            foreach (['db', 'database'] as $key) {
                try {
                    $raw = config($key);
                    if (is_array($raw) && $raw !== []) {
                        $config = $this->normalizeDatabaseConfig($raw);
                        break;
                    }
                } catch (\Throwable $e) {}
            }
        }
        foreach ([
            dirname(__DIR__) . '/config/config.php',
            dirname(__DIR__) . '/config/database.php',
            dirname(__DIR__, 2) . '/app/config/config.php',
            dirname(__DIR__, 2) . '/app/config/database.php',
        ] as $file) {
            if ($config !== [] || !is_file($file)) continue;
            try {
                $raw = require $file;
                if (is_array($raw)) {
                    if (isset($raw['db']) && is_array($raw['db'])) $config = $this->normalizeDatabaseConfig($raw['db']);
                    elseif (isset($raw['database']) && is_array($raw['database'])) $config = $this->normalizeDatabaseConfig($raw['database']);
                    else $config = $this->normalizeDatabaseConfig($raw);
                }
            } catch (\Throwable $e) {}
        }
        if (($config['database'] ?? '') === '') {
            throw new RuntimeException('Database configuration is missing the database name.');
        }
        return $config;
    }

    private function normalizeDatabaseConfig(array $config): array
    {
        if (isset($config['connections']) && is_array($config['connections'])) {
            $default = (string)($config['default'] ?? array_key_first($config['connections']));
            if (!empty($config['connections'][$default]) && is_array($config['connections'][$default])) $config = $config['connections'][$default];
        }
        return [
            'driver' => (string)($config['driver'] ?? $config['type'] ?? 'mysql'),
            'host' => (string)($config['host'] ?? $config['hostname'] ?? '127.0.0.1'),
            'port' => (int)($config['port'] ?? 3306),
            'database' => (string)($config['database'] ?? $config['dbname'] ?? $config['name'] ?? ''),
            'charset' => (string)($config['charset'] ?? 'utf8mb4'),
            'username' => (string)($config['username'] ?? $config['user'] ?? ''),
            'password' => (string)($config['password'] ?? $config['pass'] ?? ''),
        ];
    }

    private function currentUser(): array
    {
        return function_exists('auth_user') ? (auth_user() ?: []) : ($_SESSION['user'] ?? $_SESSION['auth_user'] ?? []);
    }

    private function currentUserId(): int
    {
        $user = $this->currentUser();
        return (int)($user['id'] ?? $_SESSION['user_id'] ?? $_SESSION['auth_user']['id'] ?? $_SESSION['user']['id'] ?? 0);
    }

    private function requirePartner(): void
    {
        $userId = $this->currentUserId();
        $role = $userId > 0
            ? $this->db()->fetch(
                'SELECT ur.user_id
                 FROM user_roles ur
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = :user_id
                   AND ur.role_id = 4
                   AND LOWER(COALESCE(r.slug, "")) IN ("partner", "partners")
                 LIMIT 1',
                ['user_id' => $userId]
            )
            : null;

        if (is_array($role) && (int) ($role['user_id'] ?? 0) === $userId) return;
        flash('error', 'Partner access required.');
        redirect('auth');
    }

    private function partnerId(): int
    {
        $id = $this->currentUserId();
        if ($id <= 0) { flash('error', 'Please login again.'); redirect('auth'); }
        return $id;
    }

    private function userRecord(int $id): array
    {
        $row = $this->db()->fetch(
            'SELECT u.id, u.name, u.email, u.phone, COALESCE(u.is_active, 1) AS is_active, u.created_at, u.updated_at
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.role_id = 4
             WHERE u.id = :id
             LIMIT 1',
            ['id' => $id]
        );
        return is_array($row) ? $row : [];
    }

    private function profileRecord(int $id): array
    {
        $row = $this->db()->fetch('SELECT * FROM partner_profiles WHERE partner_id=:partner_id LIMIT 1', ['partner_id'=>$id]);
        return is_array($row) ? $row : [];
    }

    public function index(): void
    {
        $this->requirePartner();
        $id = $this->partnerId();
        $this->view('partner/profile', [
            'title' => 'My Partner Profile – Tax Saathi',
            'user' => $this->userRecord($id),
            'profile' => $this->profileRecord($id),
        ], 'layouts/partner');
    }

    public function show(): void { $this->index(); }

    public function update(): void
    {
        $this->requirePartner();
        verify_csrf();
        $id = $this->partnerId();
        $name = trim((string)input('name'));
        $email = trim((string)input('email'));
        $phone = trim((string)input('phone'));
        if ($name === '') { flash('error','Name is required.'); redirect('partner/profile'); }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { flash('error','Invalid email address.'); redirect('partner/profile'); }

        $this->db()->execute('UPDATE users SET name=:name,email=:email,phone=:phone,updated_at=:updated_at WHERE id=:id', [
            'name'=>$name, 'email'=>$email, 'phone'=>$phone, 'updated_at'=>date('Y-m-d H:i:s'), 'id'=>$id
        ]);

        $existing = $this->profileRecord($id);
        $logo = $this->uploadLogo($id, (string)($existing['logo_path'] ?? ''));
        $data = [
            'partner_id'=>$id,
            'firm_name'=>trim((string)input('firm_name')),
            'legal_name'=>trim((string)input('legal_name')),
            'business_type'=>trim((string)input('business_type')),
            'gst_number'=>strtoupper(trim((string)input('gst_number'))),
            'pan_number'=>strtoupper(trim((string)input('pan_number'))),
            'address'=>trim((string)input('address')),
            'city'=>trim((string)input('city')),
            'state_name'=>trim((string)input('state_name')),
            'pincode'=>trim((string)input('pincode')),
            'website'=>trim((string)input('website')),
            'upi_id'=>trim((string)input('upi_id')),
            'bank_name'=>trim((string)input('bank_name')),
            'account_holder_name'=>trim((string)input('account_holder_name')),
            'account_number'=>trim((string)input('account_number')),
            'ifsc_code'=>strtoupper(trim((string)input('ifsc_code'))),
            'logo_path'=>$logo,
            'updated_at'=>date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            $this->db()->execute('UPDATE partner_profiles SET firm_name=:firm_name,legal_name=:legal_name,business_type=:business_type,gst_number=:gst_number,pan_number=:pan_number,address=:address,city=:city,state_name=:state_name,pincode=:pincode,website=:website,upi_id=:upi_id,bank_name=:bank_name,account_holder_name=:account_holder_name,account_number=:account_number,ifsc_code=:ifsc_code,logo_path=:logo_path,updated_at=:updated_at WHERE partner_id=:partner_id', $data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db()->execute('INSERT INTO partner_profiles (partner_id,firm_name,legal_name,business_type,gst_number,pan_number,address,city,state_name,pincode,website,upi_id,bank_name,account_holder_name,account_number,ifsc_code,logo_path,created_at,updated_at) VALUES (:partner_id,:firm_name,:legal_name,:business_type,:gst_number,:pan_number,:address,:city,:state_name,:pincode,:website,:upi_id,:bank_name,:account_holder_name,:account_number,:ifsc_code,:logo_path,:created_at,:updated_at)', $data);
        }
        foreach (['user','auth_user'] as $k) if (isset($_SESSION[$k]) && is_array($_SESSION[$k])) { $_SESSION[$k]['name']=$name; $_SESSION[$k]['email']=$email; $_SESSION[$k]['phone']=$phone; }
        flash('success','Partner profile updated successfully.');
        redirect('partner/profile');
    }

    private function uploadLogo(int $id, string $current): string
    {
        if (empty($_FILES['logo']) || !is_array($_FILES['logo'])) return $current;
        $file = $_FILES['logo'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return $current;
        $original = basename((string)($file['name'] ?? 'logo'));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) { flash('error','Logo must be JPG, PNG or WEBP.'); redirect('partner/profile'); }
        if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) { flash('error','Logo must be below 2MB.'); redirect('partner/profile'); }
        $dir = dirname(__DIR__, 2) . '/storage/uploads/partner-profiles/' . $id;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $name = 'logo-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $name)) { flash('error','Unable to upload logo. Check storage permission.'); redirect('partner/profile'); }
        return 'storage/uploads/partner-profiles/' . $id . '/' . $name;
    }
}
