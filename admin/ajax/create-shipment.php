<?php

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/logistics-helper.php';

requireAdminLogin();

$db = getDB();
$logistics = new LogisticsHelper();

// Handle POST request to create the shipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $orderId = (int)$_POST['order_id'];
    
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        jsonResponse(['success' => false, 'message' => 'Order not found.'], 404);
        return;
    }
    
    // Fetch order items
    $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order['items'] = $stmt->fetchAll();
    $order['shipping_address'] = json_decode($order['shipping_address'], true);

    $result = $logistics->createShipment($order);

    if ($result['success']) {
        // Update order status in DB
        $updateStmt = $db->prepare("UPDATE orders SET status = 'shipped', shipped_at = NOW(), tracking_provider = 'shiprocket', tracking_number = ? WHERE id = ?");
        $updateStmt->execute([$result['shipment']['awb_code'], $orderId]);

        // Log activity
        logActivity('create_shipment', 'order', $orderId, ['shipment_id' => $result['shipment']['shipment_id'], 'awb' => $result['shipment']['awb_code']]);
        
        jsonResponse(['success' => true, 'message' => 'Shipment created successfully! AWB: ' . $result['shipment']['awb_code']]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to create shipment: ' . ($result['message'] ?? 'Unknown API error')], 500);
    }
    return; // Stop execution after ajax response
}

// Handle GET request to show the shipment creation form
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    echo '<div class="alert alert-danger">Order not found.</div>';
    exit;
}

$shippingAddress = json_decode($order['shipping_address'], true);
$pincode = $shippingAddress['pincode'] ?? null;

$isCod = $order['payment_method'] === 'cod';
$couriers = $pincode ? $logistics->checkServiceability($pincode, $isCod) : [];

?>

<form id="createShipmentForm">
    <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
    <div class="alert alert-info">
        <h5><i class="icon fas fa-info"></i> Order #<?php echo clean($order['order_number']); ?></h5>
        <p><strong>Customer:</strong> <?php echo clean($order['customer_name']); ?><br>
        <strong>Address:</strong> <?php echo clean($shippingAddress['address_line1']); ?>, <?php echo clean($shippingAddress['city']); ?>, <?php echo clean($shippingAddress['pincode']); ?><br>
        <strong>Payment:</strong> <?php echo strtoupper($order['payment_method']); ?> (<?php echo clean($order['payment_status']); ?>)</p>
    </div>

    <?php if (!$pincode): ?>
        <div class="alert alert-danger">Cannot check serviceability: Pincode not found in address.</div>
    <?php elseif (empty($couriers)): ?>
        <div class="alert alert-warning"><strong>Serviceability Warning:</strong> No couriers found for pincode <?php echo clean($pincode); ?>. Shipment creation may fail.</div>
    <?php else: ?>
        <div class="form-group">
            <label>Recommended Couriers for Pincode <?php echo clean($pincode); ?>:</label>
            <div class="row">
            <?php foreach (array_slice($couriers, 0, 4) as $courier): ?>
                <div class="col-md-3 col-sm-6">
                    <div class="small-box bg-light text-center">
                        <div class="inner">
                            <p><strong><?php echo clean($courier['courier_name']); ?></strong></p>
                            <p>Rate: <?php echo formatIndianPrice($courier['rate']); ?><br>
                               ETA: <?php echo $courier['etd']; ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="form-group">
        <label for="courier_id">Select Courier</label>
        <select class="form-control" id="courier_id" name="courier_id">
            <option value="">Auto-select cheapest</option>
            <?php foreach ($couriers as $courier): ?>
            <option value="<?php echo $courier['courier_company_id']; ?>"><?php echo $courier['courier_name']; ?> (Est. Cost: <?php echo formatIndianPrice($courier['rate']); ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="row">
        <div class="col-md-6"><div class="form-group"><label>Weight (kg)</label><input type="number" class="form-control" value="0.5" name="weight"></div></div>
        <div class="col-md-6"><div class="form-group"><label>Dimensions (cm)</label><input type="text" class="form-control" value="10x10x5" name="dimensions"></div></div>
    </div>

    <div id="shipmentResult" class="mt-3"></div>

    <div class="text-right">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="submitShipmentBtn" <?php echo empty($couriers) ? 'disabled' : ''; ?>>
            <i class="fas fa-truck"></i> Create Shipment Now
        </button>
    </div>

</form>

<script>
$(document).ready(function() {
    $('#submitShipmentBtn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: 'ajax/create-shipment.php',
            type: 'POST',
            data: $('#createShipmentForm').serialize(),
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    $('#shipmentResult').html(`<div class="alert alert-success">${response.message}</div>`);
                    setTimeout(function() {
                        $('#shipmentModal').modal('hide');
                        location.reload(); // Reload the main orders page
                    }, 2000);
                } else {
                    $('#shipmentResult').html(`<div class="alert alert-danger">${response.message}</div>`);
                    btn.prop('disabled', false).html('<i class="fas fa-truck"></i> Create Shipment Now');
                }
            },
            error: function(jqXHR) {
                let errorMsg = 'An unexpected error occurred.';
                if(jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    errorMsg = jqXHR.responseJSON.message;
                }
                $('#shipmentResult').html(`<div class="alert alert-danger">Error: ${errorMsg}</div>`);
                btn.prop('disabled', false).html('<i class="fas fa-truck"></i> Create Shipment Now');
            }
        });
    });
});
</script>
