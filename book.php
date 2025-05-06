<?php
header('Content-Type: application/json; charset=utf-8');
require 'token.php'; // подключает $token

// ======== РЕЖИМ: ПОЛУЧИТЬ БЛИЖАЙШИЕ БРОНИ (GET) ==========
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $pc_id = intval($_GET['pc_id'] ?? 0);
    if (!$pc_id) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID ПК']);
        exit;
    }

    $now = date("Y-m-d H:i:s");
    $query = 'query {
      getBookings(
        hostIds: [' . $pc_id . '],
        status: "ACTIVE",
        from: "' . $now . '",
        first: 5
      ) {
        data { id from to }
      }
    }';

    $response = GetCurl("https://billing.smartshell.gg/api/graphql", [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ], json_encode(['query' => $query]));

    $bookings = $response['data']['getBookings']['data'] ?? [];
    $result = [];

    foreach ($bookings as $b) {
        $start = strtotime($b['from']);
        $end = strtotime($b['to']);
        $result[] = [
            'from' => date("d.m H:i", $start),
            'to'   => date("H:i", $end)
        ];
    }

    echo json_encode(['success' => true, 'bookings' => $result]);
    exit;
}

// ======== РЕЖИМ: СОЗДАТЬ БРОНЬ (POST) ==========
$data = json_decode(file_get_contents("php://input"), true);
$pc_id = $data['pc_id'] ?? null;
$alias = htmlspecialchars($data['pc_alias'] ?? 'Неизвестный ПК');
$phone = trim($data['phone'] ?? '');
$start = $data['start'] ?? '';
$end   = $data['end']   ?? '';

if (!$phone || !$pc_id || !$start || !$end) {
    echo json_encode(['success' => false, 'error' => 'Заполните все поля.']);
    exit;
}

$client_id = 6242; // ID компании

try {
    $from = date("Y-m-d H:i:s", strtotime($start));
    $to   = date("Y-m-d H:i:s", strtotime($end));
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Неверный формат даты/времени.']);
    exit;
}

$comment = "Онлайн-бронь. Телефон: $phone";

$query = 'mutation {
  createBooking(input: {
    hosts: [' . $pc_id . '],
    client: ' . $client_id . ',
    from: "' . $from . '",
    to: "' . $to . '",
    comment: "' . $comment . '"
  }) {
    id
    from
    to
    status
  }
}';

$response = GetCurl(
    "https://billing.smartshell.gg/api/graphql",
    ["Authorization: Bearer $token", "Content-Type: application/json"],
    json_encode(['query' => $query])
);

// === Успешно — отправляем Telegram уведомление
if (isset($response['data']['createBooking']['id'])) {
    sendTelegram("📥 Новая онлайн-бронь\n💻 ПК: $alias\n📱 Тел: $phone\n🕐 $from – $to");
    echo json_encode(['success' => true]);
} else {
    $err = $response['errors'][0]['message'] ?? 'Не удалось создать бронь.';
    echo json_encode(['success' => false, 'error' => $err]);
}

// =========== Общие функции ============
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
    curl_close($ch);
    return json_decode($res, true);
}

// ======= Telegram уведомление =========
function sendTelegram($text) {
  $botToken = "7745684574:AAGhe6vd-GktlQgVEyDO2PyjijIQ3pUcd4U";
  $chatId = "6415189694";
  

    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    file_get_contents($url . '?' . http_build_query($params));
}
