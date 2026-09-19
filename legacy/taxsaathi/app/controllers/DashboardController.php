<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use RuntimeException;

/**
 * Fully dynamic, strict user_roles-only dashboard.
 *
 * Access scope:
 * - role_id 1 (Admin) / 2 (Manager): all canonical order analytics.
 * - role_id 3 (Executive): only canonical orders assigned to the session user.
 * - role_id 4 (Partner): only canonical orders owned by the session partner.
 * - role_id 5 (Client): only canonical orders for the current client account.
 *
 * The session user id is validated through user_roles.user_id and its active
 * role_ids. users.role_id is never used as an authorization fallback.
 */
final class DashboardController extends Controller
{
    private ?Database $database = null;
    private ?array $roleContextCache = null;
    private array $tableColumnsCache = [];
    private array $tableExistsCache = [];

    private const PAYMENT_STATUSES = [
        'pending' => 'Pending',
        'pending_review' => 'Pending Review',
        'verified' => 'Verified',
        'paid' => 'Paid',
        'partial' => 'Partial',
        'unpaid' => 'Unpaid',
        'failed' => 'Failed',
    ];

    public function index(): void
    {
        require_auth();

        $context = $this->currentRoleContext();

        if (($context['role_ids'] ?? []) === []) {
            $this->denyDashboardAccess();
        }

        $filters = $this->dashboardFilters($context);
        $records = $this->accessibleRecords($context, $filters);
        $data = $this->buildDashboardData($context, $filters, $records);

        $this->view('dashboard/index', $data, 'layouts/dashboard');
    }

    public function admin(): void
    {
        $this->index();
    }

    public function manager(): void
    {
        $this->index();
    }

    public function executive(): void
    {
        $this->index();
    }

    public function partner(): void
    {
        $this->index();
    }

    public function client(): void
    {
        $this->index();
    }

    private function buildDashboardData(array $context, array $filters, array $records): array
    {
        $analytics = $this->analyticsFromRecords($records);
        $identity = $this->dashboardIdentity($context);
        $sections = $this->dashboardSections($context, $records);

        return [
            'title' => 'Dashboard – Tax Saathi',
            'workspaceLabel' => $identity['workspaceLabel'],
            'dashboardTitle' => $identity['dashboardTitle'],
            'dashboardSubtitle' => $identity['dashboardSubtitle'],
            'scopeLabel' => $identity['scopeLabel'],
            'roleKey' => $identity['roleKey'],
            'roleIds' => $context['role_ids'],
            'roleKeys' => $context['role_keys'],
            'roleNames' => $context['role_names'],
            'permissions' => $context['permissions'],
            'isAdminOrManager' => $this->hasAnyRoleId($context, [1, 2]),
            'isExecutive' => $this->hasAnyRoleId($context, [3]),
            'isPartner' => $this->hasAnyRoleId($context, [4]),
            'isClient' => $this->hasAnyRoleId($context, [5]),
            'isMultiRole' => count($context['role_ids']) > 1,
            'filters' => $filters,
            'paymentStatuses' => self::PAYMENT_STATUSES,
            'partners' => $this->partnerFilterOptions(),
            'executives' => $this->executiveFilterOptions(),
            'canFilterByPartner' => $this->hasAnyRoleId($context, [1, 2]),
            'canFilterByExecutive' => $this->hasAnyRoleId($context, [1, 2]),
            'statCards' => $this->statCards($analytics, $identity),
            'dashboardSections' => $sections,
            'quickActions' => $this->quickActions($context),
            'recentOrders' => array_slice($records, 0, 12),
            'statusSummary' => $analytics['payment_summary'],
            'chartData' => [
                'monthly' => $analytics['monthly'],
                'payment' => $analytics['payment_summary'],
                'order_status' => $analytics['order_status_summary'],
                'services' => $analytics['service_summary'],
                'sources' => $analytics['source_summary'],
            ],
            'analytics' => $analytics,
            'stats' => [
                'orders' => $analytics['total_records'],
                'clients' => $analytics['unique_clients'],
                'services' => $analytics['unique_services'],
                'staff' => $analytics['unique_executives'],
                'leads' => $analytics['outstanding_records'],
            ],
            'primaryOrdersUrl' => $identity['primaryOrdersUrl'],
            'periodLabel' => $this->periodLabel($filters),
        ];
    }

    private function dashboardIdentity(array $context): array
    {
        if ($this->hasAnyRoleId($context, [1, 2])) {
            $managerOnly = $this->hasAnyRoleId($context, [2]) && !$this->hasAnyRoleId($context, [1]);

            return [
                'roleKey' => $managerOnly ? 'manager' : 'admin',
                'workspaceLabel' => $managerOnly ? 'Manager Intelligence Workspace' : 'Admin Intelligence Workspace',
                'dashboardTitle' => 'Business Intelligence Overview',
                'dashboardSubtitle' => 'Live operational, revenue, payment, service and workload analytics across all accessible order sources.',
                'scopeLabel' => 'All business records',
                'primaryOrdersUrl' => base_url('admin/orders'),
            ];
        }

        $keys = [];
        if ($this->hasAnyRoleId($context, [3])) {
            $keys[] = 'executive';
        }
        if ($this->hasAnyRoleId($context, [4])) {
            $keys[] = 'partner';
        }
        if ($this->hasAnyRoleId($context, [5])) {
            $keys[] = 'client';
        }

        if (count($keys) > 1) {
            return [
                'roleKey' => 'multi-role',
                'workspaceLabel' => 'Unified Personal Workspace',
                'dashboardTitle' => '  Analytics',
                'dashboardSubtitle' => 'A consolidated view of every record personally accessible through your active user_roles assignments.',
                'scopeLabel' => 'My accessible records',
                'primaryOrdersUrl' => base_url('dashboard'),
            ];
        }

        if ($this->hasAnyRoleId($context, [3])) {
            return [
                'roleKey' => 'executive',
                'workspaceLabel' => 'Executive Performance Workspace',
                'dashboardTitle' => 'My Work Analytics',
                'dashboardSubtitle' => 'Track assigned orders, payment health, workload, completion performance and service distribution.',
                'scopeLabel' => 'Assigned to me',
                'primaryOrdersUrl' => base_url('admin/orders'),
            ];
        }

        if ($this->hasAnyRoleId($context, [4])) {
            return [
                'roleKey' => 'partner',
                'workspaceLabel' => 'Partner Growth Workspace',
                'dashboardTitle' => 'My Partner Analytics',
                'dashboardSubtitle' => 'Monitor your applications, payments, discounts, service mix and completion progress.',
                'scopeLabel' => 'My partner records',
                'primaryOrdersUrl' => base_url('client/orders'),
            ];
        }

        return [
            'roleKey' => 'client',
            'workspaceLabel' => 'Client Service Workspace',
            'dashboardTitle' => 'My Service Analytics',
            'dashboardSubtitle' => 'Follow your applications, payment progress, service status and completion history.',
            'scopeLabel' => 'My client records',
            'primaryOrdersUrl' => base_url('client/orders'),
        ];
    }

