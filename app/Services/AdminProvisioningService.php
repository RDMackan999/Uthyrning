<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Company;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Repositories\CompanyRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Provisions the first local administrator without creating sessions.
 */
final class AdminProvisioningService
{
    public function __construct(
        private readonly UserRepository $userRepository = new UserRepository(),
        private readonly RoleRepository $roleRepository = new RoleRepository(),
        private readonly OrganizationRepository $organizationRepository = new OrganizationRepository(),
        private readonly CompanyRepository $companyRepository = new CompanyRepository(),
        private readonly PasswordService $passwordService = new PasswordService(),
        private readonly AuditService $auditService = new AuditService()
    ) {
    }

    /**
     * Provision the first admin user and related organization records.
     *
     * @return array{organization: Organization, company: Company, user: User, role: Role}
     */
    public function provision(
        string $email,
        string $displayName,
        string $password,
        string $organizationName,
        string $companyName
    ): array {
        $normalizedEmail = $this->normalizeEmail($email);
        $this->validateInput($normalizedEmail, $displayName, $password, $organizationName, $companyName);

        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            if ($this->userRepository->emailExists($normalizedEmail)) {
                throw new RuntimeException('A user with this email already exists.');
            }

            $adminRole = $this->roleRepository->findSystemAdminRole();
            if ($adminRole === null) {
                throw new RuntimeException('Seeded system_admin role is missing. Run or verify identity seed data before creating the first admin.');
            }

            [$firstName, $lastName] = $this->splitDisplayName($displayName);
            $passwordHash = $this->passwordService->hash($password);

            $organization = $this->organizationRepository->createOrganization($organizationName);
            $organizationId = $this->modelId($organization);
            $company = $this->companyRepository->createForOrganization($organizationId, $companyName);
            $companyId = $this->modelId($company);
            $user = $this->userRepository->createLocalUser($normalizedEmail, $passwordHash, $firstName, $lastName);
            $userId = $this->modelId($user);
            $roleId = $this->modelId($adminRole);

            $this->companyRepository->attachUser($companyId, $userId);
            $this->roleRepository->assignToUser($userId, $roleId, null);

            $this->auditService->record(
                'admin_user_provisioned',
                $userId,
                'users',
                $userId,
                null,
                'CLI',
                [
                    'organization_id' => $organizationId,
                    'company_id' => $companyId,
                    'role_key' => 'system_admin',
                    'source' => 'database/create-admin.php',
                ]
            );

            $pdo->commit();

            return [
                'organization' => $organization,
                'company' => $company,
                'user' => $user,
                'role' => $adminRole,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        } finally {
            unset($passwordHash);
        }
    }

    /**
     * Normalize email addresses before lookup and storage.
     */
    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Validate all CLI input before any database writes.
     */
    private function validateInput(
        string $normalizedEmail,
        string $displayName,
        string $password,
        string $organizationName,
        string $companyName
    ): void {
        if (!filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email address is invalid.');
        }

        if (trim($displayName) === '') {
            throw new InvalidArgumentException('Display name is required.');
        }

        if (trim($organizationName) === '') {
            throw new InvalidArgumentException('Organization name is required.');
        }

        if (trim($companyName) === '') {
            throw new InvalidArgumentException('Company name is required.');
        }

        $passwordErrors = $this->passwordService->validatePolicy($password);
        if ($passwordErrors !== []) {
            throw new InvalidArgumentException(implode(' ', $passwordErrors));
        }
    }

    /**
     * Split a display name into the existing users.first_name/last_name columns.
     *
     * @return array{0: string, 1: string|null}
     */
    private function splitDisplayName(string $displayName): array
    {
        $parts = preg_split('/\s+/', trim($displayName), 2);

        if ($parts === false || $parts === []) {
            return [trim($displayName), null];
        }

        return [
            $parts[0],
            isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null,
        ];
    }

    /**
     * Read a persisted model id as an integer.
     */
    private function modelId(Organization|Company|User|Role $model): int
    {
        $id = $model->toArray()['id'] ?? null;

        if (!is_numeric($id)) {
            throw new RuntimeException('Provisioned model is missing an id.');
        }

        return (int) $id;
    }
}
