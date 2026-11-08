<?php
require_once '../includes/db.php';
requireLogin();

if ($_SESSION['user_role'] !== 'doctor') {
    redirectByRole($_SESSION['user_role']);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clinic_name    = clean($conn, $_POST['clinic_name']);
    $clinic_address = clean($conn, $_POST['clinic_address']);
    $clinic_desc    = clean($conn, $_POST['clinic_desc']);
    $userId         = $_SESSION['user_id'];

    // Collect timings
    $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    $timings = [];

    foreach ($days as $day) {
        if (isset($_POST['day_' . $day])) {
            $open  = clean($conn, $_POST['open_' . $day]);
            $close = clean($conn, $_POST['close_' . $day]);
            if ($open && $close) {
                $timings[$day] = $open . ' - ' . $close;
            }
        }
    }

    $timings_json = mysqli_real_escape_string($conn, json_encode($timings));

    if (empty($clinic_name)) {
        $error = "Please enter your clinic name.";
    } elseif (empty($clinic_address)) {
        $error = "Please enter your clinic address.";
    } elseif (empty($timings)) {
        $error = "Please select at least one working day with timings.";
    } else {
        $sql = "UPDATE doctors SET 
                    clinic_name='$clinic_name',
                    clinic_address='$clinic_address',
                    clinic_desc='$clinic_desc',
                    office_timings='$timings_json'
                WHERE user_id='$userId'";

        if (mysqli_query($conn, $sql)) {
            header("Location: select-plan.php");
            exit();
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Clinic Details — MediBook</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy:  #0b1e3d;
    --teal:  #0d7c73;
    --teal2: #0fb8aa;
    --cream: #f5f0e8;
    --white: #ffffff;
    --gray:  #8a95a3;
    --red:   #e05454;
  }

  body {
    min-height: 100vh;
    background: var(--cream);
    font-family: 'DM Sans', sans-serif;
    display: flex; flex-direction: column;
  }

  .topbar {
    background: var(--navy); padding: 14px 32px;
    display: flex; align-items: center; justify-content: space-between;
  }

  .brand { display: flex; align-items: center; gap: 10px; }

  .brand-icon {
    width: 34px; height: 34px; background: var(--teal);
    border-radius: 8px; display: flex; align-items: center;
    justify-content: center; font-size: 17px;
  }

  .brand-name {
    font-family: 'Instrument Serif', serif;
    font-size: 1.4rem; color: var(--white);
  }

  .user-greet { color: rgba(255,255,255,0.6); font-size: 0.88rem; }
  .user-greet span { color: var(--teal2); font-weight: 600; }

  .main {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; padding: 40px 24px;
  }

  .steps { display: flex; align-items: center; margin-bottom: 32px; }

  .step { display: flex; align-items: center; gap: 8px; }

  .step-circle {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; font-weight: 700;
  }

  .step-circle.done     { background: var(--teal); color: var(--white); }
  .step-circle.active   { background: var(--navy); color: var(--white); }
  .step-circle.inactive { background: #dde2e8; color: var(--gray); }

  .step-label { font-size: 0.8rem; font-weight: 600; color: var(--navy); }
  .step-label.inactive { color: var(--gray); }

  .step-line { width: 48px; height: 2px; background: #dde2e8; margin: 0 8px; }
  .step-line.done { background: var(--teal); }

  .card {
    background: var(--white); border-radius: 24px;
    padding: 44px 52px; width: 100%; max-width: 640px;
    box-shadow: 0 8px 40px rgba(11,30,61,0.08);
    animation: fadeUp 0.5s ease both;
  }

  @keyframes fadeUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
  }

  .card-header {
    display: flex; align-items: flex-start; gap: 16px; margin-bottom: 30px;
  }

  .card-icon {
    width: 56px; height: 56px; flex-shrink: 0;
    background: rgba(13,124,115,0.1); border-radius: 14px;
    display: flex; align-items: center; justify-content: center; font-size: 1.7rem;
  }

  .card-header h2 {
    font-family: 'Instrument Serif', serif;
    font-size: 1.8rem; color: var(--navy); margin-bottom: 5px;
  }

  .card-header p { color: var(--gray); font-size: 0.88rem; line-height: 1.6; }

  .alert-error {
    background: #fef0f0; border: 1px solid #f8c8c8; color: var(--red);
    padding: 12px 16px; border-radius: 10px; font-size: 0.875rem;
    margin-bottom: 22px; display: flex; align-items: center; gap: 8px;
  }

  .section-divider {
    font-size: 0.75rem; font-weight: 700; color: var(--teal);
    text-transform: uppercase; letter-spacing: 1px;
    border-bottom: 1px solid rgba(13,124,115,0.15);
    padding-bottom: 8px; margin: 28px 0 20px;
  }

  .form-group { margin-bottom: 20px; }

  label.field-label {
    display: block; font-size: 0.78rem; font-weight: 700;
    color: var(--navy); text-transform: uppercase;
    letter-spacing: 0.4px; margin-bottom: 8px;
  }

  .optional {
    font-weight: 400; text-transform: none;
    color: var(--gray); font-size: 0.75rem;
  }

  .input-wrap { position: relative; }
  .input-wrap .ico {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%); font-size: 1rem; opacity: 0.4;
    pointer-events: none;
  }

  .input-wrap.top .ico { top: 14px; transform: none; }

  input[type="text"],
  textarea {
    width: 100%; padding: 13px 14px 13px 44px;
    border: 1.5px solid #e2ddd4; border-radius: 12px;
    font-family: 'DM Sans', sans-serif; font-size: 0.92rem;
    color: var(--navy); background: var(--cream); outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  textarea {
    resize: vertical; min-height: 100px;
    line-height: 1.6; padding-top: 13px;
  }

  input[type="text"]:focus,
  textarea:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 4px rgba(13,124,115,0.08);
    background: var(--white);
  }

  .char-count {
    text-align: right; font-size: 0.75rem;
    color: var(--gray); margin-top: 5px;
  }

  /* Days table */
  .days-table { width: 100%; border-collapse: collapse; }

  .days-table th {
    font-size: 0.72rem; font-weight: 700; color: var(--gray);
    text-transform: uppercase; letter-spacing: 0.4px;
    padding: 0 0 10px 0; text-align: left;
  }

  .days-table th:nth-child(2),
  .days-table th:nth-child(3) { text-align: center; }

  .day-row { border-top: 1px solid #f0ebe0; }
  .day-row td { padding: 10px 0; vertical-align: middle; }

  .day-toggle { display: flex; align-items: center; gap: 10px; }

  .toggle-checkbox { display: none; }

  .toggle-label {
    width: 40px; height: 22px; background: #dde2e8;
    border-radius: 11px; position: relative; cursor: pointer;
    transition: background 0.2s; flex-shrink: 0;
  }

  .toggle-label::after {
    content: ''; position: absolute;
    width: 16px; height: 16px; border-radius: 50%;
    background: var(--white); top: 3px; left: 3px;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
  }

  .toggle-checkbox:checked + .toggle-label { background: var(--teal); }
  .toggle-checkbox:checked + .toggle-label::after { transform: translateX(18px); }

  .day-name {
    font-size: 0.88rem; font-weight: 600; color: var(--navy); min-width: 90px;
  }

  .time-fields {
    display: flex; align-items: center; gap: 6px; justify-content: center;
  }

  select.time-sel {
    padding: 7px 8px; border: 1.5px solid #e2ddd4;
    border-radius: 8px; font-family: 'DM Sans', sans-serif;
    font-size: 0.8rem; color: var(--navy);
    background: var(--cream); outline: none; cursor: pointer;
    transition: border-color 0.2s; appearance: none; text-align: center;
  }

  select.time-sel:focus { border-color: var(--teal); }
  select.time-sel:disabled { opacity: 0.3; cursor: not-allowed; }

  .preset-label { font-size: 0.75rem; color: var(--gray); margin-bottom: 8px; }

  .presets { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }

  .preset-btn {
    padding: 6px 14px; border: 1.5px solid #e2ddd4;
    border-radius: 20px; font-size: 0.78rem; font-weight: 600;
    color: var(--navy); background: var(--cream);
    cursor: pointer; transition: all 0.2s; user-select: none;
  }

  .preset-btn:hover { border-color: var(--teal); color: var(--teal); }

  .btn-primary {
    width: 100%; padding: 14px; margin-top: 12px;
    background: var(--navy); color: var(--white); border: none;
    border-radius: 12px; font-family: 'DM Sans', sans-serif;
    font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
  }

  .btn-primary:hover { background: var(--teal); transform: translateY(-1px); }
  .btn-primary:active { transform: translateY(0); }

  @media (max-width: 600px) {
    .card { padding: 28px 16px; }
    .steps { display: none; }
  }
</style>
</head>
<body>

<div class="topbar">
  <div class="brand">
    <div class="brand-icon">🏥</div>
    <span class="brand-name">MediBook</span>
  </div>
  <div class="user-greet">
    Dr. <span><?= htmlspecialchars($_SESSION['user_name']) ?></span> 👨‍⚕️
  </div>
</div>

<div class="main">

  <div class="steps">
    <div class="step">
      <div class="step-circle done">✓</div>
      <span class="step-label">Account</span>
    </div>
    <div class="step-line done"></div>
    <div class="step">
      <div class="step-circle done">✓</div>
      <span class="step-label">Location</span>
    </div>
    <div class="step-line done"></div>
    <div class="step">
      <div class="step-circle active">3</div>
      <span class="step-label">Clinic Details</span>
    </div>
    <div class="step-line"></div>
    <div class="step">
      <div class="step-circle inactive">4</div>
      <span class="step-label inactive">Choose Plan</span>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-icon">🏨</div>
      <div>
        <h2>Clinic Details</h2>
        <p>Help patients know your clinic — name, address, description and working hours.</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">

      <!-- BASIC INFO -->
      <div class="section-divider">🏥 Basic Information</div>

      <div class="form-group">
        <label class="field-label">Clinic / Hospital Name *</label>
        <div class="input-wrap">
          <span class="ico">🏥</span>
          <input type="text" name="clinic_name"
                 placeholder="e.g. City Care Clinic, Apollo Hospital"
                 value="<?= isset($_POST['clinic_name']) ? htmlspecialchars($_POST['clinic_name']) : '' ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="field-label">Clinic Address *</label>
        <div class="input-wrap">
          <span class="ico">📍</span>
          <input type="text" name="clinic_address"
                 placeholder="e.g. Shop 4, Sai Plaza, FC Road, Pune - 411005"
                 value="<?= isset($_POST['clinic_address']) ? htmlspecialchars($_POST['clinic_address']) : '' ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label class="field-label">
          Clinic Description
          <span class="optional">(optional)</span>
        </label>
        <div class="input-wrap top">
          <span class="ico">📝</span>
          <textarea name="clinic_desc" id="clinicDesc" maxlength="300"
                    placeholder="Tell patients about your clinic — facilities, specialties, what makes your practice special..."
                    oninput="updateCount(this)"><?= isset($_POST['clinic_desc']) ? htmlspecialchars($_POST['clinic_desc']) : '' ?></textarea>
        </div>
        <div class="char-count"><span id="charCount">0</span> / 300</div>
      </div>

      <!-- OFFICE TIMINGS -->
      <div class="section-divider">🕐 Office Timings</div>

      <div class="preset-label">Quick presets:</div>
      <div class="presets">
        <div class="preset-btn" onclick="applyPreset('weekdays')">📅 Mon–Fri (9am–6pm)</div>
        <div class="preset-btn" onclick="applyPreset('everyday')">🗓️ Everyday (10am–8pm)</div>
        <div class="preset-btn" onclick="applyPreset('morning')">🌅 Morning only (8am–1pm)</div>
        <div class="preset-btn" onclick="applyPreset('clear')">✕ Clear all</div>
      </div>

      <table class="days-table">
        <thead>
          <tr>
            <th style="width:140px">Day</th>
            <th>Open Time</th>
            <th>Close Time</th>
          </tr>
        </thead>
        <tbody id="daysBody"></tbody>
      </table>

      <button type="submit" class="btn-primary">Save & Continue →</button>
    </form>
  </div>
</div>

<script>
  const days = [
    {key:'Mon',label:'Monday'},{key:'Tue',label:'Tuesday'},
    {key:'Wed',label:'Wednesday'},{key:'Thu',label:'Thursday'},
    {key:'Fri',label:'Friday'},{key:'Sat',label:'Saturday'},
    {key:'Sun',label:'Sunday'},
  ];

  const timeOptions = [
    '6:00 AM','6:30 AM','7:00 AM','7:30 AM','8:00 AM','8:30 AM',
    '9:00 AM','9:30 AM','10:00 AM','10:30 AM','11:00 AM','11:30 AM',
    '12:00 PM','12:30 PM','1:00 PM','1:30 PM','2:00 PM','2:30 PM',
    '3:00 PM','3:30 PM','4:00 PM','4:30 PM','5:00 PM','5:30 PM',
    '6:00 PM','6:30 PM','7:00 PM','7:30 PM','8:00 PM','8:30 PM',
    '9:00 PM','9:30 PM','10:00 PM'
  ];

  function buildTimeSelect(name, defaultVal, disabled) {
    const sel = document.createElement('select');
    sel.name = name; sel.id = name;
    sel.className = 'time-sel'; sel.disabled = disabled;
    timeOptions.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t; opt.textContent = t;
      if (t === defaultVal) opt.selected = true;
      sel.appendChild(opt);
    });
    return sel;
  }

  function buildRows() {
    const tbody = document.getElementById('daysBody');
    tbody.innerHTML = '';
    days.forEach(d => {
      const tr = document.createElement('tr');
      tr.className = 'day-row';

      const tdDay = document.createElement('td');
      const wrap  = document.createElement('div');
      wrap.className = 'day-toggle';

      const chk = document.createElement('input');
      chk.type = 'checkbox'; chk.name = 'day_' + d.key;
      chk.id = 'chk_' + d.key; chk.className = 'toggle-checkbox';
      chk.addEventListener('change', () => toggleDay(d.key));

      const lbl = document.createElement('label');
      lbl.htmlFor = 'chk_' + d.key; lbl.className = 'toggle-label';

      const span = document.createElement('span');
      span.className = 'day-name'; span.textContent = d.label;

      wrap.appendChild(chk); wrap.appendChild(lbl); wrap.appendChild(span);
      tdDay.appendChild(wrap);

      const tdOpen = document.createElement('td');
      const ow = document.createElement('div');
      ow.className = 'time-fields';
      ow.appendChild(buildTimeSelect('open_' + d.key, '9:00 AM', true));
      tdOpen.appendChild(ow);

      const tdClose = document.createElement('td');
      const cw = document.createElement('div');
      cw.className = 'time-fields';
      cw.appendChild(buildTimeSelect('close_' + d.key, '6:00 PM', true));
      tdClose.appendChild(cw);

      tr.appendChild(tdDay); tr.appendChild(tdOpen); tr.appendChild(tdClose);
      tbody.appendChild(tr);
    });
  }

  function toggleDay(key) {
    const chk   = document.getElementById('chk_' + key);
    const open  = document.getElementById('open_' + key);
    const close = document.getElementById('close_' + key);
    open.disabled = close.disabled = !chk.checked;
  }

  function setDay(key, openVal, closeVal, checked) {
    const chk   = document.getElementById('chk_' + key);
    const open  = document.getElementById('open_' + key);
    const close = document.getElementById('close_' + key);
    chk.checked = checked;
    open.disabled = close.disabled = !checked;
    if (openVal)  for (let o of open.options)  if (o.value === openVal)  o.selected = true;
    if (closeVal) for (let o of close.options) if (o.value === closeVal) o.selected = true;
  }

  function applyPreset(type) {
    if      (type === 'weekdays') {
      ['Mon','Tue','Wed','Thu','Fri'].forEach(d => setDay(d,'9:00 AM','6:00 PM',true));
      ['Sat','Sun'].forEach(d => setDay(d,'9:00 AM','6:00 PM',false));
    }
    else if (type === 'everyday') days.forEach(d => setDay(d.key,'10:00 AM','8:00 PM',true));
    else if (type === 'morning')  days.forEach(d => setDay(d.key,'8:00 AM','1:00 PM',true));
    else if (type === 'clear')    days.forEach(d => setDay(d.key,'9:00 AM','6:00 PM',false));
  }

  function updateCount(el) {
    document.getElementById('charCount').textContent = el.value.length;
  }

  buildRows();
  const desc = document.getElementById('clinicDesc');
  if (desc) document.getElementById('charCount').textContent = desc.value.length;
</script>
</body>
</html>
