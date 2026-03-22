<?php

declare(strict_types=1);

namespace WABEP;

use PDO;

class LicenseService
{

    /** @var PDO */
    private $pdo;

    /** @var array */
    private $config;

    public function __construct(PDO $pdo, array $config = array())
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    public function check(array $input)
    {
        $licenseKey = $this->clean(isset($input['license_key']) ? $input['license_key'] : '');
        $domain     = $this->normalizeDomain(isset($input['domain']) ? $input['domain'] : '');
        $plugin     = $this->clean(isset($input['plugin']) ? $input['plugin'] : '');

        if ($licenseKey === '' || $domain === '') {
            return $this->signed(array(
                'ok'    => false,
                'valid' => false,
                'error' => 'license_key and domain are required',
            ));
        }

        if ($this->isTestKey($licenseKey)) {
            return $this->buildSuccessResponse(
                $licenseKey,
                $domain,
                $plugin,
                (string) $this->config['test_keys'][$licenseKey],
                'active',
                null,
                true,
                null
            );
        }

        $license = $this->findLicense($licenseKey);
        if (!$license) {
            return $this->signed(array(
                'ok'    => false,
                'valid' => false,
                'error' => 'License not found',
            ));
        }

        if (!isset($license['status']) || (string) $license['status'] !== 'active') {
            return $this->signed(array(
                'ok'    => false,
                'valid' => false,
                'error' => 'License is not active',
            ));
        }

        if (!empty($license['expires_at']) && strtotime((string) $license['expires_at']) < time()) {
            return $this->signed(array(
                'ok'    => false,
                'valid' => false,
                'error' => 'License expired',
            ));
        }

        if (!$this->isDomainAllowed((int) $license['id'], $domain)) {
            return $this->signed(array(
                'ok'    => false,
                'valid' => false,
                'error' => 'Domain not activated for this license',
            ));
        }

        $this->touchLicense((int) $license['id']);

        return $this->buildSuccessResponse(
            $licenseKey,
            $domain,
            $plugin,
            isset($license['plan']) ? (string) $license['plan'] : 'free',
            isset($license['status']) ? (string) $license['status'] : 'inactive',
            !empty($license['expires_at']) ? (string) $license['expires_at'] : null,
            false,
            !empty($license['customer_email']) ? (string) $license['customer_email'] : null
        );
    }

    public function activate(array $input)
    {
        $licenseKey = $this->clean(isset($input['license_key']) ? $input['license_key'] : '');
        $domain     = $this->normalizeDomain(isset($input['domain']) ? $input['domain'] : '');
        $plugin     = $this->clean(isset($input['plugin']) ? $input['plugin'] : '');
        $version    = $this->clean(isset($input['version']) ? $input['version'] : '');

        if ($licenseKey === '' || $domain === '') {
            return $this->signed(array(
                'ok'    => false,
                'valid' => false,
                'error' => 'license_key and domain are required',
            ));
        }

        if ($this->isTestKey($licenseKey)) {
            return $this->buildSuccessResponse(
                $licenseKey,
                $domain,
                $plugin,
                (string) $this->config['test_keys'][$licenseKey],
                'active',
                null,
                true,
                null
            );
        }

        $license = $this->findLicense($licenseKey);
        if (!$license) {
            return $this->signed(array(
                'ok'    => false,
                'valid' => false,
                'error' => 'License not found',
            ));
        }

        if (!isset($license['status']) || (string) $license['status'] !== 'active') {
            return $this->signed(array(
                'ok'    => false,
                'valid' => false,
                'error' => 'License is not active',
            ));
        }

        if (!empty($license['expires_at']) && strtotime((string) $license['expires_at']) < time()) {
            return $this->signed(array(
                'ok'    => false,
                'valid' => false,
                'error' => 'License expired',
            ));
        }

        if (!$this->activateDomain((int) $license['id'], $domain, $plugin, $version)) {
            return $this->signed(array(
                'ok'    => false,
                'valid' => false,
                'error' => 'Domain limit reached',
            ));
        }

        $this->touchLicense((int) $license['id']);

        return $this->buildSuccessResponse(
            $licenseKey,
            $domain,
            $plugin,
            isset($license['plan']) ? (string) $license['plan'] : 'free',
            isset($license['status']) ? (string) $license['status'] : 'inactive',
            !empty($license['expires_at']) ? (string) $license['expires_at'] : null,
            false,
            !empty($license['customer_email']) ? (string) $license['customer_email'] : null
        );
    }

    public function deactivate(array $input)
    {
        $licenseKey = $this->clean(isset($input['license_key']) ? $input['license_key'] : '');
        $domain     = $this->normalizeDomain(isset($input['domain']) ? $input['domain'] : '');

        if ($licenseKey === '' || $domain === '') {
            return $this->signed(array(
                'ok'    => false,
                'error' => 'license_key and domain are required',
            ));
        }

        if ($this->isTestKey($licenseKey)) {
            return $this->signed(array(
                'ok'          => true,
                'deactivated' => true,
                'test'        => true,
            ));
        }

        $license = $this->findLicense($licenseKey);
        if (!$license) {
            return $this->signed(array(
                'ok'    => false,
                'error' => 'License not found',
            ));
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM license_activations WHERE license_id = :license_id AND domain = :domain'
        );
        $stmt->execute(array(
            ':license_id' => (int) $license['id'],
            ':domain'     => $domain,
        ));

        return $this->signed(array(
            'ok'          => true,
            'deactivated' => true,
        ));
    }

