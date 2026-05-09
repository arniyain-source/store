<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/api-logger.php';

class LogisticsHelper {
    
    private $apiToken;
    private $apiUrl = 'https://apiv2.shiprocket.in/v1/external/';
    private $logger;

    public function __construct() {
        $this->apiToken = $this->getShiprocketToken();
        $this->logger = new ApiLogger();
    }

    private function getShiprocketToken() {
        $email = getSetting('shiprocket_email');
        $password = getSetting('shiprocket_token'); // Assuming token is stored in password field for now

        if (empty($email) || empty($password)) {
            return null;
        }

        $cacheKey = 'shiprocket_auth_token';
        $cachedToken = getSetting($cacheKey);
        if ($cachedToken) {
            $tokenData = json_decode($cachedToken, true);
            // Simple check if token exists. Add expiry check if API provides it.
            if (isset($tokenData['token'])) {
                 return $tokenData['token'];
            }
        }

        $response = $this->makeApiRequest('auth/login', 'POST', [
            'email' => $email,
            'password' => $password
        ]);

        if (isset($response['token'])) {
            // Cache the token with an expiry if possible (e.g., 24 hours)
            updateSetting($cacheKey, json_encode($response)); 
            return $response['token'];
        }

        return null;
    }

    private function makeApiRequest($endpoint, $method = 'GET', $data = []) {
        if (!$this->apiToken && $endpoint !== 'auth/login') {
            return ['error' => 'Shiprocket API not configured'];
        }
        
        $ch = curl_init();
        $url = $this->apiUrl . $endpoint;

        $headers = [
            'Content-Type: application/json',
        ];
        if ($this->apiToken) {
            $headers[] = 'Authorization: Bearer ' . $this->apiToken;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $duration = microtime(true) - $startTime;
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Log the transaction
        $this->logger->log('shiprocket', $url, $method, $data, $response, $httpStatus, $duration);

        if ($curlError) {
            return ['error' => 'cURL Error: ' . $curlError];
        }

        return json_decode($response, true);
    }

    public function checkServiceability($pincode, $cod = false) {
        if (!$this->apiToken) return ['error' => true, 'message' => 'Logistics not configured.'];

        $params = [
            'pickup_postcode' => getSetting('warehouse_pincode', '110001'), // Default to Delhi
            'delivery_postcode' => $pincode,
            'weight' => 0.5, // Default weight in kg
            'cod' => $cod ? 1 : 0,
        ];
        
        $query = http_build_query($params);
        $response = $this->makeApiRequest("courier/serviceability/?{$query}");

        if (isset($response['status']) && $response['status'] == 200) {
            return $response['data']['available_courier_companies'] ?? [];
        } 
        
        return [];
    }

    public function createShipment(array $orderData) {
        if (!$this->apiToken) return ['error' => true, 'message' => 'Logistics not configured.'];

        // Map DesiVastra order format to Shiprocket format
        $shipmentData = [
            "order_id" => $orderData['order_number'],
            "order_date" => date("Y-m-d H:i", strtotime($orderData['created_at'])),
            "pickup_location_name" => getSetting('warehouse_name', 'DesiVastra Delhi'),
            "billing_customer_name" => $orderData['shipping_address']['name'],
            "billing_last_name" => "", // Optional
            "billing_address" => $orderData['shipping_address']['address_line1'],
            "billing_city" => $orderData['shipping_address']['city'],
            "billing_pincode" => $orderData['shipping_address']['pincode'],
            "billing_state" => $orderData['shipping_address']['state'],
            "billing_country" => "India",
            "billing_email" => $orderData['customer_email'],
            "billing_phone" => $orderData['shipping_address']['phone'],
            "shipping_is_billing" => true,
            "order_items" => array_map(function($item) {
                return [
                    "name" => $item['product_name'],
                    "sku" => $item['sku'] ?? 'DV-PROD-' . $item['product_id'],
                    "units" => $item['quantity'],
                    "selling_price" => $item['price'],
                ];
            }, $orderData['items'] ?? []),
            "payment_method" => ($orderData['payment_method'] === 'cod') ? "COD" : "Prepaid",
            "sub_total" => $orderData['total'],
            "length" => 10, "breadth" => 10, "height" => 5, "weight" => 0.5, // Default dimensions
        ];

        $response = $this->makeApiRequest('orders/create/adhoc', 'POST', $shipmentData);

        if (isset($response['shipment_id']) && isset($response['order_id'])) {
             return ['success' => true, 'shipment' => $response];
        } else {
             return ['success' => false, 'message' => $response['message'] ?? 'Unknown error creating shipment.'];
        }
    }
    
    public function trackShipment($awb) {
        if (!$this->apiToken) return null;
        return $this->makeApiRequest("tracking/{$awb}");
    }

    public function cancelShipment($orderId) {
        if (!$this->apiToken) return null;
        return $this->makeApiRequest('orders/cancel', 'POST', ['ids' => [$orderId]]);
    }

    public function generateLabel($shipmentId) {
        if (!$this->apiToken) return null;
        return $this->makeApiRequest('courier/generate/label', 'POST', ['shipment_id' => [$shipmentId]]);
    }
}
