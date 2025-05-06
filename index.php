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

// Сортировка и группировка
uksort($pclist, function($a, $b) {
    preg_match('/\d+/', $a, $numA);
    preg_match('/\d+/', $b, $numB);
    return intval($numA[0] ?? PHP_INT_MAX) <=> intval($numB[0] ?? PHP_INT_MAX);
});
$zones = ['standart'=>[], 'vip'=>[], 'deluxe'=>[], 'ps5'=>[], 'unknown'=>[]];
foreach ($pclist as $alias => $pc) {
    if (in_array(mb_strtolower($alias), ['пс один','пс два'])) {
        $zones['ps5'][$alias] = $pc; continue;
    }
    preg_match('/\d+/', $alias, $m);
    $n = intval($m[0] ?? 0);
    if ($n >= 1 && $n <= 14)      $zones['standart'][$alias] = $pc;
    elseif ($n >= 15 && $n <= 19) $zones['vip'][$alias]       = $pc;
    elseif ($n === 20)            $zones['deluxe'][$alias]    = $pc;
    else                          $zones['unknown'][$alias]   = $pc;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <title>Карта ПК — Онлайн-бронирование</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/inputmask@5/dist/inputmask.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <style>
    /* основной стиль в cyberhub-красно-чёрной гамме */
    body { font-family: "Segoe UI", sans-serif; background: #1a0d0f; color: #fff; margin:0; padding:20px; }
    h1 { text-align:center; color:#ff2c2c; margin-bottom:30px; }
    .zone { background:#220d10; border-left:5px solid #ff2c2c; padding:20px; border-radius:12px; margin-bottom:30px; }
    .zone h2 { color:#ff5a5a; font-size:1.2em; margin-top:0; }
    .pc-map { display:grid; grid-template-columns:repeat(auto-fit,minmax(100px,1fr)); gap:15px; margin-top:15px; }
    .pc { background:#2c1315; border:2px solid #444; padding:14px 10px; border-radius:10px; text-align:center;
          transition:all .3s ease; cursor:pointer; font-weight:500; font-size:.95em; box-sizing:border-box;
          animation:neonPulse 4s ease-in-out infinite; }
    .pc.free { background:#172a17; border-color:#2dff2d; }
    .pc.busy { background:#3a1214; border-color:#ff3a3a; cursor:not-allowed; }
    .pc:hover:not(.busy) { transform:scale(1.03); box-shadow:0 0 15px #3aff3a; }
    .pc small { display:block; margin-top:5px; font-size:.8em; color:#aaa; }
    @keyframes neonPulse { 0%,100%{box-shadow:0 0 3px #ff2c2c40;}50%{box-shadow:0 0 6px #ff2c2c80;} }

    /* футер */
    .footer { text-align:center; margin-top:50px; font-size:.85em; color:#666; }

    /* модалка */
    .modal-bg { position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,.5);
                display:none; justify-content:center; align-items:center; }
    .modal-window { background:rgba(30,10,15,.95); backdrop-filter:blur(8px); border-radius:16px;
                    width:320px; padding:25px 30px; box-shadow:0 0 20px rgba(255,45,45,.15);
                    animation:fadeInUp .3s ease; color:#f1f1f1; box-sizing:border-box; }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(20px);} to{opacity:1;transform:translateY(0);} }
    .modal-window h3 { text-align:center; margin:0 0 15px; font-size:1.4em; }
    .modal-window label { display:block; margin-bottom:3px; font-size:.9em; }
    .modal-window input[type="text"], .modal-window input[type="tel"], .modal-window input[type="number"] {
      background:#2a1416; color:#f1f1f1; border:1px solid #ff3a3a40; border-radius:8px;
      padding:10px; font-size:.95em; margin-bottom:15px; width:100%; box-sizing:border-box; }
    .modal-window input:focus { border-color:#ff2c2c; box-shadow:0 0 5px #ff2c2c55; outline:none; }
    .close-btn { position:absolute; top:10px; right:15px; background:none; border:none;
                 font-size:1.5em; color:#fff; cursor:pointer; }
    .submit-btn { width:100%; padding:10px; background:#ff2c2c; border:none; border-radius:10px;
                  color:#fff; font-weight:600; cursor:pointer; box-shadow:0 0 10px #ff2c2c33;
                  transition:all .2s ease; }
    .submit-btn:hover { animation:bounceBtn .5s; background:#ff4444; box-shadow:0 0 20px #ff2c2c77; }
    @keyframes bounceBtn {0%,100%{transform:scale(1);}50%{transform:scale(1.05);} }
    .booking-info { font-size:.85em; color:#fff; line-height:1.5; margin-bottom:12px; }
    .success-msg { display:none; margin-top:10px; padding:8px; background:#e8f8ec; color:green;
                   border-radius:6px; border:1px solid #b0e6c2; animation:fadeIn .4s; text-align:center; }
    @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
  </style>
</head>
<body>
<h1>Карта ПК Компьютерного Клуба</h1>

<?php foreach ($zones as $zoneName => $pcs): if (empty($pcs)) continue; ?>
  <div class="zone">
    <h2>🎮 <?= mb_strtoupper($zoneName) ?></h2>
    <div class="pc-map">
      <?php foreach ($pcs as $alias => $pc): ?>
        <div class="pc <?= $pc['status'] ? 'busy' : 'free' ?>"
             onclick="<?= $pc['status'] ? '' : "bookPC('" . htmlspecialchars($alias) . "', {$pc['id']})" ?>"
             title="<?= $pc['status'] ? '⛔ Занят до ' . date('H:i', strtotime($pc['finished_at'])) : 'Нажмите для брони' ?>">
          <strong><?= htmlspecialchars($alias) ?></strong>
          <small>
            <?php if ($pc['status']): ?>
              ⛔ Занят до <?= date('H:i', strtotime($pc['finished_at'])) ?>
            <?php elseif (isset($pc['next_booking_time']) && strtotime($pc['next_booking_time']) - time() < 3600): ?>
              🔜 Свободен, но скоро занят (<?= date('H:i', strtotime($pc['next_booking_time'])) ?>)
            <?php else: ?>
              ✅ Свободен
            <?php endif; ?>
          </small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<div class="footer">Обновлено: <?= date('Y-m-d H:i:s') ?></div>

<div class="modal-bg" id="bookingModal">
  <div class="modal-window">
    <button class="close-btn" onclick="closeModal()">×</button>
    <h3>Бронирование: <span id="pcName"></span></h3>
    <form id="bookingForm" onsubmit="submitBooking(); return false;">
      <label for="phone">Номер телефона:</label>
      <input type="tel" id="phone" placeholder="+371 0000 0000" required>

      <label for="startTime">Начало сеанса:</label>
      <input type="text" id="startTime" placeholder="Выберите дату и время" required>

      <label for="duration">Длительность (часов):</label>
      <input type="number" id="duration" min="1" max="12" value="1" required>

      <div id="nextBooking" class="booking-info">Загрузка ближайшей брони...</div>

      <button type="submit" class="submit-btn">Забронировать</button>
      <div id="successMessage" class="success-msg">✅ Бронь отправлена!</div>
    </form>
  </div>
</div>

<script>
// Инициализация календаря и маски
flatpickr('#startTime',{ enableTime:true, time_24hr:true, dateFormat:'Y-m-d H:i' });
Inputmask('+371 9999 9999').mask('#phone');

// Открытие модалки
function bookPC(alias, pcId) {
  document.getElementById('bookingModal').style.display = 'flex';
  document.getElementById('pcName').innerText = alias;
  // сохраняем id и alias
  document.getElementById('bookingForm').dataset.pcId = pcId;
  document.getElementById('bookingForm').dataset.pcAlias = alias;

  document.getElementById('phone').value = '';
  document.getElementById('startTime').value = '';
  document.getElementById('duration').value = 1;
  document.getElementById('nextBooking').innerText = 'Загрузка ближайшей брони...';
  document.getElementById('successMessage').style.display = 'none';

  // подгрузка ближайших броней
  fetch(`book.php?pc_id=${pcId}`)
    .then(r => r.json())
    .then(data => {
      const el = document.getElementById('nextBooking');
      if(data.success && data.bookings.length){
        el.innerHTML = data.bookings.map(b => `📅 ${b.from} – ${b.to}`).join('<br>');
      } else el.innerText = 'Свободно на весь день.';
    }).catch(()=>{
      document.getElementById('nextBooking').innerText = 'Ошибка загрузки.';
    });
}

// Закрытие модалки
function closeModal(){ document.getElementById('bookingModal').style.display = 'none'; }
window.addEventListener('keydown', e=>{ if(e.key==='Escape') closeModal(); });

// Отправка брони
function submitBooking(){
  const form = document.getElementById('bookingForm');
  const phone = form.querySelector('#phone').value.trim();
  const start = form.querySelector('#startTime').value;
  const hours = parseInt(form.querySelector('#duration').value);
  const pcId = form.dataset.pcId;
  const pcAlias = form.dataset.pcAlias;

  if(!phone||!start||!hours||!pcId){ alert('Заполните все поля.'); return; }

  // вычисляем конец брони
  const end = new Date(new Date(start).getTime()+hours*3600000)
                .toISOString().slice(0,16).replace('T',' ');

  fetch('book.php',{ method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ phone, start, end, pc_id:pcId, pc_alias:pcAlias })
  }).then(r=>r.json()).then(data=>{
    if(data.success){
      document.getElementById('successMessage').style.display='block';
      setTimeout(()=>{ closeModal(); location.reload(); },1500);
    } else alert('Ошибка: '+data.error);
  }).catch(err=>{ console.error(err); alert('Ошибка соединения.'); });
}
</script>

</body>
</html>

<?php
// === TokenUP, GetBox, GetCurl без изменений ===
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
