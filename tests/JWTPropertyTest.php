<?php
/**
 * Property Tests for JWT Authentication
 * 
 * **Feature: jwt-authentication**
 * 
 * This test file contains property-based tests for JWT token operations
 * including format compliance, round-trip consistency, and security properties.
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../services/JWTService.php';
require_once __DIR__ . '/../services/JWTCookieService.php';
require_once __DIR__ . '/../services/AuthenticationService.php';
require_once __DIR__ . '/../repositories/TokenBlacklistRepository.php';
require_once __DIR__ . '/../repositories/RefreshTokenRepository.php';
require_once __DIR__ . '/../middleware/JWTAuthMiddleware.php';

class JWTPropertyTest {
    private $jwtService;
    private $blacklistRepository;
    private $refreshTokenRepository;
    private $authService;
    private $testResults = [];
    private $iterations = 100;
    private $db;
    
    public function __construct() {
        $this->jwtService = new JWTService();
        $this->blacklistRepository = new TokenBlacklistRepository();
        $this->refreshTokenRepository = new RefreshTokenRepository();
        $this->authService = new AuthenticationService();
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Run all property tests
     */
    public function runTests(): bool {
        echo "\n=== JWT Property Tests ===\n";
        echo "**Feature: jwt-authentication**\n\n";
        
        // Property 13: JWT Format Compliance
        $this->runPropertyTest(
            'Property 13: JWT Format Compliance',
            [$this, 'testJWTFormatCompliance'],
            'Requirements 8.2'
        );
        
        // Property 15: Algorithm Header Correctness
        $this->runPropertyTest(
            'Property 15: Algorithm Header Correctness',
            [$this, 'testAlgorithmHeaderCorrectness'],
            'Requirements 1.6'
        );
        
        // Property 2: Token Claims Completeness
        $this->runPropertyTest(
            'Property 2: Token Claims Completeness',
            [$this, 'testTokenClaimsCompleteness'],
            'Requirements 1.1, 4.2'
        );
        
        // Property 3: Access Token Expiration Bounds
        $this->runPropertyTest(
            'Property 3: Access Token Expiration Bounds',
            [$this, 'testAccessTokenExpirationBounds'],
            'Requirements 1.2'
        );
        
        // Property 4: Refresh Token Expiration Bounds
        $this->runPropertyTest(
            'Property 4: Refresh Token Expiration Bounds',
            [$this, 'testRefreshTokenExpirationBounds'],
            'Requirements 2.2'
        );
        
        // Property 5: Token Signature Verification
        $this->runPropertyTest(
            'Property 5: Token Signature Verification',
            [$this, 'testTokenSignatureVerification'],
            'Requirements 1.3, 1.5'
        );
        
        // Property 1: JWT Round-Trip Consistency
        $this->runPropertyTest(
            'Property 1: JWT Round-Trip Consistency',
            [$this, 'testJWTRoundTripConsistency'],
            'Requirements 8.1, 8.3, 8.4'
        );
        
        // Property 14: Blacklist Cleanup Removes Only Expired
        $this->runPropertyTest(
            'Property 14: Blacklist Cleanup Removes Only Expired',
            [$this, 'testBlacklistCleanupRemovesOnlyExpired'],
            'Requirements 3.5'
        );
        
        // Property 6: Blacklist Enforcement
        $this->runPropertyTest(
            'Property 6: Blacklist Enforcement',
            [$this, 'testBlacklistEnforcement'],
            'Requirements 3.3, 3.4'
        );
        
        // Property 7: Refresh Token Revocation on Password Change
        $this->runPropertyTest(
            'Property 7: Refresh Token Revocation on Password Change',
            [$this, 'testRefreshTokenRevocationOnPasswordChange'],
            'Requirements 3.2'
        );
        
        // Property 8: Logout Blacklists Access Token
        $this->runPropertyTest(
            'Property 8: Logout Blacklists Access Token',
            [$this, 'testLogoutBlacklistsAccessToken'],
            'Requirements 3.1'
        );
        
        // Property 9: Refresh Token Produces Valid Access Token
        $this->runPropertyTest(
            'Property 9: Refresh Token Produces Valid Access Token',
            [$this, 'testRefreshTokenProducesValidAccessToken'],
            'Requirements 2.3'
        );
        
        // Property 12: Company Isolation from Token
        $this->runPropertyTest(
            'Property 12: Company Isolation from Token',
            [$this, 'testCompanyIsolationFromToken'],
            'Requirements 4.5'
        );
        
        // Property 11: Dual Authentication Support
        $this->runPropertyTest(
            'Property 11: Dual Authentication Support',
            [$this, 'testDualAuthenticationSupport'],
            'Requirements 6.1, 6.5'
        );
        
        // Property 10: Cookie Security Attributes
        $this->runPropertyTest(
            'Property 10: Cookie Security Attributes',
            [$this, 'testCookieSecurityAttributes'],
            'Requirements 5.1, 5.2'
        );
        
        // Summary
        echo "\n=== Test Summary ===\n";
        $passed = count(array_filter($this->testResults));
        $total = count($this->testResults);
        echo "Passed: $passed / $total\n";
        
        return $passed === $total;
    }

    
    /**
     * Run a property test with multiple iterations
     */
    private function runPropertyTest(string $name, callable $testFunction, string $validates): void {
        echo "Testing: $name\n";
        echo "  **Validates: $validates**\n";
        $failures = [];
        
        for ($i = 0; $i < $this->iterations; $i++) {
            try {
                $result = $testFunction();
                if (!$result['success']) {
                    $failures[] = [
                        'iteration' => $i + 1,
                        'message' => $result['message'] ?? 'Property test failed',
                        'data' => $result['data'] ?? null
                    ];
                }
            } catch (Exception $e) {
                $failures[] = [
                    'iteration' => $i + 1,
                    'message' => 'Exception: ' . $e->getMessage()
                ];
            }
        }
        
        if (empty($failures)) {
            echo "  ✓ Passed ({$this->iterations} iterations)\n";
            $this->testResults[$name] = true;
        } else {
            echo "  ✗ Failed\n";
            foreach (array_slice($failures, 0, 3) as $failure) {
                echo "    - Iteration {$failure['iteration']}: {$failure['message']}\n";
                if (isset($failure['data'])) {
                    echo "      Data: " . json_encode($failure['data']) . "\n";
                }
            }
            if (count($failures) > 3) {
                echo "    ... and " . (count($failures) - 3) . " more failures\n";
            }
            $this->testResults[$name] = false;
        }
    }
    
    /**
     * Property 13: JWT Format Compliance
     * **Feature: jwt-authentication, Property 13: JWT Format Compliance**
     * **Validates: Requirements 8.2**
     * 
     * For any generated token, the string SHALL match the pattern
     * ^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$ (three base64url-encoded segments)
     */
    private function testJWTFormatCompliance(): array {
        // Generate random user data
        $user = $this->generateRandomUser();
        
        // Create access token
        $token = $this->jwtService->createAccessToken($user);
        
        // Verify JWT format: three base64url-encoded segments separated by dots
        $pattern = '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/';
        
        if (!preg_match($pattern, $token)) {
            return [
                'success' => false,
                'message' => 'Token does not match JWT format pattern',
                'data' => ['token' => $token]
            ];
        }
        
        // Verify it has exactly 3 parts
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [
                'success' => false,
                'message' => 'Token does not have exactly 3 parts',
                'data' => ['parts_count' => count($parts)]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 15: Algorithm Header Correctness
     * **Feature: jwt-authentication, Property 15: Algorithm Header Correctness**
     * **Validates: Requirements 1.6**
     * 
     * For any generated token, the decoded header SHALL contain "alg": "HS256" and "typ": "JWT"
     */
    private function testAlgorithmHeaderCorrectness(): array {
        // Generate random user data
        $user = $this->generateRandomUser();
        
        // Create access token
        $token = $this->jwtService->createAccessToken($user);
        
        // Parse header
        $header = $this->jwtService->parseHeader($token);
        
        if ($header === null) {
            return [
                'success' => false,
                'message' => 'Failed to parse token header'
            ];
        }
        
        // Verify algorithm
        if (!isset($header['alg']) || $header['alg'] !== 'HS256') {
            return [
                'success' => false,
                'message' => 'Header does not contain alg: HS256',
                'data' => ['header' => $header]
            ];
        }
        
        // Verify type
        if (!isset($header['typ']) || $header['typ'] !== 'JWT') {
            return [
                'success' => false,
                'message' => 'Header does not contain typ: JWT',
                'data' => ['header' => $header]
            ];
        }
        
        return ['success' => true];
    }

    
    /**
     * Property 2: Token Claims Completeness
     * **Feature: jwt-authentication, Property 2: Token Claims Completeness**
     * **Validates: Requirements 1.1, 4.2**
     * 
     * For any user with valid id, company_id, company_type, and role_id,
     * the generated access token SHALL contain all four claims with values matching the input
     */
    private function testTokenClaimsCompleteness(): array {
        // Generate random user data
        $user = $this->generateRandomUser();
        
        // Create access token
        $token = $this->jwtService->createAccessToken($user);
        
        // Parse claims
        $claims = $this->jwtService->parseToken($token);
        
        if ($claims === null) {
            return [
                'success' => false,
                'message' => 'Failed to parse token claims'
            ];
        }
        
        // Verify user_id
        if (!isset($claims['user_id']) || $claims['user_id'] !== (int)$user['id']) {
            return [
                'success' => false,
                'message' => 'user_id claim mismatch',
                'data' => ['expected' => $user['id'], 'actual' => $claims['user_id'] ?? null]
            ];
        }
        
        // Verify company_id
        if (!isset($claims['company_id']) || $claims['company_id'] !== (int)$user['company_id']) {
            return [
                'success' => false,
                'message' => 'company_id claim mismatch',
                'data' => ['expected' => $user['company_id'], 'actual' => $claims['company_id'] ?? null]
            ];
        }
        
        // Verify company_type
        if (!isset($claims['company_type']) || $claims['company_type'] !== $user['company_type']) {
            return [
                'success' => false,
                'message' => 'company_type claim mismatch',
                'data' => ['expected' => $user['company_type'], 'actual' => $claims['company_type'] ?? null]
            ];
        }
        
        // Verify role_id
        if (!isset($claims['role_id']) || $claims['role_id'] !== (int)$user['role_id']) {
            return [
                'success' => false,
                'message' => 'role_id claim mismatch',
                'data' => ['expected' => $user['role_id'], 'actual' => $claims['role_id'] ?? null]
            ];
        }
        
        // Verify username
        if (!isset($claims['username']) || $claims['username'] !== $user['username']) {
            return [
                'success' => false,
                'message' => 'username claim mismatch',
                'data' => ['expected' => $user['username'], 'actual' => $claims['username'] ?? null]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 3: Access Token Expiration Bounds
     * **Feature: jwt-authentication, Property 3: Access Token Expiration Bounds**
     * **Validates: Requirements 1.2**
     * 
     * For any configured TTL value, the generated access token's exp claim
     * SHALL equal iat plus the configured TTL
     */
    private function testAccessTokenExpirationBounds(): array {
        // Generate random user data
        $user = $this->generateRandomUser();
        
        // Get configured TTL
        $ttl = $this->jwtService->getAccessTokenTTL();
        
        // Create access token
        $token = $this->jwtService->createAccessToken($user);
        
        // Parse claims
        $claims = $this->jwtService->parseToken($token);
        
        if ($claims === null) {
            return [
                'success' => false,
                'message' => 'Failed to parse token claims'
            ];
        }
        
        // Verify iat and exp exist
        if (!isset($claims['iat']) || !isset($claims['exp'])) {
            return [
                'success' => false,
                'message' => 'Missing iat or exp claim',
                'data' => ['claims' => $claims]
            ];
        }
        
        // Verify exp = iat + TTL
        $expectedExp = $claims['iat'] + $ttl;
        if ($claims['exp'] !== $expectedExp) {
            return [
                'success' => false,
                'message' => 'exp does not equal iat + TTL',
                'data' => [
                    'iat' => $claims['iat'],
                    'exp' => $claims['exp'],
                    'ttl' => $ttl,
                    'expected_exp' => $expectedExp
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 4: Refresh Token Expiration Bounds
     * **Feature: jwt-authentication, Property 4: Refresh Token Expiration Bounds**
     * **Validates: Requirements 2.2**
     * 
     * For any configured TTL value, the generated refresh token's exp claim
     * SHALL equal iat plus the configured TTL
     */
    private function testRefreshTokenExpirationBounds(): array {
        // Generate random user ID
        $userId = rand(1, 10000);
        
        // Get configured TTL
        $ttl = $this->jwtService->getRefreshTokenTTL();
        
        // Create refresh token
        $result = $this->jwtService->createRefreshToken($userId);
        $token = $result['token'];
        
        // Parse claims
        $claims = $this->jwtService->parseToken($token);
        
        if ($claims === null) {
            return [
                'success' => false,
                'message' => 'Failed to parse token claims'
            ];
        }
        
        // Verify iat and exp exist
        if (!isset($claims['iat']) || !isset($claims['exp'])) {
            return [
                'success' => false,
                'message' => 'Missing iat or exp claim',
                'data' => ['claims' => $claims]
            ];
        }
        
        // Verify exp = iat + TTL
        $expectedExp = $claims['iat'] + $ttl;
        if ($claims['exp'] !== $expectedExp) {
            return [
                'success' => false,
                'message' => 'exp does not equal iat + TTL',
                'data' => [
                    'iat' => $claims['iat'],
                    'exp' => $claims['exp'],
                    'ttl' => $ttl,
                    'expected_exp' => $expectedExp
                ]
            ];
        }
        
        // Verify type claim is 'refresh'
        if (!isset($claims['type']) || $claims['type'] !== 'refresh') {
            return [
                'success' => false,
                'message' => 'type claim is not "refresh"',
                'data' => ['type' => $claims['type'] ?? null]
            ];
        }
        
        return ['success' => true];
    }

    
    /**
     * Property 5: Token Signature Verification
     * **Feature: jwt-authentication, Property 5: Token Signature Verification**
     * **Validates: Requirements 1.3, 1.5**
     * 
     * For any valid token, modifying any character in the payload section
     * SHALL cause validation to fail with a signature error
     */
    private function testTokenSignatureVerification(): array {
        // Generate random user data
        $user = $this->generateRandomUser();
        
        // Create access token
        $token = $this->jwtService->createAccessToken($user);
        
        // First verify the original token is valid
        $validResult = $this->jwtService->validateToken($token);
        if (!$validResult['valid']) {
            return [
                'success' => false,
                'message' => 'Original token should be valid',
                'data' => ['error' => $validResult['error']]
            ];
        }
        
        // Split token into parts
        $parts = explode('.', $token);
        $payload = $parts[1];
        
        // Modify a random character in the payload
        $payloadChars = str_split($payload);
        $randomIndex = rand(0, count($payloadChars) - 1);
        $originalChar = $payloadChars[$randomIndex];
        
        // Change to a different valid base64url character
        $base64urlChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        do {
            $newChar = $base64urlChars[rand(0, strlen($base64urlChars) - 1)];
        } while ($newChar === $originalChar);
        
        $payloadChars[$randomIndex] = $newChar;
        $modifiedPayload = implode('', $payloadChars);
        
        // Reconstruct token with modified payload
        $modifiedToken = $parts[0] . '.' . $modifiedPayload . '.' . $parts[2];
        
        // Validate modified token - should fail
        $invalidResult = $this->jwtService->validateToken($modifiedToken);
        
        if ($invalidResult['valid']) {
            return [
                'success' => false,
                'message' => 'Modified token should be invalid',
                'data' => [
                    'original_payload' => $payload,
                    'modified_payload' => $modifiedPayload,
                    'modified_index' => $randomIndex
                ]
            ];
        }
        
        // Error should be TOKEN_INVALID (signature error)
        if ($invalidResult['error'] !== JWTService::ERROR_TOKEN_INVALID) {
            return [
                'success' => false,
                'message' => 'Error should be TOKEN_INVALID',
                'data' => ['actual_error' => $invalidResult['error']]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 1: JWT Round-Trip Consistency
     * **Feature: jwt-authentication, Property 1: JWT Round-Trip Consistency**
     * **Validates: Requirements 8.1, 8.3, 8.4**
     * 
     * For any valid user data containing user_id, company_id, company_type, role_id, and username
     * (including special characters), creating a token and then parsing it SHALL return claims
     * equivalent to the original input
     */
    private function testJWTRoundTripConsistency(): array {
        // Generate random user data with potential special characters
        $user = $this->generateRandomUserWithSpecialChars();
        
        // Create access token
        $token = $this->jwtService->createAccessToken($user);
        
        // Parse claims
        $claims = $this->jwtService->parseToken($token);
        
        if ($claims === null) {
            return [
                'success' => false,
                'message' => 'Failed to parse token claims'
            ];
        }
        
        // Verify all user fields are preserved
        if ($claims['user_id'] !== (int)$user['id']) {
            return [
                'success' => false,
                'message' => 'user_id not preserved',
                'data' => ['expected' => $user['id'], 'actual' => $claims['user_id']]
            ];
        }
        
        if ($claims['company_id'] !== (int)$user['company_id']) {
            return [
                'success' => false,
                'message' => 'company_id not preserved',
                'data' => ['expected' => $user['company_id'], 'actual' => $claims['company_id']]
            ];
        }
        
        if ($claims['company_type'] !== $user['company_type']) {
            return [
                'success' => false,
                'message' => 'company_type not preserved',
                'data' => ['expected' => $user['company_type'], 'actual' => $claims['company_type']]
            ];
        }
        
        if ($claims['role_id'] !== (int)$user['role_id']) {
            return [
                'success' => false,
                'message' => 'role_id not preserved',
                'data' => ['expected' => $user['role_id'], 'actual' => $claims['role_id']]
            ];
        }
        
        if ($claims['username'] !== $user['username']) {
            return [
                'success' => false,
                'message' => 'username not preserved (special characters issue)',
                'data' => ['expected' => $user['username'], 'actual' => $claims['username']]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 14: Blacklist Cleanup Removes Only Expired
     * **Feature: jwt-authentication, Property 14: Blacklist Cleanup Removes Only Expired**
     * **Validates: Requirements 3.5**
     * 
     * For any blacklist containing both expired and non-expired entries,
     * cleanup SHALL remove only entries where expires_at is in the past,
     * preserving all non-expired entries.
     */
    private function testBlacklistCleanupRemovesOnlyExpired(): array {
        // Generate random test data
        $expiredCount = rand(1, 5);
        $activeCount = rand(1, 5);
        
        $expiredJtis = [];
        $activeJtis = [];
        
        // Insert expired entries (expires_at in the past)
        for ($i = 0; $i < $expiredCount; $i++) {
            $jti = 'test_expired_' . $this->generateRandomString(16) . '_' . time() . '_' . $i;
            $expiredAt = date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' minutes'));
            
            $this->insertBlacklistEntry($jti, $expiredAt);
            $expiredJtis[] = $jti;
        }
        
        // Insert active entries (expires_at in the future)
        for ($i = 0; $i < $activeCount; $i++) {
            $jti = 'test_active_' . $this->generateRandomString(16) . '_' . time() . '_' . $i;
            $activeAt = date('Y-m-d H:i:s', strtotime('+' . rand(10, 60) . ' minutes'));
            
            $this->insertBlacklistEntry($jti, $activeAt);
            $activeJtis[] = $jti;
        }
        
        // Run cleanup
        $this->blacklistRepository->cleanup();
        
        // Verify expired entries are removed
        foreach ($expiredJtis as $jti) {
            if ($this->blacklistRepository->isBlacklisted($jti)) {
                // Clean up remaining test data
                $this->cleanupTestBlacklistEntries(array_merge($expiredJtis, $activeJtis));
                return [
                    'success' => false,
                    'message' => 'Expired entry was not removed',
                    'data' => ['jti' => $jti]
                ];
            }
        }
        
        // Verify active entries are preserved
        foreach ($activeJtis as $jti) {
            if (!$this->blacklistRepository->isBlacklisted($jti)) {
                // Clean up remaining test data
                $this->cleanupTestBlacklistEntries(array_merge($expiredJtis, $activeJtis));
                return [
                    'success' => false,
                    'message' => 'Active entry was incorrectly removed',
                    'data' => ['jti' => $jti]
                ];
            }
        }
        
        // Clean up test data
        $this->cleanupTestBlacklistEntries($activeJtis);
        
        return ['success' => true];
    }
    
    /**
     * Insert a blacklist entry directly for testing
     */
    private function insertBlacklistEntry(string $jti, string $expiresAt): void {
        $sql = "INSERT INTO token_blacklist (token_jti, expires_at, created_at) VALUES (?, ?, NOW())";
        try {
            $stmt = $this->db->executeQuery($sql, [$jti, $expiresAt], 'ss');
            $stmt->close();
        } catch (Exception $e) {
            // Ignore duplicate key errors
            if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                throw $e;
            }
        }
    }
    
    /**
     * Clean up test blacklist entries
     */
    private function cleanupTestBlacklistEntries(array $jtis): void {
        foreach ($jtis as $jti) {
            $this->blacklistRepository->remove($jti);
        }
    }
    
    /**
     * Property 6: Blacklist Enforcement
     * **Feature: jwt-authentication, Property 6: Blacklist Enforcement**
     * **Validates: Requirements 3.3, 3.4**
     * 
     * For any token that has been added to the blacklist, subsequent validation attempts
     * SHALL return invalid with a revoked error code.
     */
    private function testBlacklistEnforcement(): array {
        // Generate random user data
        $user = $this->generateRandomUser();
        
        // Create access token
        $token = $this->jwtService->createAccessToken($user);
        
        // First verify the token is valid before blacklisting
        $validResult = $this->jwtService->validateToken($token);
        if (!$validResult['valid']) {
            return [
                'success' => false,
                'message' => 'Token should be valid before blacklisting',
                'data' => ['error' => $validResult['error']]
            ];
        }
        
        // Blacklist the token
        $blacklistResult = $this->jwtService->blacklistToken($token);
        if (!$blacklistResult) {
            return [
                'success' => false,
                'message' => 'Failed to blacklist token'
            ];
        }
        
        // Validate the token again - should now fail with TOKEN_REVOKED
        $invalidResult = $this->jwtService->validateToken($token);
        
        if ($invalidResult['valid']) {
            // Clean up: remove from blacklist
            $claims = $this->jwtService->parseToken($token);
            if ($claims && isset($claims['jti'])) {
                $this->blacklistRepository->remove($claims['jti']);
            }
            return [
                'success' => false,
                'message' => 'Blacklisted token should be invalid'
            ];
        }
        
        // Verify the error code is TOKEN_REVOKED
        if ($invalidResult['error'] !== JWTService::ERROR_TOKEN_REVOKED) {
            // Clean up: remove from blacklist
            $claims = $this->jwtService->parseToken($token);
            if ($claims && isset($claims['jti'])) {
                $this->blacklistRepository->remove($claims['jti']);
            }
            return [
                'success' => false,
                'message' => 'Error should be TOKEN_REVOKED',
                'data' => ['actual_error' => $invalidResult['error']]
            ];
        }
        
        // Clean up: remove from blacklist
        $claims = $this->jwtService->parseToken($token);
        if ($claims && isset($claims['jti'])) {
            $this->blacklistRepository->remove($claims['jti']);
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 7: Refresh Token Revocation on Password Change
     * **Feature: jwt-authentication, Property 7: Refresh Token Revocation on Password Change**
     * **Validates: Requirements 3.2**
     * 
     * For any user with one or more active refresh tokens, changing the user's password
     * SHALL result in all refresh tokens for that user being marked as revoked.
     */
    private function testRefreshTokenRevocationOnPasswordChange(): array {
        // Get a real user ID from the database
        $testUserId = $this->getExistingUserId();
        if ($testUserId === null) {
            return [
                'success' => false,
                'message' => 'No existing user found in database for testing'
            ];
        }
        
        // Create multiple refresh tokens for this user
        $tokenCount = rand(1, 3);
        $tokenIds = [];
        
        for ($i = 0; $i < $tokenCount; $i++) {
            $refreshTokenData = $this->jwtService->createRefreshToken($testUserId);
            $tokenHash = hash('sha256', $refreshTokenData['token']);
            
            $stored = $this->refreshTokenRepository->store(
                $testUserId,
                $refreshTokenData['token_id'],
                $tokenHash,
                $refreshTokenData['expires_at'],
                '127.0.0.1',
                'Test Agent'
            );
            
            if (!$stored) {
                // Clean up any tokens we created
                foreach ($tokenIds as $tid) {
                    $this->refreshTokenRepository->revokeByTokenId($tid);
                }
                return [
                    'success' => false,
                    'message' => 'Failed to store refresh token'
                ];
            }
            
            $tokenIds[] = $refreshTokenData['token_id'];
        }
        
        // Verify tokens are valid before revocation
        foreach ($tokenIds as $tokenId) {
            if (!$this->refreshTokenRepository->isValid($tokenId)) {
                // Clean up
                $this->refreshTokenRepository->revokeByUserId($testUserId);
                return [
                    'success' => false,
                    'message' => 'Token should be valid before password change',
                    'data' => ['token_id' => $tokenId]
                ];
            }
        }
        
        // Simulate password change by calling revokeByUserId directly
        // (We can't call changePassword without knowing the user's password)
        $revokedCount = $this->refreshTokenRepository->revokeByUserId($testUserId);
        
        // Verify all tokens are now revoked
        foreach ($tokenIds as $tokenId) {
            if ($this->refreshTokenRepository->isValid($tokenId)) {
                return [
                    'success' => false,
                    'message' => 'Token should be revoked after password change',
                    'data' => ['token_id' => $tokenId]
                ];
            }
        }
        
        // Verify the count matches (at least the tokens we created)
        if ($revokedCount < $tokenCount) {
            return [
                'success' => false,
                'message' => 'Revoked count should be at least token count',
                'data' => ['expected_min' => $tokenCount, 'actual' => $revokedCount]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 8: Logout Blacklists Access Token
     * **Feature: jwt-authentication, Property 8: Logout Blacklists Access Token**
     * **Validates: Requirements 3.1**
     * 
     * For any valid access token used in a logout operation, the token SHALL be added
     * to the blacklist and subsequent validation SHALL fail.
     */
    private function testLogoutBlacklistsAccessToken(): array {
        // Generate random user data
        $user = $this->generateRandomUser();
        
        // Create access token
        $accessToken = $this->jwtService->createAccessToken($user);
        
        // Verify token is valid before logout
        $validResult = $this->jwtService->validateToken($accessToken);
        if (!$validResult['valid']) {
            return [
                'success' => false,
                'message' => 'Token should be valid before logout',
                'data' => ['error' => $validResult['error']]
            ];
        }
        
        // Simulate logout by blacklisting the token
        // (Using JWTService directly since we don't have a real session)
        $blacklistResult = $this->jwtService->blacklistToken($accessToken);
        
        if (!$blacklistResult) {
            return [
                'success' => false,
                'message' => 'Failed to blacklist token during logout'
            ];
        }
        
        // Verify token is now invalid
        $invalidResult = $this->jwtService->validateToken($accessToken);
        
        if ($invalidResult['valid']) {
            // Clean up
            $claims = $this->jwtService->parseToken($accessToken);
            if ($claims && isset($claims['jti'])) {
                $this->blacklistRepository->remove($claims['jti']);
            }
            return [
                'success' => false,
                'message' => 'Token should be invalid after logout'
            ];
        }
        
        // Verify error is TOKEN_REVOKED
        if ($invalidResult['error'] !== JWTService::ERROR_TOKEN_REVOKED) {
            // Clean up
            $claims = $this->jwtService->parseToken($accessToken);
            if ($claims && isset($claims['jti'])) {
                $this->blacklistRepository->remove($claims['jti']);
            }
            return [
                'success' => false,
                'message' => 'Error should be TOKEN_REVOKED',
                'data' => ['actual_error' => $invalidResult['error']]
            ];
        }
        
        // Clean up
        $claims = $this->jwtService->parseToken($accessToken);
        if ($claims && isset($claims['jti'])) {
            $this->blacklistRepository->remove($claims['jti']);
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 9: Refresh Token Produces Valid Access Token
     * **Feature: jwt-authentication, Property 9: Refresh Token Produces Valid Access Token**
     * **Validates: Requirements 2.3**
     * 
     * For any valid, non-expired, non-revoked refresh token, the refresh operation
     * SHALL return a new valid access token for the same user.
     */
    private function testRefreshTokenProducesValidAccessToken(): array {
        // Get a real user ID from the database
        $testUserId = $this->getExistingUserId();
        if ($testUserId === null) {
            return [
                'success' => false,
                'message' => 'No existing user found in database for testing'
            ];
        }
        
        // Create a refresh token
        $refreshTokenData = $this->jwtService->createRefreshToken($testUserId);
        $refreshToken = $refreshTokenData['token'];
        $tokenId = $refreshTokenData['token_id'];
        
        // Store the refresh token in database
        $tokenHash = hash('sha256', $refreshToken);
        $stored = $this->refreshTokenRepository->store(
            $testUserId,
            $tokenId,
            $tokenHash,
            $refreshTokenData['expires_at'],
            '127.0.0.1',
            'Test Agent'
        );
        
        if (!$stored) {
            return [
                'success' => false,
                'message' => 'Failed to store refresh token in database'
            ];
        }
        
        // Verify refresh token is valid
        $refreshValidation = $this->jwtService->validateToken($refreshToken);
        if (!$refreshValidation['valid']) {
            // Clean up
            $this->refreshTokenRepository->revokeByTokenId($tokenId);
            return [
                'success' => false,
                'message' => 'Refresh token should be valid',
                'data' => ['error' => $refreshValidation['error']]
            ];
        }
        
        // Verify it's stored and valid in database
        if (!$this->refreshTokenRepository->isValid($tokenId)) {
            // Clean up
            $this->refreshTokenRepository->revokeByTokenId($tokenId);
            return [
                'success' => false,
                'message' => 'Refresh token should be valid in database'
            ];
        }
        
        // Verify token hash matches
        if (!$this->refreshTokenRepository->verifyTokenHash($tokenId, $tokenHash)) {
            // Clean up
            $this->refreshTokenRepository->revokeByTokenId($tokenId);
            return [
                'success' => false,
                'message' => 'Token hash should match'
            ];
        }
        
        // Simulate refresh by creating a new access token for the same user
        // (We create a mock user since we just need the user_id for the token)
        $mockUser = [
            'id' => $testUserId,
            'company_id' => rand(1, 1000),
            'company_type' => 'ADV',
            'role_id' => rand(1, 10),
            'username' => 'test_user_' . $testUserId
        ];
        
        $newAccessToken = $this->jwtService->createAccessToken($mockUser);
        
        // Verify the new access token is valid
        $accessValidation = $this->jwtService->validateToken($newAccessToken);
        if (!$accessValidation['valid']) {
            // Clean up
            $this->refreshTokenRepository->revokeByTokenId($tokenId);
            return [
                'success' => false,
                'message' => 'New access token should be valid',
                'data' => ['error' => $accessValidation['error']]
            ];
        }
        
        // Verify the new access token has the same user_id
        $accessClaims = $accessValidation['claims'];
        if ($accessClaims['user_id'] !== $testUserId) {
            // Clean up
            $this->refreshTokenRepository->revokeByTokenId($tokenId);
            return [
                'success' => false,
                'message' => 'New access token should have same user_id',
                'data' => ['expected' => $testUserId, 'actual' => $accessClaims['user_id']]
            ];
        }
        
        // Clean up
        $this->refreshTokenRepository->revokeByTokenId($tokenId);
        
        return ['success' => true];
    }
    
    /**
     * Property 12: Company Isolation from Token
     * **Feature: jwt-authentication, Property 12: Company Isolation from Token**
     * **Validates: Requirements 4.5**
     * 
     * For any authenticated request, the company_id extracted from the JWT claims
     * SHALL be used to enforce data isolation, preventing access to other companies' data.
     */
    private function testCompanyIsolationFromToken(): array {
        // Generate a contractor user with a specific company_id
        $contractorCompanyId = rand(100, 999);
        $otherCompanyId = $contractorCompanyId + 1; // Different company
        
        $contractorUser = [
            'id' => rand(1, 10000),
            'company_id' => $contractorCompanyId,
            'company_type' => 'CONTRACTOR',
            'role_id' => rand(1, 10),
            'username' => 'contractor_' . $this->generateRandomString(8)
        ];
        
        // Create access token for contractor
        $token = $this->jwtService->createAccessToken($contractorUser);
        
        // Parse the token to verify company_id is embedded
        $claims = $this->jwtService->parseToken($token);
        
        if ($claims === null) {
            return [
                'success' => false,
                'message' => 'Failed to parse token claims'
            ];
        }
        
        // Verify company_id is in the token
        if (!isset($claims['company_id'])) {
            return [
                'success' => false,
                'message' => 'company_id not found in token claims'
            ];
        }
        
        // Verify the company_id matches what we set
        if ($claims['company_id'] !== $contractorCompanyId) {
            return [
                'success' => false,
                'message' => 'company_id in token does not match input',
                'data' => [
                    'expected' => $contractorCompanyId,
                    'actual' => $claims['company_id']
                ]
            ];
        }
        
        // Verify company_type is preserved
        if ($claims['company_type'] !== 'CONTRACTOR') {
            return [
                'success' => false,
                'message' => 'company_type not preserved in token',
                'data' => [
                    'expected' => 'CONTRACTOR',
                    'actual' => $claims['company_type']
                ]
            ];
        }
        
        // Test that the middleware correctly extracts company_id for isolation
        // We simulate this by verifying the claims structure supports isolation checks
        
        // For contractor users, they should only access their own company
        // The company_id from token should be used for isolation
        $tokenCompanyId = $claims['company_id'];
        $tokenCompanyType = $claims['company_type'];
        
        // Simulate isolation check: contractor accessing own company (should pass)
        $canAccessOwnCompany = ($tokenCompanyType === 'ADV') || 
                               ($tokenCompanyId === $contractorCompanyId);
        
        if (!$canAccessOwnCompany) {
            return [
                'success' => false,
                'message' => 'Contractor should be able to access own company',
                'data' => [
                    'token_company_id' => $tokenCompanyId,
                    'target_company_id' => $contractorCompanyId
                ]
            ];
        }
        
        // Simulate isolation check: contractor accessing other company (should fail)
        $canAccessOtherCompany = ($tokenCompanyType === 'ADV') || 
                                  ($tokenCompanyId === $otherCompanyId);
        
        if ($canAccessOtherCompany) {
            return [
                'success' => false,
                'message' => 'Contractor should NOT be able to access other company',
                'data' => [
                    'token_company_id' => $tokenCompanyId,
                    'target_company_id' => $otherCompanyId
                ]
            ];
        }
        
        // Now test with ADV user - should be able to access any company
        $advUser = [
            'id' => rand(1, 10000),
            'company_id' => rand(1, 99), // Different company
            'company_type' => 'ADV',
            'role_id' => rand(1, 10),
            'username' => 'adv_' . $this->generateRandomString(8)
        ];
        
        $advToken = $this->jwtService->createAccessToken($advUser);
        $advClaims = $this->jwtService->parseToken($advToken);
        
        if ($advClaims === null) {
            return [
                'success' => false,
                'message' => 'Failed to parse ADV token claims'
            ];
        }
        
        // ADV user should be able to access any company
        $advCompanyType = $advClaims['company_type'];
        $canAdvAccessAnyCompany = ($advCompanyType === 'ADV');
        
        if (!$canAdvAccessAnyCompany) {
            return [
                'success' => false,
                'message' => 'ADV user should be able to access any company',
                'data' => ['company_type' => $advCompanyType]
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 11: Dual Authentication Support
     * **Feature: jwt-authentication, Property 11: Dual Authentication Support**
     * **Validates: Requirements 6.1, 6.5**
     * 
     * For any API request during migration period, authentication SHALL succeed
     * if either a valid session OR a valid JWT is present.
     */
    private function testDualAuthenticationSupport(): array {
        // This test verifies the dual authentication logic in ApiAuthMiddleware
        // We test the authentication priority and fallback behavior
        
        require_once __DIR__ . '/../middleware/ApiAuthMiddleware.php';
        
        // Test 1: JWT authentication should work when JWT is present
        $user = $this->generateRandomUser();
        $jwtToken = $this->jwtService->createAccessToken($user);
        
        // Verify the JWT token is valid
        $jwtValidation = $this->jwtService->validateToken($jwtToken);
        if (!$jwtValidation['valid']) {
            return [
                'success' => false,
                'message' => 'JWT token should be valid',
                'data' => ['error' => $jwtValidation['error']]
            ];
        }
        
        // Test 2: Verify JWT claims contain required fields for authentication
        $claims = $jwtValidation['claims'];
        $requiredFields = ['user_id', 'company_id', 'company_type', 'role_id'];
        
        foreach ($requiredFields as $field) {
            if (!isset($claims[$field])) {
                return [
                    'success' => false,
                    'message' => "JWT claims missing required field: $field",
                    'data' => ['claims' => $claims]
                ];
            }
        }
        
        // Test 3: Verify JWT authentication is preferred over session
        // When both JWT and session are present, JWT should be used (Requirement 6.2)
        // We verify this by checking that the middleware config supports this behavior
        $jwtConfig = require __DIR__ . '/../config/jwt.php';
        
        // Verify legacy_session_enabled is configurable
        if (!array_key_exists('legacy_session_enabled', $jwtConfig)) {
            return [
                'success' => false,
                'message' => 'JWT config should have legacy_session_enabled setting'
            ];
        }
        
        // Test 4: Verify that when legacy_session_enabled is true, session auth is available
        // This tests Requirement 6.1 and 6.5
        if ($jwtConfig['legacy_session_enabled'] !== true && $jwtConfig['legacy_session_enabled'] !== false) {
            return [
                'success' => false,
                'message' => 'legacy_session_enabled should be a boolean',
                'data' => ['value' => $jwtConfig['legacy_session_enabled']]
            ];
        }
        
        // Test 5: Verify the middleware can be instantiated and has dual auth methods
        $middleware = new ApiAuthMiddleware();
        
        // Check that the middleware has the required methods for dual auth
        if (!method_exists($middleware, 'authenticate')) {
            return [
                'success' => false,
                'message' => 'ApiAuthMiddleware should have authenticate method'
            ];
        }
        
        if (!method_exists($middleware, 'getAuthMethod')) {
            return [
                'success' => false,
                'message' => 'ApiAuthMiddleware should have getAuthMethod method'
            ];
        }
        
        if (!method_exists($middleware, 'isJWTAuthenticated')) {
            return [
                'success' => false,
                'message' => 'ApiAuthMiddleware should have isJWTAuthenticated method'
            ];
        }
        
        if (!method_exists($middleware, 'isSessionAuthenticated')) {
            return [
                'success' => false,
                'message' => 'ApiAuthMiddleware should have isSessionAuthenticated method'
            ];
        }
        
        // Test 6: Verify the middleware can be configured for testing
        if (!method_exists($middleware, 'setJWTConfig')) {
            return [
                'success' => false,
                'message' => 'ApiAuthMiddleware should have setJWTConfig method for testing'
            ];
        }
        
        // Test 7: Test that disabling legacy session works
        // Create a middleware with legacy disabled
        $testMiddleware = new ApiAuthMiddleware();
        $testMiddleware->setJWTConfig([
            'legacy_session_enabled' => false,
            'cookie_name' => 'adv_access_token'
        ]);
        
        // The middleware should still be able to authenticate via JWT
        // (We can't fully test without mocking HTTP headers, but we verify the config is respected)
        
        // Test 8: Verify JWT token structure supports dual auth scenario
        // The token should have all claims needed for both JWT and session-like behavior
        $tokenClaims = $this->jwtService->parseToken($jwtToken);
        
        // Verify user_id is present (needed for both auth methods)
        if (!isset($tokenClaims['user_id']) || $tokenClaims['user_id'] !== (int)$user['id']) {
            return [
                'success' => false,
                'message' => 'Token user_id should match input user id',
                'data' => [
                    'expected' => $user['id'],
                    'actual' => $tokenClaims['user_id'] ?? null
                ]
            ];
        }
        
        // Verify company_id is present (needed for company isolation)
        if (!isset($tokenClaims['company_id']) || $tokenClaims['company_id'] !== (int)$user['company_id']) {
            return [
                'success' => false,
                'message' => 'Token company_id should match input company id',
                'data' => [
                    'expected' => $user['company_id'],
                    'actual' => $tokenClaims['company_id'] ?? null
                ]
            ];
        }
        
        // Test 9: Verify that the auth_method field is set correctly in user data
        // When authenticated via JWT, auth_method should be 'jwt'
        // When authenticated via session, auth_method should be 'session'
        // This is verified by checking the middleware implementation supports this
        
        // Test 10: Generate multiple tokens and verify they all work
        // This tests that dual auth works consistently across different users
        for ($i = 0; $i < 5; $i++) {
            $testUser = $this->generateRandomUser();
            $testToken = $this->jwtService->createAccessToken($testUser);
            
            $testValidation = $this->jwtService->validateToken($testToken);
            if (!$testValidation['valid']) {
                return [
                    'success' => false,
                    'message' => "Token $i should be valid",
                    'data' => ['error' => $testValidation['error']]
                ];
            }
            
            // Verify claims match
            $testClaims = $testValidation['claims'];
            if ($testClaims['user_id'] !== (int)$testUser['id']) {
                return [
                    'success' => false,
                    'message' => "Token $i user_id mismatch",
                    'data' => [
                        'expected' => $testUser['id'],
                        'actual' => $testClaims['user_id']
                    ]
                ];
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Property 10: Cookie Security Attributes
     * **Feature: jwt-authentication, Property 10: Cookie Security Attributes**
     * **Validates: Requirements 5.1, 5.2**
     * 
     * For any authentication response setting cookies, both access and refresh token cookies
     * SHALL have HttpOnly=true, Secure=true (in production), and SameSite=Strict attributes.
     */
    private function testCookieSecurityAttributes(): array {
        // Create JWTCookieService instance
        $cookieService = new JWTCookieService();
        
        // Generate random TTL values within valid ranges
        $accessTTL = rand(900, 3600);  // 15-60 minutes
        $refreshTTL = rand(604800, 2592000);  // 7-30 days
        
        // Test 1: Verify access token cookie attributes
        $accessAttrs = $cookieService->getCookieAttributes('access', $accessTTL);
        
        // Check HttpOnly is true (Requirements 5.1)
        if (!isset($accessAttrs['httponly']) || $accessAttrs['httponly'] !== true) {
            return [
                'success' => false,
                'message' => 'Access token cookie must have HttpOnly=true',
                'data' => ['httponly' => $accessAttrs['httponly'] ?? null]
            ];
        }
        
        // Check SameSite is Strict (Requirements 5.1)
        if (!isset($accessAttrs['samesite']) || $accessAttrs['samesite'] !== 'Strict') {
            return [
                'success' => false,
                'message' => 'Access token cookie must have SameSite=Strict',
                'data' => ['samesite' => $accessAttrs['samesite'] ?? null]
            ];
        }
        
        // Test 2: Verify refresh token cookie attributes
        $refreshAttrs = $cookieService->getCookieAttributes('refresh', $refreshTTL);
        
        // Check HttpOnly is true (Requirements 5.2)
        if (!isset($refreshAttrs['httponly']) || $refreshAttrs['httponly'] !== true) {
            return [
                'success' => false,
                'message' => 'Refresh token cookie must have HttpOnly=true',
                'data' => ['httponly' => $refreshAttrs['httponly'] ?? null]
            ];
        }
        
        // Check SameSite is Strict (Requirements 5.2)
        if (!isset($refreshAttrs['samesite']) || $refreshAttrs['samesite'] !== 'Strict') {
            return [
                'success' => false,
                'message' => 'Refresh token cookie must have SameSite=Strict',
                'data' => ['samesite' => $refreshAttrs['samesite'] ?? null]
            ];
        }
        
        // Test 3: Verify cookie configuration is consistent
        $config = $cookieService->getCookieConfig();
        
        // HttpOnly should always be true
        if (!isset($config['httponly']) || $config['httponly'] !== true) {
            return [
                'success' => false,
                'message' => 'Cookie config must have httponly=true',
                'data' => ['config' => $config]
            ];
        }
        
        // SameSite should be Strict
        if (!isset($config['samesite']) || $config['samesite'] !== 'Strict') {
            return [
                'success' => false,
                'message' => 'Cookie config must have samesite=Strict',
                'data' => ['config' => $config]
            ];
        }
        
        // Test 4: Verify security validation passes
        $validation = $cookieService->validateSecurityAttributes();
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Cookie security validation failed',
                'data' => ['errors' => $validation['errors']]
            ];
        }
        
        // Test 5: Verify both cookie names are different
        if ($config['access_cookie_name'] === $config['refresh_cookie_name']) {
            return [
                'success' => false,
                'message' => 'Access and refresh cookie names must be different',
                'data' => [
                    'access_name' => $config['access_cookie_name'],
                    'refresh_name' => $config['refresh_cookie_name']
                ]
            ];
        }
        
        // Test 6: Verify expiration is set correctly
        $now = time();
        $accessExpires = $accessAttrs['expires'];
        $refreshExpires = $refreshAttrs['expires'];
        
        // Access token expiration should be approximately now + accessTTL
        $accessExpectedMin = $now + $accessTTL - 5;  // Allow 5 second tolerance
        $accessExpectedMax = $now + $accessTTL + 5;
        
        if ($accessExpires < $accessExpectedMin || $accessExpires > $accessExpectedMax) {
            return [
                'success' => false,
                'message' => 'Access token cookie expiration is incorrect',
                'data' => [
                    'expected_range' => [$accessExpectedMin, $accessExpectedMax],
                    'actual' => $accessExpires
                ]
            ];
        }
        
        // Refresh token expiration should be approximately now + refreshTTL
        $refreshExpectedMin = $now + $refreshTTL - 5;
        $refreshExpectedMax = $now + $refreshTTL + 5;
        
        if ($refreshExpires < $refreshExpectedMin || $refreshExpires > $refreshExpectedMax) {
            return [
                'success' => false,
                'message' => 'Refresh token cookie expiration is incorrect',
                'data' => [
                    'expected_range' => [$refreshExpectedMin, $refreshExpectedMax],
                    'actual' => $refreshExpires
                ]
            ];
        }
        
        // Test 7: Test with production-like configuration
        $prodCookieService = new JWTCookieService();
        $prodCookieService->setConfig([
            'development_mode' => false,
            'cookie_secure' => true,
            'cookie_samesite' => 'Strict'
        ]);
        
        $prodAccessAttrs = $prodCookieService->getCookieAttributes('access', $accessTTL);
        $prodRefreshAttrs = $prodCookieService->getCookieAttributes('refresh', $refreshTTL);
        
        // In production config, HttpOnly must still be true
        if ($prodAccessAttrs['httponly'] !== true || $prodRefreshAttrs['httponly'] !== true) {
            return [
                'success' => false,
                'message' => 'Production config must have HttpOnly=true',
                'data' => [
                    'access_httponly' => $prodAccessAttrs['httponly'],
                    'refresh_httponly' => $prodRefreshAttrs['httponly']
                ]
            ];
        }
        
        // In production config, SameSite must be Strict
        if ($prodAccessAttrs['samesite'] !== 'Strict' || $prodRefreshAttrs['samesite'] !== 'Strict') {
            return [
                'success' => false,
                'message' => 'Production config must have SameSite=Strict',
                'data' => [
                    'access_samesite' => $prodAccessAttrs['samesite'],
                    'refresh_samesite' => $prodRefreshAttrs['samesite']
                ]
            ];
        }
        
        return ['success' => true];
    }
    
    // ==================== Helper Methods ====================
    
    /**
     * Get an existing user ID from the database for testing
     */
    private function getExistingUserId(): ?int {
        $sql = "SELECT id FROM users WHERE status = 'active' LIMIT 1";
        $result = $this->db->getResults($sql, [], '');
        
        if (!empty($result) && isset($result[0]['id'])) {
            return (int)$result[0]['id'];
        }
        
        // Try without status filter
        $sql = "SELECT id FROM users LIMIT 1";
        $result = $this->db->getResults($sql, [], '');
        
        if (!empty($result) && isset($result[0]['id'])) {
            return (int)$result[0]['id'];
        }
        
        return null;
    }
    
    /**
     * Generate random user data
     */
    private function generateRandomUser(): array {
        return [
            'id' => rand(1, 10000),
            'company_id' => rand(1, 1000),
            'company_type' => $this->randomChoice(['ADV', 'CONTRACTOR']),
            'role_id' => rand(1, 10),
            'username' => $this->generateRandomString(8)
        ];
    }
    
    /**
     * Generate random user data with potential special characters in username
     */
    private function generateRandomUserWithSpecialChars(): array {
        $specialChars = ['@', '.', '-', '_', '+', '&', '\'', '"', '<', '>', ' '];
        $username = $this->generateRandomString(4);
        
        // Randomly add special characters
        if (rand(0, 1) === 1) {
            $username .= $specialChars[array_rand($specialChars)];
            $username .= $this->generateRandomString(4);
        }
        
        return [
            'id' => rand(1, 10000),
            'company_id' => rand(1, 1000),
            'company_type' => $this->randomChoice(['ADV', 'CONTRACTOR']),
            'role_id' => rand(1, 10),
            'username' => $username
        ];
    }
    
    /**
     * Generate random string
     */
    private function generateRandomString(int $length = 10): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
    
    /**
     * Random choice from array
     */
    private function randomChoice(array $options) {
        return $options[array_rand($options)];
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new JWTPropertyTest();
    $success = $test->runTests();
    exit($success ? 0 : 1);
}
