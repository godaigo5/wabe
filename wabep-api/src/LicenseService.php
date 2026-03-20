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
        $domain = $this->normalizeDomain($input['domain'] ?? '');
        $plugin = $this->clean($input['plugin'] ?? '');

        if ($licenseKey === '' || $domain === '') {
            return $this->signed([
                'ok' => false,
                'valid' => false,
                'error' => 'license_key and domain are required',
            ]);
        }

        if ($this->isTestKey($licenseKey)) {
            return $this->buildSuccessResponse($licenseKey, $domain, $plugin, $this->config['test_keys'][$licenseKey], 'active', null, true);
        }

        $license = $this->findLicense($licenseKey);
        if (!$license) {
            return $this->signed(['ok' => false, 'valid' => false, 'error' => 'License not found']);
        }

        if ($license['status'] !== 'active') {
            return $this->signed(['ok' => false, 'valid' => false, 'error' => 'License is not active']);
        }

        if (!empty($license['expires_at']) && strtotime((string)$license['expires_at']) < time()) {
            return $this->signed(['ok' => false, 'valid' => false, 'error' => 'License expired']);
        }

        if (!$this->isDomainAllowed((int)$license['id'], $domain)) {
            return $this->signed(['ok' => false, 'valid' => false, 'error' => 'Domain not activated for this license']);
        }

        $this->touchLicense((int)$license['id']);

        return $this->buildSuccessResponse(
            $licenseKey,
            $domain,
            $plugin,
            (string)$license['plan'],
            (string)$license['status'],
            $license['expires_at'] ?: null,
            false
        );
    }

    public function activate(array $input): array
    {
        $licenseKey = $this->clean($input['license_key'] ?? '');
        $domain = $this->normalizeDomain($input['domain'] ?? '');
        $plugin = $this->clean($input['plugin'] ?? '');
        $version = $this->clean($input['version'] ?? '');

        if ($licenseKey === '' || $domain === '') {
            return $this->signed(['ok' => false, 'valid' => false, 'error' => 'license_key and domain are required']);
        }

        if ($this->isTestKey($licenseKey)) {
            return $this->buildSuccessResponse($licenseKey, $domain, $plugin, $this->config['test_keys'][$licenseKey], 'active', null, true);
        }

        $license = $this->findLicense($licenseKey);
        if (!$license) {
            return $this->signed(['ok' => false, 'valid' => false, 'error' => 'License not found']);
        }

        if ($license['status'] !== 'active') {
            return $this->signed(['ok' => false, 'valid' => false, 'error' => 'License is not active']);
        }

        if (!empty($license['expires_at']) && strtotime((string)$license['expires_at']) < time()) {
            return $this->signed(['ok' => false, 'valid' => false, 'error' => 'License expired']);
        }

        if (!$this->activateDomain((int)$license['id'], $domain, $plugin, $version)) {
            return $this->signed(['ok' => false, 'valid' => false, 'error' => 'Domain limit reached']);
        }

        $this->touchLicense((int)$license['id']);

        return $this->buildSuccessResponse(
            $licenseKey,
            $domain,
            $plugin,
            (string)$license['plan'],
            (string)$license['status'],
            $license['expires_at'] ?: null,
            false
        );
    }

    public function deactivate(array $input): array
    {
        $licenseKey = $this->clean($input['license_key'] ?? '');
        $domain = $this->normalizeDomain($input['domain'] ?? '');

        if ($licenseKey === '' || $domain === '') {
            return $this->signed(['ok' => false, 'error' => 'license_key and domain are required']);
        }

        if ($this->isTestKey($licenseKey)) {
            return $this->signed(['ok' => true, 'deactivated' => true, 'test' => true]);
        }

        $license = $this->findLicense($licenseKey);
        if (!$license) {
            return $this->signed(['ok' => false, 'error' => 'License not found']);
        }

        $stmt = $this->pdo->prepare('DELETE FROM license_activations WHERE license_id = :license_id AND domain = :domain');
        $stmt->execute([
            ':license_id' => (int)$license['id'],
            ':domain' => $domain,
        ]);

        return $this->signed(['ok' => true, 'deactivated' => true]);
    }

    private function buildSuccessResponse(string $licenseKey, string $domain, string $plugin, string $plan, string $status, ?string $expiresAt, bool $test): array
    {
        $features = $this->featuresForPlan($plan);

        return $this->signed([
            'ok' => true,
            'valid' => true,
            'license_key' => $licenseKey,
            'domain' => $domain,
            'plugin' => $plugin,
            'plan' => $plan,
            'status' => $status,
            'expires_at' => $expiresAt,
            'features' => $features,
            'test' => $test,
            'checked_at' => gmdate('c'),
        ]);
    }

    private function signed(array $payload): array
    {
        $payload['signature'] = hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $this->config['secret']);
        return $payload;
    }

    private function featuresForPlan(string $plan): array
    {
        switch ($plan) {
            case 'pro':
                return [
                    'weekly_posts_max' => 7,
                    'title_count_max' => 6,
                    'can_publish' => true,
                    'can_use_seo' => true,
                    'can_use_images' => true,
                    'can_use_topic_generator' => true,
                    'can_use_internal_links' => true,
                    'can_use_outline_generator' => true,
                ];
            case 'advanced':
                return [
                    'weekly_posts_max' => 7,
                    'title_count_max' => 6,
                    'can_publish' => true,
                    'can_use_seo' => true,
                    'can_use_images' => true,
                    'can_use_topic_generator' => false,
                    'can_use_internal_links' => false,
                    'can_use_outline_generator' => false,
                ];
            case 'free':
            default:
                return [
                    'weekly_posts_max' => 1,
                    'title_count_max' => 1,
                    'can_publish' => false,
                    'can_use_seo' => false,
                    'can_use_images' => false,
                    'can_use_topic_generator' => false,
                    'can_use_internal_links' => false,
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
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM license_activations WHERE license_id = :license_id AND domain = :domain');
        $stmt->execute([':license_id' => $licenseId, ':domain' => $domain]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function activateDomain(int $licenseId, string $domain, string $plugin, string $version): bool
    {
        if ($this->isDomainAllowed($licenseId, $domain)) {
            $stmt = $this->pdo->prepare('UPDATE license_activations SET plugin = :plugin, version = :version, last_seen_at = NOW() WHERE license_id = :license_id AND domain = :domain');
            $stmt->execute([
                ':plugin' => $plugin,
                ':version' => $version,
                ':license_id' => $licenseId,
                ':domain' => $domain,
            ]);
            return true;
        }

        $stmt = $this->pdo->prepare('SELECT domain_limit FROM licenses WHERE id = :id');
        $stmt->execute([':id' => $licenseId]);
        $domainLimit = (int)$stmt->fetchColumn();
        if ($domainLimit < 1) {
            $domainLimit = (int)($this->config['domain_limit_default'] ?? 1);
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM license_activations WHERE license_id = :license_id');
        $stmt->execute([':license_id' => $licenseId]);
        $count = (int)$stmt->fetchColumn();
        if ($count >= $domainLimit) {
            return false;
        }

        $stmt = $this->pdo->prepare('INSERT INTO license_activations (license_id, domain, plugin, version, activated_at, last_seen_at) VALUES (:license_id, :domain, :plugin, :version, NOW(), NOW())');
        $stmt->execute([
            ':license_id' => $licenseId,
            ':domain' => $domain,
            ':plugin' => $plugin,
            ':version' => $version,
        ]);
        return true;
    }

    private function touchLicense(int $licenseId): void
    {
        $stmt = $this->pdo->prepare('UPDATE licenses SET last_checked_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $licenseId]);
    }

    private function clean(string $value): string
    {
        return trim((string)$value);
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim((string)$domain);
        if ($domain === '') {
            return '';
        }

        if (filter_var($domain, FILTER_VALIDATE_URL)) {
            $host = parse_url($domain, PHP_URL_HOST);
            return $host ? strtolower($host) : '';
        }

        return strtolower(preg_replace('#^https?://#', '', $domain));
    }

    private function isTestKey(string $licenseKey): bool
    {
        return !empty($this->config['allow_test_keys']) && isset($this->config['test_keys'][$licenseKey]);
    }
}
