<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use DomainException;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__) . '/helpers/user_roles_rbac_helper.php';

/**
 * Executive payout ledger for Administrators and Managers.
 *
 * A payout is always attached to one completed order and one assigned
 * Executive user (user_roles.role_id = 3). The unique order_id constraint in
 * executive_order_payouts is the final one-time-payment safeguard.
 */
final class AdminExecutivePayoutController extends Controller
{
    private const EXECUTIVE_ROLE_ID = 3;
    private const ADMIN_ROLE_ID = 1;
    private const MANAGER_ROLE_ID = 2;
    private const PAYMENT_METHODS = ['bank_transfer', 'upi', 'cash'];

    private function db(): Database
    {
        $db = app('db');

        if (!$db instanceof Database) {
            throw new RuntimeException('Database connection is unavailable.');
        }

        return $db;
    }

    private function currentUserId(): int
    {
        return rbac_current_user_id();
    }

    private function hasRole(int $roleId): bool
    {
        $context = rbac_context($this->currentUserId(), true);
        $roleIds = is_array($context['role_ids'] ?? null) ? $context['role_ids'] : [];

        return in_array($roleId, array_map('intval', $roleIds), true);
    }

    private function requireViewAccess(): void
    {
        require_auth();

        $userId = $this->currentUserId();

        if ($userId <= 0
            || (!$this->hasRole(self::ADMIN_ROLE_ID)
                && !$this->hasRole(self::MANAGER_ROLE_ID))
            || !rbac_can('executive_payouts.view', $userId)
        ) {
            http_response_code(403);
            exit('Administrator or Manager access required. Please check user_roles and executive payout permissions.');
        }
    }

    private function requirePayAccess(): void
    {
        $this->requireViewAccess();

        $userId = $this->currentUserId();

        if (!$this->hasRole(self::ADMIN_ROLE_ID)
            || !rbac_can('executive_payouts.pay', $userId)
        ) {
            http_response_code(403);
            exit('Only an Administrator can record Executive payouts.');
        }
    }

    private function payoutTableReady(): bool
    {
        return rbac_table_exists('executive_order_payouts');
    }

    private function paymentProfileTableReady(): bool
    {
        return rbac_table_exists('executive_payment_profiles');
    }