    private function dashboardFilters(array $context): array
    {
        $dateFrom = $this->validDate((string) ($_GET['date_from'] ?? ''));
        $dateTo = $this->validDate((string) ($_GET['date_to'] ?? ''));

        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $source = strtolower(trim((string) ($_GET['source'] ?? 'all')));
        if (!in_array($source, ['all', 'client', 'partner'], true)) {
            $source = 'all';
        }

        $paymentStatus = strtolower(trim((string) ($_GET['payment_status'] ?? 'all')));
        if ($paymentStatus !== 'all' && !array_key_exists($paymentStatus, self::PAYMENT_STATUSES)) {
            $paymentStatus = 'all';
        }

        $isAdminOrManager = $this->hasAnyRoleId($context, [1, 2]);
        $partnerId = $isAdminOrManager ? max(0, (int) ($_GET['partner_id'] ?? 0)) : 0;
        $executiveId = $isAdminOrManager ? (int) ($_GET['executive_id'] ?? 0) : 0;

        if ($this->hasAnyRoleId($context, [4]) && !$isAdminOrManager) {
            $partnerId = $this->currentUserId();
        }

        if ($this->hasAnyRoleId($context, [3]) && !$isAdminOrManager) {
            $executiveId = $this->currentUserId();
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'source' => $source,
            'payment_status' => $paymentStatus,
            'partner_id' => $partnerId,
            'executive_id' => $executiveId,
        ];
    }

    private function accessibleRecords(array $context, array $filters): array
    {
        $queries = [];
        $params = [];
        if ($this->tableExists('orders')) {
            [$query, $queryParams] = $this->regularOrdersQuery($context, $filters);
            if ($query !== '') {
                $queries[] = $query;
                $params = array_merge($params, $queryParams);
            }
        }

        if ($queries === []) {
            return [];
        }

        $sql = count($queries) === 1
            ? $queries[0] . ' ORDER BY created_at DESC, id DESC LIMIT 25000'
            : 'SELECT * FROM (' . implode(' UNION ALL ', $queries) . ') AS dashboard_records ORDER BY created_at DESC, id DESC LIMIT 25000';

        try {
            return $this->db()->fetchAll($sql, $params) ?: [];
        } catch (\Throwable $e) {
            error_log('Dashboard analytics query failed: ' . $e->getMessage());
            return [];
        }
    }


    private function regularOrdersQuery(array $context, array $filters): array
    {
        $columns = $this->tableColumns('orders');
        if (!in_array('id', $columns, true)) {
            return ['', []];
        }

        $assignedColumn = $this->firstExistingColumn($columns, ['assigned_user_id', 'assigned_to', 'executive_id', 'staff_id']);
        $hasPartnerId = in_array('partner_id', $columns, true);
        $hasClientId = in_array('client_id', $columns, true);
        $isAdminOrManager = $this->hasAnyRoleId($context, [1, 2]);
        $userId = $this->currentUserId();
        $clientId = $this->resolveClientIdForCurrentUser();

        $scopeParts = [];
        $params = [];

        if ($isAdminOrManager) {
            $scopeParts[] = '1=1';
        } else {
            if ($this->hasAnyRoleId($context, [3]) && $assignedColumn !== '') {
                $scopeParts[] = 'o.`' . $assignedColumn . '` = :regular_scope_executive';
                $params['regular_scope_executive'] = $userId;
            }

            if ($this->hasAnyRoleId($context, [4]) && $hasPartnerId) {
                $scopeParts[] = 'o.partner_id = :regular_scope_partner';
                $params['regular_scope_partner'] = $userId;
            }

            if ($this->hasAnyRoleId($context, [5]) && $hasClientId && $clientId > 0) {
                $scopeParts[] = $hasPartnerId
                    ? 'o.client_id = :regular_scope_client AND (o.partner_id IS NULL OR o.partner_id = 0)'
                    : 'o.client_id = :regular_scope_client';
                $params['regular_scope_client'] = $clientId;
            }
        }

        if ($scopeParts === []) {
            $scopeParts[] = '1=0';
        }

        $where = ['(' . implode(' OR ', $scopeParts) . ')'];
        $this->appendCommonFilters($where, $params, $filters, 'o', 'regular');

        $source = (string) ($filters['source'] ?? 'all');
        if ($source === 'partner') {
            $where[] = $hasPartnerId ? 'o.partner_id > 0' : '1=0';
        } elseif ($source === 'client') {
            $where[] = $hasPartnerId ? '(o.partner_id IS NULL OR o.partner_id = 0)' : '1=1';
        }

        $selectedPartnerId = (int) ($filters['partner_id'] ?? 0);
        if ($isAdminOrManager && $selectedPartnerId > 0) {
            $where[] = $hasPartnerId
                ? 'o.partner_id = :regular_filter_partner'
                : '1=0';

            if ($hasPartnerId) {
                $params['regular_filter_partner'] = $selectedPartnerId;
            }
        }

        $selectedExecutiveId = (int) ($filters['executive_id'] ?? 0);
        if ($isAdminOrManager && $selectedExecutiveId !== 0) {
            if ($assignedColumn === '') {
                $where[] = '1=0';
            } elseif ($selectedExecutiveId === -1) {
                $where[] = '(o.`' . $assignedColumn . '` IS NULL OR o.`' . $assignedColumn . '` = 0)';
            } else {
                $where[] = 'o.`' . $assignedColumn . '` = :regular_filter_executive';
                $params['regular_filter_executive'] = $selectedExecutiveId;
            }
        }

        $serviceJoin = '';
        $serviceTitle = "'Service'";
        if ($this->tableExists('services') && in_array('service_id', $columns, true)) {
            $serviceJoin = ' LEFT JOIN services s ON s.id = o.service_id';
            $serviceTitle = "COALESCE(NULLIF(s.title, ''), 'Service')";
        }

        $clientJoin = '';
        $clientName = "'Client'";
        if ($hasClientId && $this->tableExists('clients')) {
            $clientColumns = $this->tableColumns('clients');
            $nameParts = [];
            if (in_array('company_name', $clientColumns, true)) {
                $nameParts[] = "NULLIF(c.company_name, '')";
            }
            if (in_array('name', $clientColumns, true)) {
                $nameParts[] = "NULLIF(c.name, '')";
            }
            $nameParts[] = "CONCAT('Client #', o.client_id)";
            $clientName = 'COALESCE(' . implode(', ', $nameParts) . ')';
            $clientJoin = ' LEFT JOIN clients c ON c.id = o.client_id';
        }

        $partnerJoin = '';
        $partnerName = "''";
        if ($hasPartnerId && $this->tableExists('users')) {
            $partnerJoin = ' LEFT JOIN users pu ON pu.id = o.partner_id';
            $partnerName = "COALESCE(NULLIF(pu.name, ''), CONCAT('Partner #', o.partner_id))";
        }

        $assignedJoin = '';
        $assignedName = "''";
        $assignedIdExpression = '0';
        if ($assignedColumn !== '') {
            $assignedIdExpression = 'COALESCE(o.`' . $assignedColumn . '`, 0)';
            if ($this->tableExists('users')) {
                $assignedJoin = ' LEFT JOIN users au ON au.id = o.`' . $assignedColumn . '`';
                $assignedName = "COALESCE(NULLIF(au.name, ''), CASE WHEN o.`{$assignedColumn}` > 0 THEN CONCAT('User #', o.`{$assignedColumn}`) ELSE '' END)";
            }
        }

        /*
         * Financial truth for normal orders:
         * - orders.fee_amount is the pre-tax/base fee.
         * - invoices.total_amount is the final payable amount.
         * - payments.amount is the actual collected amount.
         *
         * Aggregated joins avoid duplicate dashboard rows when multiple payments exist.
         */
        $invoiceJoin = '';
        $paymentJoin = '';
        $invoiceTotalExpression = 'NULL';
        $invoicePaidExpression = 'NULL';
        $paymentTotalExpression = 'NULL';

        if ($this->tableExists('invoices')) {
            $invoiceJoin = "
                LEFT JOIN (
                    SELECT
                        order_id,
                        MAX(total_amount) AS invoice_total,
                        MAX(paid_amount) AS invoice_paid
                    FROM invoices
                    GROUP BY order_id
                ) inv ON inv.order_id = o.id
            ";
            $invoiceTotalExpression = 'inv.invoice_total';
            $invoicePaidExpression = 'inv.invoice_paid';
        }

        if ($this->tableExists('payments')) {
            $paymentJoin = "
                LEFT JOIN (
                    SELECT
                        order_id,
                        COALESCE(SUM(amount), 0) AS payment_total
                    FROM payments
                    GROUP BY order_id
                ) pay ON pay.order_id = o.id
            ";
            $paymentTotalExpression = 'pay.payment_total';
        }

        $partnerRowExpression = $hasPartnerId ? 'COALESCE(o.partner_id, 0) > 0' : '0=1';
        $clientGrossExpression = in_array('fee_amount', $columns, true) ? 'COALESCE(o.fee_amount, 0)' : '0';
        $partnerGrossExpression = in_array('gross_fee_amount', $columns, true)
            ? 'COALESCE(o.gross_fee_amount, ' . $clientGrossExpression . ')'
            : $clientGrossExpression;
        $grossExpression = "CASE WHEN {$partnerRowExpression} THEN {$partnerGrossExpression} ELSE {$clientGrossExpression} END";

        $clientPayableExpression = $invoiceTotalExpression !== 'NULL'
            ? 'COALESCE(NULLIF(' . $invoiceTotalExpression . ', 0), ' . $clientGrossExpression . ')'
            : $clientGrossExpression;
        $partnerPayableExpression = in_array('payable_amount', $columns, true)
            ? 'COALESCE(o.payable_amount, ' . $partnerGrossExpression . ')'
            : $partnerGrossExpression;
        $payableExpression = "CASE WHEN {$partnerRowExpression} THEN {$partnerPayableExpression} ELSE {$clientPayableExpression} END";

        $paymentStatusSql = in_array('payment_status', $columns, true)
            ? "LOWER(COALESCE(NULLIF(o.payment_status, ''), 'pending'))"
            : "'pending'";

        $collectedCandidates = [];
        if ($paymentTotalExpression !== 'NULL') {
            $collectedCandidates[] = $paymentTotalExpression;
        }
        if ($invoicePaidExpression !== 'NULL') {
            $collectedCandidates[] = $invoicePaidExpression;
        }
        $collectedCandidates[] = "CASE WHEN {$paymentStatusSql} IN ('paid','verified','success') THEN {$payableExpression} ELSE 0 END";
        $collectedExpression = 'GREATEST(0, ' . implode(', ', array_map(
            static fn (string $candidate): string => 'COALESCE(' . $candidate . ', 0)',
            $collectedCandidates
        )) . ')';

        $discountExpression = in_array('coupon_discount_amount', $columns, true) ? 'COALESCE(o.coupon_discount_amount, 0)' : '0';
        $statusExpression = in_array('status', $columns, true) ? "COALESCE(NULLIF(o.status, ''), 'unknown')" : "'unknown'";
        $paymentExpression = in_array('payment_status', $columns, true) ? "COALESCE(NULLIF(o.payment_status, ''), 'pending')" : "'pending'";
        $orderNoExpression = in_array('order_no', $columns, true) ? "COALESCE(NULLIF(o.order_no, ''), CONCAT('#', o.id))" : "CONCAT('#', o.id)";
        $createdExpression = in_array('created_at', $columns, true) ? 'o.created_at' : 'NULL';
        $updatedExpression = in_array('updated_at', $columns, true) ? 'o.updated_at' : $createdExpression;
        $serviceIdExpression = in_array('service_id', $columns, true) ? 'COALESCE(o.service_id, 0)' : '0';
        $clientIdExpression = $hasClientId ? 'COALESCE(o.client_id, 0)' : '0';
        $partnerIdExpression = $hasPartnerId ? 'COALESCE(o.partner_id, 0)' : '0';

        $sql = "
            SELECT
                CASE WHEN {$partnerRowExpression} THEN 'partner' ELSE 'client' END AS report_source,
                o.id,
                {$orderNoExpression} AS order_no,
                {$statusExpression} AS status,
                {$paymentExpression} AS payment_status,
                {$createdExpression} AS created_at,
                {$updatedExpression} AS updated_at,
                {$grossExpression} AS gross_amount,
                {$payableExpression} AS payable_amount,
                {$collectedExpression} AS collected_amount,
                GREATEST({$payableExpression} - {$collectedExpression}, 0) AS outstanding_amount,
                {$discountExpression} AS discount_amount,
                {$serviceIdExpression} AS service_id,
                {$serviceTitle} AS service_title,
                {$clientIdExpression} AS client_id,
                {$clientName} AS client_name,
                {$partnerIdExpression} AS partner_id,
                {$partnerName} AS partner_name,
                {$assignedIdExpression} AS assigned_user_id,
                {$assignedName} AS assigned_user_name
            FROM orders o
            {$serviceJoin}
            {$clientJoin}
            {$partnerJoin}
            {$assignedJoin}
            {$invoiceJoin}
            {$paymentJoin}
            WHERE " . implode(' AND ', $where);

        return [$sql, $params];
    }


