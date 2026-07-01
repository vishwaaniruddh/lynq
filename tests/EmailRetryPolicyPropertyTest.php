<?php
/**
 * Property Test for Email Retry Policy Compliance
 * **Feature: email-management-system, Property 14: Email Retry Policy Compliance**
 * **Validates: Requirements 6.3**
 */

require_once 'PropertyTestBase.php';
require_once __DIR__ . '/../services/EmailQueueService.php';
require_once __DIR__ . '/../repositories/EmailQueueRepository.php';

class EmailRetryPolicyPropertyTest extends PropertyTestBase {
    
    private $emailQueueService;
    private $emailQueueRepository;
    
    // Test data IDs for cleanup
    private $createdEmailIds = [];
    private $testCompanyId;
    private $testUserId;
    
    public function __construct() {
        parent::__construct();
        $this->iterations = 20; // Reduce iterations for faster testing
        $this->emailQueueService = new EmailQueueService();
        $this->emailQueueRepository = new EmailQueueRepository();
        $this->setupTestData();
    }
    
    /**
     * Setup test company and user
     */
    private function setupTestData(): void {
        // Get or create test company
        $result = $this->getResults("SELECT id FROM companies WHERE type = 'adv' AND status = 1 LIMIT 1");
        if (!empty($result)) {
            $this->testCompanyId = (int)$result[0]['id'];
        } else {
            $stmt = $this->executeQuery(
                "INSERT INTO companies (name, type, status) VALUES (?, ?, ?)",
                ['Test Email Company ' . uniqid(), 'adv', 1],
                'ssi'
            );
            $this->testCompanyId = $this->db->insert_id;
            $stmt->close();
        }
        
        // Get or create test user
        $result = $this->getResults(
            "SELECT id FROM users WHERE company_id = ? AND status = 1 LIMIT 1",
            [$this->testCompanyId],
            'i'
        );
        if (!empty($result)) {
            $this->testUserId = (int)$result[0]['id'];
        } else {
            $username = 'email_test_user_' . uniqid();
            $email = $username . '@test.com';
            
            // Get a valid role_id
            $roleResult = $this->getResults("SELECT id FROM roles LIMIT 1");
            $roleId = !empty($roleResult) ? (int)$roleResult[0]['id'] : 1;
            
            $stmt = $this->executeQuery(
                "INSERT INTO users (username, email, password_hash, first_name, last_name, company_id, role_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$username, $email, password_hash('test123', PASSWORD_DEFAULT), 'Test', 'User', $this->testCompanyId, $roleId, 1],
                'sssssiis'
            );
            $this->testUserId = $this->db->insert_id;
            $stmt->close();
        }
    }
    
    public function runTests(): bool {
        echo "=== Email Retry Policy Compliance Property Tests ===\n\n";
        
        $allPassed = true;
        
        // Property 14: Email Retry Policy Compliance
        $allPassed &= $this->runPropertyTest(
            "Property 14: Failed emails are retried according to configured retry policies",
            [$this, 'testFailedEmailRetryPolicy']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 14: Maximum attempt limits are respected",
            [$this, 'testMaximumAttemptLimits']
        );
        
        $allPassed &= $this->runPropertyTest(
            "Property 14: Retry scheduling follows exponential backoff",
            [$this, 'testExponentialBackoffScheduling']
        );
        
        // Cleanup
        $this->cleanupTestData();
        
        return $allPassed;
    }
    
