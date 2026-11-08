<?php
require_once '../includes/db.php';
requireLogin();

if ($_SESSION['user_role'] !== 'patient') {
    redirectByRole($_SESSION['user_role']);
}

$userId   = $_SESSION['user_id'];
$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;

if (!$doctorId) {
    header("Location: dashboard.php?tab=doctors");
    exit();
}

// Fetch doctor info
$docSql = "SELECT d.*, u.name, u.email, u.phone
           FROM doctors d
           JOIN users u ON u.id = d.user_id
           WHERE d.id = '$doctorId'";
$docRes = mysqli_query($conn, $docSql);
$doctor = mysqli_fetch_assoc($docRes);

if (!$doctor) {
    header("Location: dashboard.php?tab=doctors");
    exit();
}

$timings = $doctor['office_timings'] ? json_decode($doctor['office_timings'], true) : [];

$error   = '';
$success = '';

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date      = clean($conn, $_POST['appointment_date']);
    $timeSlot  = clean($conn, $_POST['time_slot']);
    $reason    = clean($conn, $_POST['reason']);

    if (empty($date) || empty($timeSlot)) {
        $error = "Please select a date and time slot.";
    } else {
        // Check if slot already booked
        $checkSql = "SELECT id FROM appointments
                     WHERE doctor_id='$doctorId'
                     AND appointment_date='$date'
                     AND time_slot='$timeSlot'
                     AND status != 'cancelled'";
        $checkRes = mysqli_query($conn, $checkSql);

        if (mysqli_num_rows($checkRes) > 0) {
            $error = "This slot is already booked. Please choose another.";
        } else {
            $bookSql = "INSERT INTO appointments
                        (patient_id, doctor_id, appointment_date, time_slot, reason, status, created_at)
                        VALUES ('$userId', '$doctorId', '$date', '$timeSlot', '$reason', 'pending', NOW())";

            if (mysqli_query($conn, $bookSql)) {
                $success = true;
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}

// Get booked slots for selected date via AJAX
if (isset($_GET['fetch_slots']) && isset($_GET['date'])) {
    $date = clean($conn, $_GET['date']);
    $slotsSql = "SELECT time_slot FROM appointments
                 WHERE doctor_id='$doctorId'
                 AND appointment_date='$date'
                 AND status != 'cancelled'";
    $slotsRes = mysqli_query($conn, $slotsSql);
    $booked = [];
    while ($row = mysqli_fetch_assoc($slotsRes)) {
        $booked[] = $row['time_slot'];
    }
    header('Content-Type: application/json');
    echo json_encode($booked);
    exit();
}

// Generate time slots from doctor timings for a given day
function generateSlots($open, $close) {
    $slots = [];
    $current = strtotime($open);
    $end     = strtotime($close);
    while ($current < $end) {
        $slots[] = date('H:i:s', $current);
        $current = strtotime('+30 minutes', $current);
    }
    return $slots;
}

// Get available days
$availableDays = array_keys($timings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Book Appointment — MediBook</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy:   #0b1e3d;
    --teal:   #0d7c73;
    --teal2:  #0fb8aa;
    --cream:  #f5f0e8;
    --white:  #ffffff;
    --gray:   #8a95a3;
    --red:    #e05454;
    --green:  #1a9e6c;
    --orange: #e07d2a;
  }

  body {
    min-height: 100vh;
    background: #eee9df;
    font-family: 'DM Sans', sans-serif;
  }

  /* Topbar */
  .topbar {
    background: var(--navy); padding: 0 32px;
    display: flex; align-items: center; justify-content: space-between;
    height: 60px; position: sticky; top: 0; z-index: 100;
    box-shadow: 0 2px 16px rgba(0,0,0,0.15);
  }

  .brand { display: flex; align-items: center; gap: 10px; }

  .brand-icon {
    width: 32px; height: 32px; background: var(--teal);
    border-radius: 8px; display: flex; align-items: center;
    justify-content: center; font-size: 16px;
  }

  .brand-name {
    font-family: 'Instrument Serif', serif;
    font-size: 1.3rem; color: var(--white);
  }

  .back-btn {
    display: flex; align-items: center; gap: 7px;
    color: rgba(255,255,255,0.7); font-size: 0.85rem;
    text-decoration: none; transition: color 0.2s;
  }

  .back-btn:hover { color: var(--white); }

  /* Main */
  .main {
    max-width: 960px; margin: 0 auto;
    padding: 36px 24px;
    display: grid; grid-template-columns: 1fr 360px;
    gap: 24px; align-items: start;
  }

  /* ── Doctor Info Card ── */
  .doctor-card {
    background: var(--white); border-radius: 20px;
    padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    position: sticky; top: 80px;
  }

  .doc-top {
    display: flex; align-items: flex-start; gap: 14px; margin-bottom: 20px;
  }

  .doc-avatar {
    width: 60px; height: 60px; border-radius: 14px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--teal), var(--teal2));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: var(--white); font-weight: 700;
  }

  .doc-name {
    font-family: 'Instrument Serif', serif;
    font-size: 1.2rem; color: var(--navy); margin-bottom: 3px;
  }

  .doc-spec { font-size: 0.8rem; color: var(--teal); font-weight: 600; margin-bottom: 4px; }
  .doc-exp  { font-size: 0.75rem; color: var(--gray); }

  .doc-details { display: flex; flex-direction: column; gap: 8px; }

  .doc-detail {
    display: flex; align-items: flex-start; gap: 8px;
    font-size: 0.82rem; color: var(--gray); line-height: 1.4;
  }

  .doc-detail strong { color: var(--navy); }

  .fee-box {
    background: rgba(13,124,115,0.06);
    border: 1px solid rgba(13,124,115,0.15);
    border-radius: 12px; padding: 14px 16px;
    margin-top: 16px; text-align: center;
  }

  .fee-box .fee-label { font-size: 0.75rem; color: var(--gray); margin-bottom: 4px; }

  .fee-box .fee-amount {
    font-family: 'Instrument Serif', serif;
    font-size: 1.8rem; color: var(--navy);
  }

  .fee-box .fee-per { font-size: 0.75rem; color: var(--gray); }

  /* Timings */
  .timings-section { margin-top: 18px; }

  .timings-label {
    font-size: 0.72rem; font-weight: 700; color: var(--gray);
    text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 10px;
  }

  .timing-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 6px 0; border-bottom: 1px solid #f5f0e8;
    font-size: 0.8rem;
  }

  .timing-day { font-weight: 600; color: var(--navy); }
  .timing-val { color: var(--teal); }
  .timing-closed { color: var(--gray); font-style: italic; }

  /* ── Booking Form ── */
  .booking-card {
    background: var(--white); border-radius: 20px;
    padding: 30px 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.06);
  }

  .booking-card h2 {
    font-family: 'Instrument Serif', serif;
    font-size: 1.6rem; color: var(--navy); margin-bottom: 6px;
  }

  .booking-card > p {
    color: var(--gray); font-size: 0.88rem; margin-bottom: 28px;
  }

  .alert-error {
    background: #fef0f0; border: 1px solid #f8c8c8; color: var(--red);
    padding: 12px 16px; border-radius: 10px; font-size: 0.875rem;
    margin-bottom: 22px; display: flex; align-items: center; gap: 8px;
  }

  /* Steps */
  .booking-steps {
    display: flex; gap: 0; margin-bottom: 30px;
  }

  .b-step {
    flex: 1; text-align: center; position: relative;
  }

  .b-step::after {
    content: ''; position: absolute;
    top: 14px; left: 50%; width: 100%; height: 2px;
    background: #e8e2d8; z-index: 0;
  }

  .b-step:last-child::after { display: none; }

  .b-step-circle {
    width: 28px; height: 28px; border-radius: 50%;
    background: #e8e2d8; color: var(--gray);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700;
    margin: 0 auto 6px; position: relative; z-index: 1;
    transition: all 0.3s;
  }

  .b-step.active .b-step-circle  { background: var(--navy); color: var(--white); }
  .b-step.done   .b-step-circle  { background: var(--teal); color: var(--white); }
  .b-step.active::after, .b-step.done::after { background: var(--teal); }

  .b-step-label {
    font-size: 0.7rem; color: var(--gray); font-weight: 500;
  }

  .b-step.active .b-step-label { color: var(--navy); font-weight: 600; }
  .b-step.done   .b-step-label { color: var(--teal); }

  /* Section label */
  .section-label {
    font-size: 0.78rem; font-weight: 700; color: var(--navy);
    text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;
  }

  /* Calendar */
  .calendar-wrap { margin-bottom: 28px; }

  .cal-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px;
  }

  .cal-title {
    font-family: 'Instrument Serif', serif;
    font-size: 1rem; color: var(--navy);
  }

  .cal-nav {
    background: none; border: 1.5px solid #e8e2d8;
    border-radius: 8px; width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 0.9rem; color: var(--navy);
    transition: all 0.2s;
  }

  .cal-nav:hover { border-color: var(--teal); color: var(--teal); }

  .cal-grid {
    display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px;
  }

  .cal-day-header {
    text-align: center; font-size: 0.68rem; font-weight: 700;
    color: var(--gray); text-transform: uppercase; padding: 4px 0;
  }

  .cal-day {
    aspect-ratio: 1; display: flex; align-items: center; justify-content: center;
    border-radius: 8px; font-size: 0.82rem; cursor: pointer;
    transition: all 0.15s; font-weight: 500; border: 1.5px solid transparent;
    user-select: none;
  }

  .cal-day.empty     { cursor: default; }
  .cal-day.past      { color: #ccc; cursor: not-allowed; }
  .cal-day.closed    { color: #ccc; cursor: not-allowed; background: #f9f7f4; }
  .cal-day.available { color: var(--navy); background: var(--cream); }
  .cal-day.available:hover { background: rgba(13,124,115,0.1); border-color: var(--teal); color: var(--teal); }
  .cal-day.selected  { background: var(--navy); color: var(--white); border-color: var(--navy); }
  .cal-day.today     { border-color: var(--teal2); }

  /* Time slots */
  .slots-section { margin-bottom: 28px; }

  .slots-legend {
    display: flex; gap: 16px; margin-bottom: 14px; flex-wrap: wrap;
  }

  .legend-item {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.75rem; color: var(--gray);
  }

  .legend-dot {
    width: 12px; height: 12px; border-radius: 3px;
  }

  .legend-dot.available { background: var(--cream); border: 1.5px solid #e8e2d8; }
  .legend-dot.booked    { background: #f5e8e8; border: 1.5px solid #f0cece; }
  .legend-dot.selected  { background: var(--teal); }

  .slots-loading {
    text-align: center; padding: 24px; color: var(--gray); font-size: 0.88rem;
  }

  .no-date-msg {
    text-align: center; padding: 20px; color: var(--gray);
    font-size: 0.85rem; background: var(--cream); border-radius: 12px;
  }

  .slots-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;
  }

  .slot {
    padding: 9px 6px; text-align: center; border-radius: 10px;
    font-size: 0.78rem; font-weight: 600; cursor: pointer;
    border: 1.5px solid #e8e2d8; background: var(--cream);
    color: var(--navy); transition: all 0.15s; user-select: none;
  }

  .slot:hover:not(.booked) {
    border-color: var(--teal); background: rgba(13,124,115,0.06); color: var(--teal);
  }

  .slot.selected {
    background: var(--teal); color: var(--white); border-color: var(--teal);
  }

  .slot.booked {
    background: #fef0f0; color: #d99; border-color: #f5d0d0;
    cursor: not-allowed; text-decoration: line-through;
    font-weight: 400;
  }

  /* Reason */
  .reason-section { margin-bottom: 24px; }

  .reason-section textarea {
    width: 100%; padding: 12px 14px;
    border: 1.5px solid #e8e2d8; border-radius: 12px;
    font-family: 'DM Sans', sans-serif; font-size: 0.9rem;
    color: var(--navy); background: var(--cream); outline: none;
    resize: none; height: 90px; line-height: 1.5;
    transition: border-color 0.2s;
  }

  .reason-section textarea:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 4px rgba(13,124,115,0.08);
    background: var(--white);
  }

  /* Summary box */
  .summary-box {
    background: rgba(13,124,115,0.05);
    border: 1.5px solid rgba(13,124,115,0.15);
    border-radius: 14px; padding: 16px 18px;
    margin-bottom: 20px; display: none;
  }

  .summary-box.show { display: block; }

  .summary-row {
    display: flex; justify-content: space-between;
    font-size: 0.85rem; padding: 5px 0;
    border-bottom: 1px solid rgba(13,124,115,0.1);
  }

  .summary-row:last-child { border-bottom: none; font-weight: 600; }
  .summary-row span:first-child { color: var(--gray); }
  .summary-row span:last-child  { color: var(--navy); font-weight: 500; }

  /* Submit */
  .btn-book {
    width: 100%; padding: 14px; background: var(--navy);
    color: var(--white); border: none; border-radius: 12px;
    font-family: 'DM Sans', sans-serif; font-size: 1rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s;
    opacity: 0.4; pointer-events: none;
  }

  .btn-book.ready { opacity: 1; pointer-events: all; }
  .btn-book.ready:hover { background: var(--teal); transform: translateY(-1px); }

  /* Success screen */
  .success-screen {
    text-align: center; padding: 32px 16px;
    animation: fadeUp 0.5s ease both;
  }

  @keyframes fadeUp {
    from { opacity:0; transform:translateY(20px); }
    to   { opacity:1; transform:translateY(0); }
  }

  .success-circle {
    width: 80px; height: 80px; border-radius: 50%;
    background: rgba(26,158,108,0.1); border: 2px solid var(--green);
    display: flex; align-items: center; justify-content: center;
    font-size: 2.2rem; margin: 0 auto 20px;
  }

  .success-screen h2 {
    font-family: 'Instrument Serif', serif;
    font-size: 1.8rem; color: var(--navy); margin-bottom: 8px;
  }

  .success-screen p {
    color: var(--gray); font-size: 0.9rem; margin-bottom: 24px;
  }

  .btn-back {
    display: inline-block; padding: 12px 28px;
    background: var(--navy); color: var(--white);
    border-radius: 12px; text-decoration: none;
    font-weight: 600; font-size: 0.9rem; transition: all 0.2s;
  }

  .btn-back:hover { background: var(--teal); }

  @media (max-width: 768px) {
    .main { grid-template-columns: 1fr; }
    .doctor-card { position: static; order: -1; }
    .slots-grid { grid-template-columns: repeat(3,1fr); }
    .booking-card { padding: 22px 18px; }
  }
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
  <div class="brand">
    <div class="brand-icon">🏥</div>
    <span class="brand-name">MediBook</span>
  </div>
  <a href="dashboard.php?tab=doctors" class="back-btn">← Back to Doctors</a>
</div>

<div class="main">

  <!-- LEFT: BOOKING FORM -->
  <div class="booking-card">

    <?php if ($success): ?>
    <!-- SUCCESS SCREEN -->
    <div class="success-screen">
      <div class="success-circle">✅</div>
      <h2>Appointment Booked!</h2>
      <p>Your appointment with Dr. <?= htmlspecialchars($doctor['name']) ?> has been requested. You'll be confirmed shortly.</p>
      <a href="dashboard.php?tab=appointments" class="btn-back">View My Appointments →</a>
    </div>

    <?php else: ?>

    <h2>Book Appointment</h2>
    <p>Select a date and available time slot</p>

    <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="bookingForm">
      <input type="hidden" name="appointment_date" id="selectedDate" value="">
      <input type="hidden" name="time_slot"         id="selectedSlot" value="">

      <!-- STEP 1: Date -->
      <div class="calendar-wrap">
        <div class="section-label">Step 1 — Select Date</div>
        <div class="cal-header">
          <button type="button" class="cal-nav" onclick="changeMonth(-1)">‹</button>
          <div class="cal-title" id="calTitle"></div>
          <button type="button" class="cal-nav" onclick="changeMonth(1)">›</button>
        </div>
        <div class="cal-grid" id="calGrid"></div>
      </div>

      <!-- STEP 2: Time Slot -->
      <div class="slots-section">
        <div class="section-label">Step 2 — Select Time Slot</div>
        <div class="slots-legend">
          <div class="legend-item"><div class="legend-dot available"></div> Available</div>
          <div class="legend-item"><div class="legend-dot booked"></div> Booked</div>
          <div class="legend-item"><div class="legend-dot selected"></div> Your Selection</div>
        </div>
        <div id="slotsContainer">
          <div class="no-date-msg">📅 Please select a date first</div>
        </div>
      </div>

      <!-- STEP 3: Reason -->
      <div class="reason-section">
        <div class="section-label">Step 3 — Reason for Visit <span style="font-weight:400;text-transform:none;color:var(--gray)">(optional)</span></div>
        <textarea name="reason" placeholder="e.g. Fever and headache since 2 days, routine checkup, follow-up visit..."></textarea>
      </div>

      <!-- Summary -->
      <div class="summary-box" id="summaryBox">
        <div class="summary-row">
          <span>Doctor</span>
          <span>Dr. <?= htmlspecialchars($doctor['name']) ?></span>
        </div>
        <div class="summary-row">
          <span>Date</span>
          <span id="summaryDate">—</span>
        </div>
        <div class="summary-row">
          <span>Time</span>
          <span id="summaryTime">—</span>
        </div>
        <div class="summary-row">
          <span>Fee</span>
          <span>₹<?= number_format($doctor['fees'] ?? 0) ?></span>
        </div>
      </div>

      <button type="submit" class="btn-book" id="bookBtn">
        Confirm Appointment →
      </button>
    </form>

    <?php endif; ?>
  </div>

  <!-- RIGHT: DOCTOR INFO -->
  <div class="doctor-card">
    <div class="doc-top">
      <div class="doc-avatar"><?= strtoupper(substr($doctor['name'],0,1)) ?></div>
      <div>
        <div class="doc-name">Dr. <?= htmlspecialchars($doctor['name']) ?></div>
        <div class="doc-spec"><?= htmlspecialchars($doctor['specialization'] ?? 'General') ?></div>
        <div class="doc-exp">🏅 <?= $doctor['experience'] ?? 0 ?> years experience</div>
      </div>
    </div>

    <div class="doc-details">
      <?php if (!empty($doctor['clinic_name'])): ?>
      <div class="doc-detail">🏥 <strong><?= htmlspecialchars($doctor['clinic_name']) ?></strong></div>
      <?php endif; ?>
      <?php if (!empty($doctor['clinic_address'])): ?>
      <div class="doc-detail">📍 <?= htmlspecialchars($doctor['clinic_address']) ?></div>
      <?php endif; ?>
      <?php if (!empty($doctor['clinic_city'])): ?>
      <div class="doc-detail">🌆 <?= htmlspecialchars($doctor['clinic_area'] ? $doctor['clinic_area'].', '.$doctor['clinic_city'] : $doctor['clinic_city']) ?></div>
      <?php endif; ?>
      <div class="doc-detail">📱 <?= htmlspecialchars($doctor['phone']) ?></div>
    </div>

    <div class="fee-box">
      <div class="fee-label">Consultation Fee</div>
      <div class="fee-amount">₹<?= number_format($doctor['fees'] ?? 0) ?></div>
      <div class="fee-per">per visit</div>
    </div>

    <?php if (!empty($timings)): ?>
    <div class="timings-section">
      <div class="timings-label">Working Hours</div>
      <?php
      $allDays = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
      foreach ($allDays as $d): ?>
      <div class="timing-row">
        <span class="timing-day"><?= $d ?></span>
        <?php if (isset($timings[$d])): ?>
          <span class="timing-val">⏰ <?= $timings[$d] ?></span>
        <?php else: ?>
          <span class="timing-closed">Closed</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<script>
  // Doctor timings from PHP
  const docTimings = <?= json_encode($timings) ?>;
  const doctorId   = <?= $doctorId ?>;

  let currentYear  = new Date().getFullYear();
  let currentMonth = new Date().getMonth();
  let selectedDate = '';
  let selectedSlot = '';

  const dayMap = {0:'Sun',1:'Mon',2:'Tue',3:'Wed',4:'Thu',5:'Fri',6:'Sat'};
  const months = ['January','February','March','April','May','June',
                  'July','August','September','October','November','December'];

  // ── Generate 30-min slots between open and close time ──
  function generateSlots(timeRange) {
    if (!timeRange) return [];
    const [open, close] = timeRange.split(' - ');
    const slots = [];
    let cur = parseTime(open);
    const end = parseTime(close);
    while (cur < end) {
      slots.push(formatTime(cur));
      cur += 30;
    }
    return slots;
  }

  function parseTime(str) {
    // e.g. "9:00 AM" → minutes from midnight
    const [time, period] = str.trim().split(' ');
    let [h, m] = time.split(':').map(Number);
    if (period === 'PM' && h !== 12) h += 12;
    if (period === 'AM' && h === 12) h = 0;
    return h * 60 + m;
  }

  function formatTime(mins) {
    let h = Math.floor(mins / 60);
    const m = mins % 60;
    const period = h >= 12 ? 'PM' : 'AM';
    if (h > 12) h -= 12;
    if (h === 0) h = 12;
    return `${h}:${m.toString().padStart(2,'0')} ${period}`;
  }

  function formatTimeForDB(display) {
    // Convert "9:00 AM" to "09:00:00" for MySQL
    const mins = parseTime(display);
    const h = Math.floor(mins/60).toString().padStart(2,'0');
    const m = (mins%60).toString().padStart(2,'0');
    return `${h}:${m}:00`;
  }

  // ── Build calendar ──
  function buildCalendar() {
    const title = document.getElementById('calTitle');
    const grid  = document.getElementById('calGrid');
    title.textContent = `${months[currentMonth]} ${currentYear}`;
    grid.innerHTML = '';

    // Day headers
    ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(d => {
      const el = document.createElement('div');
      el.className = 'cal-day-header'; el.textContent = d;
      grid.appendChild(el);
    });

    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth+1, 0).getDate();
    const today = new Date(); today.setHours(0,0,0,0);

    // Empty cells
    for (let i = 0; i < firstDay; i++) {
      const el = document.createElement('div');
      el.className = 'cal-day empty'; grid.appendChild(el);
    }

    for (let day = 1; day <= daysInMonth; day++) {
      const date    = new Date(currentYear, currentMonth, day);
      const dayKey  = dayMap[date.getDay()];
      const isPast  = date < today;
      const isOpen  = docTimings[dayKey] !== undefined;
      const dateStr = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
      const isToday = date.toDateString() === today.toDateString();

      const el = document.createElement('div');
      el.textContent = day;

      if (isPast)       el.className = 'cal-day past';
      else if (!isOpen) el.className = 'cal-day closed';
      else {
        el.className = 'cal-day available';
        if (isToday) el.classList.add('today');
        if (dateStr === selectedDate) el.classList.add('selected');
        el.onclick = () => selectDate(dateStr, el);
      }

      grid.appendChild(el);
    }
  }

  function changeMonth(dir) {
    currentMonth += dir;
    if (currentMonth > 11) { currentMonth = 0; currentYear++; }
    if (currentMonth < 0)  { currentMonth = 11; currentYear--; }
    buildCalendar();
  }

  // ── Select date ──
  function selectDate(dateStr, el) {
    selectedDate = dateStr;
    selectedSlot = '';
    document.getElementById('selectedDate').value = dateStr;
    document.getElementById('selectedSlot').value = '';

    // Highlight calendar
    document.querySelectorAll('.cal-day.available').forEach(d => d.classList.remove('selected'));
    el.classList.add('selected');

    // Update summary
    const d = new Date(dateStr + 'T00:00:00');
    document.getElementById('summaryDate').textContent =
      d.toLocaleDateString('en-IN', {weekday:'short', day:'numeric', month:'long', year:'numeric'});

    // Load slots
    loadSlots(dateStr);
    updateBookBtn();
  }

  // ── Load slots via AJAX ──
  function loadSlots(dateStr) {
    const date    = new Date(dateStr + 'T00:00:00');
    const dayKey  = dayMap[date.getDay()];
    const timing  = docTimings[dayKey];

    if (!timing) {
      document.getElementById('slotsContainer').innerHTML =
        '<div class="no-date-msg">❌ Doctor is closed on this day</div>';
      return;
    }

    const allSlots = generateSlots(timing);
    document.getElementById('slotsContainer').innerHTML =
      '<div class="slots-loading">Loading slots...</div>';

    fetch(`book-appointment.php?doctor_id=${doctorId}&fetch_slots=1&date=${dateStr}`)
      .then(r => r.json())
      .then(booked => renderSlots(allSlots, booked))
      .catch(() => renderSlots(allSlots, []));
  }

  // ── Render time slots ──
  function renderSlots(allSlots, booked) {
    if (allSlots.length === 0) {
      document.getElementById('slotsContainer').innerHTML =
        '<div class="no-date-msg">No slots available</div>';
      return;
    }

    const grid = document.createElement('div');
    grid.className = 'slots-grid';

    allSlots.forEach(slot => {
      const dbSlot = formatTimeForDB(slot);
      const isBooked = booked.some(b => b.substring(0,5) === dbSlot.substring(0,5));

      const el = document.createElement('div');
      el.className = 'slot' + (isBooked ? ' booked' : '');
      el.textContent = slot;

      if (!isBooked) {
        el.onclick = () => selectSlot(slot, dbSlot, el);
      }

      grid.appendChild(el);
    });

    document.getElementById('slotsContainer').innerHTML = '';
    document.getElementById('slotsContainer').appendChild(grid);
  }

  // ── Select slot ──
  function selectSlot(displaySlot, dbSlot, el) {
    selectedSlot = dbSlot;
    document.getElementById('selectedSlot').value = dbSlot;
    document.querySelectorAll('.slot:not(.booked)').forEach(s => s.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('summaryTime').textContent = displaySlot;
    document.getElementById('summaryBox').classList.add('show');
    updateBookBtn();
  }

  function updateBookBtn() {
    const btn = document.getElementById('bookBtn');
    if (selectedDate && selectedSlot) {
      btn.classList.add('ready');
    } else {
      btn.classList.remove('ready');
    }
  }

  // Init calendar
  buildCalendar();
</script>
</body>
</html>