    private function partnerOrdersQuery(array $context, array $filters): array
    {
        $columns = $this->tableColumns('partner_orders');
        if (!in_array('id', $columns, true)) {
            return ['', []];
        }

        $isAdminOrManager = $this->hasAnyRoleId($context, [1, 2]);
        $userId = $this->currentUserId();
        $hasPartnerId = in_array('partner_id', $columns, true);
        $scopeParts = [];
        $params = [];

        if ($isAdminOrManager) {
            $scopeParts[] = '1=1';
        } elseif ($this->hasAnyRoleId($context, [4]) && $hasPartnerId) {
            $scopeParts[] = 'po.partner_id = :partner_scope_owner';
            $params['partner_scope_owner'] = $userId;
        }

        if ($scopeParts === []) {
            $scopeParts[] = '1=0';
        }

        $where = ['(' . implode(' OR ', $scopeParts) . ')'];
        $this->appendCommonFilters($where, $params, $filters, 'po', 'partner');

        $selectedPartnerId = (int) ($filters['partner_id'] ?? 0);
        if ($isAdminOrManager && $selectedPartnerId > 0) {
            $where[] = $hasPartnerId
                ? 'po.partner_id = :partner_filter_partner'
                : '1=0';

            if ($hasPartnerId) {
                $params['partner_filter_partner'] = $selectedPartnerId;
            }
        }

        if ($isAdminOrManager && (int) ($filters['executive_id'] ?? 0) !== 0) {
            // partner_orders has no assigned executive column in the supplied schema.
            $where[] = '1=0';
        }

        $serviceJoin = '';
        $serviceTitle = "'Service'";
        if ($this->tableExists('services') && in_array('service_id', $columns, true)) {
            $serviceJoin = ' LEFT JOIN services s ON s.id = po.service_id';
            $serviceTitle = "COALESCE(NULLIF(s.title, ''), 'Service')";
        }

        $partnerJoin = '';
        $partnerName = $hasPartnerId ? "CONCAT('Partner #', po.partner_id)" : "'Partner'";
        if ($hasPartnerId && $this->tableExists('users')) {
            $partnerJoin = ' LEFT JOIN users pu ON pu.id = po.partner_id';
            $partnerName = "COALESCE(NULLIF(pu.name, ''), CONCAT('Partner #', po.partner_id))";
        }

        $grossExpression = in_array('filing_fee', $columns, true) ? 'COALESCE(po.filing_fee, 0)' : '0';
        $payableExpression = in_array('payable_amount', $columns, true)
            ? 'COALESCE(po.payable_amount, ' . $grossExpression . ')'
            : $grossExpression;
        $discountExpression = in_array('coupon_discount_amount', $columns, true) ? 'COALESCE(po.coupon_discount_amount, 0)' : '0';
        $statusColumn = $this->firstExistingColumn($columns, ['order_status', 'filing_status', 'status']);
        $statusExpression = $statusColumn !== '' ? "COALESCE(NULLIF(po.`{$statusColumn}`, ''), 'unknown')" : "'unknown'";
        $paymentExpression = in_array('payment_status', $columns, true) ? "COALESCE(NULLIF(po.payment_status, ''), 'pending')" : "'pending'";
        $paymentStatusSql = in_array('payment_status', $columns, true)
            ? "LOWER(COALESCE(NULLIF(po.payment_status, ''), 'pending'))"
            : "'pending'";
        $paidAtCondition = in_array('paid_at', $columns, true) ? ' OR po.paid_at IS NOT NULL' : '';

        /*
         * partner_orders stores its final payable amount directly. It does not
         * contain a partial-paid numeric field in the supplied schema, therefore
         * a row is counted as fully collected only when its payment is approved,
         * verified, paid/success, or paid_at is present.
         */
        $collectedExpression = "CASE
            WHEN {$paymentStatusSql} IN ('approved','verified','paid','success'){$paidAtCondition}
            THEN {$payableExpression}
            ELSE 0
        END";

        $orderNoExpression = in_array('order_no', $columns, true) ? "COALESCE(NULLIF(po.order_no, ''), CONCAT('#', po.id))" : "CONCAT('#', po.id)";
        $createdExpression = in_array('created_at', $columns, true) ? 'po.created_at' : 'NULL';
        $updatedExpression = in_array('updated_at', $columns, true) ? 'po.updated_at' : $createdExpression;
        $serviceIdExpression = in_array('service_id', $columns, true) ? 'COALESCE(po.service_id, 0)' : '0';
        $partnerIdExpression = $hasPartnerId ? 'COALESCE(po.partner_id, 0)' : '0';

        $sql = "
            SELECT
                'partner_orders' AS report_source,
                po.id,
                {$orderNoExpression} AS order_no,
                {$statusExpression} AS status,
                {$paymentExpression} AS payment_status,
                {$createdExpression} AS created_at,
                {$updatedExpression} AS updated_at,
                {$grossExpression} AS gross_amount,
                {$payableExpression} AS payable_amount,
                {$collectedExpression} AS collected_amount,
                GREATEST({$payableExpression} - ({$collectedExpression}), 0) AS outstanding_amount,
                {$discountExpression} AS discount_amount,
                {$serviceIdExpression} AS service_id,
                {$serviceTitle} AS service_title,
                0 AS client_id,
                'Partner Order' AS client_name,
                {$partnerIdExpression} AS partner_id,
                {$partnerName} AS partner_name,
                0 AS assigned_user_id,
                '' AS assigned_user_name
            FROM partner_orders po
            {$serviceJoin}
            {$partnerJoin}
            WHERE " . implode(' AND ', $where);

        return [$sql, $params];
    }


