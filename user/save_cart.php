<?php
// user/save_cart.php
session_start();

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    $_SESSION['cart'][$data['cart_id']] = [
        'item' => $data['item'],
        'type' => $data['type'],
        'quantity' => $data['quantity'],
        'seats' => $data['seats']
    ];
    http_response_code(200);
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false]);
}
?>