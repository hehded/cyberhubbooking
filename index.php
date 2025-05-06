<?php
$login = "37127177620";
$password = "46menepe255";
$id = "6242";

if (file_exists("token.php")) {
    require 'token.php';
} else {
    TokenUP($login, $password, $id);
}

$pclist = GetBox($token, $login, $password, $id);
if (!$pclist) die("Не удалось получить данные о ПК.");

// Сортируем и группируем, как было
uksort($pclist, function ($a, $b) {
    preg_match('/\d+/', $a, $numA);
    preg_match('/\d+/', $b, $numB);
    return ($numA[0] ?? PHP_INT_MAX) <=> ($numB[0] ?? PHP_INT_MAX);
});
$zones = ['standart'=>[], 'vip'=>[], 'deluxe'=>[], 'ps5'=>[], 'unknown'=>[]];
foreach ($pclist as $alias=>$pc) {
    if (in_array(mb_strtolower($alias), ['пс один','пс два'])) { $zones['ps5'][$alias]=$pc; continue; }
    preg_match('/\d+/', $alias, $m); $n=intval($m[0]??0);
    if ($n>=1&&$n<=14)       $zones['standart'][$alias]=$pc;
    elseif ($n>=15&&$n<=19)  $zones['vip'][$alias]=$pc;
    elseif ($n===20)         $zones['deluxe'][$alias]=$pc;
    else                     $zones['unknown'][$alias]=$pc;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Карта ПК — Онлайн-бронирование</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <style>
    body{font-family:Arial;padding:20px;}
    .zone{border:1px solid #ddd;padding:15px;border-radius:8px;margin-bottom:20px;}
    .pc-map{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:15px;}
    .pc{border:2px solid #ccc;padding:15px;text-align:center;border-radius:8px;cursor:pointer;transition:0.3s;}
    .pc.busy{background:#f8d7da;border-color:#721c24;cursor:not-allowed;}
    .pc.free{background:#d4edda;border-color:#155724;}
    .pc:hover{background:#e9ecef;}
    .footer{margin-top:30px;font-size:.9em;color:#666;}
    /* Модалка */
    .modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);
           display:none;align-items:center;justify-content:center;z-index:1000;}
    .modal-content{background:#fff;padding:20px;border-radius:8px;width:300px;position:relative;}
    .modal-close{position:absolute;top:10px;right:15px;cursor:pointer;font-size:20px;}
    .modal input, .modal button{width:100%;margin-bottom:10px;padding:8px;box-sizing:border-box;}
  </style>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>

<h1>Карта ПК Компьютерного Клуба</h1>

<?php foreach ($zones as $zoneName=>$pcs): if (empty($pcs)) continue; ?>
  <div class="zone">
    <h2>🎮 <?= mb_strtoupper($zoneName) ?></h2>
    <div class="pc-map">
      <?php foreach ($pcs as $alias=>$pc): ?>
        <div class="pc <?= $pc['status'] ? 'busy' : 'free' ?>"
     onclick="<?= $pc['status'] ? '' : "bookPC('$alias', '{$pc['id']}')" ?>"
     title="<?= $pc['status'] ? 'Занят до ' . date("H:i", strtotime($pc['finished_at'])) : 'Нажмите для брони' ?>">
    <strong><?= htmlspecialchars($alias) ?></strong><br>
    <small>
        <?php if ($pc['status']): ?>
            ⛔ Занят до <?= date("H:i", strtotime($pc['finished_at'])) ?>
        <?php elseif (isset($pc['next_booking_time']) && (strtotime($pc['next_booking_time']) - time() < 3600)): ?>
            🔜 Свободен, но скоро занят (<?= date("H:i", strtotime($pc['next_booking_time'])) ?>)
        <?php else: ?>
            ✅ Свободен
        <?php endif; ?>
    </small>
</div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<div class="footer">Обновлено: <?= date("Y-m-d H:i:s") ?></div>

<!-- МОДАЛЬНОЕ ОКНО -->
<div class="modal" id="bookingModal">
  <div class="modal-content">
    <span class="modal-close" onclick="closeModal()">×</span>
    <h3>Бронирование: <span id="pcName"></span></h3>
    <input type="hidden" id="pcId">
    <input type="tel" id="phone" placeholder="Номер телефона" required>
    <input type="text" id="startTime" placeholder="Начало" readonly>
    <input type="text" id="endTime" placeholder="Окончание" readonly>
    <div id="nextBooking" style="font-size:.9em;color:#555;">...</div>
    <button type="button" id="submitBookingBtn">Забронировать</button>
  </div>
</div>

<script>
  // Инициализация календарей и кнопки
  document.addEventListener('DOMContentLoaded', () => {
    flatpickr("#startTime", { enableTime: true, dateFormat: "Y-m-d H:i" });
    flatpickr("#endTime",   { enableTime: true, dateFormat: "Y-m-d H:i" });
    document.getElementById("submitBookingBtn")
            .addEventListener("click", submitBooking);
  });

  function bookPC(alias, pcId) {
    document.getElementById("bookingModal").style.display = "flex";
    document.getElementById("pcName").innerText = alias;
    document.getElementById("pcId").value = pcId;
    document.getElementById("phone").value = "";
    document.getElementById("startTime").value = "";
    document.getElementById("endTime").value = "";
    document.getElementById("nextBooking").innerText = "Загрузка ближайшей брони...";

    fetch(`get_next_booking.php?pc_id=${pcId}`)
      .then(r => r.json())
      .then(data => {
        document.getElementById("nextBooking").innerText =
          data.time
            ? `Следующая бронь: ${data.time}`
            : `Свободно на весь день.`;
      })
      .catch(e => {
        console.error(e);
        document.getElementById("nextBooking").innerText =
          `Не удалось загрузить бронь.`;
      });
  }

  function closeModal() {
    document.getElementById("bookingModal").style.display = "none";
  }

  function submitBooking(e) {
    e.preventDefault();
    console.log("Кнопка Забронировать нажата");
    const payload = {
      pc_id: document.getElementById("pcId").value,
      phone: document.getElementById("phone").value.trim(),
      start: document.getElementById("startTime").value,
      end:   document.getElementById("endTime").value
    };
    if (!payload.phone || !payload.start || !payload.end) {
      return alert("Заполните все поля!");
    }

    fetch("book.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    })
    .then(resp => {
      if (!resp.ok) throw new Error("Сетевой ответ не OK " + resp.status);
      return resp.json();
    })
    .then(data => {
      console.log("Ответ book.php:", data);
      if (data.success) {
        alert("Бронирование успешно создано!");
        closeModal();
        location.reload();
      } else {
        alert("Ошибка: " + data.error);
      }
    })
    .catch(err => {
      console.error(err);
      alert("Произошла ошибка при бронировании. Смотрите консоль.");
    });
  }
</script>
</body>
</html>

<?php
// === PHP-функции TokenUP, GetBox, GetCurl остаются без изменений ===
?>

<?php
function TokenUP($login, $password, $id) {
    global $token;
    if (file_exists('token.php')) unlink('token.php');
    $url = "https://billing.smartshell.gg/api/graphql";
    $headers = ['Content-Type: application/json'];
    $post_fields = '{"operationName":"login","variables":{"input":{"login":"'.$login.'","password":"'.$password.'","company_id":'.$id.'}},"query":"mutation login($input: LoginInput!) { login(input: $input) { access_token }}"}';
    $response = GetCurl($url, $headers, $post_fields);
    if (!isset($response['data']['login']['access_token'])) die("Ошибка авторизации.");
    file_put_contents('token.php', '<?php $token = "'.$response['data']['login']['access_token'].'";');
    require 'token.php';
}

function GetBox($token, $login, $password, $id) {
    $url = "https://billing.smartshell.gg/api/graphql";
    $headers = ['authorization: Bearer '.$token, 'Content-Type: application/json'];
    $post_fields = '{"operationName":"hostGroups","variables":{},"query":"query hostGroups {hostGroups {hosts {id group_id alias client_sessions {id status finished_at}}}}"}';
    $response = GetCurl($url, $headers, $post_fields);
    if (!isset($response['data']['hostGroups'][0])) {
        TokenUP($login, $password, $id);
        return GetBox($token, $login, $password, $id);
    }

    $pcList = [];
    $hostIds = [];

    foreach ($response['data']['hostGroups'] as $group) {
        foreach ($group['hosts'] as $pc) {
            $alias = $pc['alias'];
            $host_id = $pc['id'];
            $hostIds[] = $host_id;
            $pcList[$alias]['id'] = $host_id;
            $pcList[$alias]['group_id'] = $pc['group_id'];

            if (!empty($pc['client_sessions'][0])) {
                $session = $pc['client_sessions'][0];
                $finishedAt = strtotime($session['finished_at']);
                $now = time();
                $pcList[$alias]['status'] = ($session['status'] === 'ACTIVE' && $finishedAt > $now) ? 1 : 0;
                $pcList[$alias]['finished_at'] = $session['finished_at'];
            } else {
                $pcList[$alias]['status'] = 0;
                $pcList[$alias]['finished_at'] = null;
            }
        }
    }

    // === ДОБАВЛЯЕМ БЛИЖАЙШИЕ БРОНИ ===
    $now = date("c");
    $bookingsQuery = <<<GQL
query {
  getBookings(hostIds: [${implode(',', $hostIds)}], from: "$now") {
    data {
      id
      hosts
      from
    }
  }
}
GQL;

    $bookingsResp = GetCurl($url, $headers, json_encode(['query' => $bookingsQuery]));
    $bookingData = $bookingsResp['data']['getBookings']['data'] ?? [];

    foreach ($bookingData as $booking) {
        $bookingTime = strtotime($booking['from']);
        foreach ($booking['hosts'] as $hId) {
            foreach ($pcList as &$pc) {
                if ($pc['id'] == $hId) {
                    if (!isset($pc['next_booking_time']) || $bookingTime < strtotime($pc['next_booking_time'])) {
                        $pc['next_booking_time'] = $booking['from'];
                    }
                }
            }
        }
    }

    return $pcList;
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
    $response = curl_exec($ch);
    if (curl_errno($ch)) die("cURL error: " . curl_error($ch));
    curl_close($ch);
    return json_decode($response, true);
}
?>