    private function appendCommonFilters(array &$where, array &$params, array $filters, string $alias, string $prefix): void
    {
        $table = $alias === 'o' ? 'orders' : 'partner_orders';
        $columns = $this->tableColumns($table);

        if (in_array('created_at', $columns, true)) {
            if (($filters['date_from'] ?? '') !== '') {
                $where[] = $alias . '.created_at >= :' . $prefix . '_date_from';
                $params[$prefix . '_date_from'] = (string) $filters['date_from'] . ' 00:00:00';
            }

            if (($filters['date_to'] ?? '') !== '') {
                $where[] = $alias . '.created_at <= :' . $prefix . '_date_to';
                $params[$prefix . '_date_to'] = (string) $filters['date_to'] . ' 23:59:59';
            }
        }

        $paymentStatus = (string) ($filters['payment_status'] ?? 'all');
        if ($paymentStatus === 'all' || !in_array('payment_status', $columns, true)) {
            return;
        }

        if ($table === 'partner_orders') {
            $parameter = $prefix . '_payment_status';

            switch ($paymentStatus) {
                case 'pending':
                    $where[] = $alias . ".payment_status IN ('pending','waiting_for_payment')";
                    return;

                case 'pending_review':
                    $where[] = $alias . ".payment_status IN ('pending_review','payment_submitted')";
                    return;

                case 'verified':
                    $where[] = $alias . ".payment_status IN ('verified','approved')";
                    return;

                case 'paid':
                    $where[] = $alias . ".payment_status IN ('paid','success')";
                    return;

                default:
                    $where[] = $alias . '.payment_status = :' . $parameter;
                    $params[$parameter] = $paymentStatus;
                    return;
            }
        }

        $where[] = $alias . '.payment_status = :' . $prefix . '_payment_status';
        $params[$prefix . '_payment_status'] = $paymentStatus;
    }