    /**
     * Property 14: Failed emails are retried according to configured retry policies
     * **Feature: email-management-system, Property 14: Email Retry Policy Compliance**
     * **Validates: Requirements 6.3**
     */
    public function testFailedEmailRetryPolicy(): array {
        try {
            // Generate random max_attempts between 1 and 5
            $maxAttempts = $this->generateRandomInt(1, 5);
            
            // Create email with specific retry policy
            $emailData = [
                'to_email' => $this->generateRandomEmail(),
                'subject' => 'Test Email ' . uniqid(),
                'body_text' => 'Test email body content',
                'max_attempts' => $maxAttempts,
                'company_id' => $this->testCompanyId
            ];
            
            $queuedEmail = $this->emailQueueService->queueEmail($emailData, $this->testUserId);
            $this->createdEmailIds[] = $queuedEmail['id'];
            
            // Verify initial state
            $this->assert(
                $queuedEmail['status'] === 'pending',
                "Newly queued email should have pending status"
            );
            
            $this->assert(
                (int)$queuedEmail['attempts'] === 0,
                "Newly queued email should have 0 attempts"
            );
            
            $this->assert(
                (int)$queuedEmail['max_attempts'] === $maxAttempts,
                "Email should have configured max_attempts: $maxAttempts"
            );
            
            // Simulate failures up to max_attempts - 1
            for ($attempt = 1; $attempt < $maxAttempts; $attempt++) {
                // Mark as processing
                $this->emailQueueRepository->markAsProcessing($queuedEmail['id']);
                
                // Mark as failed
                $failedEmail = $this->emailQueueRepository->markAsFailed($queuedEmail['id'], "Test failure $attempt");
                
                // Should still be pending for retry (not permanently failed)
                $this->assert(
                    $failedEmail['status'] === 'pending',
                    "Email should be pending for retry after attempt $attempt (max: $maxAttempts)"
                );
                
                $this->assert(
                    (int)$failedEmail['attempts'] === $attempt,
                    "Email should have $attempt attempts after failure"
                );
            }
            
            // Final failure should mark as permanently failed
            $this->emailQueueRepository->markAsProcessing($queuedEmail['id']);
            $finalFailedEmail = $this->emailQueueRepository->markAsFailed($queuedEmail['id'], "Final test failure");
            
            $this->assert(
                $finalFailedEmail['status'] === 'failed',
                "Email should be permanently failed after reaching max_attempts"
            );
            
            $this->assert(
                (int)$finalFailedEmail['attempts'] === $maxAttempts,
                "Email should have max_attempts ($maxAttempts) after final failure"
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 14: Maximum attempt limits are respected
     * **Feature: email-management-system, Property 14: Email Retry Policy Compliance**
     * **Validates: Requirements 6.3**
     */
    public function testMaximumAttemptLimits(): array {
        try {
            // Generate random max_attempts between 1 and 10
            $maxAttempts = $this->generateRandomInt(1, 10);
            
            // Create email with specific max_attempts
            $emailData = [
                'to_email' => $this->generateRandomEmail(),
                'subject' => 'Test Email ' . uniqid(),
                'body_text' => 'Test email body content',
                'max_attempts' => $maxAttempts,
                'company_id' => $this->testCompanyId
            ];
            
            $queuedEmail = $this->emailQueueService->queueEmail($emailData, $this->testUserId);
            $this->createdEmailIds[] = $queuedEmail['id'];
            
            // Simulate failures beyond max_attempts
            for ($attempt = 1; $attempt <= $maxAttempts + 2; $attempt++) {
                $this->emailQueueRepository->markAsProcessing($queuedEmail['id']);
                $failedEmail = $this->emailQueueRepository->markAsFailed($queuedEmail['id'], "Test failure $attempt");
                
                if ($attempt < $maxAttempts) {
                    // Should still be pending for retry
                    $this->assert(
                        $failedEmail['status'] === 'pending',
                        "Email should be pending for retry at attempt $attempt (max: $maxAttempts)"
                    );
                } else {
                    // Should be permanently failed
                    $this->assert(
                        $failedEmail['status'] === 'failed',
                        "Email should be permanently failed at attempt $attempt (max: $maxAttempts)"
                    );
                    
                    // Attempts should not exceed max_attempts
                    $this->assert(
                        (int)$failedEmail['attempts'] <= $maxAttempts,
                        "Attempts should not exceed max_attempts: {$failedEmail['attempts']} <= $maxAttempts"
                    );
                    
                    break; // Stop testing once permanently failed
                }
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Property 14: Retry scheduling follows exponential backoff
     * **Feature: email-management-system, Property 14: Email Retry Policy Compliance**
     * **Validates: Requirements 6.3**
     */
    public function testExponentialBackoffScheduling(): array {
        try {
            // Test the retry schedule calculation
            $attempts = $this->generateRandomInt(0, 7); // Test different attempt counts
            
            $retryTime = $this->emailQueueRepository->getRetrySchedule($attempts);
            
            // Verify retry time is in the future
            $this->assert(
                strtotime($retryTime) > time(),
                "Retry time should be in the future"
            );
            
            // Verify exponential backoff pattern
            $expectedDelays = [60, 300, 900, 1800, 3600, 7200, 14400, 28800]; // seconds
            $expectedDelay = $expectedDelays[min($attempts, count($expectedDelays) - 1)];
            
            $actualDelay = strtotime($retryTime) - time();
            
            // Allow some tolerance for processing time (±10 seconds)
            $tolerance = 10;
            $this->assert(
                abs($actualDelay - $expectedDelay) <= $tolerance,
                "Retry delay should follow exponential backoff pattern. Expected: ~$expectedDelay seconds, Actual: ~$actualDelay seconds"
            );
            
            // Test that delays increase with attempt count (for attempts 0-6)
            if ($attempts < 6) {
                $nextRetryTime = $this->emailQueueRepository->getRetrySchedule($attempts + 1);
                $nextDelay = strtotime($nextRetryTime) - time();
                
                $this->assert(
                    $nextDelay > $actualDelay,
                    "Retry delay should increase with attempt count. Current: ~$actualDelay, Next: ~$nextDelay"
                );
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up test data
     */
    protected function cleanupTestData(): void {
        // Delete created emails
        foreach ($this->createdEmailIds as $emailId) {
            try {
                $stmt = $this->executeQuery(
                    "DELETE FROM email_queue WHERE id = ?",
                    [$emailId],
                    'i'
                );
                $stmt->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdEmailIds = [];
    }
}