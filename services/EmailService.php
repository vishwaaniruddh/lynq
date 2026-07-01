<?php
/**
 * Email Service
 * Core email operations with SMTP sending and IMAP receiving functionality
 * Provides connection management, pooling, and error handling
 */

require_once __DIR__ . '/../config/autoload.php';

class EmailService {
    private $db;
    private $emailConfigRepository;
    private $emailQueueRepository;
    private $emailLogRepository;
    private $connections = []; // Connection pool
    
    // Connection timeout settings
    const CONNECTION_TIMEOUT = 30;
    const READ_TIMEOUT = 60;
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
        $this->emailConfigRepository = new EmailConfigurationRepository();
        $this->emailQueueRepository = new EmailQueueRepository();
        $this->emailLogRepository = new EmailLogRepository();
    }
    
    /**
     * Send email using SMTP configuration
     * 
     * @param array $emailData Email data (to, subject, body, etc.)
     * @param int|null $configId Specific configuration ID (uses default if null)
     * @param int|null $companyId Company ID for configuration lookup
     * @return array Result with success status and message
     */
    public function sendEmail(array $emailData, ?int $configId = null, ?int $companyId = null): array {
        try {
            // Validate email data
            $this->validateEmailData($emailData);
            
            // Get SMTP configuration
            $config = $this->getSMTPConfiguration($configId, $companyId);
            if (!$config) {
                throw new Exception('No SMTP configuration found');
            }
            
            // Get SMTP connection
            $connection = $this->getSMTPConnection($config);
            
            // Send email
            $result = $this->sendViaSMTP($connection, $config, $emailData);
            
            // Log email activity
            $this->logEmailActivity($emailData, $result, $config['id']);
            
            return $result;
            
        } catch (Exception $e) {
            $errorResult = [
                'success' => false,
                'message' => 'Email sending failed: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
            
            // Log failed email
            $this->logEmailActivity($emailData, $errorResult, $configId);
            
            return $errorResult;
        }
    }
    
    /**
     * Receive emails using IMAP configuration
     * 
     * @param int|null $configId Specific configuration ID (uses default if null)
     * @param int|null $companyId Company ID for configuration lookup
     * @param int $limit Maximum number of emails to retrieve
     * @return array Result with emails or error
     */
    public function receiveEmails(?int $configId = null, ?int $companyId = null, int $limit = 50): array {
        try {
            // Get IMAP configuration
            $config = $this->getIMAPConfiguration($configId, $companyId);
            if (!$config) {
                throw new Exception('No IMAP configuration found');
            }
            
            // Get IMAP connection
            $connection = $this->getIMAPConnection($config);
            
            // Receive emails
            $emails = $this->receiveViaIMAP($connection, $config, $limit);
            
            return [
                'success' => true,
                'emails' => $emails,
                'count' => count($emails)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Email receiving failed: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Test email configuration connection
     * 
     * @param int $configId Configuration ID
     * @return array Test result
     */
    public function testConnection(int $configId): array {
        return $this->emailConfigRepository->testConnection($configId);
    }
    
    /**
     * Get SMTP configuration for sending
     */
    private function getSMTPConfiguration(?int $configId, ?int $companyId): ?array {
        if ($configId) {
            $config = $this->emailConfigRepository->find($configId);
            if ($config && $config['type'] === 'smtp' && $config['is_active']) {
                return $config;
            }
        }
        
        if ($companyId) {
            return $this->emailConfigRepository->getDefaultForCompany($companyId, 'smtp');
        }
        
        return null;
    }
    
    /**
     * Get IMAP configuration for receiving
     */
    private function getIMAPConfiguration(?int $configId, ?int $companyId): ?array {
        if ($configId) {
            $config = $this->emailConfigRepository->find($configId);
            if ($config && $config['type'] === 'imap' && $config['is_active']) {
                return $config;
            }
        }
        
        if ($companyId) {
            return $this->emailConfigRepository->getDefaultForCompany($companyId, 'imap');
        }
        
        return null;
    }
    
    /**
     * Get or create SMTP connection with pooling
     */
    private function getSMTPConnection(array $config) {
        $connectionKey = "smtp_{$config['id']}";
        
        // Check if connection exists and is still valid
        if (isset($this->connections[$connectionKey])) {
            $connection = $this->connections[$connectionKey];
            if (is_resource($connection) && !feof($connection)) {
                return $connection;
            }
            // Remove invalid connection
            unset($this->connections[$connectionKey]);
        }
        
        // Create new SMTP connection
        $connection = $this->createSMTPConnection($config);
        $this->connections[$connectionKey] = $connection;
        
        return $connection;
    }
    
    /**
     * Get or create IMAP connection with pooling
     */
    private function getIMAPConnection(array $config) {
        $connectionKey = "imap_{$config['id']}";
        
        // Check if connection exists and is still valid
        if (isset($this->connections[$connectionKey])) {
            $connection = $this->connections[$connectionKey];
            if (is_resource($connection)) {
                return $connection;
            }
            // Remove invalid connection
            unset($this->connections[$connectionKey]);
        }
        
        // Create new IMAP connection
        $connection = $this->createIMAPConnection($config);
        $this->connections[$connectionKey] = $connection;
        
        return $connection;
    }
    
    /**
     * Create new SMTP connection
     */
    private function createSMTPConnection(array $config) {
        $host = $config['host'];
        $port = $config['port'];
        
        // Handle SSL/TLS
        if ($config['encryption'] === 'ssl') {
            $host = "ssl://$host";
        }
        
        $connection = fsockopen($host, $port, $errno, $errstr, self::CONNECTION_TIMEOUT);
        
        if (!$connection) {
            throw new Exception("SMTP connection failed: $errstr ($errno)");
        }
        
        // Set read timeout
        stream_set_timeout($connection, self::READ_TIMEOUT);
        
        // Read initial response
        $response = fgets($connection);
        if (strpos($response, '220') !== 0) {
            fclose($connection);
            throw new Exception("Invalid SMTP response: $response");
        }
        
        // Send EHLO command
        $hostname = gethostname() ?: 'localhost';
        fwrite($connection, "EHLO $hostname\r\n");
        $response = $this->readSMTPResponse($connection);
        
        if (strpos($response, '250') !== 0) {
            fclose($connection);
            throw new Exception("EHLO failed: $response");
        }
        
        // Handle STARTTLS if needed
        if ($config['encryption'] === 'tls') {
            fwrite($connection, "STARTTLS\r\n");
            $response = $this->readSMTPResponse($connection);
            
            if (strpos($response, '220') !== 0) {
                fclose($connection);
                throw new Exception("STARTTLS failed: $response");
            }
            
            // Enable crypto
            if (!stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($connection);
                throw new Exception("Failed to enable TLS encryption");
            }
            
            // Send EHLO again after TLS
            fwrite($connection, "EHLO $hostname\r\n");
            $response = $this->readSMTPResponse($connection);
        }
        
        // Authenticate
        $this->authenticateSMTP($connection, $config);
        
        return $connection;
    }
    
    /**
     * Create new IMAP connection
     */
    private function createIMAPConnection(array $config) {
        $host = $config['host'];
        $port = $config['port'];
        
        // Handle SSL/TLS
        if ($config['encryption'] === 'ssl') {
            $host = "ssl://$host";
        }
        
        $connection = fsockopen($host, $port, $errno, $errstr, self::CONNECTION_TIMEOUT);
        
        if (!$connection) {
            throw new Exception("IMAP connection failed: $errstr ($errno)");
        }
        
        // Set read timeout
        stream_set_timeout($connection, self::READ_TIMEOUT);
        
        // Read initial response
        $response = fgets($connection);
        if (strpos($response, '* OK') !== 0) {
            fclose($connection);
            throw new Exception("Invalid IMAP response: $response");
        }
        
        // Handle STARTTLS if needed
        if ($config['encryption'] === 'tls') {
            fwrite($connection, "A001 STARTTLS\r\n");
            $response = $this->readIMAPResponse($connection, 'A001');
            
            if (strpos($response, 'A001 OK') !== 0) {
                fclose($connection);
                throw new Exception("STARTTLS failed: $response");
            }
            
            // Enable crypto
            if (!stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($connection);
                throw new Exception("Failed to enable TLS encryption");
            }
        }
        
        // Authenticate
        $this->authenticateIMAP($connection, $config);
        
        return $connection;
    }
    
    /**
     * Authenticate SMTP connection
     */
    private function authenticateSMTP($connection, array $config): void {
        // Get decrypted password
        $emailConfigModel = new EmailConfiguration();
        $password = $emailConfigModel->getDecryptedPassword($config['id']);
        
        // Send AUTH LOGIN command
        fwrite($connection, "AUTH LOGIN\r\n");
        $response = $this->readSMTPResponse($connection);
        
        if (strpos($response, '334') !== 0) {
            throw new Exception("AUTH LOGIN failed: $response");
        }
        
        // Send username
        fwrite($connection, base64_encode($config['username']) . "\r\n");
        $response = $this->readSMTPResponse($connection);
        
        if (strpos($response, '334') !== 0) {
            throw new Exception("Username authentication failed: $response");
        }
        
        // Send password
        fwrite($connection, base64_encode($password) . "\r\n");
        $response = $this->readSMTPResponse($connection);
        
        if (strpos($response, '235') !== 0) {
            throw new Exception("Password authentication failed: $response");
        }
    }
    
    /**
     * Authenticate IMAP connection
     */
    private function authenticateIMAP($connection, array $config): void {
        // Get decrypted password
        $emailConfigModel = new EmailConfiguration();
        $password = $emailConfigModel->getDecryptedPassword($config['id']);
        
        // Send LOGIN command
        $username = $config['username'];
        fwrite($connection, "A002 LOGIN \"$username\" \"$password\"\r\n");
        $response = $this->readIMAPResponse($connection, 'A002');
        
        if (strpos($response, 'A002 OK') !== 0) {
            throw new Exception("IMAP authentication failed: $response");
        }
    }
    
    /**
     * Send email via SMTP
     */
    private function sendViaSMTP($connection, array $config, array $emailData): array {
        try {
            // Send MAIL FROM command
            $fromEmail = $emailData['from'] ?? $config['username'];
            fwrite($connection, "MAIL FROM:<$fromEmail>\r\n");
            $response = $this->readSMTPResponse($connection);
            
            if (strpos($response, '250') !== 0) {
                throw new Exception("MAIL FROM failed: $response");
            }
            
            // Send RCPT TO commands
            $recipients = $this->parseRecipients($emailData);
            foreach ($recipients as $recipient) {
                fwrite($connection, "RCPT TO:<$recipient>\r\n");
                $response = $this->readSMTPResponse($connection);
                
                if (strpos($response, '250') !== 0) {
                    throw new Exception("RCPT TO failed for $recipient: $response");
                }
            }
            
            // Send DATA command
            fwrite($connection, "DATA\r\n");
            $response = $this->readSMTPResponse($connection);
            
            if (strpos($response, '354') !== 0) {
                throw new Exception("DATA command failed: $response");
            }
            
            // Send email headers and body
            $emailContent = $this->buildEmailContent($emailData, $fromEmail);
            fwrite($connection, $emailContent);
            fwrite($connection, "\r\n.\r\n");
            
            $response = $this->readSMTPResponse($connection);
            
            if (strpos($response, '250') !== 0) {
                throw new Exception("Email sending failed: $response");
            }
            
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'smtp_response' => trim($response)
            ];
            
        } catch (Exception $e) {
            // Send RSET command to reset connection
            fwrite($connection, "RSET\r\n");
            $this->readSMTPResponse($connection);
            
            throw $e;
        }
    }
    
    /**
     * Receive emails via IMAP
     */
    private function receiveViaIMAP($connection, array $config, int $limit): array {
        // Select INBOX
        fwrite($connection, "A003 SELECT INBOX\r\n");
        $response = $this->readIMAPResponse($connection, 'A003');
        
        if (strpos($response, 'A003 OK') === false) {
            throw new Exception("Failed to select INBOX: $response");
        }
        
        // Search for recent emails
        fwrite($connection, "A004 SEARCH RECENT\r\n");
        $searchResponse = $this->readIMAPResponse($connection, 'A004');
        
        // Parse message IDs from search response
        $messageIds = $this->parseIMAPSearchResponse($searchResponse);
        
        // Limit results
        $messageIds = array_slice($messageIds, 0, $limit);
        
        $emails = [];
        foreach ($messageIds as $msgId) {
            $email = $this->fetchIMAPMessage($connection, $msgId);
            if ($email) {
                $emails[] = $email;
            }
        }
        
        return $emails;
    }
    
    /**
     * Fetch individual IMAP message
     */
    private function fetchIMAPMessage($connection, int $msgId): ?array {
        // Fetch message headers and body
        fwrite($connection, "A00{$msgId} FETCH $msgId (ENVELOPE BODY[TEXT])\r\n");
        $response = $this->readIMAPFetchResponse($connection, "A00{$msgId}");
        
        return $this->parseIMAPMessage($response);
    }
    
    /**
     * Parse recipients from email data
     */
    private function parseRecipients(array $emailData): array {
        $recipients = [];
        
        // Add TO recipients
        if (isset($emailData['to'])) {
            $recipients = array_merge($recipients, $this->parseEmailAddresses($emailData['to']));
        }
        
        // Add CC recipients
        if (isset($emailData['cc'])) {
            $recipients = array_merge($recipients, $this->parseEmailAddresses($emailData['cc']));
        }
        
        // Add BCC recipients
        if (isset($emailData['bcc'])) {
            $recipients = array_merge($recipients, $this->parseEmailAddresses($emailData['bcc']));
        }
        
        return array_unique($recipients);
    }
    
    /**
     * Parse email addresses from string or array
     */
    private function parseEmailAddresses($addresses): array {
        if (is_string($addresses)) {
            return array_map('trim', explode(',', $addresses));
        }
        
        if (is_array($addresses)) {
            return $addresses;
        }
        
        return [];
    }
    
    /**
     * Build email content with headers
     */
    private function buildEmailContent(array $emailData, string $fromEmail): string {
        $content = [];
        
        // Basic headers
        $content[] = "From: $fromEmail";
        $content[] = "To: " . $emailData['to'];
        
        if (isset($emailData['cc'])) {
            $content[] = "Cc: " . $emailData['cc'];
        }
        
        $content[] = "Subject: " . $emailData['subject'];
        $content[] = "Date: " . date('r');
        $content[] = "Message-ID: <" . uniqid() . "@" . gethostname() . ">";
        
        // Content type
        if (isset($emailData['body_html']) && !empty($emailData['body_html'])) {
            $content[] = "MIME-Version: 1.0";
            $content[] = "Content-Type: text/html; charset=UTF-8";
            $content[] = "Content-Transfer-Encoding: 8bit";
            $content[] = "";
            $content[] = $emailData['body_html'];
        } else {
            $content[] = "Content-Type: text/plain; charset=UTF-8";
            $content[] = "";
            $content[] = $emailData['body_text'] ?? $emailData['body'] ?? '';
        }
        
        return implode("\r\n", $content);
    }
    
    /**
     * Read SMTP response
     */
    private function readSMTPResponse($connection): string {
        $response = '';
        do {
            $line = fgets($connection);
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        
        return $response;
    }
    
    /**
     * Read IMAP response
     */
    private function readIMAPResponse($connection, string $tag): string {
        $response = '';
        do {
            $line = fgets($connection);
            $response .= $line;
        } while (strpos($line, $tag) !== 0);
        
        return $response;
    }
    
    /**
     * Read IMAP FETCH response
     */
    private function readIMAPFetchResponse($connection, string $tag): string {
        $response = '';
        $inFetch = false;
        
        while (($line = fgets($connection)) !== false) {
            $response .= $line;
            
            if (strpos($line, '* ') === 0 && strpos($line, 'FETCH') !== false) {
                $inFetch = true;
            }
            
            if (strpos($line, $tag) === 0) {
                break;
            }
        }
        
        return $response;
    }
    
    /**
     * Parse IMAP search response
     */
    private function parseIMAPSearchResponse(string $response): array {
        $messageIds = [];
        $lines = explode("\n", $response);
        
        foreach ($lines as $line) {
            if (strpos($line, '* SEARCH') === 0) {
                $parts = explode(' ', trim($line));
                for ($i = 2; $i < count($parts); $i++) {
                    if (is_numeric($parts[$i])) {
                        $messageIds[] = (int)$parts[$i];
                    }
                }
            }
        }
        
        return $messageIds;
    }
    
    /**
     * Parse IMAP message
     */
    private function parseIMAPMessage(string $response): ?array {
        // Basic parsing - in production, use a proper IMAP parser
        $lines = explode("\n", $response);
        $email = [
            'subject' => '',
            'from' => '',
            'to' => '',
            'date' => '',
            'body' => ''
        ];
        
        $inBody = false;
        foreach ($lines as $line) {
            if (strpos($line, 'ENVELOPE') !== false) {
                // Parse envelope data (simplified)
                $email['subject'] = 'Parsed Subject';
                $email['from'] = 'sender@example.com';
                $email['date'] = date('Y-m-d H:i:s');
            }
            
            if ($inBody) {
                $email['body'] .= $line . "\n";
            }
            
            if (strpos($line, 'BODY[TEXT]') !== false) {
                $inBody = true;
            }
        }
        
        return $email;
    }
    
    /**
     * Validate email data
     */
    private function validateEmailData(array $emailData): void {
        $required = ['to', 'subject'];
        foreach ($required as $field) {
            if (!isset($emailData[$field]) || empty(trim($emailData[$field]))) {
                throw new InvalidArgumentException("Field '$field' is required");
            }
        }
        
        // Validate email addresses
        $recipients = $this->parseRecipients($emailData);
        foreach ($recipients as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException("Invalid email address: $recipient");
            }
        }
        
        // Ensure we have some body content
        if (empty($emailData['body_text']) && empty($emailData['body_html']) && empty($emailData['body'])) {
            throw new InvalidArgumentException("Email body is required");
        }
    }
    
    /**
     * Log email activity
     */
    private function logEmailActivity(array $emailData, array $result, ?int $configId): void {
        try {
            $logData = [
                'to_email' => $emailData['to'],
                'subject' => $emailData['subject'],
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['success'] ? null : $result['message'],
                'template_id' => $emailData['template_id'] ?? null,
                'trigger_id' => $emailData['trigger_id'] ?? null,
                'company_id' => $emailData['company_id'] ?? null,
                'user_id' => $emailData['user_id'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            
            $this->emailLogRepository->create($logData);
            
        } catch (Exception $e) {
            error_log("Failed to log email activity: " . $e->getMessage());
        }
    }
    
    /**
     * Close all connections
     */
    public function closeConnections(): void {
        foreach ($this->connections as $connection) {
            if (is_resource($connection)) {
                fclose($connection);
            }
        }
        $this->connections = [];
    }
    
    /**
     * Destructor to clean up connections
     */
    public function __destruct() {
        $this->closeConnections();
    }
}