    private function analyticsFromRecords(array $records): array
    {
        $total = count($records);
        $gross = 0.0;
        $payable = 0.0;
        $discount = 0.0;
        $paidAmount = 0.0;
        $outstandingAmount = 0.0;
        $completed = 0;
        $outstandingRecords = 0;
        $clients = [];
        $services = [];
        $executives = [];
        $partners = [];
        $paymentSummary = [];
        $orderSummary = [];
        $serviceSummary = [];
        $sourceSummary = [];

        foreach (self::PAYMENT_STATUSES as $key => $label) {
            $paymentSummary[$key] = [
                'status' => $key,
                'label' => $label,
                'total' => 0,
                'count' => 0,
                'amount' => 0.0,
                'collected' => 0.0,
                'outstanding' => 0.0,
            ];
        }

        $monthly = $this->emptyMonthlySeries(12);

        foreach ($records as &$record) {
            $grossAmount = max(0.0, (float) ($record['gross_amount'] ?? 0));
            $payableAmount = max(0.0, (float) ($record['payable_amount'] ?? $grossAmount));
            $discountAmount = max(0.0, (float) ($record['discount_amount'] ?? 0));
            $paymentStatus = $this->normalizePaymentStatus((string) ($record['payment_status'] ?? 'pending'));

            $fallbackCollected = in_array($paymentStatus, ['paid', 'verified'], true)
                ? $payableAmount
                : 0.0;

            $collectedAmount = array_key_exists('collected_amount', $record)
                ? max(0.0, (float) $record['collected_amount'])
                : $fallbackCollected;

            $collectedAmount = min($collectedAmount, $payableAmount);
            $recordOutstanding = array_key_exists('outstanding_amount', $record)
                ? max(0.0, (float) $record['outstanding_amount'])
                : max($payableAmount - $collectedAmount, 0.0);

            $record['payment_status'] = $paymentStatus;
            $record['collected_amount'] = $collectedAmount;
            $record['outstanding_amount'] = $recordOutstanding;

            $gross += $grossAmount;
            $payable += $payableAmount;
            $discount += $discountAmount;
            $paidAmount += $collectedAmount;
            $outstandingAmount += $recordOutstanding;

            if ($recordOutstanding > 0.009) {
                $outstandingRecords++;
            }

            if (!isset($paymentSummary[$paymentStatus])) {
                $paymentSummary[$paymentStatus] = [
                    'status' => $paymentStatus,
                    'label' => $this->labelFromStatus($paymentStatus),
                    'total' => 0,
                    'count' => 0,
                    'amount' => 0.0,
                    'collected' => 0.0,
                    'outstanding' => 0.0,
                ];
            }

            $paymentSummary[$paymentStatus]['total']++;
            $paymentSummary[$paymentStatus]['count']++;
            $paymentSummary[$paymentStatus]['amount'] += $payableAmount;
            $paymentSummary[$paymentStatus]['collected'] += $collectedAmount;
            $paymentSummary[$paymentStatus]['outstanding'] += $recordOutstanding;

            $status = $this->normalizeStatus((string) ($record['status'] ?? 'unknown'));
            if (!isset($orderSummary[$status])) {
                $orderSummary[$status] = [
                    'status' => $status,
                    'label' => $this->labelFromStatus($status),
                    'total' => 0,
                    'amount' => 0.0,
                ];
            }
            $orderSummary[$status]['total']++;
            $orderSummary[$status]['amount'] += $payableAmount;

            if (in_array($status, ['completed', 'closed', 'success'], true)) {
                $completed++;
            }

            $serviceKey = trim((string) ($record['service_title'] ?? 'Service')) ?: 'Service';
            if (!isset($serviceSummary[$serviceKey])) {
                $serviceSummary[$serviceKey] = [
                    'label' => $serviceKey,
                    'total' => 0,
                    'amount' => 0.0,
                    'collected' => 0.0,
                ];
            }
            $serviceSummary[$serviceKey]['total']++;
            $serviceSummary[$serviceKey]['amount'] += $payableAmount;
            $serviceSummary[$serviceKey]['collected'] += $collectedAmount;

            $source = (string) ($record['report_source'] ?? 'client');
            if (!isset($sourceSummary[$source])) {
                $sourceSummary[$source] = [
                    'source' => $source,
                    'label' => $source === 'partner' ? 'Partner Orders' : 'Client Orders',
                    'total' => 0,
                    'amount' => 0.0,
                    'collected' => 0.0,
                ];
            }
            $sourceSummary[$source]['total']++;
            $sourceSummary[$source]['amount'] += $payableAmount;
            $sourceSummary[$source]['collected'] += $collectedAmount;

            $clientId = (int) ($record['client_id'] ?? 0);
            if ($clientId > 0) {
                $clients[$clientId] = true;
            }

            $serviceId = (int) ($record['service_id'] ?? 0);
            if ($serviceId > 0) {
                $services[$serviceId] = true;
            } else {
                $services['name:' . $serviceKey] = true;
            }

            $executiveId = (int) ($record['assigned_user_id'] ?? 0);
            if ($executiveId > 0) {
                $executives[$executiveId] = true;
            }

            $partnerId = (int) ($record['partner_id'] ?? 0);
            if ($partnerId > 0) {
                $partners[$partnerId] = true;
            }

            $monthKey = $this->monthKey((string) ($record['created_at'] ?? ''));
            if ($monthKey !== '' && isset($monthly[$monthKey])) {
                $monthly[$monthKey]['orders']++;
                $monthly[$monthKey]['amount'] += $payableAmount;
                $monthly[$monthKey]['collected'] = (float) ($monthly[$monthKey]['collected'] ?? 0) + $collectedAmount;
            }
        }
        unset($record);

        uasort($orderSummary, static fn (array $a, array $b): int => ($b['total'] <=> $a['total']));
        uasort($serviceSummary, static function (array $a, array $b): int {
            $amountCompare = $b['amount'] <=> $a['amount'];
            return $amountCompare !== 0 ? $amountCompare : ($b['total'] <=> $a['total']);
        });

        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;
        $collectionRate = $payable > 0 ? round(($paidAmount / $payable) * 100, 1) : 0.0;
        $averageOrderValue = $total > 0 ? $payable / $total : 0.0;
        $topService = array_values($serviceSummary)[0] ?? [
            'label' => 'No service data',
            'total' => 0,
            'amount' => 0.0,
            'collected' => 0.0,
        ];

        return [
            'total_records' => $total,
            'gross_amount' => $gross,
            'payable_amount' => $payable,
            'discount_amount' => $discount,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $outstandingAmount,
            'outstanding_records' => $outstandingRecords,
            'completed_records' => $completed,
            'completion_rate' => $completionRate,
            'collection_rate' => min(100.0, $collectionRate),
            'average_order_value' => $averageOrderValue,
            'unique_clients' => count($clients),
            'unique_services' => count($services),
            'unique_executives' => count($executives),
            'unique_partners' => count($partners),
            'top_service' => $topService,
            'payment_summary' => array_values($paymentSummary),
            'order_status_summary' => array_slice(array_values($orderSummary), 0, 8),
            'service_summary' => array_slice(array_values($serviceSummary), 0, 8),
            'source_summary' => array_values($sourceSummary),
            'monthly' => array_values($monthly),
        ];
    }

    private function statCards(array $analytics, array $identity): array
    {
        $ordersUrl = (string) ($identity['primaryOrdersUrl'] ?? base_url('dashboard'));

        return [
            [
                'label' => 'Accessible Records',
                'value' => number_format((int) $analytics['total_records']),
                'raw_value' => (int) $analytics['total_records'],
                'href' => $ordersUrl,
                'icon' => 'orders',
                'tone' => 'indigo',
                'note' => 'Current filtered scope',
            ],
            [
                'label' => 'Gross Value',
                'value' => $this->formatCompactMoney((float) $analytics['gross_amount']),
                'raw_value' => (float) $analytics['gross_amount'],
                'href' => $ordersUrl,
                'icon' => 'revenue',
                'tone' => 'slate',
                'note' => 'Before discounts',
            ],
            [
                'label' => 'Collected Value',
                'value' => $this->formatCompactMoney((float) $analytics['paid_amount']),
                'raw_value' => (float) $analytics['paid_amount'],
                'href' => $ordersUrl,
                'icon' => 'paid',
                'tone' => 'emerald',
                'note' => number_format((float) $analytics['collection_rate'], 1) . '% collection rate',
            ],
            [
                'label' => 'Outstanding',
                'value' => $this->formatCompactMoney((float) $analytics['outstanding_amount']),
                'raw_value' => (float) $analytics['outstanding_amount'],
                'href' => $ordersUrl,
                'icon' => 'pending',
                'tone' => 'amber',
                'note' => number_format((int) $analytics['outstanding_records']) . ' record(s)',
            ],
            [
                'label' => 'Completed',
                'value' => number_format((int) $analytics['completed_records']),
                'raw_value' => (int) $analytics['completed_records'],
                'href' => $ordersUrl,
                'icon' => 'completed',
                'tone' => 'cyan',
                'note' => number_format((float) $analytics['completion_rate'], 1) . '% completion rate',
            ],
            [
                'label' => 'Average Order Value',
                'value' => $this->formatCompactMoney((float) $analytics['average_order_value']),
                'raw_value' => (float) $analytics['average_order_value'],
                'href' => $ordersUrl,
                'icon' => 'average',
                'tone' => 'violet',
                'note' => 'Across accessible records',
            ],
        ];
    }

