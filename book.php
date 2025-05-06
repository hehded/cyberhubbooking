<?php
header('Content-Type: application/json; charset=utf-8');
require 'token.php'; // переменная $token подключается отсюда

$data = json_decode(file_get_contents("php://input"), true);
$pc_id = $data['pc_id'] ?? null;
$phone = trim($data['phone'] ?? '');
$start = $data['start'] ?? '';
$end   = $data['end']   ?? '';

if (!$phone || !$pc_id || !$start || !$end) {
    echo json_encode(['success' => false, 'error' => 'Заполните все поля.']); exit;
}

// Смарт ID клиента — как вы указали
$client_id = 6242;

// Преобразуем время в ISO формат
try {
    $from = date("Y-m-d H:i:s", strtotime($start));
    $to   = date("Y-m-d H:i:s", strtotime($end));
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Неверный формат даты/времени.']); exit;
}

$comment = "Онлайн-бронь. Телефон: $phone";

// GraphQL запрос
$query = <<<GQL
mutation {
  createBooking(input: {
    hosts: [$pc_id],
    client: $client_id,
    from: "$from",
    to: "$to",
    comment: "$comment"
  }) {
    id
    from
    to
    status
  }
}
GQL;

$response = GetCurl(
    "https://billing.smartshell.gg/api/graphql",
    ["Authorization: Bearer $token", "Content-Type: application/json"],
    json_encode(['query' => $query])
);

if (isset($response['data']['createBooking']['id'])) {
    echo json_encode(['success' => true]);
} else {
    $err = $response['errors'][0]['message'] ?? 'Не удалось создать бронь.';
    echo json_encode(['success' => false, 'error' => $err]);
}

function GetCurl($url, $headers = [], $post_fields = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if (!empty($post_fields)) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    }
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    return json_decode($res, true);
}
