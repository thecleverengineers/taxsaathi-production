<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminInquiryController extends Controller
{
    public function index(): void
    {
        require_permission('services.manage');

        $db = app('db');

        $filter = trim((string) input('filter', 'all'));
        $q = trim((string) input('q', ''));

        $allowedFilters = ['all', 'unread', 'read', 'new', 'contacted', 'converted', 'closed'];
        if (!in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        $where = [];
        $params = [];

        if ($filter === 'unread') {
            $where[] = 'is_read = :is_read';
            $params['is_read'] = 0;
        } elseif ($filter === 'read') {
            $where[] = 'is_read = :is_read';
            $params['is_read'] = 1;
        } elseif (in_array($filter, ['new', 'contacted', 'converted', 'closed'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $filter;
        }

        if ($q !== '') {
            $where[] = '(
                full_name LIKE :q
                OR email LIKE :q
                OR mobile LIKE :q
                OR state_name LIKE :q
                OR service_title LIKE :q
                OR service_slug LIKE :q
            )';
            $params['q'] = '%' . $q . '%';
        }

        $sql = 'SELECT * FROM service_applications';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';

        $inquiries = $db->fetchAll($sql, $params);

        $stats = [
            'total' => (int) $db->scalar('SELECT COUNT(*) FROM service_applications'),
            'unread' => (int) $db->scalar('SELECT COUNT(*) FROM service_applications WHERE is_read = 0'),
            'read' => (int) $db->scalar('SELECT COUNT(*) FROM service_applications WHERE is_read = 1'),
            'new' => (int) $db->scalar('SELECT COUNT(*) FROM service_applications WHERE status = "new"'),
            'contacted' => (int) $db->scalar('SELECT COUNT(*) FROM service_applications WHERE status = "contacted"'),
            'converted' => (int) $db->scalar('SELECT COUNT(*) FROM service_applications WHERE status = "converted"'),
            'closed' => (int) $db->scalar('SELECT COUNT(*) FROM service_applications WHERE status = "closed"'),
        ];

        $this->view('admin/manage_inquiry', [
            'title' => 'Manage Inquiries – Tax Saathi',
            'stats' => $stats,
            'inquiries' => $inquiries,
            'filter' => $filter,
            'q' => $q,
        ], 'layouts/dashboard');
    }

    public function show(): void
    {
        require_permission('services.manage');

        $id = (int) input('id', 0);
        if ($id <= 0) {
            flash('error', 'Invalid inquiry.');
            redirect('admin/inquiries');
        }

        $db = app('db');

        $inquiry = $db->fetch(
            "SELECT *
             FROM service_applications
             WHERE id = :id
             LIMIT 1",
            ['id' => $id]
        );

        if (!$inquiry) {
            flash('error', 'Inquiry not found.');
            redirect('admin/inquiries');
        }

        if ((int) ($inquiry['is_read'] ?? 0) !== 1) {
            $this->dbUpdateRow('service_applications', [
                'is_read' => 1,
                'updated_at' => $this->now(),
            ], ['id' => $id]);

            $inquiry['is_read'] = 1;
            $inquiry['updated_at'] = $this->now();
        }

        $this->markNotificationReadForCurrentUser('application-' . $id);

        $this->view('admin/inquiry-show', [
            'title' => 'Inquiry #' . $id . ' – Tax Saathi',
            'inquiry' => $inquiry,
        ], 'layouts/dashboard');
    }

    public function markRead(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid inquiry.');
            redirect('admin/inquiries');
        }

        $this->dbUpdateRow('service_applications', [
            'is_read' => 1,
            'updated_at' => $this->now(),
        ], ['id' => $id]);

        $this->markNotificationReadForCurrentUser('application-' . $id);

        flash('success', 'Inquiry marked as read.');
        redirect($this->backToInquiryList());
    }

    public function markUnread(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid inquiry.');
            redirect('admin/inquiries');
        }

        $this->dbUpdateRow('service_applications', [
            'is_read' => 0,
            'updated_at' => $this->now(),
        ], ['id' => $id]);

        $this->markNotificationUnreadForCurrentUser('application-' . $id);

        flash('success', 'Inquiry marked as unread.');
        redirect($this->backToInquiryList());
    }

    public function updateStatus(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);
        $status = trim((string) input('status', 'new'));

        $allowed = ['new', 'contacted', 'converted', 'closed'];
        if (!in_array($status, $allowed, true)) {
            flash('error', 'Invalid inquiry status.');
            redirect($this->backToInquiryList());
        }

        if ($id <= 0) {
            flash('error', 'Invalid inquiry.');
            redirect('admin/inquiries');
        }

        $payload = [
            'status' => $status,
            'updated_at' => $this->now(),
        ];

        if ($status !== 'new') {
            $payload['is_read'] = 1;
        }

        $this->dbUpdateRow('service_applications', $payload, ['id' => $id]);

        if ($status !== 'new') {
            $this->markNotificationReadForCurrentUser('application-' . $id);
        }

        flash('success', 'Inquiry status updated.');
        redirect($this->backToInquiryList());
    }

    public function deleteInquiry(): void
    {
        require_permission('services.manage');
        verify_csrf();

        $id = (int) input('id', 0);

        if ($id <= 0) {
            flash('error', 'Invalid inquiry.');
            redirect('admin/inquiries');
        }

        $this->dbDeleteRow('service_applications', ['id' => $id]);

        flash('success', 'Inquiry deleted.');
        redirect($this->backToInquiryList());
    }

    private function markNotificationReadForCurrentUser(string $uid): void
    {
        $user = auth_user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0 || trim($uid) === '') {
            return;
        }

        $now = $this->now();

        $sql = "
            INSERT INTO notification_user_reads
                (user_id, notification_uid, is_read, read_at, created_at, updated_at)
            VALUES
                (:user_id, :notification_uid, 1, :read_at, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE
                is_read = 1,
                read_at = VALUES(read_at),
                updated_at = VALUES(updated_at)
        ";

        $this->dbWrite($sql, [
            'user_id' => $userId,
            'notification_uid' => $uid,
            'read_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function markNotificationUnreadForCurrentUser(string $uid): void
    {
        $user = auth_user() ?? [];
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0 || trim($uid) === '') {
            return;
        }

        $now = $this->now();

        $sql = "
            INSERT INTO notification_user_reads
                (user_id, notification_uid, is_read, read_at, created_at, updated_at)
            VALUES
                (:user_id, :notification_uid, 0, NULL, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE
                is_read = 0,
                read_at = NULL,
                updated_at = VALUES(updated_at)
        ";

        $this->dbWrite($sql, [
            'user_id' => $userId,
            'notification_uid' => $uid,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function backToInquiryList(): string
    {
        $filter = trim((string) input('filter', 'all'));
        $q = trim((string) input('q', ''));

        $url = 'admin/inquiries?filter=' . urlencode($filter === '' ? 'all' : $filter);

        if ($q !== '') {
            $url .= '&q=' . urlencode($q);
        }

        return $url;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
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

        throw new \RuntimeException('Database write method not supported by App\Core\Database.');
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