    private function dashboardSections(array $context, array $records): array
    {
        $sections = [];
        $isAdminOrManager = $this->hasAnyRoleId($context, [1, 2]);

        if ($isAdminOrManager) {
            $sections[] = [
                'title' => 'Business Network',
                'subtitle' => 'Live system-wide entities and operational reach.',
                'statCards' => [
                    ['label' => 'Clients', 'value' => $this->countRows('clients'), 'href' => base_url('admin/clients')],
                    ['label' => 'Executives', 'value' => $this->countUsersByRole(3), 'href' => base_url('admin/users/roles')],
                    ['label' => 'Partners', 'value' => $this->countUsersByRole(4), 'href' => base_url('admin/users/roles')],
                    ['label' => 'Active Services', 'value' => $this->countRows('services', 'COALESCE(is_active, 1) = 1'), 'href' => base_url('admin/services')],
                ],
            ];
        }

        if (!$isAdminOrManager && $this->hasAnyRoleId($context, [3])) {
            $own = array_values(array_filter($records, fn (array $row): bool => (int) ($row['assigned_user_id'] ?? 0) === $this->currentUserId()));
            $analytics = $this->analyticsFromRecords($own);
            $sections[] = [
                'title' => 'Executive Workload',
                'subtitle' => 'Orders currently assigned to your account.',
                'statCards' => [
                    ['label' => 'Assigned', 'value' => $analytics['total_records'], 'href' => base_url('admin/orders')],
                    ['label' => 'Outstanding', 'value' => $analytics['outstanding_records'], 'href' => base_url('admin/orders')],
                    ['label' => 'Completed', 'value' => $analytics['completed_records'], 'href' => base_url('admin/orders')],
                    ['label' => 'Completion Rate', 'value' => number_format((float) $analytics['completion_rate'], 1) . '%', 'href' => base_url('admin/orders')],
                ],
            ];
        }

        if (!$isAdminOrManager && $this->hasAnyRoleId($context, [4])) {
            $own = array_values(array_filter($records, fn (array $row): bool => (int) ($row['partner_id'] ?? 0) === $this->currentUserId()));
            $analytics = $this->analyticsFromRecords($own);
            $sections[] = [
                'title' => 'Partner Performance',
                'subtitle' => 'Your order value, savings and payment performance.',
                'statCards' => [
                    ['label' => 'Applications', 'value' => $analytics['total_records'], 'href' => base_url('client/orders')],
                    ['label' => 'Payable', 'value' => $this->formatCompactMoney((float) $analytics['payable_amount']), 'href' => base_url('client/orders')],
                    ['label' => 'Discount Saved', 'value' => $this->formatCompactMoney((float) $analytics['discount_amount']), 'href' => base_url('client/orders')],
                    ['label' => 'Completed', 'value' => $analytics['completed_records'], 'href' => base_url('client/orders')],
                ],
            ];
        }

        if (!$isAdminOrManager && $this->hasAnyRoleId($context, [5])) {
            $clientId = $this->resolveClientIdForCurrentUser();
            $own = array_values(array_filter($records, static fn (array $row): bool => (int) ($row['client_id'] ?? 0) === $clientId));
            $analytics = $this->analyticsFromRecords($own);
            $sections[] = [
                'title' => 'Client Service Progress',
                'subtitle' => 'Your application and payment progress.',
                'statCards' => [
                    ['label' => 'Applications', 'value' => $analytics['total_records'], 'href' => base_url('client/orders')],
                    ['label' => 'Paid Value', 'value' => $this->formatCompactMoney((float) $analytics['paid_amount']), 'href' => base_url('client/orders')],
                    ['label' => 'Outstanding', 'value' => $this->formatCompactMoney((float) $analytics['outstanding_amount']), 'href' => base_url('client/orders')],
                    ['label' => 'Completed', 'value' => $analytics['completed_records'], 'href' => base_url('client/orders')],
                ],
            ];
        }

        return $sections;
    }

    private function quickActions(array $context): array
    {
        if ($this->hasAnyRoleId($context, [1, 2])) {
            return [
                ['label' => 'Manage Orders', 'href' => base_url('admin/orders'), 'icon' => 'orders'],
                ['label' => 'Advanced Reports', 'href' => base_url('reports/advanced'), 'icon' => 'reports'],
                ['label' => 'Manage Users & Roles', 'href' => base_url('admin/users/roles'), 'icon' => 'users'],
                ['label' => 'Manage Services', 'href' => base_url('admin/services'), 'icon' => 'services'],
            ];
        }

        $actions = [];

        if ($this->hasAnyRoleId($context, [3])) {
            $actions[] = ['label' => 'My Assigned Orders', 'href' => base_url('admin/orders'), 'icon' => 'orders'];
            $actions[] = ['label' => 'My Reports', 'href' => base_url('reports/advanced'), 'icon' => 'reports'];
        }

        if ($this->hasAnyRoleId($context, [4])) {
            $actions[] = ['label' => 'Place New Order', 'href' => base_url('partner/services'), 'icon' => 'add'];
            $actions[] = ['label' => 'My Partner Orders', 'href' => base_url('client/orders'), 'icon' => 'orders'];
            $actions[] = ['label' => 'My Reports', 'href' => base_url('reports/advanced'), 'icon' => 'reports'];
        }

        if ($this->hasAnyRoleId($context, [5])) {
            $actions[] = ['label' => 'New Service Order', 'href' => base_url('services'), 'icon' => 'add'];
            $actions[] = ['label' => 'My Applications', 'href' => base_url('client/orders'), 'icon' => 'orders'];
            $actions[] = ['label' => 'My Profile', 'href' => base_url('client/profile'), 'icon' => 'profile'];
        }

        return $this->dedupeActions($actions);
    }


    private function currentRoleContext(): array
    {
        if ($this->roleContextCache !== null) {
            return $this->roleContextCache;
        }

        $resolvedUserId = $this->currentUserId();
        $roles = $resolvedUserId > 0
            ? $this->roleRowsForUser($resolvedUserId)
            : [];

        if ($resolvedUserId > 0) {
            $_SESSION['user_id'] = $resolvedUserId;
        }

        $roleIds = [];
        $roleKeys = [];
        $roleNames = [];
        $permissions = [];

        foreach ($roles as $role) {
            $roleId = (int) ($role['id'] ?? $role['role_id'] ?? 0);
            $name = trim((string) ($role['name'] ?? ''));
            $slug = trim((string) ($role['slug'] ?? $name));
            $key = $this->normalizeRoleKey($slug !== '' ? $slug : $name);

            if ($roleId > 0) {
                $roleIds[$roleId] = $roleId;
            }
            if ($key !== '') {
                $roleKeys[$key] = $key;
            }
            if ($name !== '') {
                $roleNames[$name] = $name;
            }

            foreach ($this->decodePermissions($role['permissions_json'] ?? []) as $permission) {
                $permissions[$permission] = $permission;
            }
        }

        $this->roleContextCache = [
            'user_id' => $resolvedUserId > 0 ? $resolvedUserId : $this->currentUserId(),
            'role_ids' => array_values($roleIds),
            'role_keys' => array_values($roleKeys),
            'role_names' => array_values($roleNames),
            'permissions' => array_values($permissions),
            'permission_map' => array_fill_keys(array_values($permissions), true),
        ];

        return $this->roleContextCache;
    }

    private function roleRowsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            if (!$this->tableExists('user_roles') || !$this->tableExists('users') || !$this->tableExists('roles')) {
                return [];
            }

