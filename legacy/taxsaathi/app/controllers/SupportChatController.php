<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use RuntimeException;
use Throwable;

final class SupportChatController extends Controller
{
    private const REQUESTER_ROLES = ['client', 'partner', 'partners'];
    private const SUPPORT_ROLES = [
        'admin',
        'administrator',
        'super-admin',
        'superadmin',
        'manager',
        'telecaller',
        'tele-caller',
        'tele caller',
        'call-center',
        'call center',
    ];

    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp',
        'pdf', 'doc', 'docx',
        'xls', 'xlsx', 'csv',
        'txt',
    ];

    private const MAX_ATTACHMENT_BYTES = 8 * 1024 * 1024;

    /*
    |--------------------------------------------------------------------------
    | Client / Partner floating chat endpoints
    |--------------------------------------------------------------------------
    */

    public function poll(): void
    {
        require_auth();

        if (!$this->isRequester()) {
            $this->json(['ok' => false, 'message' => 'Support chat is available to clients and partners.'], 403);
        }

        $user = $this->currentUser();
        $conversation = $this->activeRequesterConversation((int) $user['id']);

        if (!$conversation) {
            $this->json([
                'ok' => true,
                'conversation' => null,
                'messages' => [],
                'unread_count' => 0,
            ]);
        }

        $afterId = max(0, (int) ($_GET['after_id'] ?? 0));
        $messages = $this->messagesForConversation((int) $conversation['id'], $afterId);

        $this->json([
            'ok' => true,
            'conversation' => $this->formatConversation($conversation),
            'messages' => array_map(fn (array $row): array => $this->formatMessage($row), $messages),
            'unread_count' => (int) ($conversation['requester_unread_count'] ?? 0),
        ]);
    }

    public function send(): void
    {
        require_auth();
        verify_csrf();

        if (!$this->isRequester()) {
            $this->json(['ok' => false, 'message' => 'Only clients and partners can start support chats.'], 403);
        }

        $user = $this->currentUser();
        $userId = (int) ($user['id'] ?? 0);

        if ($userId <= 0) {
            $this->json(['ok' => false, 'message' => 'Session user was not found.'], 401);
        }

        $messageText = trim((string) ($_POST['message'] ?? ''));
        $category = $this->normalizeCategory((string) ($_POST['category'] ?? 'general'));
        $priority = $this->normalizePriority((string) ($_POST['priority'] ?? 'normal'));
        $conversationId = max(0, (int) ($_POST['conversation_id'] ?? 0));

        $attachment = $this->uploadedAttachment();

        if ($messageText === '' && !$attachment) {
            $this->json(['ok' => false, 'message' => 'Please type a message or attach a file.'], 422);
        }

        if (mb_strlen($messageText) > 5000) {
            $this->json(['ok' => false, 'message' => 'Message cannot exceed 5,000 characters.'], 422);
        }

        $conversation = null;

        if ($conversationId > 0) {
            $conversation = $this->requesterConversationById($conversationId, $userId);
        }

        if (!$conversation) {
            $conversation = $this->activeRequesterConversation($userId);
        }

        if (!$conversation || strtolower((string) ($conversation['status'] ?? '')) === 'closed') {
            $conversation = $this->createConversation($user, $category, $priority);
        }

        $storedAttachment = null;

        try {
            if ($attachment) {
                $storedAttachment = $this->storeAttachment(
                    $attachment,
                    (string) $conversation['public_id']
                );
            }

            $messageId = $this->insertMessage(
                (int) $conversation['id'],
                $user,
                $messageText,
                $storedAttachment
            );

            $preview = $messageText !== ''
                ? mb_substr(preg_replace('/\s+/', ' ', $messageText) ?: $messageText, 0, 240)
                : ('Attachment: ' . (string) ($storedAttachment['original_name'] ?? 'file'));

            $this->db()->execute(
                "UPDATE support_conversations
                 SET status = :status,
                     category = :category,
                     priority = :priority,
                     last_message_at = :last_message_at,
                     last_message_preview = :preview,
                     last_message_by = 'requester',
                     requester_unread_count = 0,
                     support_unread_count = support_unread_count + 1,
                     updated_at = :updated_at,
                     resolved_at = NULL,
                     closed_at = NULL
                 WHERE id = :id",
                [
                    'status' => 'open',
                    'category' => $category,
                    'priority' => $priority,
                    'last_message_at' => $this->now(),
                    'preview' => $preview,
                    'updated_at' => $this->now(),
                    'id' => (int) $conversation['id'],
                ]
            );

            $message = $this->messageById($messageId);

            $this->json([
                'ok' => true,
                'message' => $message ? $this->formatMessage($message) : null,
                'conversation_id' => (int) $conversation['id'],
            ]);
        } catch (Throwable $e) {
            if ($storedAttachment && !empty($storedAttachment['absolute_path'])) {
                @unlink((string) $storedAttachment['absolute_path']);
            }

            $this->json([
                'ok' => false,
                'message' => 'Unable to send message: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function markRead(): void
    {
        require_auth();
        verify_csrf();

        if (!$this->isRequester()) {
            $this->json(['ok' => false, 'message' => 'Access denied.'], 403);
        }

        $user = $this->currentUser();
        $conversationId = max(0, (int) ($_POST['conversation_id'] ?? 0));

        if ($conversationId <= 0) {
            $this->json(['ok' => true]);
        }

        $conversation = $this->requesterConversationById(
            $conversationId,
            (int) $user['id']
        );

        if (!$conversation) {
            $this->json(['ok' => false, 'message' => 'Conversation not found.'], 404);
        }

        $this->db()->execute(
            "UPDATE support_conversations
             SET requester_unread_count = 0,
                 updated_at = :updated_at
             WHERE id = :id",
            [
                'updated_at' => $this->now(),
                'id' => $conversationId,
            ]
        );

        $this->json(['ok' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | Support team inbox
    |--------------------------------------------------------------------------
    */

    public function staffIndex(): void
    {
        require_auth();
        $this->requireSupport();

        $selectedId = max(0, (int) ($_GET['conversation_id'] ?? 0));
        $conversations = $this->staffConversationList();

        if ($selectedId <= 0 && $conversations !== []) {
            $selectedId = (int) ($conversations[0]['id'] ?? 0);
        }

        $selectedConversation = $selectedId > 0
            ? $this->conversationById($selectedId)
            : null;

        $messages = $selectedConversation
            ? $this->messagesForConversation((int) $selectedConversation['id'])
            : [];

        if ($selectedConversation) {
            $this->clearSupportUnread((int) $selectedConversation['id']);
            $selectedConversation['support_unread_count'] = 0;
        }

        $this->view('support/index', [
            'title' => 'Support Inbox – Tax Saathi',
            'conversations' => array_map(fn (array $row): array => $this->formatConversation($row), $conversations),
            'selectedConversation' => $selectedConversation ? $this->formatConversation($selectedConversation) : null,
            'messages' => array_map(fn (array $row): array => $this->formatMessage($row), $messages),
            'stats' => $this->supportStats(),
            'supportStaff' => $this->supportStaff(),
            'currentSupportUserId' => (int) $this->currentUser()['id'],
        ], 'layouts/dashboard');
    }

    public function staffPoll(): void
    {
        require_auth();
        $this->requireSupport();

        $selectedId = max(0, (int) ($_GET['conversation_id'] ?? 0));
        $afterId = max(0, (int) ($_GET['after_id'] ?? 0));

        $conversation = $selectedId > 0
            ? $this->conversationById($selectedId)
            : null;

        $messages = [];

        if ($conversation) {
            $this->clearSupportUnread($selectedId);
            $conversation['support_unread_count'] = 0;
            $messages = $this->messagesForConversation($selectedId, $afterId);
        }

        $this->json([
            'ok' => true,
            'conversations' => array_map(
                fn (array $row): array => $this->formatConversation($row),
                $this->staffConversationList()
            ),
            'conversation' => $conversation ? $this->formatConversation($conversation) : null,
            'messages' => array_map(fn (array $row): array => $this->formatMessage($row), $messages),
            'stats' => $this->supportStats(),
        ]);
    }

    public function staffReply(): void
    {
        require_auth();
        verify_csrf();
        $this->requireSupport();

        $supportUser = $this->currentUser();
        $conversationId = max(0, (int) ($_POST['conversation_id'] ?? 0));
        $messageText = trim((string) ($_POST['message'] ?? ''));
        $attachment = $this->uploadedAttachment();

        if ($conversationId <= 0) {
            $this->json(['ok' => false, 'message' => 'Conversation is required.'], 422);
        }

        if ($messageText === '' && !$attachment) {
            $this->json(['ok' => false, 'message' => 'Please type a reply or attach a file.'], 422);
        }

        if (mb_strlen($messageText) > 5000) {
            $this->json(['ok' => false, 'message' => 'Reply cannot exceed 5,000 characters.'], 422);
        }

        $conversation = $this->conversationById($conversationId);

        if (!$conversation) {
            $this->json(['ok' => false, 'message' => 'Conversation not found.'], 404);
        }

        $storedAttachment = null;

        try {
            if ($attachment) {
                $storedAttachment = $this->storeAttachment(
                    $attachment,
                    (string) $conversation['public_id']
                );
            }

            $messageId = $this->insertMessage(
                $conversationId,
                $supportUser,
                $messageText,
                $storedAttachment
            );

            $preview = $messageText !== ''
                ? mb_substr(preg_replace('/\s+/', ' ', $messageText) ?: $messageText, 0, 240)
                : ('Attachment: ' . (string) ($storedAttachment['original_name'] ?? 'file'));

            $status = strtolower((string) ($conversation['status'] ?? 'open'));
            if (in_array($status, ['resolved', 'closed'], true)) {
                $status = 'open';
            }

            $this->db()->execute(
                "UPDATE support_conversations
                 SET status = :status,
                     assigned_to_user_id = COALESCE(assigned_to_user_id, :assigned_to_user_id),
                     assigned_to_name = COALESCE(assigned_to_name, :assigned_to_name),
                     last_message_at = :last_message_at,
                     last_message_preview = :preview,
                     last_message_by = 'support',
                     support_unread_count = 0,
                     requester_unread_count = requester_unread_count + 1,
                     updated_at = :updated_at,
                     resolved_at = NULL,
                     closed_at = NULL
                 WHERE id = :id",
                [
                    'status' => $status,
                    'assigned_to_user_id' => (int) $supportUser['id'],
                    'assigned_to_name' => (string) $supportUser['name'],
                    'last_message_at' => $this->now(),
                    'preview' => $preview,
                    'updated_at' => $this->now(),
                    'id' => $conversationId,
                ]
            );

            $message = $this->messageById($messageId);

            $this->json([
                'ok' => true,
                'message' => $message ? $this->formatMessage($message) : null,
            ]);
        } catch (Throwable $e) {
            if ($storedAttachment && !empty($storedAttachment['absolute_path'])) {
                @unlink((string) $storedAttachment['absolute_path']);
            }

            $this->json([
                'ok' => false,
                'message' => 'Unable to send reply: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function staffUpdate(): void
    {
        require_auth();
        verify_csrf();
        $this->requireSupport();

        $conversationId = max(0, (int) ($_POST['conversation_id'] ?? 0));
        $action = strtolower(trim((string) ($_POST['action'] ?? '')));

        $conversation = $this->conversationById($conversationId);

        if (!$conversation) {
            $this->json(['ok' => false, 'message' => 'Conversation not found.'], 404);
        }

        if ($action === 'status') {
            $status = strtolower(trim((string) ($_POST['status'] ?? 'open')));

            if (!in_array($status, ['open', 'pending', 'resolved', 'closed'], true)) {
                $this->json(['ok' => false, 'message' => 'Invalid status.'], 422);
            }

            $this->db()->execute(
                "UPDATE support_conversations
                 SET status = :status,
                     resolved_at = :resolved_at,
                     closed_at = :closed_at,
                     updated_at = :updated_at
                 WHERE id = :id",
                [
                    'status' => $status,
                    'resolved_at' => $status === 'resolved' ? $this->now() : null,
                    'closed_at' => $status === 'closed' ? $this->now() : null,
                    'updated_at' => $this->now(),
                    'id' => $conversationId,
                ]
            );

            $this->insertSystemMessage(
                $conversationId,
                'Conversation status changed to ' . ucwords($status) . '.'
            );

            $this->json(['ok' => true, 'message' => 'Status updated.']);
        }

        if ($action === 'priority') {
            $priority = $this->normalizePriority((string) ($_POST['priority'] ?? 'normal'));

            $this->db()->execute(
                "UPDATE support_conversations
                 SET priority = :priority,
                     updated_at = :updated_at
                 WHERE id = :id",
                [
                    'priority' => $priority,
                    'updated_at' => $this->now(),
                    'id' => $conversationId,
                ]
            );

            $this->json(['ok' => true, 'message' => 'Priority updated.']);
        }

        if ($action === 'assign') {
            $assignedTo = trim((string) ($_POST['assigned_to'] ?? 'self'));
            $assignedUserId = null;
            $assignedName = null;

            if ($assignedTo === 'self') {
                $current = $this->currentUser();
                $assignedUserId = (int) $current['id'];
                $assignedName = (string) $current['name'];
            } elseif ($assignedTo !== '' && ctype_digit($assignedTo)) {
                $staff = $this->supportStaffById((int) $assignedTo);

                if (!$staff) {
                    $this->json(['ok' => false, 'message' => 'Support staff member not found.'], 404);
                }

                $assignedUserId = (int) $staff['id'];
                $assignedName = (string) $staff['name'];
            }

            $this->db()->execute(
                "UPDATE support_conversations
                 SET assigned_to_user_id = :assigned_to_user_id,
                     assigned_to_name = :assigned_to_name,
                     updated_at = :updated_at
                 WHERE id = :id",
                [
                    'assigned_to_user_id' => $assignedUserId,
                    'assigned_to_name' => $assignedName,
                    'updated_at' => $this->now(),
                    'id' => $conversationId,
                ]
            );

            $this->insertSystemMessage(
                $conversationId,
                $assignedName
                    ? ('Conversation assigned to ' . $assignedName . '.')
                    : 'Conversation unassigned.'
            );

            $this->json(['ok' => true, 'message' => 'Assignment updated.']);
        }

        $this->json(['ok' => false, 'message' => 'Unsupported update action.'], 422);
    }

    public function attachment(): void
    {
        require_auth();

        $messageId = max(0, (int) ($_GET['message_id'] ?? 0));
        $message = $this->messageById($messageId);

        if (!$message || empty($message['attachment_path'])) {
            http_response_code(404);
            exit('Attachment not found.');
        }

        $conversation = $this->conversationById((int) $message['conversation_id']);

        if (!$conversation) {
            http_response_code(404);
            exit('Conversation not found.');
        }

        $user = $this->currentUser();
        $canAccess = $this->isSupport()
            || (int) ($conversation['requester_user_id'] ?? 0) === (int) ($user['id'] ?? 0);

        if (!$canAccess) {
            http_response_code(403);
            exit('Access denied.');
        }

        $relativePath = ltrim((string) $message['attachment_path'], '/\\');
        $baseDir = realpath($this->uploadBaseDir());
        $absolutePath = realpath(dirname(__DIR__, 2) . '/' . $relativePath);

        if (!$baseDir || !$absolutePath || !str_starts_with($absolutePath, $baseDir) || !is_file($absolutePath)) {
            http_response_code(404);
            exit('Attachment file not found.');
        }

        $downloadName = basename((string) ($message['attachment_name'] ?? 'support-file'));
        $mimeType = trim((string) ($message['attachment_mime'] ?? 'application/octet-stream'));

        header('Content-Type: ' . ($mimeType !== '' ? $mimeType : 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
        header('X-Content-Type-Options: nosniff');

        readfile($absolutePath);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Data helpers
    |--------------------------------------------------------------------------
    */

    private function activeRequesterConversation(int $userId): ?array
    {
        $row = $this->db()->fetch(
            "SELECT *
             FROM support_conversations
             WHERE requester_user_id = :requester_user_id
               AND status <> 'closed'
             ORDER BY id DESC
             LIMIT 1",
            ['requester_user_id' => $userId]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function requesterConversationById(int $conversationId, int $userId): ?array
    {
        $row = $this->db()->fetch(
            "SELECT *
             FROM support_conversations
             WHERE id = :id
               AND requester_user_id = :requester_user_id
             LIMIT 1",
            [
                'id' => $conversationId,
                'requester_user_id' => $userId,
            ]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function conversationById(int $conversationId): ?array
    {
        $row = $this->db()->fetch(
            "SELECT *
             FROM support_conversations
             WHERE id = :id
             LIMIT 1",
            ['id' => $conversationId]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function createConversation(array $user, string $category, string $priority): array
    {
        $publicId = bin2hex(random_bytes(16));
        $now = $this->now();
        $subject = $this->categoryLabel($category);

        $this->db()->execute(
            "INSERT INTO support_conversations
            (
                public_id,
                requester_user_id,
                requester_role,
                requester_name,
                requester_email,
                requester_phone,
                subject,
                category,
                priority,
                status,
                last_message_at,
                last_message_preview,
                last_message_by,
                requester_unread_count,
                support_unread_count,
                created_at,
                updated_at
            )
            VALUES
            (
                :public_id,
                :requester_user_id,
                :requester_role,
                :requester_name,
                :requester_email,
                :requester_phone,
                :subject,
                :category,
                :priority,
                'open',
                :last_message_at,
                '',
                'requester',
                0,
                0,
                :created_at,
                :updated_at
            )",
            [
                'public_id' => $publicId,
                'requester_user_id' => (int) $user['id'],
                'requester_role' => $this->currentRoleKey(),
                'requester_name' => (string) ($user['name'] ?? 'User'),
                'requester_email' => (string) ($user['email'] ?? ''),
                'requester_phone' => (string) ($user['phone'] ?? $user['mobile'] ?? ''),
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'last_message_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $conversation = $this->db()->fetch(
            "SELECT *
             FROM support_conversations
             WHERE public_id = :public_id
             LIMIT 1",
            ['public_id' => $publicId]
        );

        if (!is_array($conversation) || $conversation === []) {
            throw new RuntimeException('Unable to create support conversation.');
        }

        return $conversation;
    }

    private function insertMessage(
        int $conversationId,
        array $sender,
        string $messageText,
        ?array $attachment = null
    ): int {
        $messageType = $attachment ? 'file' : 'text';
        $createdAt = $this->now();

        $this->db()->execute(
            "INSERT INTO support_messages
            (
                conversation_id,
                sender_user_id,
                sender_role,
                sender_name,
                message,
                message_type,
                attachment_name,
                attachment_path,
                attachment_mime,
                attachment_size,
                created_at
            )
            VALUES
            (
                :conversation_id,
                :sender_user_id,
                :sender_role,
                :sender_name,
                :message,
                :message_type,
                :attachment_name,
                :attachment_path,
                :attachment_mime,
                :attachment_size,
                :created_at
            )",
            [
                'conversation_id' => $conversationId,
                'sender_user_id' => (int) ($sender['id'] ?? 0),
                'sender_role' => $this->currentRoleKey(),
                'sender_name' => (string) ($sender['name'] ?? 'User'),
                'message' => $messageText !== '' ? $messageText : null,
                'message_type' => $messageType,
                'attachment_name' => $attachment['original_name'] ?? null,
                'attachment_path' => $attachment['relative_path'] ?? null,
                'attachment_mime' => $attachment['mime_type'] ?? null,
                'attachment_size' => $attachment['size_bytes'] ?? null,
                'created_at' => $createdAt,
            ]
        );

        $row = $this->db()->fetch(
            "SELECT id
             FROM support_messages
             WHERE conversation_id = :conversation_id
               AND sender_user_id = :sender_user_id
               AND created_at = :created_at
             ORDER BY id DESC
             LIMIT 1",
            [
                'conversation_id' => $conversationId,
                'sender_user_id' => (int) ($sender['id'] ?? 0),
                'created_at' => $createdAt,
            ]
        );

        return (int) ($row['id'] ?? 0);
    }

    private function insertSystemMessage(int $conversationId, string $message): void
    {
        $current = $this->currentUser();

        $this->db()->execute(
            "INSERT INTO support_messages
            (
                conversation_id,
                sender_user_id,
                sender_role,
                sender_name,
                message,
                message_type,
                created_at
            )
            VALUES
            (
                :conversation_id,
                :sender_user_id,
                :sender_role,
                :sender_name,
                :message,
                'system',
                :created_at
            )",
            [
                'conversation_id' => $conversationId,
                'sender_user_id' => (int) ($current['id'] ?? 0),
                'sender_role' => $this->currentRoleKey(),
                'sender_name' => (string) ($current['name'] ?? 'Support'),
                'message' => $message,
                'created_at' => $this->now(),
            ]
        );
    }

    private function messageById(int $messageId): ?array
    {
        if ($messageId <= 0) {
            return null;
        }

        $row = $this->db()->fetch(
            "SELECT *
             FROM support_messages
             WHERE id = :id
             LIMIT 1",
            ['id' => $messageId]
        );

        return is_array($row) && $row !== [] ? $row : null;
    }

    private function messagesForConversation(int $conversationId, int $afterId = 0): array
    {
        return $this->db()->fetchAll(
            "SELECT *
             FROM support_messages
             WHERE conversation_id = :conversation_id
               AND id > :after_id
             ORDER BY id ASC
             LIMIT 250",
            [
                'conversation_id' => $conversationId,
                'after_id' => $afterId,
            ]
        ) ?: [];
    }

    private function staffConversationList(): array
    {
        return $this->db()->fetchAll(
            "SELECT *
             FROM support_conversations
             ORDER BY
                CASE priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'normal' THEN 3
                    ELSE 4
                END,
                CASE status
                    WHEN 'open' THEN 1
                    WHEN 'pending' THEN 2
                    WHEN 'resolved' THEN 3
                    ELSE 4
                END,
                last_message_at DESC,
                id DESC
             LIMIT 300"
        ) ?: [];
    }

    private function clearSupportUnread(int $conversationId): void
    {
        $this->db()->execute(
            "UPDATE support_conversations
             SET support_unread_count = 0,
                 updated_at = :updated_at
             WHERE id = :id",
            [
                'updated_at' => $this->now(),
                'id' => $conversationId,
            ]
        );
    }

    private function supportStats(): array
    {
        $rows = $this->db()->fetchAll(
            "SELECT status, COUNT(*) AS total
             FROM support_conversations
             GROUP BY status"
        ) ?: [];

        $stats = [
            'all' => 0,
            'open' => 0,
            'pending' => 0,
            'resolved' => 0,
            'closed' => 0,
            'unread' => 0,
        ];

        foreach ($rows as $row) {
            $status = strtolower((string) ($row['status'] ?? ''));
            $total = (int) ($row['total'] ?? 0);

            $stats['all'] += $total;

            if (array_key_exists($status, $stats)) {
                $stats[$status] = $total;
            }
        }

        $unread = $this->db()->fetch(
            "SELECT COALESCE(SUM(support_unread_count), 0) AS total
             FROM support_conversations"
        );

        $stats['unread'] = (int) ($unread['total'] ?? 0);

        return $stats;
    }

    private function supportStaff(): array
    {
        try {
            return $this->db()->fetchAll(
                "SELECT
                    u.id,
                    COALESCE(NULLIF(u.name, ''), NULLIF(u.email, ''), CONCAT('User #', u.id)) AS name,
                    LOWER(COALESCE(NULLIF(r.slug, ''), NULLIF(r.name, ''), '')) AS role_key
                 FROM users u
                 LEFT JOIN roles r ON r.id = u.role_id
                 WHERE LOWER(COALESCE(NULLIF(r.slug, ''), NULLIF(r.name, ''))) IN
                    ('admin', 'administrator', 'super-admin', 'superadmin', 'manager', 'telecaller', 'tele-caller', 'tele caller')
                 ORDER BY u.name ASC, u.id ASC"
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function supportStaffById(int $userId): ?array
    {
        foreach ($this->supportStaff() as $staff) {
            if ((int) ($staff['id'] ?? 0) === $userId) {
                return $staff;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Upload helpers
    |--------------------------------------------------------------------------
    */

    private function uploadedAttachment(): ?array
    {
        if (
            !isset($_FILES['attachment'])
            || !is_array($_FILES['attachment'])
            || (int) ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            return null;
        }

        return $_FILES['attachment'];
    }

    private function storeAttachment(array $file, string $conversationPublicId): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Attachment upload failed.');
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > self::MAX_ATTACHMENT_BYTES) {
            throw new RuntimeException('Attachment must be smaller than 8 MB.');
        }

        $originalName = basename((string) ($file['name'] ?? 'attachment'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('Unsupported attachment type.');
        }

        $publicId = preg_replace('/[^a-zA-Z0-9_-]/', '', $conversationPublicId) ?: 'conversation';
        $dir = $this->uploadBaseDir() . '/' . $publicId;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create support upload directory.');
        }

        $storedName = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $absolutePath = $dir . '/' . $storedName;

        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $absolutePath)) {
            throw new RuntimeException('Unable to save attachment.');
        }

        $mimeType = (string) ($file['type'] ?? 'application/octet-stream');

        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($absolutePath);
            if (is_string($detected) && $detected !== '') {
                $mimeType = $detected;
            }
        }

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'absolute_path' => $absolutePath,
            'relative_path' => 'storage/uploads/support-chat/' . $publicId . '/' . $storedName,
        ];
    }

    private function uploadBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/uploads/support-chat';
    }

    /*
    |--------------------------------------------------------------------------
    | Auth and formatting helpers
    |--------------------------------------------------------------------------
    */

    private function requireSupport(): void
    {
        if (!$this->isSupport()) {
            http_response_code(403);
            exit('Support team access required.');
        }
    }

    private function isRequester(): bool
    {
        return in_array($this->currentRoleKey(), self::REQUESTER_ROLES, true);
    }

    private function isSupport(): bool
    {
        return in_array($this->currentRoleKey(), self::SUPPORT_ROLES, true);
    }

    private function currentUser(): array
    {
        $user = [];

        if (function_exists('auth_user')) {
            $candidate = auth_user();

            if (is_array($candidate)) {
                $user = $candidate;
            }
        }

        if ($user === [] && is_array($_SESSION['auth_user'] ?? null)) {
            $user = $_SESSION['auth_user'];
        }

        if ($user === [] && is_array($_SESSION['user'] ?? null)) {
            $user = $_SESSION['user'];
        }

        $userId = (int) (
            $user['id']
            ?? $_SESSION['user_id']
            ?? $_SESSION['auth_user']['id']
            ?? $_SESSION['user']['id']
            ?? 0
        );

        if ($userId > 0 && (
            empty($user['name'])
            || empty($user['email'])
            || (
                empty($user['role'])
                && empty($user['role_name'])
                && empty($user['role_slug'])
                && empty($user['role_slugs'])
            )
        )) {
            try {
                $dbUser = $this->db()->fetch(
                    "SELECT
                        u.*
                     FROM users u
                     WHERE u.id = :id
                     LIMIT 1",
                    ['id' => $userId]
                );

                if (is_array($dbUser)) {
                    $user = array_merge($dbUser, $user);
                }
            } catch (Throwable $e) {
            }
        }

        $user['id'] = $userId;
        $user['name'] = trim((string) (
            $user['name']
            ?? $user['full_name']
            ?? $user['email']
            ?? ('User #' . $userId)
        ));
        $user['email'] = trim((string) ($user['email'] ?? ''));
        $user['phone'] = trim((string) ($user['phone'] ?? $user['mobile'] ?? ''));

        return $user;
    }

    private function currentRoleKey(): string
    {
        $user = $this->currentUser();
        $roleKeys = [];

        foreach (array_merge(
            is_array($user['role_slugs'] ?? null) ? $user['role_slugs'] : [],
            is_array($user['role_names'] ?? null) ? $user['role_names'] : []
        ) as $raw) {
            $roleKey = strtolower(trim((string) $raw));
            $roleKey = str_replace(['_', ' '], '-', $roleKey);
            $roleKey = preg_replace('/-+/', '-', $roleKey) ?: $roleKey;
            if ($roleKey !== '') {
                $roleKeys[$roleKey] = $roleKey;
            }
        }

        $userId = (int) ($user['id'] ?? $_SESSION['user_id'] ?? $_SESSION['auth_user']['id'] ?? $_SESSION['user']['id'] ?? 0);
        if ($userId > 0 && $roleKeys === []) {
            try {
                $rows = $this->db()->fetchAll(
                    'SELECT r.slug, r.name
                     FROM user_roles ur
                     INNER JOIN roles r ON r.id = ur.role_id
                     WHERE ur.user_id = :user_id
                     ORDER BY ur.role_id ASC',
                    ['user_id' => $userId]
                ) ?: [];

                foreach ($rows as $row) {
                    foreach ([$row['slug'] ?? null, $row['name'] ?? null] as $raw) {
                        $roleKey = strtolower(trim((string) $raw));
                        $roleKey = str_replace(['_', ' '], '-', $roleKey);
                        $roleKey = preg_replace('/-+/', '-', $roleKey) ?: $roleKey;
                        if ($roleKey !== '') {
                            $roleKeys[$roleKey] = $roleKey;
                        }
                    }
                }
            } catch (Throwable $e) {
            }
        }

        foreach (self::SUPPORT_ROLES as $role) {
            if (isset($roleKeys[$role])) {
                return $role;
            }
        }

        foreach (self::REQUESTER_ROLES as $role) {
            if (isset($roleKeys[$role])) {
                return $role;
            }
        }

        return '';
    }

    private function normalizeCategory(string $category): string
    {
        $category = strtolower(trim($category));

        return in_array($category, ['general', 'order', 'payment', 'documents', 'technical'], true)
            ? $category
            : 'general';
    }

    private function normalizePriority(string $priority): string
    {
        $priority = strtolower(trim($priority));

        return in_array($priority, ['low', 'normal', 'high', 'urgent'], true)
            ? $priority
            : 'normal';
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'order' => 'Order Support',
            'payment' => 'Payment Support',
            'documents' => 'Document Support',
            'technical' => 'Technical Support',
            default => 'General Support',
        };
    }

    private function formatConversation(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'public_id' => (string) ($row['public_id'] ?? ''),
            'requester_user_id' => (int) ($row['requester_user_id'] ?? 0),
            'requester_role' => (string) ($row['requester_role'] ?? ''),
            'requester_name' => (string) ($row['requester_name'] ?? 'User'),
            'requester_email' => (string) ($row['requester_email'] ?? ''),
            'requester_phone' => (string) ($row['requester_phone'] ?? ''),
            'subject' => (string) ($row['subject'] ?? 'Support'),
            'category' => (string) ($row['category'] ?? 'general'),
            'priority' => (string) ($row['priority'] ?? 'normal'),
            'status' => (string) ($row['status'] ?? 'open'),
            'assigned_to_user_id' => isset($row['assigned_to_user_id']) ? (int) $row['assigned_to_user_id'] : null,
            'assigned_to_name' => (string) ($row['assigned_to_name'] ?? ''),
            'last_message_at' => (string) ($row['last_message_at'] ?? ''),
            'last_message_preview' => (string) ($row['last_message_preview'] ?? ''),
            'last_message_by' => (string) ($row['last_message_by'] ?? ''),
            'requester_unread_count' => (int) ($row['requester_unread_count'] ?? 0),
            'support_unread_count' => (int) ($row['support_unread_count'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function formatMessage(array $row): array
    {
        $role = strtolower((string) ($row['sender_role'] ?? ''));
        $isSupportSender = in_array($role, self::SUPPORT_ROLES, true);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'conversation_id' => (int) ($row['conversation_id'] ?? 0),
            'sender_user_id' => (int) ($row['sender_user_id'] ?? 0),
            'sender_role' => $role,
            'sender_name' => (string) ($row['sender_name'] ?? 'User'),
            'sender_side' => $isSupportSender ? 'support' : 'requester',
            'message' => (string) ($row['message'] ?? ''),
            'message_type' => (string) ($row['message_type'] ?? 'text'),
            'attachment_name' => (string) ($row['attachment_name'] ?? ''),
            'attachment_mime' => (string) ($row['attachment_mime'] ?? ''),
            'attachment_size' => (int) ($row['attachment_size'] ?? 0),
            'attachment_url' => !empty($row['attachment_path'])
                ? base_url('support/chat/attachment?message_id=' . (int) ($row['id'] ?? 0))
                : '',
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function db(): object
    {
        $db = app('db');

        if (!is_object($db)) {
            throw new RuntimeException('Database connection not available.');
        }

        return $db;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        exit;
    }
}
