<?php
/**
 * API Logger - A simple, file-based logger for external API calls.
 */
class ApiLogger {

    private $logFile;
    private $logData = [];
    private $logLimit = 100; // Max number of log entries

    public function __construct($logFilePath = 'api_logs.json') {
        $this->logFile = __DIR__ . '/' . $logFilePath;
        $this->loadLogs();
    }

    private function loadLogs() {
        if (file_exists($this->logFile)) {
            $json = file_get_contents($this->logFile);
            $this->logData = json_decode($json, true) ?: [];
        } else {
            // Create the file if it doesn't exist, to prevent errors on first run
            file_put_contents($this->logFile, '[]');
        }
    }

    private function saveLogs() {
        // Keep the log file size manageable
        if (count($this->logData) > $this->logLimit) {
            $this->logData = array_slice($this->logData, -$this->logLimit);
        }
        file_put_contents($this->logFile, json_encode($this->logData, JSON_PRETTY_PRINT));
    }

    /**
     * Logs an API request and its response.
     *
     * @param string $provider (e.g., 'facebook', 'shiprocket')
     * @param string $endpoint The API endpoint URL or path.
     * @param string $method The HTTP method (e.g., 'POST', 'GET').
     * @param array|null $requestData The data sent in the request.
     * @param array|string|null $response The response from the API.
     * @param int $httpStatus The HTTP status code of the response.
     * @param float $duration The duration of the API call in seconds.
     * @return void
     */
    public function log(
        string $provider,
        string $endpoint,
        string $method,
        ?array $requestData,
        $response,
        int $httpStatus,
        float $duration
    ) {
        // Basic sanitation and data normalization
        $isSuccess = $httpStatus >= 200 && $httpStatus < 300;

        $logEntry = [
            'timestamp'       => date('c'), // ISO 8601 format
            'provider'        => strtolower(trim($provider)),
            'endpoint'        => $endpoint,
            'request_method'  => strtoupper($method),
            'request_payload' => $this->sanitizePayload($requestData),
            'response_status' => $httpStatus,
            'response_body'   => is_string($response) ? $response : json_encode($response),
            'duration'        => round($duration, 4),
            'status'          => $isSuccess ? 'success' : 'failed',
            'client_ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        array_unshift($this->logData, $logEntry);
        $this->saveLogs();
    }

    /**
     * Retrieves logs, optionally filtering by status.
     *
     * @param int $limit The maximum number of logs to return.
     * @param string $filter ('all', 'success', 'failed')
     * @return array
     */
    public function getLogs(int $limit = 20, string $filter = 'all'): array {
        if ($filter === 'all') {
            return array_slice($this->logData, 0, $limit);
        }

        $filteredLogs = array_filter($this->logData, function($log) use ($filter) {
            return isset($log['status']) && $log['status'] === $filter;
        });
        
        return array_slice($filteredLogs, 0, $limit);
    }

    /**
     * Clears all logs.
     */
    public function clearLogs() {
        $this->logData = [];
        $this->saveLogs();
    }

    /**
     * Sanitizes payload data, removing sensitive information.
     *
     * @param array|null $payload
     * @return array|null
     */
    private function sanitizePayload(?array $payload): ?array {
        if (!$payload) return null;

        $sensitiveKeys = ['password', 'token', 'key', 'secret', 'auth', 'card_number', 'cvv'];

        foreach ($payload as $key => &$value) {
            foreach ($sensitiveKeys as $sensitive) {
                if (stripos($key, $sensitive) !== false) {
                    $value = '***REDACTED***';
                    continue 2;
                }
            }
        }
        return $payload;
    }
}
