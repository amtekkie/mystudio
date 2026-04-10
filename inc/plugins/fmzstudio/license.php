<?php
/**
 * FMZ Studio — License Client (Blesta License Manager)
 *
 * Validates license keys against a Blesta License Manager installation.
 * Uses Blesta's RSA Digital Signature protocol (AES-256 + RSA-1024 via phpseclib).
 * All license data is stored encrypted (AES-256-CBC) and signed (HMAC-SHA256) locally.
 *
 * Security layers:
 *   1. Blesta RSA Digital Signature protocol — encrypted + signed server communication
 *   2. AES-256-CBC encryption of stored data — DB values are opaque blobs
 *   3. HMAC-SHA256 integrity on stored blobs — tampering detected
 *   4. Site-bound encryption key — derived from DB credentials, not portable between installs
 *   5. File integrity self-verification — detects source code tampering
 *   6. Periodic server re-validation — cached data expires, forces fresh check
 *   7. HTTPS + SSL verification — authenticates the license server
 *
 * Requirements:
 *   - Blesta's license.php client library → place at: inc/plugins/fmzstudio/blesta_license.php
 *   - phpseclib library                   → place at: inc/plugins/fmzstudio/vendor/phpseclib/
 *   - Configure SERVER_URL and SHARED_SECRET constants below
 *
 * @version 3.0.0
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

class FMZLicense
{
    /* ─────────────────────────────────
       Configuration — SET THESE VALUES
       ───────────────────────────────── */

    // Blesta License Manager validate endpoint
    const SERVER_URL = 'https://tektove.com/plugin/license_manager/validate/';

    // Shared secret — configured per-product in Blesta's License Module settings
    const SHARED_SECRET = 'GFY`j5/S2?A[~mfe0hD+2EW2';

    // Product version reported to Blesta for version-based validation
    const PRODUCT_VERSION = '2.1.0';

    /* ─────────────────────────────────
       Storage & Timing
       ───────────────────────────────── */

    const SETTING_BLOB  = 'fmz_license_blob';
    const SETTING_CHECK = 'fmz_license_check';

    // How often to re-request license data from Blesta (seconds)
    const REVALIDATION_INTERVAL = 86400; // 24

    // How long locally-stored license data is considered valid (Blesta TTL)
    const LICENSE_TTL = 1209600; // 14 days (60*60*24*14)

    // Internal salt for local encryption key derivation
    const DERIVATION_SALT = 'fmz_2026_studio_v2_kr9Xm4pQ';

    /* ─────────────────────────────────
       Blesta LicenseManager Factory
       ───────────────────────────────── */

    /**
     * Initialize a Blesta LicenseManager instance.
     *
     * @param  string $licenseKey  The license key
     * @param  string $publicKey   The RSA public key from Blesta (empty on first activation)
     * @return object              Blesta LicenseManager instance
     * @throws \RuntimeException   If required files are missing
     */
    private static function getManager(string $licenseKey = '', string $publicKey = ''): object
    {
        static $classLoaded = false;

        $blestaLib = __DIR__ . DIRECTORY_SEPARATOR . 'blesta_license.php';
        $phpseclibPath = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR
                       . 'phpseclib' . DIRECTORY_SEPARATOR;

        if (!$classLoaded) {
            if (!file_exists($blestaLib)) {
                throw new \RuntimeException(
                    'Blesta license client library not found. '
                    . 'Place the Blesta license.php file at: inc/plugins/fmzstudio/blesta_license.php'
                );
            }
            require_once $blestaLib;
            $classLoaded = true;
        }

        $license = new \License($phpseclibPath);
        $manager = $license->getManager();
        $manager->setLicenseServerUrl(self::SERVER_URL);
        $manager->setKeys($licenseKey, $publicKey, self::SHARED_SECRET);

        return $manager;
    }

    /* ─────────────────────────────────
       Settings Management
       ───────────────────────────────── */

    public static function ensureSettings(): void
    {
        global $db;

        $names = [self::SETTING_BLOB, self::SETTING_CHECK];
        foreach ($names as $name) {
            $q = $db->simple_select('settings', 'name', "name='" . $db->escape_string($name) . "'");
            if (!$db->num_rows($q)) {
                $db->insert_query('settings', [
                    'name'        => $name,
                    'title'       => 'FMZ License Data',
                    'description' => '',
                    'optionscode' => 'text',
                    'value'       => '',
                    'disporder'   => 0,
                    'gid'         => 0,
                ]);
            }
        }

        self::migrateFromOldFormat();

        rebuild_settings();
    }

    public static function removeSettings(): void
    {
        global $db;
        $db->delete_query('settings', "name IN ('fmz_license_blob','fmz_license_check','fmz_license_key','fmz_license_status','fmz_license_email','fmz_license_expiry','fmz_license_domain')");
        rebuild_settings();
    }

    /* ─────────────────────────────────
       Public Getters (from decrypted blob)
       ───────────────────────────────── */

    private static ?array $_cache = null;

    private static function loadLicenseData(): array
    {
        if (self::$_cache !== null) {
            return self::$_cache;
        }

        global $mybb;
        $blob = $mybb->settings[self::SETTING_BLOB] ?? '';
        if (empty($blob)) {
            self::$_cache = [];
            return [];
        }

        $data = self::decryptBlob($blob);
        if ($data === null) {
            self::$_cache = [];
            return [];
        }

        self::$_cache = $data;
        return $data;
    }

    public static function getKey(): string
    {
        return self::loadLicenseData()['key'] ?? '';
    }

    public static function getStatus(): string
    {
        return self::loadLicenseData()['status'] ?? '';
    }

    public static function getEmail(): string
    {
        return self::loadLicenseData()['email'] ?? '';
    }

    public static function getExpiry(): string
    {
        return self::loadLicenseData()['expiry'] ?? '';
    }

    public static function getDomain(): string
    {
        return self::loadLicenseData()['domain'] ?? '';
    }

    /* ─────────────────────────────────
       Validation
       ───────────────────────────────── */

    /**
     * Primary validation — checks local encrypted data + domain binding.
     * Triggers periodic re-validation with the Blesta server.
     */
    public static function isValid(): bool
    {
        $data = self::loadLicenseData();
        if (empty($data) || empty($data['key'])) {
            return false;
        }

        $status = $data['status'] ?? '';
        if ($status !== 'valid') {
            return false;
        }

        // Check domain binding
        if (($data['domain'] ?? '') !== self::getSiteDomain()) {
            return false;
        }

        // Periodic server re-validation
        self::periodicRevalidation();

        // Re-check after revalidation — blob may have been cleared by the server
        $data = self::loadLicenseData();
        if (empty($data)) {
            return false;
        }

        return ($data['status'] ?? '') === 'valid';
    }

    /**
     * Secondary inline validation — called from protected actions (editors, etc.)
     * Uses a different code path so patching isValid() alone won't suffice.
     */
    public static function assertLicensed(): bool
    {
        $d = self::loadLicenseData();
        if (empty($d) || empty($d['key'])) {
            return false;
        }
        // Re-derive and verify HMAC of the stored blob directly
        global $mybb;
        $raw = $mybb->settings[self::SETTING_BLOB] ?? '';
        if (empty($raw)) {
            return false;
        }
        $parts = explode('.', $raw, 3);
        if (count($parts) !== 3) {
            return false;
        }
        $eKey = self::deriveKey();
        $computedHmac = hash_hmac('sha256', $parts[0] . '.' . $parts[1], $eKey);
        if (!hash_equals($computedHmac, $parts[2])) {
            return false;
        }
        return ($d['status'] ?? '') === 'valid';
    }

    /**
     * File integrity check — verifies this file hasn't been tampered with.
     */
    public static function integrityHash(): string
    {
        $file = __FILE__;
        if (!file_exists($file)) {
            return '';
        }
        return hash_hmac('sha256', file_get_contents($file), self::DERIVATION_SALT);
    }

    public static function getSiteDomain(): string
    {
        global $mybb;
        $url = $mybb->settings['bburl'] ?? '';
        $parsed = parse_url($url);
        return $parsed['host'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    }

    /* ─────────────────────────────────
       API Calls (Blesta License Manager)
       ───────────────────────────────── */

    /**
     * Activate a license key via Blesta:
     *   1. Request the RSA public key from the server
     *   2. Request license data (encrypted + signed)
     *   3. Validate the license data locally
     *   4. Store everything in an encrypted local blob
     */
    public static function activate(string $key): array
    {
        $key = trim($key);
        if (empty($key)) {
            return ['success' => false, 'status' => 'invalid', 'message' => 'Please enter a license key.'];
        }

        // Validate key length and format
        if (strlen($key) > 255) {
            return ['success' => false, 'status' => 'invalid', 'message' => 'License key is too long.'];
        }
        if (!preg_match('/^[a-zA-Z0-9\-_.]+$/', $key)) {
            return ['success' => false, 'status' => 'invalid', 'message' => 'License key contains invalid characters.'];
        }

        try {
            // Step 1: Request RSA public key from Blesta
            $manager   = self::getManager($key, '');
            $publicKey = $manager->requestKey();

            if (empty($publicKey)) {
                return [
                    'success' => false,
                    'status'  => 'invalid',
                    'message' => 'Could not retrieve public key from license server. Verify the license key is correct.',
                ];
            }

            // Step 2: Request license data with the public key
            $manager     = self::getManager($key, $publicKey);
            $licenseData = $manager->requestData(['version' => self::PRODUCT_VERSION]);

            if (empty($licenseData)) {
                return [
                    'success' => false,
                    'status'  => 'invalid',
                    'message' => 'Could not retrieve license data from server.',
                ];
            }

            // Step 3: Validate the license data
            $result = $manager->validate($licenseData, self::LICENSE_TTL);
            $status = $result['status'] ?? 'unknown';

            if ($status === 'valid') {
                self::saveLicenseBlob([
                    'key'          => $key,
                    'public_key'   => $publicKey,
                    'license_data' => $licenseData,
                    'status'       => 'valid',
                    'email'        => '',
                    'expiry'       => 'managed',
                    'domain'       => self::getSiteDomain(),
                    'activated'    => time(),
                ]);
                self::saveCheckTimestamp(time());
                self::$_cache = null;

                return [
                    'success' => true,
                    'status'  => 'valid',
                    'message' => 'License activated successfully.',
                    'data'    => $result,
                ];
            }

            return [
                'success' => false,
                'status'  => $status,
                'message' => self::statusMessage($status),
                'data'    => $result,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'status'  => 'error',
                'message' => 'License server error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Deactivate — clears local license data.
     * Blesta manages the license lifecycle on the server side (cancel/suspend
     * through the Blesta admin panel or client area).
     */
    public static function deactivate(): array
    {
        $data = self::loadLicenseData();
        if (empty($data['key'])) {
            return ['success' => false, 'message' => 'No license key is currently active.'];
        }

        self::clearLicenseBlob();
        self::$_cache = null;

        return [
            'success' => true,
            'message' => 'License cleared locally. To release the license for use on another domain, manage it through your client area.',
        ];
    }

    /**
     * Check license status by requesting fresh data from Blesta.
     * Falls back to local validation if the server is unreachable.
     */
    public static function checkStatus(): array
    {
        $data = self::loadLicenseData();
        $key  = $data['key'] ?? '';

        if (empty($key)) {
            return ['success' => false, 'status' => '', 'message' => 'No license key stored.'];
        }

        try {
            $publicKey  = $data['public_key']   ?? '';
            $storedData = $data['license_data'] ?? '';

            $manager = self::getManager($key, $publicKey);

            // Request fresh license data from Blesta
            $licenseData = $manager->requestData(['version' => self::PRODUCT_VERSION]);

            if (empty($licenseData)) {
                // Server unreachable — validate stored data locally if available
                if (!empty($storedData)) {
                    $result = $manager->validate($storedData, self::LICENSE_TTL);
                    $status = $result['status'] ?? 'unknown';

                    if ($status === 'valid') {
                        return ['success' => true, 'status' => 'valid', 'message' => 'License valid (cached).'];
                    }
                }

                return [
                    'success' => false,
                    'status'  => 'unknown',
                    'message' => 'Could not reach license server and local cache has expired.',
                ];
            }

            // Validate the fresh data
            $result = $manager->validate($licenseData, self::LICENSE_TTL);
            $status = $result['status'] ?? 'unknown';

            if ($status === 'valid') {
                $data['license_data'] = $licenseData;
                $data['status']       = 'valid';
                self::saveLicenseBlob($data);
                self::saveCheckTimestamp(time());
                self::$_cache = null;

                return ['success' => true, 'status' => 'valid', 'message' => 'License is valid.'];
            }

            // License is no longer valid — clear local data for terminal statuses
            if (in_array($status, ['suspended', 'expired', 'canceled', 'invalid_location', 'invalid_version'], true)) {
                self::clearLicenseBlob();
                self::$_cache = null;
            }

            return [
                'success' => false,
                'status'  => $status,
                'message' => self::statusMessage($status),
            ];

        } catch (\Exception $e) {
            // Network/library error — fall back to local validation
            if (!empty($data['license_data']) && !empty($data['public_key'])) {
                try {
                    $manager = self::getManager($key, $data['public_key']);
                    $result  = $manager->validate($data['license_data'], self::LICENSE_TTL);
                    if (($result['status'] ?? '') === 'valid') {
                        return ['success' => true, 'status' => 'valid', 'message' => 'License valid (cached).'];
                    }
                } catch (\Exception $inner) {
                    // Fall through to error
                }
            }

            return [
                'success' => false,
                'status'  => 'error',
                'message' => 'Could not reach license server: ' . $e->getMessage(),
            ];
        }
    }

    /* ─────────────────────────────────
       Status Messages (Blesta statuses)
       ───────────────────────────────── */

    private static function statusMessage(string $status): string
    {
        $messages = [
            'valid'            => 'License is valid.',
            'invalid_location' => 'License is not valid for this domain.',
            'invalid_version'  => 'License is not valid for this product version.',
            'suspended'        => 'License has been suspended. Contact support.',
            'expired'          => 'License has expired. Please renew.',
            'canceled'         => 'License has been canceled.',
            'unknown'          => 'License data could not be verified. Please re-activate.',
        ];
        return $messages[$status] ?? 'License validation failed (status: ' . $status . ').';
    }

    /* ─────────────────────────────────
       Periodic Re-validation
       ───────────────────────────────── */

    private static function periodicRevalidation(): void
    {
        global $mybb;
        $lastCheck = intval($mybb->settings[self::SETTING_CHECK] ?? 0);
        if ((time() - $lastCheck) > self::REVALIDATION_INTERVAL) {
            try {
                $result = self::checkStatus();
                if (!$result['success']) {
                    $status = $result['status'] ?? '';
                    if (in_array($status, ['suspended', 'expired', 'canceled', 'invalid_location', 'invalid_version'], true)) {
                        self::clearLicenseBlob();
                        self::$_cache = null;
                    }
                }
            } catch (\Throwable $e) {
                // Network errors should not break page loads — silently skip revalidation
                // The next request will retry after REVALIDATION_INTERVAL
            }
        }
    }

    /* ─────────────────────────────────
       Encryption & Decryption
       ───────────────────────────────── */

    /**
     * Derive a site-specific encryption key from database credentials.
     * This ensures encrypted blobs cannot be copied between MyBB installations.
     */
    private static function deriveKey(): string
    {
        global $config;
        $material = ($config['database']['hostname'] ?? 'localhost')
                  . '|' . ($config['database']['database'] ?? 'mybb')
                  . '|' . ($config['database']['table_prefix'] ?? 'mybb_')
                  . '|' . self::DERIVATION_SALT;
        return hash('sha256', $material, true); // 32 bytes for AES-256
    }

    /**
     * Encrypt license data array into a storable blob.
     * Format: base64(IV) . '.' . base64(ciphertext) . '.' . HMAC
     */
    private static function encryptBlob(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $key  = self::deriveKey();
        $iv   = random_bytes(16);

        $ciphertext = openssl_encrypt($json, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            return '';
        }

        $b64Iv   = base64_encode($iv);
        $b64Data = base64_encode($ciphertext);
        $hmac    = hash_hmac('sha256', $b64Iv . '.' . $b64Data, $key);

        return $b64Iv . '.' . $b64Data . '.' . $hmac;
    }

    /**
     * Decrypt a stored blob back into a license data array.
     * Returns null if tampered, corrupted, or wrong encryption key.
     */
    private static function decryptBlob(string $blob): ?array
    {
        $parts = explode('.', $blob, 3);
        if (count($parts) !== 3) {
            return null;
        }

        [$b64Iv, $b64Data, $storedHmac] = $parts;
        $key = self::deriveKey();

        // Verify HMAC first (timing-safe)
        $computedHmac = hash_hmac('sha256', $b64Iv . '.' . $b64Data, $key);
        if (!hash_equals($computedHmac, $storedHmac)) {
            return null; // tampered
        }

        $iv         = base64_decode($b64Iv, true);
        $ciphertext = base64_decode($b64Data, true);
        if ($iv === false || $ciphertext === false) {
            return null;
        }

        $json = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /* ─────────────────────────────────
       Storage
       ───────────────────────────────── */

    private static function saveLicenseBlob(array $data): void
    {
        global $mybb;
        $blob = self::encryptBlob($data);
        self::saveSettingValue(self::SETTING_BLOB, $blob);
        $mybb->settings[self::SETTING_BLOB] = $blob;
        rebuild_settings();
    }

    private static function clearLicenseBlob(): void
    {
        global $mybb;
        self::saveSettingValue(self::SETTING_BLOB, '');
        self::saveSettingValue(self::SETTING_CHECK, '');
        $mybb->settings[self::SETTING_BLOB] = '';
        $mybb->settings[self::SETTING_CHECK] = '';
        self::$_cache = null;
        rebuild_settings();
    }

    private static function saveCheckTimestamp(int $time): void
    {
        global $mybb;
        self::saveSettingValue(self::SETTING_CHECK, (string)$time);
        $mybb->settings[self::SETTING_CHECK] = (string)$time;
    }

    private static function saveSettingValue(string $name, string $value): void
    {
        global $db;
        $db->update_query('settings', ['value' => $db->escape_string($value)], "name='" . $db->escape_string($name) . "'");
    }

    /* ─────────────────────────────────
       Migration from old WordPress format
       ───────────────────────────────── */

    private static function migrateFromOldFormat(): void
    {
        global $db;

        // Check if old plaintext settings exist (from WordPress-based v1/v2)
        $q = $db->simple_select('settings', 'value', "name='fmz_license_key'");
        if (!$db->num_rows($q)) {
            return;
        }

        $oldKey = $db->fetch_field($q, 'value');
        if (empty($oldKey)) {
            $db->delete_query('settings', "name IN ('fmz_license_key','fmz_license_status','fmz_license_email','fmz_license_expiry','fmz_license_domain')");
            return;
        }

        // Old WordPress licenses are not compatible with Blesta — clear them
        // User will need to re-activate with a Blesta-issued license key
        $db->delete_query('settings', "name IN ('fmz_license_key','fmz_license_status','fmz_license_email','fmz_license_expiry','fmz_license_domain')");

        // Also clear existing v2 encrypted blob if it was WordPress-based
        self::clearLicenseBlob();
    }
}
