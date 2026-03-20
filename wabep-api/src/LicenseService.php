<?php

declare(strict_types=1);

namespace WABEP;

use PDO;

class LicenseService
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    public function check(array $input): array
    {
        $licenseKey = $this->clean($input['license_key'] ?? '');
        $domain     = $this->normalizeDomain($input['domain'] ?? '');
        $plugin     = $this->clean($input['plugin'] ?? '');

        if ($licenseKey === '' || $domain === '') {
            return $this->signed([
                'ok'    => false,
                'valid' => false,
                'error' => 'license_key and domain are required',
            ]);
        }

        if ($this->isTestKey($licenseKey)) {
            $plan = (string)$this->config['test_keys'][$licenseKey];
            return $this->buildSuccessResponse($licenseKey, $domain, $plugin, $plan, 'active', null, true);
        }

        $license = $this->findLicense($licenseKey);

        if (!$license) {
            return $this->signed([
                'ok'    => false,
                'valid' => false,
                'error' => 'License not found',
            ]);
        }

        if (($license['status'] ?? '') !== 'active') {
            return $this->signed([
                'ok'    => false,
                'valid' => false,
                'error' => 'License is not active',
            ]);
        }

        if (!empty($license['expires_at']) && strtotime((string)$license['expires_at']) < time()) {
            return $this->signed([
                'ok'    => false,
                'valid' => false,
                'error' => 'License expired',
            ]);
        }

        if (!$this->isDomainAllowed((int)$license['id'], $domain)) {
            return $this->signed([
                'ok'    => false,
                'valid' => false,
                'error' => 'Domain not activated for this license',
            ]);
        }

        $this->touchLicense((int)$license['id']);
        $this->touchActivation((int)$license['id'], $domain);

        return $this->buildSuccessResponse(
            $licenseKey,
            $domain,
            $plugin,
            (string)$license['plan'],
            (string)$license['status'],
            !empty($license['expires_at']) ? (string)$license['expires_at'] : null,
            false,
            $license
        );
    }

    public function activate(array $input): array
    {
        $licenseKey = $this->clean($input['license_key'] ?? '');
        $domain     = $this->normalizeDomain($input['domain'] ?? '');
        $plugin     = $this->clean($input['plugin'] ?? '');
        $version    = $this->clean($input['version'] ?? '');

        if ($licenseKey === '' || $domain === '') {
            return $this->signed([
                'ok'    => false,
                'valid' => false,
                'error' => 'license_key and domain are required',
            ]);
        }

        if ($this->isTestKey($licenseKey)) {
            $plan = (string)$this->config['test_keys'][$licenseKey];
            return $this->buildSuccessResponse($licenseKey, $domain, $plugin, $plan, 'active', null, true);
        }

        $license = $this->findLicense($licenseKey);

        if (!$license) {
            return $this->signed([
                'ok'    => false,
                'valid' => false,
                'error' => 'License not found',
            ]);
        }

        if (($license['status'] ?? '') !== 'active') {
            return $this->signed([
                'ok'    => false,
                'valid' => false,
                'error' => 'License is not active',
            ]);
        }

        if (!empty($license['expires_at']) && strtotime((string)$license['expires_at']) < time()) {
            return $this->signed([
                'ok'    => false,
                'valid' => false,
                'error' => 'License expired',
            ]);
        }

        if (!$this->canActivateDomain((int)$license['id'], $domain, (int)$license['domain_limit'])) {
            return $this->signed([
                'ok'    => false,
                'valid' => false,
                'error' => 'Domain limit exceeded',
            ]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO license_activations (license_id, domain, plugin, version, activated_at, last_seen_at)
             VALUES (:license_id, :domain, :plugin, :version, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                plugin = VALUES(plugin),
                version = VALUES(version),
                last_seen_at = NOW()'
        );

        $stmt->execute([
            ':license_id' => (int)$license['id'],
            ':domain'     => $domain,
            ':plugin'     => $plugin !== '' ? $plugin : null,
            ':version'    => $version !== '' ? $version : null,
        ]);

        $this->touchLicense((int)$license['id']);

        return $this->buildSuccessResponse(
            $licenseKey,
            $domain,
            $plugin,
            (string)$license['plan'],
            (string)$license['status'],
            !empty($license['expires_at']) ? (string)$license['expires_at'] : null,
            false,
            $license
        );
    }

    public function deactivate(array $input): array
    {
        $licenseKey = $this->clean($input['license_key'] ?? '');
        $domain     = $this->normalizeDomain($input['domain'] ?? '');

        if ($licenseKey === '' || $domain === '') {
            return $this->signed([
                'ok'    => false,
                'error' => 'license_key and domain are required',
            ]);
        }

        if ($this->isTestKey($licenseKey)) {
            return $this->signed([
                'ok'          => true,
                'deactivated' => true,
                'test'        => true,
            ]);
        }

        $license = $this->findLicense($licenseKey);

        if (!$license) {
            return $this->signed([
                'ok'    => false,
                'error' => 'License not found',
            ]);
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM license_activations
             WHERE license_id = :license_id AND domain = :domain'
        );

        $stmt->execute([
            ':license_id' => (int)$license['id'],
            ':domain'     => $domain,
        ]);

        return $this->signed([
            'ok'          => true,
            'deactivated' => true,
        ]);
    }

    private function buildSuccessResponse(
        string $licenseKey,
        string $domain,
        string $plugin,
        string $plan,
        string $status,
        ?string $expiresAt,
        bool $test,
        ?array $license = null
    ): array {
        $plan = $this->normalizePlan($plan);

        return $this->signed([
            'ok'             => true,
            'valid'          => true,
            'license_key'    => $licenseKey,
            'domain'         => $domain,
            'plugin'         => $plugin,
            'plan'           => $plan,
            'status'         => $status,
            'expires_at'     => $expiresAt,
            'customer_email' => isset($license['customer_email']) ? (string)$license['customer_email'] : '',
            'features'       => $this->featuresForPlan($plan),
            'test'           => $test,
            'checked_at'     => gmdate('c'),
        ]);
    }

    private function signed(array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payload['signature'] = hash_hmac('sha256', (string)$json, (string)$this->config['secret']);
        return $payload;
    }

    private function featuresForPlan(string $plan): array
    {
        switch ($this->normalizePlan($plan)) {
            case 'pro':
                return [
                    'weekly_posts_max'          => 7,
                    'title_count_max'           => 6,
                    'can_publish'               => true,
                    'can_use_seo'               => true,
                    'can_use_images'            => true,
                    'can_use_internal_links'    => true,
                    'can_use_external_links'    => true,
                    'can_use_topic_prediction'  => true,
                    'can_use_duplicate_check'   => true,
                    'can_use_outline_generator' => true,
                ];

            case 'advanced':
                return [
                    'weekly_posts_max'          => 3,
                    'title_count_max'           => 3,
                    'can_publish'               => true,
                    'can_use_seo'               => true,
                    'can_use_images'            => true,
                    'can_use_internal_links'    => false,
                    'can_use_external_links'    => false,
                    'can_use_topic_prediction'  => false,
                    'can_use_duplicate_check'   => false,
                    'can_use_outline_generator' => false,
                ];

            case 'free':
            default:
                return [
                    'weekly_posts_max'          => 1,
                    'title_count_max'           => 1,
                    'can_publish'               => false,
                    'can_use_seo'               => false,
                    'can_use_images'            => false,
                    'can_use_internal_links'    => false,
                    'can_use_external_links'    => false,
                    'can_use_topic_prediction'  => false,
                    'can_use_duplicate_check'   => false,
                    'can_use_outline_generator' => false,
                ];
        }
    }

    private function findLicense(string $licenseKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM licenses WHERE license_key = :license_key LIMIT 1');
        $stmt->execute([':license_key' => $licenseKey]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function isDomainAllowed(int $licenseId, string $domain): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM license_activations WHERE license_id = :license_id AND domain = :domain LIMIT 1'
        );
        $stmt->execute([
            ':license_id' => $licenseId,
            ':domain'     => $domain,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    private function canActivateDomain(int $licenseId, string $domain, int $domainLimit): bool
    {
        if ($this->isDomainAllowed($licenseId, $domain)) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM license_activations WHERE license_id = :license_id'
        );
        $stmt->execute([
            ':license_id' => $licenseId,
        ]);

        $count = (int)$stmt->fetchColumn();
        return $count < max(1, $domainLimit);
    }

    private function touchLicense(int $licenseId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE licenses SET last_checked_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $licenseId]);
    }

    private function touchActivation(int $licenseId, string $domain): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE license_activations
             SET last_seen_at = NOW()
             WHERE license_id = :license_id AND domain = :domain'
        );
        $stmt->execute([
            ':license_id' => $licenseId,
            ':domain'     => $domain,
        ]);
    }

    private function isTestKey(string $licenseKey): bool
    {
        return !empty($this->config['allow_test_keys'])
            && !empty($this->config['test_keys'][$licenseKey]);
    }

    private function normalizePlan(string $plan): string
    {
        $plan = strtolower(trim($plan));

        if (!in_array($plan, ['free', 'advanced', 'pro'], true)) {
            return 'free';
        }

        return $plan;
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', (string)$domain);
        $domain = preg_replace('#:\d+$#', '', (string)$domain);
        $domain = preg_replace('#^www\.#', '', (string)$domain);

        return trim((string)$domain);
    }

    private function clean(mixed $value): string
    {
        return trim((string)$value);
    }
}
