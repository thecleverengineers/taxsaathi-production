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
 * Executive self-service payout tracker and payment destination profile.
 *
 * The session user is authorized only when user_roles contains role_id 3.
 * All payout rows are scoped to that same session user id.
 */
final class ExecutivePayoutController extends Controller
{
    private const EXECUTIVE_ROLE_ID = 3;
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

    private function hasExecutiveRole(): bool
    {
        $context = rbac_context($this->currentUserId(), true);
        $roleIds = is_array($context['role_ids'] ?? null) ? $context['role_ids'] : [];

        return in_array(self::EXECUTIVE_ROLE_ID, array_map('intval', $roleIds), true);
    }

    private function requireExecutive(string $permission): void
    {
        require_auth();

        $userId = $this->currentUserId();

        if (
            $userId <= 0
            || !$this->hasExecutiveRole()
            || !rbac_can($permission, $userId)
        ) {
            http_response_code(403);
            exit('Executive access required. Please check user_roles and Executive payout permissions.');
        }
    }

    private function payoutTableReady(): bool
    {
        return rbac_table_exists('executive_order_payouts');
    }

    private function salaryTableReady(): bool
    {
        return rbac_table_exists('executive_salary_payments');
    }

    private function paymentProfileTableReady(): bool
    {
        return rbac_table_exists('executive_payment_profiles');
    }

    private function executive(): ?array
    {
        return $this->db()->fetch(
            'SELECT
                u.id,
                u.name,
                u.phone,
                u.email,
                u.is_active
             FROM users u
             INNER JOIN user_roles ur
                ON ur.user_id = u.id
               AND ur.role_id = :executive_role_id
             WHERE u.id = :user_id
             LIMIT 1',
            [
                'executive_role_id' => self::EXECUTIVE_ROLE_ID,
                'user_id' => $this->currentUserId(),
            ]
        );
    }

