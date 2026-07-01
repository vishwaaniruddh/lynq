<?php
/**
 * Property Test: Lock Duration Constraint
 * 
 * **Feature: ip-configuration-management, Property 9: Lock Duration Constraint**
 * **Validates: Requirements 4.1**
 * 
 * Property: For any IP lock created, the lock duration SHALL be exactly 20 minutes 
 * from creation time.
 */

require_once __DIR__ . '/PropertyTestBase.php';
require_once __DIR__ . '/../models/IPLock.php';

class LockDurationConstraintTest extends PropertyTestBase {
    
    const LOCK_DURATION_SECONDS = 20 * 60; // 20 minutes in seconds
    const TOLERANCE_SECONDS = 2; // Allow 2 seconds tolerance for test execution time
    
    /**
     * Generate a random timestamp within the last year
     */
    protected function generateRandomTimestamp(): string {
        $now = time();
        $oneYearAgo = $now - (365 * 24 * 60 * 60);
        $randomTime = rand($oneYearAgo, $now);
        return date('Y-m-d H:i:s', $randomTime);
    }
    
    /**
     * Property Test: Lock expiry time is exactly 20 minutes from creation
     * 
     * For any lock creation time, the calculated expiry time should be
     * exactly 20 minutes (1200 seconds) later.
     */
    public function testLockDurationIsExactly20Minutes(): bool {
        echo "\n=== Property Test: Lock Duration Is Exactly 20 Minutes ===\n";
        
        return $this->runPropertyTest(
            'Lock duration is exactly 20 minutes from creation time',
            function() {
                // Generate a random start time
                $startTime = $this->generateRandomTimestamp();
                $startTimestamp = strtotime($startTime);
                
                // Calculate expiry using the model method
                $expiryTime = IPLock::calculateExpiryTime($startTime);
                $expiryTimestamp = strtotime($expiryTime);
                
                // Calculate the actual duration
                $actualDuration = $expiryTimestamp - $startTimestamp;
                
                // Verify duration is exactly 20 minutes (1200 seconds)
                if ($actualDuration !== self::LOCK_DURATION_SECONDS) {
                    return [
                        'success' => false,
                        'message' => "Lock duration is $actualDuration seconds, expected " . self::LOCK_DURATION_SECONDS,
                        'data' => [
                            'start_time' => $startTime,
                            'expiry_time' => $expiryTime,
                            'actual_duration_seconds' => $actualDuration,
                            'expected_duration_seconds' => self::LOCK_DURATION_SECONDS
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Lock constant matches expected duration
     * 
     * The LOCK_DURATION_MINUTES constant should be exactly 20.
     */
    public function testLockDurationConstant(): bool {
        echo "\n=== Property Test: Lock Duration Constant ===\n";
        
        return $this->runPropertyTest(
            'Lock duration constant is 20 minutes',
            function() {
                $constant = IPLock::LOCK_DURATION_MINUTES;
                
                if ($constant !== 20) {
                    return [
                        'success' => false,
                        'message' => "LOCK_DURATION_MINUTES is $constant, expected 20",
                        'data' => ['actual' => $constant, 'expected' => 20]
                    ];
                }
                
                return ['success' => true];
            },
            1 // Only need to run once for constant check
        );
    }
    
    /**
     * Property Test: isExpired correctly identifies expired locks
     * 
     * For any lock with expiry time in the past, isExpired should return true.
     * For any lock with expiry time in the future, isExpired should return false.
     */
    public function testIsExpiredCorrectness(): bool {
        echo "\n=== Property Test: isExpired Correctness ===\n";
        
        return $this->runPropertyTest(
            'isExpired correctly identifies expired vs active locks',
            function() {
                $now = time();
                
                // Test expired lock (expiry in the past)
                $pastOffset = rand(1, 3600); // 1 second to 1 hour in the past
                $expiredLock = [
                    'expires_at' => date('Y-m-d H:i:s', $now - $pastOffset)
                ];
                
                if (!IPLock::isExpired($expiredLock)) {
                    return [
                        'success' => false,
                        'message' => 'Lock with past expiry was not identified as expired',
                        'data' => [
                            'expires_at' => $expiredLock['expires_at'],
                            'current_time' => date('Y-m-d H:i:s', $now),
                            'offset_seconds' => -$pastOffset
                        ]
                    ];
                }
                
                // Test active lock (expiry in the future)
                $futureOffset = rand(60, 1200); // 1 minute to 20 minutes in the future
                $activeLock = [
                    'expires_at' => date('Y-m-d H:i:s', $now + $futureOffset)
                ];
                
                if (IPLock::isExpired($activeLock)) {
                    return [
                        'success' => false,
                        'message' => 'Lock with future expiry was incorrectly identified as expired',
                        'data' => [
                            'expires_at' => $activeLock['expires_at'],
                            'current_time' => date('Y-m-d H:i:s', $now),
                            'offset_seconds' => $futureOffset
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: getRemainingSeconds returns correct value
     * 
     * For any lock, the remaining seconds should equal (expiry_time - current_time)
     * when positive, or 0 when expired.
     */
    public function testGetRemainingSecondsCorrectness(): bool {
        echo "\n=== Property Test: getRemainingSeconds Correctness ===\n";
        
        return $this->runPropertyTest(
            'getRemainingSeconds returns correct remaining time',
            function() {
                $now = time();
                
                // Test with future expiry
                $futureOffset = rand(60, 1200);
                $futureLock = [
                    'expires_at' => date('Y-m-d H:i:s', $now + $futureOffset)
                ];
                
                $remaining = IPLock::getRemainingSeconds($futureLock);
                
                // Allow small tolerance for execution time
                if (abs($remaining - $futureOffset) > self::TOLERANCE_SECONDS) {
                    return [
                        'success' => false,
                        'message' => "Remaining seconds mismatch for future lock",
                        'data' => [
                            'expected_approx' => $futureOffset,
                            'actual' => $remaining,
                            'difference' => abs($remaining - $futureOffset)
                        ]
                    ];
                }
                
                // Test with past expiry (should return 0)
                $pastOffset = rand(1, 3600);
                $pastLock = [
                    'expires_at' => date('Y-m-d H:i:s', $now - $pastOffset)
                ];
                
                $remaining = IPLock::getRemainingSeconds($pastLock);
                
                if ($remaining !== 0) {
                    return [
                        'success' => false,
                        'message' => "Expired lock should have 0 remaining seconds",
                        'data' => [
                            'expected' => 0,
                            'actual' => $remaining
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: getRemainingTimeFormatted returns valid format
     * 
     * For any lock, the formatted time should be in MM:SS format.
     */
    public function testGetRemainingTimeFormattedFormat(): bool {
        echo "\n=== Property Test: getRemainingTimeFormatted Format ===\n";
        
        return $this->runPropertyTest(
            'getRemainingTimeFormatted returns valid MM:SS format',
            function() {
                $now = time();
                
                // Test with various remaining times
                $offsets = [
                    rand(0, 59),           // Less than 1 minute
                    rand(60, 599),         // 1-9 minutes
                    rand(600, 1199),       // 10-19 minutes
                    rand(1200, 3600),      // 20+ minutes
                    -rand(1, 3600)         // Expired (negative)
                ];
                
                $offset = $offsets[array_rand($offsets)];
                $lock = [
                    'expires_at' => date('Y-m-d H:i:s', $now + $offset)
                ];
                
                $formatted = IPLock::getRemainingTimeFormatted($lock);
                
                // Verify format is MM:SS
                if (!preg_match('/^\d{2}:\d{2}$/', $formatted)) {
                    return [
                        'success' => false,
                        'message' => "Invalid format: '$formatted', expected MM:SS",
                        'data' => [
                            'formatted' => $formatted,
                            'offset_seconds' => $offset
                        ]
                    ];
                }
                
                // Parse and verify values are reasonable
                list($minutes, $seconds) = explode(':', $formatted);
                $minutes = (int)$minutes;
                $seconds = (int)$seconds;
                
                if ($seconds < 0 || $seconds > 59) {
                    return [
                        'success' => false,
                        'message' => "Invalid seconds value: $seconds",
                        'data' => ['formatted' => $formatted]
                    ];
                }
                
                if ($minutes < 0) {
                    return [
                        'success' => false,
                        'message' => "Invalid minutes value: $minutes",
                        'data' => ['formatted' => $formatted]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Property Test: Expired locks return 00:00 formatted time
     */
    public function testExpiredLocksReturnZeroFormattedTime(): bool {
        echo "\n=== Property Test: Expired Locks Return 00:00 ===\n";
        
        return $this->runPropertyTest(
            'Expired locks return 00:00 formatted time',
            function() {
                $now = time();
                $pastOffset = rand(1, 3600);
                
                $expiredLock = [
                    'expires_at' => date('Y-m-d H:i:s', $now - $pastOffset)
                ];
                
                $formatted = IPLock::getRemainingTimeFormatted($expiredLock);
                
                if ($formatted !== '00:00') {
                    return [
                        'success' => false,
                        'message' => "Expired lock should return '00:00', got '$formatted'",
                        'data' => [
                            'expires_at' => $expiredLock['expires_at'],
                            'formatted' => $formatted
                        ]
                    ];
                }
                
                return ['success' => true];
            }
        );
    }
    
    /**
     * Run all property tests
     */
    public function runAllTests(): array {
        $results = [];
        
        $results['lock_duration_20_minutes'] = $this->testLockDurationIsExactly20Minutes();
        $results['lock_duration_constant'] = $this->testLockDurationConstant();
        $results['is_expired_correctness'] = $this->testIsExpiredCorrectness();
        $results['remaining_seconds_correctness'] = $this->testGetRemainingSecondsCorrectness();
        $results['formatted_time_format'] = $this->testGetRemainingTimeFormattedFormat();
        $results['expired_locks_zero_time'] = $this->testExpiredLocksReturnZeroFormattedTime();
        
        $passed = array_filter($results);
        $total = count($results);
        $passedCount = count($passed);
        
        echo "\n=== Summary ===\n";
        echo "Passed: $passedCount / $total\n";
        
        if ($passedCount === $total) {
            echo "✓ All property tests passed!\n";
        } else {
            echo "✗ Some property tests failed.\n";
            foreach ($results as $name => $result) {
                if (!$result) {
                    echo "  - Failed: $name\n";
                }
            }
        }
        
        return $results;
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $test = new LockDurationConstraintTest();
    $results = $test->runAllTests();
    exit(in_array(false, $results, true) ? 1 : 0);
}
