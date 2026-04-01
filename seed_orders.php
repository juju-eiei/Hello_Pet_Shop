<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    $db->beginTransaction();

    // 1. Get a customer and address
    $stmtC = $db->query("SELECT customer_id FROM customers LIMIT 1");
    $customer = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        throw new Exception("No customer found. Please make sure database is initialized.");
    }
    $customerId = $customer['customer_id'];

    $stmtA = $db->prepare("SELECT address_id FROM addresses WHERE customer_id = ? LIMIT 1");
    $stmtA->execute([$customerId]);
    $address = $stmtA->fetch(PDO::FETCH_ASSOC);
    $addressId = $address ? $address['address_id'] : null;

    // 2. Get some products
    $stmtP = $db->query("SELECT product_id, selling_price as price, cost_price FROM products LIMIT 3");
    $products = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    if (!$products) {
        throw new Exception("No products found to seed orders.");
    }

    $statuses = [1, 2, 3, 4, 5];
    
    echo "Starting to seed 12 orders...\n";

    for ($i = 1; $i <= 12; $i++) {
        shuffle($products);
        $numProducts = rand(1, 2);
        
        $subtotal = 0;
        $order_details = [];
        
        for ($j = 0; $j < $numProducts; $j++) {
            $p = $products[$j];
            $qty = rand(1, 3);
            $total_price = $p['price'] * $qty;
            $subtotal += $total_price;
            
            $order_details[] = [
                'product_id' => $p['product_id'],
                'quantity' => $qty,
                'unit_cost' => $p['cost_price'],
                'unit_price' => $p['price']
            ];
        }
        
        $shipping_fee = 50.00;
        $discount_amount = 0.00;
        $net_total = $subtotal + $shipping_fee;
        
        $status = $statuses[array_rand($statuses)];
        
        // Random date within last 30 days
        $days_ago = rand(0, 30);
        $order_date = date('Y-m-d H:i:s', strtotime("-$days_ago days"));

        // Insert Order
        $stmtOrder = $db->prepare("INSERT INTO orders (customer_id, address_id, order_date, subtotal, shipping_fee, discount_amount, net_total, order_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)");
        $stmtOrder->execute([$customerId, $addressId, $order_date, $subtotal, $shipping_fee, $discount_amount, $net_total, $status]);
        $order_id = $db->lastInsertId();
        
        // Insert Details
        $stmtDetail = $db->prepare("INSERT INTO order_details (order_id, product_id, quantity, unit_price, unit_cost) VALUES (?, ?, ?, ?, ?)");
        foreach ($order_details as $detail) {
            $stmtDetail->execute([$order_id, $detail['product_id'], $detail['quantity'], $detail['unit_price'], $detail['unit_cost']]);
        }
    }

    $db->commit();
    echo "12 Mock Orders seeded successfully!\n";

} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
?>