    private function paymentProfile(): ?array
    {
        if (!$this->paymentProfileTableReady()) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT *
             FROM executive_payment_profiles
             WHERE executive_user_id = :executive_user_id
             LIMIT 1',
            ['executive_user_id' => $this->currentUserId()]
        );
    }

    private function payoutRows(): array
    {
        if (!$this->payoutTableReady()) {
            return [];
        }

        return $this->db()->fetchAll(
            'SELECT
                p.id,
                p.amount,
                p.currency,
                p.status,
                p.payment_method,
                p.payment_reference,
                p.notes,
                p.paid_at,
                o.id AS order_id,
                o.order_no,
                o.status AS order_status,
                o.completed_at,
                COALESCE(NULLIF(c.company_name, \'\'), NULLIF(c.name, \'\'), \'Client\') AS client_name,
                COALESCE(NULLIF(s.title, \'\'), \'Service\') AS service_title,
                COALESCE(NULLIF(payer.name, \'\'), CONCAT(\'Administrator #\', p.paid_by_user_id)) AS paid_by_name
             FROM executive_order_payouts p
             LEFT JOIN orders o ON o.id = p.order_id
             LEFT JOIN clients c ON c.id = o.client_id
             LEFT JOIN services s ON s.id = o.service_id
             LEFT JOIN users payer ON payer.id = p.paid_by_user_id
             WHERE p.executive_user_id = :executive_user_id
             ORDER BY p.paid_at DESC, p.id DESC
             LIMIT 250',
            ['executive_user_id' => $this->currentUserId()]
        ) ?: [];
    }

    private function salaryRows(): array
    {
        if (!$this->salaryTableReady()) {
            return [];
        }

        return $this->db()->fetchAll(
            'SELECT
                sp.period_type,
                sp.period_start,
                sp.period_end,
                sp.amount,
                sp.currency,
                sp.status,
                sp.payment_method,
                sp.payment_reference,
                sp.paid_at,
                COALESCE(NULLIF(payer.name, \'\'), CONCAT(\'Administrator #\', sp.paid_by_user_id)) AS paid_by_name
             FROM executive_salary_payments sp
             LEFT JOIN users payer ON payer.id = sp.paid_by_user_id
             WHERE sp.executive_user_id = :executive_user_id
             ORDER BY sp.period_start DESC, sp.id DESC
             LIMIT 100',
            ['executive_user_id' => $this->currentUserId()]
        ) ?: [];
    }

    private function cleanText(mixed $value, int $maxLength): string
    {
        $value = trim((string) $value);
        return $value === '' ? '' : substr($value, 0, $maxLength);
    }

    private function validateProfile(array $profile): void
    {
        $upiId = (string) ($profile['upi_id'] ?? '');
        $ifscCode = (string) ($profile['ifsc_code'] ?? '');
        $accountNumber = (string) ($profile['account_number'] ?? '');
        $confirmed = (int) ($profile['payment_details_confirmed'] ?? 0) === 1;

        if ($upiId !== ''
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,118}@[A-Za-z0-9._-]{2,119}$/', $upiId) !== 1
        ) {
            throw new DomainException('Enter a valid UPI ID, for example name@bank.');
        }

        if ($ifscCode !== '' && preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifscCode) !== 1) {
            throw new DomainException('Enter a valid 11-character IFSC code.');
        }

        if ($accountNumber !== ''
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9 -]{3,79}$/', $accountNumber) !== 1
        ) {
            throw new DomainException('Enter a valid bank account number.');
        }

        $hasUpi = $upiId !== '';
        $hasBank = trim((string) ($profile['bank_name'] ?? '')) !== ''
            && trim((string) ($profile['account_holder_name'] ?? '')) !== ''
            && $accountNumber !== ''
            && $ifscCode !== '';

        if ($confirmed && !$hasUpi && !$hasBank) {
            throw new DomainException('Add a UPI ID or complete bank details before marking payment details ready.');
        }

        $preferredMethod = (string) ($profile['preferred_method'] ?? 'bank_transfer');
        if ($confirmed && $preferredMethod === 'upi' && !$hasUpi) {
            throw new DomainException('Add a UPI ID or choose another preferred payment method.');
        }

        if ($confirmed && $preferredMethod === 'bank_transfer' && !$hasBank) {
            throw new DomainException('Complete bank details or choose UPI/cash as the preferred payment method.');
        }
    }

    public function index(): void
    {
        $this->requireExecutive('executive_payouts.own.view');

        $executive = $this->executive();
        if (!is_array($executive)) {
            http_response_code(403);
            exit('Executive account not found.');
        }

        $rows = $this->payoutRows();
        $salaryRows = $this->salaryRows();
        $paidAmount = 0.0;

        foreach ($rows as $row) {
            if (strtolower(trim((string) ($row['status'] ?? ''))) === 'paid') {
                $paidAmount += (float) ($row['amount'] ?? 0);
            }
        }

        $this->view('executive/payouts', [
            'title' => 'My Payouts – Tax Saathi',
            'executive' => $executive,
            'rows' => $rows,
            'salaryRows' => $salaryRows,
            'profile' => $this->paymentProfile() ?: [],
            'payoutTableReady' => $this->payoutTableReady(),
            'salaryTableReady' => $this->salaryTableReady(),
            'paymentProfileTableReady' => $this->paymentProfileTableReady(),
            'paymentMethods' => self::PAYMENT_METHODS,
            'stats' => [
                'paid_count' => count($rows),
                'paid_amount' => $paidAmount,
                'salary_count' => count($salaryRows),
            ],
        ], 'layouts/dashboard');
    }

    public function savePaymentProfile(): void
    {
        $this->requireExecutive('executive_payment_profile.manage');
        verify_csrf();

        if (!$this->paymentProfileTableReady()) {
            flash('error', 'Executive payment profile storage is not installed. Run the latest migration first.');
            redirect('executive/payouts');
        }

        $preferredMethod = strtolower(trim((string) input('preferred_method', 'bank_transfer')));
        if (!in_array($preferredMethod, self::PAYMENT_METHODS, true)) {
            $preferredMethod = 'bank_transfer';
        }

        $profile = [
            'upi_id' => $this->cleanText(input('upi_id', ''), 120),
            'bank_name' => $this->cleanText(input('bank_name', ''), 191),
            'account_holder_name' => $this->cleanText(input('account_holder_name', ''), 191),
            'account_number' => $this->cleanText(input('account_number', ''), 80),
            'ifsc_code' => strtoupper($this->cleanText(input('ifsc_code', ''), 30)),
            'bank_branch' => $this->cleanText(input('bank_branch', ''), 191),
            'preferred_method' => $preferredMethod,
            'payment_details_confirmed' => (string) input('payment_details_confirmed', '') === '1' ? 1 : 0,
        ];

        try {
            $this->validateProfile($profile);

            $now = date('Y-m-d H:i:s');
            $this->db()->execute(
                'INSERT INTO executive_payment_profiles
                (
                    executive_user_id,
                    upi_id,
                    bank_name,
                    account_holder_name,
                    account_number,
                    ifsc_code,
                    bank_branch,
                    preferred_method,
                    payment_details_confirmed,
                    updated_by_user_id,
                    created_at,
                    updated_at
                )
                VALUES
                (
                    :executive_user_id,
                    :upi_id,
                    :bank_name,
                    :account_holder_name,
                    :account_number,
                    :ifsc_code,
                    :bank_branch,
                    :preferred_method,
                    :payment_details_confirmed,
                    :updated_by_user_id,
                    :created_at,
                    :updated_at
                )
                ON DUPLICATE KEY UPDATE
                    upi_id = VALUES(upi_id),
                    bank_name = VALUES(bank_name),
                    account_holder_name = VALUES(account_holder_name),
                    account_number = VALUES(account_number),
                    ifsc_code = VALUES(ifsc_code),
                    bank_branch = VALUES(bank_branch),
                    preferred_method = VALUES(preferred_method),
                    payment_details_confirmed = VALUES(payment_details_confirmed),
                    updated_by_user_id = VALUES(updated_by_user_id),
                    updated_at = VALUES(updated_at)',
                array_merge(
                    $profile,
                    [
                        'executive_user_id' => $this->currentUserId(),
                        'updated_by_user_id' => $this->currentUserId(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                )
            );

            activity_log(
                $this->currentUserId(),
                null,
                'executive.payment_profile.updated',
                'Executive payment destination details were updated.',
                [
                    'preferred_method' => $preferredMethod,
                    'payment_details_confirmed' => (int) $profile['payment_details_confirmed'],
                    'has_upi' => trim((string) $profile['upi_id']) !== '',
                    'has_bank_details' => trim((string) $profile['bank_name']) !== ''
                        && trim((string) $profile['account_number']) !== '',
                ]
            );

            flash('success', 'Your UPI and bank details were saved. Administrators can now use the confirmed destination for payouts.');
        } catch (DomainException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Executive payment profile update failed: ' . $e->getMessage());
            flash('error', 'Payment details could not be saved.');
        }

        redirect('executive/payouts#payment-details');
    }
}