    private function buildSuccessResponse($licenseKey, $domain, $plugin, $plan, $status, $expiresAt, $test, $customerEmail)
    {
        $features = $this->featuresForPlan($plan);

        return $this->signed(array(
            'ok'             => true,
            'valid'          => true,
            'license_key'    => $licenseKey,
            'domain'         => $domain,
            'plugin'         => $plugin,
            'plan'           => $plan,
            'status'         => $status,
            'expires_at'     => $expiresAt,
            'customer_email' => $customerEmail,
            'features'       => $features,
            'test'           => (bool) $test,
            'checked_at'     => gmdate('c'),
            'message'        => 'License active',
        ));
    }

    private function signed(array $payload)
    {
        $secret = isset($this->config['secret']) ? (string) $this->config['secret'] : '';

        $payload['signature'] = hash_hmac(
            'sha256',
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $secret
        );

        return $payload;
    }

    private function featuresForPlan($plan)
    {
        $plan = strtolower(trim((string) $plan));

        switch ($plan) {
            case 'pro':
                return array(
                    'weekly_posts_max'          => 7,
                    'title_count_max'           => 1,
                    'heading_count_max'         => 6,
                    'can_publish'               => true,
                    'can_use_seo'               => true,
                    'can_use_images'            => true,
                    'can_use_internal_links'    => true,
                    'can_use_external_links'    => true,
                    'can_use_topic_prediction'  => true,
                    'can_use_duplicate_check'   => true,
                    'can_use_outline_generator' => true,
                );

            case 'advanced':
                return array(
                    'weekly_posts_max'          => 7,
                    'title_count_max'           => 1,
                    'heading_count_max'         => 6,
                    'can_publish'               => true,
                    'can_use_seo'               => true,
                    'can_use_images'            => true,
                    'can_use_internal_links'    => false,
                    'can_use_external_links'    => false,
                    'can_use_topic_prediction'  => false,
                    'can_use_duplicate_check'   => false,
                    'can_use_outline_generator' => false,
                );

            case 'free':
            default:
                return array(
                    'weekly_posts_max'          => 1,
                    'title_count_max'           => 1,
                    'heading_count_max'         => 1,
                    'can_publish'               => false,
                    'can_use_seo'               => false,
                    'can_use_images'            => false,
                    'can_use_internal_links'    => false,
                    'can_use_external_links'    => false,
                    'can_use_topic_prediction'  => false,
                    'can_use_duplicate_check'   => false,
                    'can_use_outline_generator' => false,
                );
        }
    }

    private function findLicense($licenseKey)
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM licenses WHERE license_key = :license_key LIMIT 1'
        );
        $stmt->execute(array(
            ':license_key' => $licenseKey,
        ));

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row : null;
    }

    private function isDomainAllowed($licenseId, $domain)
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM license_activations WHERE license_id = :license_id AND domain = :domain'
        );
        $stmt->execute(array(
            ':license_id' => (int) $licenseId,
            ':domain'     => $domain,
        ));

        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function activateDomain($licenseId, $domain, $plugin, $version)
    {
        if ($this->isDomainAllowed($licenseId, $domain)) {
            $stmt = $this->pdo->prepare(
                'UPDATE license_activations
                 SET plugin = :plugin, version = :version, last_seen_at = NOW()
                 WHERE license_id = :license_id AND domain = :domain'
            );
            $stmt->execute(array(
                ':plugin'     => $plugin,
                ':version'    => $version,
                ':license_id' => (int) $licenseId,
                ':domain'     => $domain,
            ));

            return true;
        }

        $stmt = $this->pdo->prepare('SELECT domain_limit FROM licenses WHERE id = :id');
        $stmt->execute(array(
            ':id' => (int) $licenseId,
        ));

        $domainLimit = (int) $stmt->fetchColumn();
        if ($domainLimit < 1) {
            $domainLimit = isset($this->config['domain_limit_default'])
                ? (int) $this->config['domain_limit_default']
                : 1;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM license_activations WHERE license_id = :license_id'
        );
        $stmt->execute(array(
            ':license_id' => (int) $licenseId,
        ));

        $count = (int) $stmt->fetchColumn();
        if ($count >= $domainLimit) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO license_activations
             (license_id, domain, plugin, version, activated_at, last_seen_at)
             VALUES (:license_id, :domain, :plugin, :version, NOW(), NOW())'
        );
        $stmt->execute(array(
            ':license_id' => (int) $licenseId,
            ':domain'     => $domain,
            ':plugin'     => $plugin,
            ':version'    => $version,
        ));

        return true;
    }

    private function touchLicense($licenseId)
    {
        $stmt = $this->pdo->prepare(
            'UPDATE licenses SET last_checked_at = NOW() WHERE id = :id'
        );
        $stmt->execute(array(
            ':id' => (int) $licenseId,
        ));
    }

    private function clean($value)
    {
        return trim((string) $value);
    }

    private function normalizeDomain($domain)
    {
        $domain = trim((string) $domain);
        if ($domain === '') {
            return '';
        }

        if (filter_var($domain, FILTER_VALIDATE_URL)) {
            $host = parse_url($domain, PHP_URL_HOST);
            return $host ? strtolower((string) $host) : '';
        }

        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#/.*$#', '', (string) $domain);

        return strtolower(trim((string) $domain));
    }

    private function isTestKey($licenseKey)
    {
        return !empty($this->config['allow_test_keys'])
            && isset($this->config['test_keys'][$licenseKey]);
    }
}