            return $this->db()->fetchAll(
                'SELECT DISTINCT
                    r.id,
                    r.name,
                    r.slug,
                    r.permissions_json
                 FROM user_roles ur
                 INNER JOIN users u ON u.id = ur.user_id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = :user_id
                   AND COALESCE(u.is_active, 1) = 1
                 ORDER BY r.id ASC',
                ['user_id' => $userId]
            ) ?: [];
        } catch (\Throwable $e) {
            error_log('Dashboard user_roles lookup failed for user ' . $userId . ': ' . $e->getMessage());
            return [];
        }
    }

    private function currentUserIdCandidates(): array
    {
        $user = $this->currentUser();

        $values = [
            $_SESSION['user_id'] ?? null,
            $_SESSION['auth_user']['user_id'] ?? null,
            $_SESSION['user']['user_id'] ?? null,
            $user['user_id'] ?? null,
            $_SESSION['auth_id'] ?? null,
            $_SESSION['auth_user']['id'] ?? null,
            $_SESSION['user']['id'] ?? null,
            $user['id'] ?? null,
        ];

        $ids = [];
        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function resolveUserIdFromIdentity(): int
    {
        $user = $this->currentUser();

        $email = trim((string) (
            $user['email']
            ?? $_SESSION['email']
            ?? $_SESSION['auth_user']['email']
            ?? $_SESSION['user']['email']
            ?? ''
        ));

        $phone = trim((string) (
            $user['phone']
            ?? $user['mobile']
            ?? $_SESSION['phone']
            ?? $_SESSION['mobile']
            ?? $_SESSION['auth_user']['phone']
            ?? $_SESSION['auth_user']['mobile']
            ?? $_SESSION['user']['phone']
            ?? $_SESSION['user']['mobile']
            ?? ''
        ));

        if ($email === '' && $phone === '') {
            return 0;
        }

        try {
            if ($email !== '') {
                $row = $this->db()->fetch(
                    'SELECT id
                     FROM users
                     WHERE LOWER(email) = LOWER(:identity_email)
                       AND COALESCE(is_active, 1) = 1
                     ORDER BY id DESC
                     LIMIT 1',
                    ['identity_email' => $email]
                );

                if ((int) ($row['id'] ?? 0) > 0) {
                    return (int) $row['id'];
                }
            }

            if ($phone !== '') {
                $normalizedPhone = preg_replace('/\D+/', '', $phone) ?: $phone;
                $row = $this->db()->fetch(
                    "SELECT id
                     FROM users
                     WHERE (
                        phone = :identity_phone
                        OR REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', '') LIKE :identity_phone_like
                     )
                       AND COALESCE(is_active, 1) = 1
                     ORDER BY id DESC
                     LIMIT 1",
                    [
                        'identity_phone' => $phone,
                        'identity_phone_like' => '%' . substr($normalizedPhone, -10),
                    ]
                );

                return (int) ($row['id'] ?? 0);
            }
        } catch (\Throwable $e) {
            error_log('Dashboard identity-to-user lookup failed: ' . $e->getMessage());
        }

        return 0;
    }


    private function currentUser(): array
    {
        try {
            if (function_exists('auth_user')) {
                $user = auth_user();
                if (is_array($user) && $user !== []) {
                    return $user;
                }
            }
        } catch (\Throwable $e) {
            error_log('Dashboard auth_user lookup failed: ' . $e->getMessage());
        }

        if (is_array($_SESSION['auth_user'] ?? null)) {
            return $_SESSION['auth_user'];
        }

        if (is_array($_SESSION['user'] ?? null)) {
            return $_SESSION['user'];
        }

        return [
            'id' => (int) ($_SESSION['user_id'] ?? 0),
            'email' => (string) ($_SESSION['email'] ?? ''),
            'phone' => (string) ($_SESSION['phone'] ?? $_SESSION['mobile'] ?? ''),
        ];
    }

    private function currentUserId(): int
    {
        if (is_array($this->roleContextCache ?? null) && (int) ($this->roleContextCache['user_id'] ?? 0) > 0) {
            return (int) $this->roleContextCache['user_id'];
        }

        $user = $this->currentUser();
        $candidates = [
            $_SESSION['user_id'] ?? null,
            $user['id'] ?? null,
            $_SESSION['auth_user']['id'] ?? null,
            $_SESSION['user']['id'] ?? null,
            $_SESSION['auth_user_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $userId = (int) $candidate;
            if ($userId > 0) {
                return $userId;
            }
        }

        return 0;
    }

    private function resolveClientIdForCurrentUser(): int
    {
        $userId = $this->currentUserId();
        if ($userId <= 0 || !$this->tableExists('users')) {
            return 0;
        }

        $columns = $this->tableColumns('users');
        if (!in_array('client_id', $columns, true)) {
            return 0;
        }

        try {
            $row = $this->db()->fetch(
                'SELECT client_id FROM users WHERE id = :id LIMIT 1',
                ['id' => $userId]
            );

            return max(0, (int) ($row['client_id'] ?? 0));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function partnerFilterOptions(): array
    {
        return $this->usersByRole(4);
    }

    private function executiveFilterOptions(): array
    {
        return $this->usersByRole(3);
    }

    private function usersByRole(int $roleId): array
    {
        if (!$this->tableExists('users') || !$this->tableExists('user_roles')) {
            return [];
        }

        try {
            return $this->db()->fetchAll(
                'SELECT DISTINCT u.id, u.name, u.email, u.phone
                 FROM users u
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 WHERE ur.role_id = :role_id
                   AND COALESCE(u.is_active, 1) = 1
                 ORDER BY COALESCE(NULLIF(u.name, ""), u.email, u.id) ASC',
                ['role_id' => $roleId]
            ) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function countUsersByRole(int $roleId): int
    {
        if (!$this->tableExists('user_roles')) {
            return 0;
        }

        try {
            $row = $this->db()->fetch(
                'SELECT COUNT(DISTINCT user_id) AS total FROM user_roles WHERE role_id = :role_id',
                ['role_id' => $roleId]
            );
            return (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function hasAnyRoleId(array $context, array $roleIds): bool
    {
        $current = array_map('intval', $context['role_ids'] ?? []);

        foreach ($roleIds as $roleId) {
            if (in_array((int) $roleId, $current, true)) {
                return true;
            }
        }

        return false;
    }

    private function decodePermissions(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $raw);
        }

        if (!is_array($raw)) {
            return [];
        }

        $permissions = [];
        foreach ($raw as $permission) {
            $permission = trim((string) $permission);
            if ($permission !== '') {
                $permissions[$permission] = $permission;
            }
        }

        return array_values($permissions);
    }

    private function normalizeRoleKey(string $role): string
    {
        $role = strtolower(trim($role));
        $role = str_replace([' ', '_'], '-', $role);
        return preg_replace('/[^a-z0-9\-]+/', '', $role) ?: '';
    }


    private function normalizePaymentStatus(string $status): string
    {
        $status = $this->normalizeStatus($status);

        return match ($status) {
            'approved' => 'verified',
            'waiting_for_payment', 'pending_payment' => 'pending',
            'payment_submitted', 'submitted_for_review' => 'pending_review',
            'success', 'completed_payment' => 'paid',
            default => $status !== '' ? $status : 'pending',
        };
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $status = str_replace([' ', '-'], '_', $status);
        return $status !== '' ? $status : 'unknown';
    }

    private function labelFromStatus(string $status): string
    {
        return self::PAYMENT_STATUSES[$status] ?? ucwords(str_replace('_', ' ', $status));
    }

    private function emptyMonthlySeries(int $months): array
    {
        $series = [];
        $anchor = new \DateTimeImmutable('first day of this month');

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $date = $anchor->modify('-' . $offset . ' months');
            $key = $date->format('Y-m');
            $series[$key] = [
                'key' => $key,
                'label' => $date->format('M y'),
                'orders' => 0,
                'amount' => 0.0,
            ];
        }

        return $series;
    }

    private function monthKey(string $value): string
    {
        $time = strtotime($value);
        return $time ? date('Y-m', $time) : '';
    }

    private function validDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function periodLabel(array $filters): string
    {
        $from = (string) ($filters['date_from'] ?? '');
        $to = (string) ($filters['date_to'] ?? '');

        if ($from === '' && $to === '') {
            return 'All-time analytics';
        }

        if ($from !== '' && $to !== '') {
            return date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to));
        }

        if ($from !== '') {
            return 'From ' . date('d M Y', strtotime($from));
        }

        return 'Up to ' . date('d M Y', strtotime($to));
    }

    private function dedupeActions(array $actions): array
    {
        $unique = [];
        foreach ($actions as $action) {
            $key = (string) ($action['label'] ?? '') . '|' . (string) ($action['href'] ?? '');
            $unique[$key] = $action;
        }
        return array_values($unique);
    }

    private function firstExistingColumn(array $columns, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }
        return '';
    }

    private function countRows(string $table, string $where = '1=1', array $params = []): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }

        try {
            $row = $this->db()->fetch(
                'SELECT COUNT(*) AS total FROM `' . str_replace('`', '', $table) . '` WHERE ' . $where,
                $params
            );
            return (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }


    private function tableExists(string $table): bool
    {
        $table = preg_replace('/[^A-Za-z0-9_]/', '', $table) ?: '';
        if ($table === '') {
            return false;
        }

        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        try {
            $row = $this->db()->fetch(
                'SELECT 1 AS found
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :table_name
                 LIMIT 1',
                ['table_name' => $table]
            );

            return $this->tableExistsCache[$table] = is_array($row) && (int) ($row['found'] ?? 0) === 1;
        } catch (\Throwable $e) {
            /*
             * Direct DESCRIBE fallback works on MariaDB installations where
             * information_schema access is restricted.
             */
            try {
                $rows = $this->db()->fetchAll('DESCRIBE `' . $table . '`') ?: [];
                return $this->tableExistsCache[$table] = $rows !== [];
            } catch (\Throwable $fallbackError) {
                error_log('Dashboard table lookup failed for ' . $table . ': ' . $fallbackError->getMessage());
                return $this->tableExistsCache[$table] = false;
            }
        }
    }

    private function tableColumns(string $table): array
    {
        if (isset($this->tableColumnsCache[$table])) {
            return $this->tableColumnsCache[$table];
        }

        if (!$this->tableExists($table)) {
            return $this->tableColumnsCache[$table] = [];
        }

        try {
            $rows = $this->db()->fetchAll('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`') ?: [];
            $columns = [];
            foreach ($rows as $row) {
                if (!empty($row['Field'])) {
                    $columns[] = (string) $row['Field'];
                }
            }
            return $this->tableColumnsCache[$table] = $columns;
        } catch (\Throwable $e) {
            return $this->tableColumnsCache[$table] = [];
        }
    }

    private function formatCompactMoney(float $amount): string
    {
        if (function_exists('format_money')) {
            return (string) format_money($amount);
        }

        $absolute = abs($amount);
        if ($absolute >= 10000000) {
            return '₹' . number_format($amount / 10000000, 2) . ' Cr';
        }
        if ($absolute >= 100000) {
            return '₹' . number_format($amount / 100000, 2) . ' L';
        }
        if ($absolute >= 1000) {
            return '₹' . number_format($amount / 1000, 1) . ' K';
        }

        return '₹' . number_format($amount, 2);
    }

    private function denyDashboardAccess(): never
    {
        http_response_code(403);
        echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>403 Forbidden</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a}.box{width:min(440px,90vw);padding:36px;border:1px solid #e2e8f0;border-radius:24px;background:#fff;box-shadow:0 24px 70px rgba(15,23,42,.08);text-align:center}h1{font-size:42px;margin:0 0 8px}p{color:#64748b;line-height:1.6}</style></head><body><div class="box"><h1>403</h1><p>No active role was found for this account. Please check the <strong>user_roles</strong> assignment for this session user.</p></div></body></html>';
        exit;
    }


    private function db(): Database
    {
        if ($this->database instanceof Database) {
            return $this->database;
        }

        if (function_exists('app')) {
            try {
                $shared = app('db');
                if ($shared instanceof Database) {
                    return $this->database = $shared;
                }
            } catch (\Throwable $e) {
                // Continue with the controller-local database configuration.
            }
        }

        return $this->database = new Database($this->databaseConfig());
    }

    private function databaseConfig(): array
    {
        $config = [];

        if (function_exists('config')) {
            foreach (['db', 'database'] as $configKey) {
                try {
                    $raw = config($configKey);
                    if (is_array($raw) && $raw !== []) {
                        $config = $this->normalizeDatabaseConfig($raw);
                        break;
                    }
                } catch (\Throwable $e) {
                    $config = [];
                }
            }
        }

        foreach ([
            dirname(__DIR__) . '/config/config.php',
            dirname(__DIR__) . '/config/database.php',
            dirname(__DIR__, 2) . '/app/config/config.php',
            dirname(__DIR__, 2) . '/app/config/database.php',
            dirname(__DIR__, 2) . '/config/config.php',
            dirname(__DIR__, 2) . '/config/database.php',
        ] as $configFile) {
            if ($config !== [] || !is_file($configFile)) {
                continue;
            }

            try {
                $raw = require $configFile;
                if (!is_array($raw)) {
                    continue;
                }
                if (isset($raw['db']) && is_array($raw['db'])) {
                    $config = $this->normalizeDatabaseConfig($raw['db']);
                    break;
                }
                if (isset($raw['database']) && is_array($raw['database'])) {
                    $config = $this->normalizeDatabaseConfig($raw['database']);
                    break;
                }
                if (isset($raw['connections']) || isset($raw['driver']) || isset($raw['host'])) {
                    $config = $this->normalizeDatabaseConfig($raw);
                    break;
                }
            } catch (\Throwable $e) {
                $config = [];
            }
        }

        if ($config === []) {
            $env = static function (string $key, mixed $default = null): mixed {
                if (function_exists('env')) {
                    return env($key, $default);
                }
                $value = function_exists('getenv') ? getenv($key) : false;
                return $value !== false ? $value : $default;
            };

            $config = [
                'driver' => (string) $env('DB_DRIVER', 'mysql'),
                'host' => (string) $env('DB_HOST', '127.0.0.1'),
                'port' => (int) $env('DB_PORT', 3306),
                'database' => (string) $env('DB_DATABASE', $env('DB_NAME', 'taxsathi2')),
                'charset' => (string) $env('DB_CHARSET', 'utf8mb4'),
                'username' => (string) $env('DB_USERNAME', $env('DB_USER', 'taxsathi2')),
                'password' => (string) $env('DB_PASSWORD', $env('DB_PASS', '')),
            ];
        }

        if (($config['database'] ?? '') === '') {
            throw new RuntimeException('Database configuration not found.');
        }

        return $config;
    }

    private function normalizeDatabaseConfig(array $config): array
    {
        if (isset($config['connections']) && is_array($config['connections'])) {
            $default = (string) ($config['default'] ?? array_key_first($config['connections']));
            $connection = $config['connections'][$default] ?? [];
            if (is_array($connection) && $connection !== []) {
                $config = $connection;
            }
        }

        return [
            'driver' => (string) ($config['driver'] ?? $config['type'] ?? 'mysql'),
            'host' => (string) ($config['host'] ?? $config['hostname'] ?? '127.0.0.1'),
            'port' => (int) ($config['port'] ?? 3306),
            'database' => (string) ($config['database'] ?? $config['dbname'] ?? $config['name'] ?? ''),
            'charset' => (string) ($config['charset'] ?? 'utf8mb4'),
            'username' => (string) ($config['username'] ?? $config['user'] ?? ''),
            'password' => (string) ($config['password'] ?? $config['pass'] ?? ''),
        ];
    }
}