    private function executiveUsers(): array
    {
        $profileJoin = $this->paymentProfileTableReady()
            ? 'LEFT JOIN executive_payment_profiles epp
                    ON epp.executive_user_id = u.id'
            : '';
        $profileSelect = $this->paymentProfileTableReady()
            ? ', COALESCE(epp.payment_details_confirmed, 0) AS payment_details_confirmed,
                    epp.preferred_method'
            : ", 0 AS payment_details_confirmed, 'bank_transfer' AS preferred_method";

        return $this->db()->fetchAll(
            'SELECT
                u.id,
                COALESCE(NULLIF(u.name, \'\'), CONCAT(\'Executive #\', u.id)) AS name,
                u.email,
                u.phone' . $profileSelect . '
             FROM users u
             INNER JOIN user_roles ur
                ON ur.user_id = u.id
               AND ur.role_id = :executive_role_id
             ' . $profileJoin . '
             GROUP BY u.id, u.name, u.email, u.phone'
                . ($this->paymentProfileTableReady()
                    ? ', epp.payment_details_confirmed, epp.preferred_method'
                    : '') . '
             ORDER BY u.name ASC, u.id ASC',
            ['executive_role_id' => self::EXECUTIVE_ROLE_ID]
        ) ?: [];
    }

    private function paymentProfile(int $executiveId): ?array
    {
        if (!$this->paymentProfileTableReady() || $executiveId <= 0) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT
                epp.*,
                COALESCE(NULLIF(u.name, \'\'), CONCAT(\'Executive #\', u.id)) AS executive_name
             FROM executive_payment_profiles epp
             INNER JOIN users u ON u.id = epp.executive_user_id
             INNER JOIN user_roles ur
                ON ur.user_id = u.id
               AND ur.role_id = :executive_role_id
             WHERE epp.executive_user_id = :executive_user_id
             LIMIT 1',
            [
                'executive_role_id' => self::EXECUTIVE_ROLE_ID,
                'executive_user_id' => $executiveId,
            ]
        );
    }

    /**
     * Return completed orders whose current assigned user has the Executive
     * role in user_roles. Existing payout rows are joined for audit display.
     */
    private function completedExecutiveOrders(
        string $payoutFilter = 'all',
        string $search = '',
        int $executiveId = 0
    ): array
    {
        $where = [
            "LOWER(COALESCE(o.status, '')) IN ('completed', 'complete', 'closed', 'delivered')",
        ];
        $params = [];

        if ($payoutFilter === 'pending') {
            $where[] = 'p.id IS NULL';
        } elseif ($payoutFilter === 'paid') {
            $where[] = "p.status = 'paid'";
        }

        if ($executiveId > 0) {
            $where[] = 'eu.id = :executive_user_id';
            $params['executive_user_id'] = $executiveId;
        }

        if ($search !== '') {
            $where[] = '(
                o.order_no LIKE :search_order
                OR COALESCE(eu.name, \'\') LIKE :search_executive
                OR COALESCE(eu.email, \'\') LIKE :search_email
                OR COALESCE(c.name, \'\') LIKE :search_client
                OR COALESCE(c.company_name, \'\') LIKE :search_company
                OR COALESCE(s.title, \'\') LIKE :search_service
            )';
            $like = '%' . $search . '%';
            $params['search_order'] = $like;
            $params['search_executive'] = $like;
            $params['search_email'] = $like;
            $params['search_client'] = $like;
            $params['search_company'] = $like;
            $params['search_service'] = $like;
        }

        return $this->db()->fetchAll(
            'SELECT
                o.id,
                o.order_no,
                o.client_id,
                o.service_id,
                o.fee_amount,
                o.status,
                o.completed_at,
                o.created_at,
                o.updated_at,
                COALESCE(NULLIF(c.company_name, \'\'), NULLIF(c.name, \'\'), \'Client\') AS client_name,
                COALESCE(NULLIF(s.title, \'\'), \'Service\') AS service_title,
                eu.id AS executive_user_id,
                COALESCE(NULLIF(eu.name, \'\'), CONCAT(\'Executive #\', eu.id)) AS executive_name,
                eu.email AS executive_email,
                p.id AS payout_id,
                p.amount AS payout_amount,
                p.currency AS payout_currency,
                p.status AS payout_status,
                p.payment_method,
                p.payment_reference,
                p.notes AS payout_notes,
                p.paid_at,
                p.paid_by_user_id,
                COALESCE(NULLIF(payer.name, \'\'), CONCAT(\'Admin #\', p.paid_by_user_id)) AS paid_by_name
             FROM orders o
            INNER JOIN users eu
                ON eu.id = o.assigned_user_id
             INNER JOIN user_roles eur
                ON eur.user_id = eu.id
               AND eur.role_id = :executive_role_id
             LEFT JOIN clients c ON c.id = o.client_id
             LEFT JOIN services s ON s.id = o.service_id
             LEFT JOIN executive_order_payouts p ON p.order_id = o.id
             LEFT JOIN users payer ON payer.id = p.paid_by_user_id
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY
                o.id, o.order_no, o.client_id, o.service_id, o.fee_amount,
                o.status, o.completed_at, o.created_at, c.company_name,
                o.updated_at, c.name, s.title, eu.id, eu.name, eu.email, p.id, p.amount,
                p.currency, p.status, p.payment_method, p.payment_reference,
                p.notes, p.paid_at, p.paid_by_user_id, payer.name
             ORDER BY COALESCE(o.completed_at, o.updated_at, o.created_at) DESC, o.id DESC',
            array_merge(['executive_role_id' => self::EXECUTIVE_ROLE_ID], $params)
        );
    }

    private function completedOrderForUpdate(int $orderId, int $expectedExecutiveId = 0): ?array
    {
        $executiveFilter = '';
        $params = [
            'order_id' => $orderId,
            'executive_role_id' => self::EXECUTIVE_ROLE_ID,
        ];

        if ($expectedExecutiveId > 0) {
            $executiveFilter = ' AND o.assigned_user_id = :expected_executive_id';
            $params['expected_executive_id'] = $expectedExecutiveId;
        }

        return $this->db()->fetch(
            "SELECT
                o.id,
                o.order_no,
                o.status,
                o.fee_amount,
                o.assigned_user_id,
                eu.name AS executive_name
             FROM orders o
            INNER JOIN users eu
                ON eu.id = o.assigned_user_id
             INNER JOIN user_roles eur
                ON eur.user_id = eu.id
               AND eur.role_id = :executive_role_id
             WHERE o.id = :order_id
               AND LOWER(COALESCE(o.status, '')) IN ('completed', 'complete', 'closed', 'delivered')
               {$executiveFilter}
             LIMIT 1
             FOR UPDATE",
            $params
        );
    }

    private function unpaidOrderIdsForUpdate(int $executiveId): array
    {
        if ($executiveId <= 0) {
            return [];
        }

        $rows = $this->db()->fetchAll(
            "SELECT o.id
             FROM orders o
             INNER JOIN user_roles eur
                ON eur.user_id = o.assigned_user_id
               AND eur.role_id = :executive_role_id
             LEFT JOIN executive_order_payouts p ON p.order_id = o.id
             WHERE o.assigned_user_id = :executive_user_id
               AND p.id IS NULL
               AND LOWER(COALESCE(o.status, '')) IN ('completed', 'complete', 'closed', 'delivered')
             ORDER BY COALESCE(o.completed_at, o.updated_at, o.created_at) ASC, o.id ASC
             FOR UPDATE",
            [
                'executive_role_id' => self::EXECUTIVE_ROLE_ID,
                'executive_user_id' => $executiveId,
            ]
        );

        return array_values(array_unique(array_filter(
            array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function assertPaymentDestinationReady(int $executiveId, string $paymentMethod): void
    {
        if ($paymentMethod === 'cash') {
            return;
        }

        if (!$this->paymentProfileTableReady()) {
            throw new DomainException('Executive payment details are not installed. Run the latest migration first.');
        }

        $profile = $this->paymentProfile($executiveId);

        if (!is_array($profile) || (int) ($profile['payment_details_confirmed'] ?? 0) !== 1) {
            throw new DomainException('The Executive has not marked payment details ready to receive payment.');
        }

        if ($paymentMethod === 'upi' && trim((string) ($profile['upi_id'] ?? '')) === '') {
            throw new DomainException('The Executive has not provided a UPI ID.');
        }

        if ($paymentMethod === 'bank_transfer'
            && (
                trim((string) ($profile['bank_name'] ?? '')) === ''
                || trim((string) ($profile['account_holder_name'] ?? '')) === ''
                || trim((string) ($profile['account_number'] ?? '')) === ''
                || trim((string) ($profile['ifsc_code'] ?? '')) === ''
            )
        ) {
            throw new DomainException('The Executive has not provided complete bank details.');
        }
    }

    private function parseAmount(mixed $value): string
    {
        $raw = trim(str_replace(',', '', (string) $value));

        if ($raw === '' || preg_match('/^\d+(?:\.\d{1,2})?$/', $raw) !== 1) {
            throw new DomainException('Enter a valid executive payment amount with up to two decimal places.');
        }

        $amount = (float) $raw;

        if ($amount <= 0 || $amount > 999999999.99) {
            throw new DomainException('Executive payment amount must be greater than zero.');
        }

        return number_format($amount, 2, '.', '');
    }

    public function index(): void
    {
        $this->requireViewAccess();

        $payoutFilter = strtolower(trim((string) input('payout_status', 'all')));
        if (!in_array($payoutFilter, ['all', 'pending', 'paid'], true)) {
            $payoutFilter = 'all';
        }

        $search = trim((string) input('q', ''));
        $executiveId = max(0, (int) input('executive_user_id', 0));
        $executives = $this->executiveUsers();
        $validExecutiveIds = array_map(
            static fn (array $executive): int => (int) ($executive['id'] ?? 0),
            $executives
        );

        if ($executiveId > 0 && !in_array($executiveId, $validExecutiveIds, true)) {
            $executiveId = 0;
        }

        $schemaReady = $this->payoutTableReady();
        $rows = $schemaReady
            ? $this->completedExecutiveOrders($payoutFilter, $search, $executiveId)
            : [];
        $stats = [
            'total' => count($rows),
            'pending' => 0,
            'paid' => 0,
            'pending_amount' => 0.0,
            'paid_amount' => 0.0,
        ];

        foreach ($rows as $row) {
            $isPaid = strtolower(trim((string) ($row['payout_status'] ?? ''))) === 'paid';
            if ($isPaid) {
                $stats['paid']++;
                $stats['paid_amount'] += (float) ($row['payout_amount'] ?? 0);
            } else {
                $stats['pending']++;
                $stats['pending_amount'] += (float) ($row['fee_amount'] ?? 0);
            }
        }

        $this->view('admin/executive-payouts', [
            'title' => 'Executive Payouts – Tax Saathi',
            'rows' => $rows,
            'stats' => $stats,
            'filters' => [
                'payout_status' => $payoutFilter,
                'q' => $search,
                'executive_user_id' => $executiveId,
            ],
            'schemaReady' => $schemaReady,
            'executives' => $executives,
            'selectedExecutiveId' => $executiveId,
            'selectedExecutive' => $executiveId > 0
                ? ($executives[array_search($executiveId, $validExecutiveIds, true)] ?? null)
                : null,
            'selectedPaymentProfile' => $this->hasRole(self::ADMIN_ROLE_ID)
                ? $this->paymentProfile($executiveId)
                : null,
            'canViewPaymentDetails' => $this->hasRole(self::ADMIN_ROLE_ID),
            'paymentProfileTableReady' => $this->paymentProfileTableReady(),
            'paymentMethods' => self::PAYMENT_METHODS,
            'canPay' => $this->hasRole(self::ADMIN_ROLE_ID)
                && rbac_can('executive_payouts.pay', $this->currentUserId()),
        ], 'layouts/dashboard');
    }

    public function pay(): void
    {
        $this->requirePayAccess();
        verify_csrf();

        if (!$this->payoutTableReady()) {
            flash('error', 'Executive payout table is not installed. Run the latest database migration first.');
            redirect('admin/executive-payouts');
        }

        $orderId = (int) input('order_id', 0);
        try {
            $amount = $this->parseAmount(input('amount', ''));
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
            redirect('admin/executive-payouts');
        }
        $paymentMethod = strtolower(trim((string) input('payment_method', '')));
        $paymentReference = trim((string) input('payment_reference', ''));
        $notes = trim((string) input('notes', ''));

        if ($orderId <= 0) {
            flash('error', 'Please select a valid completed order.');
            redirect('admin/executive-payouts');
        }

        if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            flash('error', 'Please select a valid executive payout method.');
            redirect('admin/executive-payouts');
        }

        if (strlen($paymentReference) > 120) {
            $paymentReference = substr($paymentReference, 0, 120);
        }

        if (strlen($notes) > 4000) {
            $notes = substr($notes, 0, 4000);
        }

        $adminUserId = $this->currentUserId();
        $paidAt = date('Y-m-d H:i:s');

        try {
            $order = null;

            $this->db()->transaction(function (Database $db) use (
                $orderId,
                $amount,
                $paymentMethod,
                $paymentReference,
                $notes,
                $adminUserId,
                $paidAt,
                &$order
            ): void {
                $order = $this->completedOrderForUpdate($orderId);

                if (!is_array($order) || $order === []) {
                    throw new DomainException('Only a completed order assigned to an Executive user can be paid.');
                }

                $this->assertPaymentDestinationReady(
                    (int) ($order['assigned_user_id'] ?? 0),
                    $paymentMethod
                );

                $alreadyPaid = $db->fetch(
                    'SELECT id FROM executive_order_payouts WHERE order_id = :order_id LIMIT 1 FOR UPDATE',
                    ['order_id' => $orderId]
                );

                if (is_array($alreadyPaid) && $alreadyPaid !== []) {
                    throw new DomainException('This completed order has already been paid to the Executive.');
                }

                $db->execute(
                    'INSERT INTO executive_order_payouts
                    (
                        order_id,
                        executive_user_id,
                        amount,
                        currency,
                        status,
                        payment_method,
                        payment_reference,
                        notes,
                        paid_by_user_id,
                        paid_at,
                        created_at,
                        updated_at
                    )
                    VALUES
                    (
                        :order_id,
                        :executive_user_id,
                        :amount,
                        :currency,
                        :status,
                        :payment_method,
                        :payment_reference,
                        :notes,
                        :paid_by_user_id,
                        :paid_at,
                        :created_at,
                        :updated_at
                    )',
                    [
                        'order_id' => $orderId,
                        'executive_user_id' => (int) ($order['assigned_user_id'] ?? 0),
                        'amount' => $amount,
                        'currency' => 'INR',
                        'status' => 'paid',
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                        'notes' => $notes !== '' ? $notes : null,
                        'paid_by_user_id' => $adminUserId,
                        'paid_at' => $paidAt,
                        'created_at' => $paidAt,
                        'updated_at' => $paidAt,
                    ]
                );
            });

            $executiveName = trim((string) ($order['executive_name'] ?? 'Executive'));
            activity_log(
                $adminUserId,
                $orderId,
                'executive.payout.paid',
                'Paid INR ' . number_format((float) $amount, 2) . ' to ' . $executiveName . ' for completed order ' . (string) ($order['order_no'] ?? $orderId) . '.',
                [
                    'order_id' => $orderId,
                    'order_no' => (string) ($order['order_no'] ?? ''),
                    'executive_user_id' => (int) ($order['assigned_user_id'] ?? 0),
                    'amount' => $amount,
                    'currency' => 'INR',
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $paymentReference,
                ]
            );

            flash('success', 'Executive payment recorded. This order is now marked as paid once.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Executive payout failed: ' . $e->getMessage());
            flash('error', 'Executive payment could not be recorded. No payment was saved.');
        }

        redirect('admin/executive-payouts');
    }

    public function payMany(): void
    {
        $this->requirePayAccess();
        verify_csrf();

        $executiveId = (int) input('executive_user_id', 0);
        $payAllUnpaid = (string) input('pay_all_unpaid', '') === '1';
        $rawOrderIds = input('order_ids', []);
        $rawAmounts = input('amounts', []);
        $paymentMethod = strtolower(trim((string) input('payment_method', '')));
        $paymentReference = trim((string) input('payment_reference', ''));
        $notes = trim((string) input('notes', ''));

        $rawOrderIds = is_array($rawOrderIds) ? $rawOrderIds : [$rawOrderIds];
        $orderIds = array_values(array_unique(array_filter(
            array_map('intval', $rawOrderIds),
            static fn (int $id): bool => $id > 0
        )));
        $amounts = is_array($rawAmounts) ? $rawAmounts : [];

        if ($executiveId <= 0) {
            flash('error', 'Filter by one Executive before using bulk payment.');
            redirect('admin/executive-payouts');
        }

        if (!$this->payoutTableReady()) {
            flash('error', 'Executive payout records are not installed. Run the latest migration first.');
            redirect('admin/executive-payouts?executive_user_id=' . $executiveId);
        }

        if (!$payAllUnpaid && $orderIds === []) {
            flash('error', 'Select at least one unpaid completed order, or choose all unpaid orders.');
            redirect('admin/executive-payouts?executive_user_id=' . $executiveId . '&payout_status=pending');
        }

        if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            flash('error', 'Please select a valid Executive payout method.');
            redirect('admin/executive-payouts?executive_user_id=' . $executiveId . '&payout_status=pending');
        }

        if (strlen($paymentReference) > 120) {
            $paymentReference = substr($paymentReference, 0, 120);
        }

        if (strlen($notes) > 4000) {
            $notes = substr($notes, 0, 4000);
        }

        $adminUserId = $this->currentUserId();
        $paidAt = date('Y-m-d H:i:s');
        $records = [];

        try {
            $this->db()->transaction(function (Database $db) use (
                $executiveId,
                $payAllUnpaid,
                $orderIds,
                $amounts,
                $paymentMethod,
                $paymentReference,
                $notes,
                $adminUserId,
                $paidAt,
                &$records
            ): void {
                $ids = $payAllUnpaid
                    ? $this->unpaidOrderIdsForUpdate($executiveId)
                    : $orderIds;

                if ($ids === []) {
                    throw new DomainException('There are no unpaid completed orders for this Executive.');
                }

                $this->assertPaymentDestinationReady($executiveId, $paymentMethod);

                foreach ($ids as $orderId) {
                    $order = $this->completedOrderForUpdate($orderId, $executiveId);

                    if (!is_array($order) || $order === []) {
                        throw new DomainException('One selected order is not a completed order assigned to this Executive.');
                    }

                    $alreadyPaid = $db->fetch(
                        'SELECT id FROM executive_order_payouts WHERE order_id = :order_id LIMIT 1 FOR UPDATE',
                        ['order_id' => $orderId]
                    );

                    if (is_array($alreadyPaid) && $alreadyPaid !== []) {
                        throw new DomainException('One selected order has already been paid. Reload the list and try again.');
                    }

                    $rawAmount = $amounts[$orderId] ?? $amounts[(string) $orderId] ?? ($order['fee_amount'] ?? '');
                    $amount = $this->parseAmount($rawAmount);

                    $db->execute(
                        'INSERT INTO executive_order_payouts
                        (
                            order_id,
                            executive_user_id,
                            amount,
                            currency,
                            status,
                            payment_method,
                            payment_reference,
                            notes,
                            paid_by_user_id,
                            paid_at,
                            created_at,
                            updated_at
                        )
                        VALUES
                        (
                            :order_id,
                            :executive_user_id,
                            :amount,
                            :currency,
                            :status,
                            :payment_method,
                            :payment_reference,
                            :notes,
                            :paid_by_user_id,
                            :paid_at,
                            :created_at,
                            :updated_at
                        )',
                        [
                            'order_id' => $orderId,
                            'executive_user_id' => $executiveId,
                            'amount' => $amount,
                            'currency' => 'INR',
                            'status' => 'paid',
                            'payment_method' => $paymentMethod,
                            'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                            'notes' => $notes !== '' ? $notes : null,
                            'paid_by_user_id' => $adminUserId,
                            'paid_at' => $paidAt,
                            'created_at' => $paidAt,
                            'updated_at' => $paidAt,
                        ]
                    );

                    $records[] = [
                        'order_id' => $orderId,
                        'order_no' => (string) ($order['order_no'] ?? $orderId),
                        'executive_user_id' => $executiveId,
                        'executive_name' => (string) ($order['executive_name'] ?? 'Executive'),
                        'amount' => $amount,
                    ];
                }
            });

            foreach ($records as $record) {
                activity_log(
                    $adminUserId,
                    (int) $record['order_id'],
                    'executive.payout.paid',
                    'Paid INR ' . number_format((float) $record['amount'], 2) . ' to ' . (string) $record['executive_name'] . ' for completed order ' . (string) $record['order_no'] . '.',
                    [
                        'order_id' => (int) $record['order_id'],
                        'order_no' => (string) $record['order_no'],
                        'executive_user_id' => $executiveId,
                        'amount' => (string) $record['amount'],
                        'currency' => 'INR',
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference,
                        'bulk' => true,
                    ]
                );
            }

            $total = 0.0;
            foreach ($records as $record) {
                $total += (float) $record['amount'];
            }

            flash('success', count($records) . ' Executive order payout(s) recorded for a total of INR ' . number_format($total, 2) . '. Each order was marked paid once.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Bulk Executive payout failed: ' . $e->getMessage());
            flash('error', 'Bulk Executive payment could not be recorded. No payment was saved.');
        }

        redirect('admin/executive-payouts?executive_user_id=' . $executiveId . '&payout_status=pending');
    }
}
