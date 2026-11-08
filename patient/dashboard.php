<?php
require_once '../includes/db.php';
requireLogin();

if ($_SESSION['user_role'] !== 'patient') {
    redirectByRole($_SESSION['user_role']);
}

$userId = $_SESSION['user_id'];
$tab    = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Fetch patient info
$patSql = "SELECT * FROM users WHERE id = '$userId'";
$patRes = mysqli_query($conn, $patSql);
$patient = mysqli_fetch_assoc($patRes);

// Patient city from session
$patientCity = isset($_SESSION['patient_city']) ? $_SESSION['patient_city'] : 'All';
$patientArea = isset($_SESSION['patient_area']) ? $_SESSION['patient_area'] : '';

// Fetch all doctors with user info
// Sort: same area first, then same city, then others
$doctorsSql = "SELECT d.*, u.name, u.email, u.phone
               FROM doctors d
               JOIN users u ON u.id = d.user_id
               ORDER BY
                 CASE
                   WHEN d.clinic_area = '$patientArea' AND d.clinic_city = '$patientCity' THEN 1
                   WHEN d.clinic_city = '$patientCity' THEN 2
                   ELSE 3
                 END ASC,
                 d.experience DESC";
$doctorsRes = mysqli_query($conn, $doctorsSql);
$doctors = [];
if ($doctorsRes) {
    while ($row = mysqli_fetch_assoc($doctorsRes)) {
        $doctors[] = $row;
    }
}

// Fetch patient's booked appointments
$apptSql = "SELECT a.*, u.name as doctor_name, d.specialization, d.clinic_name, d.clinic_address
            FROM appointments a
            JOIN doctors d ON d.id = a.doctor_id
            JOIN users u ON u.id = d.user_id
            WHERE a.patient_id = '$userId'
            ORDER BY a.appointment_date DESC, a.time_slot DESC";
$apptRes = mysqli_query($conn, $apptSql);
$appointments = [];
if ($apptRes) {
    while ($row = mysqli_fetch_assoc($apptRes)) {
        $appointments[] = $row;
    }
}

$totalAppts    = count($appointments);
$upcomingAppts = array_filter($appointments, fn($a) => $a['appointment_date'] >= date('Y-m-d') && $a['status'] !== 'cancelled');
$pendingAppts  = array_filter($appointments, fn($a) => $a['status'] === 'pending');

// Cancel appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appt'])) {
    $apptId = (int)$_POST['appt_id'];
    mysqli_query($conn, "UPDATE appointments SET status='cancelled' WHERE id='$apptId' AND patient_id='$userId'");
    header("Location: dashboard.php?tab=appointments");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Patient Dashboard — MediBook</title>
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

  /* ── Topbar ── */
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

  .topbar-right { display: flex; align-items: center; gap: 14px; }

  .location-pill {
    display: flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 20px; padding: 5px 14px;
    font-size: 0.8rem; color: rgba(255,255,255,0.7);
    cursor: pointer; transition: all 0.2s; text-decoration: none;
  }

  .location-pill:hover { background: rgba(255,255,255,0.14); color: var(--white); }

  .pat-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: var(--teal); display: flex; align-items: center;
    justify-content: center; font-size: 0.9rem; color: var(--white); font-weight: 700;
  }

  .pat-info .name { font-size: 0.85rem; font-weight: 600; color: var(--white); }
  .pat-info .role { font-size: 0.7rem; color: rgba(255,255,255,0.45); }

  .logout-btn {
    padding: 6px 14px; border: 1px solid rgba(255,255,255,0.2);
    border-radius: 8px; color: rgba(255,255,255,0.65);
    font-size: 0.78rem; cursor: pointer; background: transparent;
    font-family: 'DM Sans', sans-serif; transition: all 0.2s; text-decoration: none;
  }

  .logout-btn:hover { background: rgba(255,255,255,0.1); color: var(--white); }

  /* ── Layout ── */
  .layout { display: flex; min-height: calc(100vh - 60px); }

  /* ── Sidebar ── */
  .sidebar {
    width: 230px; background: var(--white);
    padding: 24px 14px; flex-shrink: 0;
    border-right: 1px solid #e8e2d8;
    position: sticky; top: 60px; height: calc(100vh - 60px);
    overflow-y: auto;
  }

  .sidebar-profile {
    text-align: center; padding-bottom: 20px;
    border-bottom: 1px solid #f0ebe0; margin-bottom: 18px;
  }

  .sidebar-avatar {
    width: 64px; height: 64px; border-radius: 50%;
    background: linear-gradient(135deg, var(--teal), var(--teal2));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: var(--white); font-weight: 700;
    margin: 0 auto 8px;
  }

  .sidebar-name {
    font-family: 'Instrument Serif', serif;
    font-size: 0.95rem; color: var(--navy); margin-bottom: 2px;
  }

  .sidebar-loc {
    font-size: 0.73rem; color: var(--gray);
    display: flex; align-items: center; justify-content: center; gap: 4px;
  }

  .nav-item {
    display: flex; align-items: center; gap: 11px;
    padding: 10px 13px; border-radius: 10px;
    font-size: 0.88rem; font-weight: 500; color: var(--gray);
    cursor: pointer; transition: all 0.2s; text-decoration: none;
    margin-bottom: 3px;
  }

  .nav-item:hover { background: var(--cream); color: var(--navy); }
  .nav-item.active { background: rgba(13,124,115,0.08); color: var(--teal); font-weight: 600; }
  .nav-item .nav-icon { font-size: 1rem; }

  .nav-badge {
    margin-left: auto; background: var(--red); color: var(--white);
    font-size: 0.68rem; font-weight: 700; padding: 2px 6px;
    border-radius: 10px; min-width: 18px; text-align: center;
  }

  /* ── Content ── */
  .content { flex: 1; padding: 30px 34px; overflow-y: auto; }

  .page-header { margin-bottom: 24px; }

  .page-header h1 {
    font-family: 'Instrument Serif', serif;
    font-size: 1.85rem; color: var(--navy); margin-bottom: 3px;
  }

  .page-header p { color: var(--gray); font-size: 0.88rem; }

  /* Stats row */
  .stats-row {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 14px; margin-bottom: 28px;
  }

  .stat-card {
    background: var(--white); border-radius: 14px;
    padding: 18px 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    display: flex; align-items: center; gap: 12px;
  }

  .stat-icon {
    width: 42px; height: 42px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; flex-shrink: 0;
  }

  .stat-icon.teal   { background: rgba(13,124,115,0.1); }
  .stat-icon.orange { background: rgba(224,125,42,0.1); }
  .stat-icon.green  { background: rgba(26,158,108,0.1); }

  .stat-num {
    font-family: 'Instrument Serif', serif;
    font-size: 1.5rem; color: var(--navy); line-height: 1;
  }

  .stat-label { font-size: 0.73rem; color: var(--gray); margin-top: 2px; }

  /* Section card */
  .section-card {
    background: var(--white); border-radius: 16px;
    padding: 26px 28px; box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    margin-bottom: 20px;
  }

  .section-card h3 {
    font-family: 'Instrument Serif', serif;
    font-size: 1.15rem; color: var(--navy); margin-bottom: 18px;
    display: flex; align-items: center; gap: 8px;
  }

  /* Profile grid */
  .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .profile-field-full { grid-column: 1/-1; }

  .profile-field label {
    display: block; font-size: 0.73rem; font-weight: 700;
    color: var(--gray); text-transform: uppercase;
    letter-spacing: 0.4px; margin-bottom: 6px;
  }

  .profile-field input {
    width: 100%; padding: 11px 14px;
    border: 1.5px solid #e8e2d8; border-radius: 10px;
    font-family: 'DM Sans', sans-serif; font-size: 0.88rem;
    color: var(--navy); background: var(--cream); outline: none;
  }

  .btn-edit {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 20px; background: var(--navy); color: var(--white);
    border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem; font-weight: 600; cursor: pointer;
    transition: all 0.2s; text-decoration: none; margin-top: 16px;
  }

  .btn-edit:hover { background: var(--teal); transform: translateY(-1px); }

  /* Tab content */
  .tab-content { display: none; }
  .tab-content.active { display: block; }

  /* ── DOCTORS SECTION ── */
  .search-filter-bar {
    display: flex; gap: 12px; margin-bottom: 22px; flex-wrap: wrap;
    align-items: center;
  }

  .search-wrap { position: relative; flex: 1; min-width: 200px; }

  .search-wrap .ico {
    position: absolute; left: 12px; top: 50%;
    transform: translateY(-50%); font-size: 0.9rem; opacity: 0.4;
  }

  .search-wrap input {
    width: 100%; padding: 10px 14px 10px 38px;
    border: 1.5px solid #e8e2d8; border-radius: 10px;
    font-family: 'DM Sans', sans-serif; font-size: 0.88rem;
    color: var(--navy); background: var(--white); outline: none;
    transition: border-color 0.2s;
  }

  .search-wrap input:focus { border-color: var(--teal); }

  .spec-filter {
    padding: 10px 14px; border: 1.5px solid #e8e2d8;
    border-radius: 10px; font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem; color: var(--navy); background: var(--white);
    outline: none; cursor: pointer; min-width: 160px;
  }

  .spec-filter:focus { border-color: var(--teal); }

  /* Doctor cards grid */
  .doctors-grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: 16px;
  }

  .doctor-card {
    background: var(--white); border-radius: 16px;
    padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.04);
    border: 1.5px solid transparent;
    transition: all 0.2s; cursor: pointer;
  }

  .doctor-card:hover {
    border-color: var(--teal);
    box-shadow: 0 4px 20px rgba(13,124,115,0.1);
    transform: translateY(-2px);
  }

  .doctor-card.nearby { border-color: rgba(13,124,115,0.25); }

  .doc-card-top {
    display: flex; align-items: flex-start; gap: 14px; margin-bottom: 14px;
  }

  .doc-avatar-card {
    width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--teal), var(--teal2));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; color: var(--white); font-weight: 700;
  }

  .doc-card-info { flex: 1; }

  .doc-card-name {
    font-family: 'Instrument Serif', serif;
    font-size: 1rem; color: var(--navy); margin-bottom: 2px;
  }

  .doc-card-spec {
    font-size: 0.78rem; color: var(--teal); font-weight: 600;
    margin-bottom: 4px;
  }

  .doc-card-exp { font-size: 0.75rem; color: var(--gray); }

  .distance-badge {
    font-size: 0.7rem; font-weight: 600; padding: 3px 10px;
    border-radius: 20px; white-space: nowrap; flex-shrink: 0;
  }

  .distance-badge.near   { background: rgba(26,158,108,0.1); color: var(--green); }
  .distance-badge.city   { background: rgba(13,124,115,0.1); color: var(--teal); }
  .distance-badge.far    { background: rgba(138,149,163,0.1); color: var(--gray); }

  .doc-card-details {
    display: flex; flex-direction: column; gap: 5px;
    padding-top: 12px; border-top: 1px solid #f5f0e8;
  }

  .doc-detail-row {
    display: flex; align-items: center; gap: 6px;
    font-size: 0.78rem; color: var(--gray);
  }

  .doc-detail-row strong { color: var(--navy); }

  .doc-card-footer {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 14px; padding-top: 12px; border-top: 1px solid #f5f0e8;
  }

  .doc-fee {
    font-family: 'Instrument Serif', serif;
    font-size: 1.1rem; color: var(--navy);
  }

  .doc-fee span { font-size: 0.72rem; color: var(--gray); font-family: 'DM Sans', sans-serif; }

  .btn-book {
    padding: 8px 20px; background: var(--teal); color: var(--white);
    border: none; border-radius: 9px; font-family: 'DM Sans', sans-serif;
    font-size: 0.82rem; font-weight: 600; cursor: pointer;
    transition: all 0.2s; text-decoration: none;
  }

  .btn-book:hover { background: var(--navy); transform: translateY(-1px); }

  .no-timing {
    font-size: 0.75rem; color: var(--gray);
    font-style: italic;
  }

  /* ── APPOINTMENTS SECTION ── */
  .appt-filters {
    display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap;
  }

  .filter-btn {
    padding: 6px 16px; border: 1.5px solid #e8e2d8;
    border-radius: 20px; font-size: 0.8rem; font-weight: 600;
    color: var(--gray); background: var(--cream);
    cursor: pointer; transition: all 0.2s; user-select: none;
  }

  .filter-btn.active { border-color: var(--teal); color: var(--teal); background: rgba(13,124,115,0.06); }

  .appt-list { display: flex; flex-direction: column; gap: 14px; }

  .appt-card {
    background: var(--white); border-radius: 14px;
    padding: 18px 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    border-left: 4px solid #e8e2d8;
    display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
    transition: all 0.2s;
  }

  .appt-card.pending   { border-left-color: var(--orange); }
  .appt-card.confirmed { border-left-color: var(--teal); }
  .appt-card.completed { border-left-color: var(--green); }
  .appt-card.cancelled { border-left-color: var(--red); opacity: 0.7; }

  .appt-doc-avatar {
    width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--teal), var(--teal2));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: var(--white); font-weight: 700;
  }

  .appt-info { flex: 1; min-width: 160px; }

  .appt-doc-name {
    font-family: 'Instrument Serif', serif;
    font-size: 1rem; color: var(--navy); margin-bottom: 2px;
  }

  .appt-spec { font-size: 0.78rem; color: var(--teal); font-weight: 600; margin-bottom: 3px; }
  .appt-clinic { font-size: 0.75rem; color: var(--gray); }

  .appt-datetime {
    text-align: center; min-width: 110px;
  }

  .appt-date-val {
    font-size: 0.88rem; font-weight: 600; color: var(--navy); margin-bottom: 3px;
  }

  .appt-time-val { font-size: 0.78rem; color: var(--gray); }

  .status-badge {
    display: inline-block; padding: 4px 12px;
    border-radius: 20px; font-size: 0.72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.3px;
  }

  .status-badge.pending   { background: rgba(224,125,42,0.1); color: var(--orange); }
  .status-badge.confirmed { background: rgba(13,124,115,0.1); color: var(--teal); }
  .status-badge.completed { background: rgba(26,158,108,0.1); color: var(--green); }
  .status-badge.cancelled { background: rgba(224,84,84,0.1); color: var(--red); }

  .btn-cancel {
    padding: 7px 16px; border: 1.5px solid #e8e2d8;
    border-radius: 9px; font-family: 'DM Sans', sans-serif;
    font-size: 0.78rem; font-weight: 600; color: var(--red);
    background: transparent; cursor: pointer; transition: all 0.2s;
  }

  .btn-cancel:hover { background: rgba(224,84,84,0.06); border-color: var(--red); }

  .empty-state {
    text-align: center; padding: 48px 24px; color: var(--gray);
  }

  .empty-state .empty-icon { font-size: 3rem; margin-bottom: 12px; }
  .empty-state h4 { font-size: 1rem; color: var(--navy); margin-bottom: 6px; }

  @media (max-width: 900px) {
    .sidebar { display: none; }
    .content { padding: 20px 14px; }
    .doctors-grid { grid-template-columns: 1fr; }
    .stats-row { grid-template-columns: repeat(3,1fr); }
    .profile-grid { grid-template-columns: 1fr; }
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
  <div class="topbar-right">
    <a href="../patient/select-location.php" class="location-pill">
      📍 <?= htmlspecialchars($patientArea ?: $patientCity) ?>
    </a>
    <div class="pat-info">
      <div class="name"><?= htmlspecialchars($patient['name']) ?></div>
      <div class="role">Patient</div>
    </div>
    <div class="pat-avatar"><?= strtoupper(substr($patient['name'],0,1)) ?></div>
    <a href="../auth/logout.php" class="logout-btn">Logout</a>
  </div>
</div>

<div class="layout">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sidebar-profile">
      <div class="sidebar-avatar"><?= strtoupper(substr($patient['name'],0,1)) ?></div>
      <div class="sidebar-name"><?= htmlspecialchars($patient['name']) ?></div>
      <div class="sidebar-loc">📍 <?= htmlspecialchars($patientArea ?: $patientCity) ?></div>
    </div>

    <a class="nav-item <?= $tab==='profile'      ? 'active':'' ?>" href="?tab=profile">
      <span class="nav-icon">👤</span> My Profile
    </a>
    <a class="nav-item <?= $tab==='doctors'      ? 'active':'' ?>" href="?tab=doctors">
      <span class="nav-icon">🩺</span> Doctors & Clinics
    </a>
    <a class="nav-item <?= $tab==='appointments' ? 'active':'' ?>" href="?tab=appointments">
      <span class="nav-icon">📅</span> My Appointments
      <?php if (count($pendingAppts) > 0): ?>
        <span class="nav-badge"><?= count($pendingAppts) ?></span>
      <?php endif; ?>
    </a>
    <a class="nav-item" href="../patient/select-location.php">
      <span class="nav-icon">📍</span> Change Location
    </a>
    <a class="nav-item" href="../auth/logout.php">
      <span class="nav-icon">🚪</span> Logout
    </a>
  </div>

  <!-- CONTENT -->
  <div class="content">

    <!-- ══════════════════════════ -->
    <!--  MY PROFILE TAB           -->
    <!-- ══════════════════════════ -->
    <div class="tab-content <?= $tab==='profile' ? 'active':'' ?>">

      <div class="page-header">
        <h1>My Profile</h1>
        <p>Your personal information and appointment summary</p>
      </div>

      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon teal">📋</div>
          <div>
            <div class="stat-num"><?= $totalAppts ?></div>
            <div class="stat-label">Total Booked</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">📅</div>
          <div>
            <div class="stat-num"><?= count($upcomingAppts) ?></div>
            <div class="stat-label">Upcoming</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">✅</div>
          <div>
            <div class="stat-num"><?= count(array_filter($appointments, fn($a)=>$a['status']==='completed')) ?></div>
            <div class="stat-label">Completed</div>
          </div>
        </div>
      </div>

      <div class="section-card">
        <h3>👤 Personal Information</h3>
        <div class="profile-grid">
          <div class="profile-field">
            <label>Full Name</label>
            <input type="text" value="<?= htmlspecialchars($patient['name']) ?>" readonly>
          </div>
          <div class="profile-field">
            <label>Email</label>
            <input type="text" value="<?= htmlspecialchars($patient['email']) ?>" readonly>
          </div>
          <div class="profile-field">
            <label>Phone</label>
            <input type="text" value="<?= htmlspecialchars($patient['phone']) ?>" readonly>
          </div>
          <div class="profile-field">
            <label>Location</label>
            <input type="text" value="<?= htmlspecialchars($patientArea ? $patientArea.', '.$patientCity : $patientCity) ?>" readonly>
          </div>
          <div class="profile-field">
            <label>Member Since</label>
            <input type="text" value="<?= date('d M Y', strtotime($patient['created_at'])) ?>" readonly>
          </div>
          <div class="profile-field">
            <label>Account Type</label>
            <input type="text" value="Patient" readonly>
          </div>
        </div>
        <a href="edit-profile.php" class="btn-edit">✏️ Edit Profile</a>
      </div>

      <!-- Upcoming appointments preview -->
      <?php if (!empty($upcomingAppts)): ?>
      <div class="section-card">
        <h3>📅 Upcoming Appointments</h3>
        <div class="appt-list">
          <?php foreach (array_slice(array_values($upcomingAppts), 0, 3) as $appt): ?>
          <div class="appt-card <?= $appt['status'] ?>">
            <div class="appt-doc-avatar"><?= strtoupper(substr($appt['doctor_name'],0,1)) ?></div>
            <div class="appt-info">
              <div class="appt-doc-name">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></div>
              <div class="appt-spec"><?= htmlspecialchars($appt['specialization'] ?? '') ?></div>
              <div class="appt-clinic">🏥 <?= htmlspecialchars($appt['clinic_name'] ?? '—') ?></div>
            </div>
            <div class="appt-datetime">
              <div class="appt-date-val">📅 <?= date('d M Y', strtotime($appt['appointment_date'])) ?></div>
              <div class="appt-time-val">🕐 <?= date('h:i A', strtotime($appt['time_slot'])) ?></div>
            </div>
            <span class="status-badge <?= $appt['status'] ?>"><?= ucfirst($appt['status']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="?tab=appointments" class="btn-edit" style="margin-top:16px">View All →</a>
      </div>
      <?php endif; ?>

    </div>

    <!-- ══════════════════════════ -->
    <!--  DOCTORS & CLINICS TAB    -->
    <!-- ══════════════════════════ -->
    <div class="tab-content <?= $tab==='doctors' ? 'active':'' ?>">

      <div class="page-header">
        <h1>Doctors & Clinics</h1>
        <p>Showing doctors near <strong><?= htmlspecialchars($patientArea ?: $patientCity) ?></strong> — closest first</p>
      </div>

      <!-- Search & Filter -->
      <div class="search-filter-bar">
        <div class="search-wrap">
          <span class="ico">🔍</span>
          <input type="text" id="docSearch" placeholder="Search doctor name or clinic..."
                 oninput="filterDoctors()">
        </div>
        <select class="spec-filter" id="specFilter" onchange="filterDoctors()">
          <option value="">All Specializations</option>
          <option>Cardiologist</option>
          <option>Dermatologist</option>
          <option>Neurologist</option>
          <option>Orthopedic</option>
          <option>Pediatrician</option>
          <option>Psychiatrist</option>
          <option>General Physician</option>
          <option>Gynecologist</option>
          <option>Ophthalmologist</option>
          <option>ENT Specialist</option>
        </select>
      </div>

      <!-- Doctor Cards -->
      <?php if (empty($doctors)): ?>
        <div class="empty-state">
          <div class="empty-icon">🔍</div>
          <h4>No doctors found</h4>
          <p>No doctors have registered in your area yet</p>
        </div>
      <?php else: ?>
        <div class="doctors-grid" id="doctorsGrid">
          <?php foreach ($doctors as $doc):
            $timings = $doc['office_timings'] ? json_decode($doc['office_timings'], true) : [];
            $today   = date('D'); // Mon, Tue etc
            $todayShort = substr($today, 0, 3);
            $todayTiming = isset($timings[$todayShort]) ? $timings[$todayShort] : null;

            // Distance label
            if ($doc['clinic_area'] === $patientArea && !empty($patientArea)) {
                $distLabel = '📍 Same Area';
                $distClass = 'near';
            } elseif ($doc['clinic_city'] === $patientCity) {
                $distLabel = '🏙️ Same City';
                $distClass = 'city';
            } else {
                $distLabel = '📌 ' . ($doc['clinic_city'] ?: 'Other City');
                $distClass = 'far';
            }
          ?>
          <div class="doctor-card <?= $distClass === 'near' ? 'nearby' : '' ?>"
               data-name="<?= strtolower($doc['name']) ?>"
               data-clinic="<?= strtolower($doc['clinic_name'] ?? '') ?>"
               data-spec="<?= htmlspecialchars($doc['specialization'] ?? '') ?>">

            <div class="doc-card-top">
              <div class="doc-avatar-card"><?= strtoupper(substr($doc['name'],0,1)) ?></div>
              <div class="doc-card-info">
                <div class="doc-card-name">Dr. <?= htmlspecialchars($doc['name']) ?></div>
                <div class="doc-card-spec"><?= htmlspecialchars($doc['specialization'] ?? 'General') ?></div>
                <div class="doc-card-exp">🏅 <?= $doc['experience'] ?? '0' ?> yrs experience</div>
              </div>
              <span class="distance-badge <?= $distClass ?>"><?= $distLabel ?></span>
            </div>

            <div class="doc-card-details">
              <?php if (!empty($doc['clinic_name'])): ?>
              <div class="doc-detail-row">
                🏥 <strong><?= htmlspecialchars($doc['clinic_name']) ?></strong>
              </div>
              <?php endif; ?>
              <?php if (!empty($doc['clinic_address'])): ?>
              <div class="doc-detail-row">
                📍 <?= htmlspecialchars($doc['clinic_address']) ?>
              </div>
              <?php endif; ?>
              <div class="doc-detail-row">
                🕐
                <?php if ($todayTiming): ?>
                  Today: <strong><?= htmlspecialchars($todayTiming) ?></strong>
                <?php else: ?>
                  <span class="no-timing">Closed today</span>
                <?php endif; ?>
              </div>
              <?php if (!empty($doc['clinic_desc'])): ?>
              <div class="doc-detail-row" style="margin-top:4px;font-style:italic;color:var(--gray)">
                "<?= htmlspecialchars(substr($doc['clinic_desc'], 0, 80)) . (strlen($doc['clinic_desc']) > 80 ? '...' : '') ?>"
              </div>
              <?php endif; ?>
            </div>

            <div class="doc-card-footer">
              <div class="doc-fee">
                ₹<?= number_format($doc['fees'] ?? 0) ?>
                <span>/ visit</span>
              </div>
              <a href="book-appointment.php?doctor_id=<?= $doc['id'] ?>" class="btn-book">
                Book Now →
              </a>
            </div>

          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>

    <!-- ══════════════════════════ -->
    <!--  MY APPOINTMENTS TAB      -->
    <!-- ══════════════════════════ -->
    <div class="tab-content <?= $tab==='appointments' ? 'active':'' ?>">

      <div class="page-header">
        <h1>My Appointments</h1>
        <p>Track all your booked appointments</p>
      </div>

      <div class="appt-filters">
        <div class="filter-btn active" onclick="filterAppts('all',this)">All (<?= $totalAppts ?>)</div>
        <div class="filter-btn" onclick="filterAppts('pending',this)">⏳ Pending</div>
        <div class="filter-btn" onclick="filterAppts('confirmed',this)">✅ Confirmed</div>
        <div class="filter-btn" onclick="filterAppts('completed',this)">🏁 Completed</div>
        <div class="filter-btn" onclick="filterAppts('cancelled',this)">❌ Cancelled</div>
      </div>

      <?php if (empty($appointments)): ?>
        <div class="empty-state">
          <div class="empty-icon">📭</div>
          <h4>No appointments yet</h4>
          <p>Browse doctors and book your first appointment!</p>
          <a href="?tab=doctors" class="btn-edit" style="margin-top:16px;display:inline-flex">Find Doctors →</a>
        </div>
      <?php else: ?>
        <div class="appt-list" id="apptList">
          <?php foreach ($appointments as $appt): ?>
          <div class="appt-card <?= $appt['status'] ?>" data-status="<?= $appt['status'] ?>">
            <div class="appt-doc-avatar"><?= strtoupper(substr($appt['doctor_name'],0,1)) ?></div>
            <div class="appt-info">
              <div class="appt-doc-name">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></div>
              <div class="appt-spec"><?= htmlspecialchars($appt['specialization'] ?? '') ?></div>
              <div class="appt-clinic">🏥 <?= htmlspecialchars($appt['clinic_name'] ?? '—') ?></div>
              <?php if ($appt['clinic_address']): ?>
              <div class="appt-clinic">📍 <?= htmlspecialchars($appt['clinic_address']) ?></div>
              <?php endif; ?>
            </div>
            <div class="appt-datetime">
              <div class="appt-date-val">📅 <?= date('d M Y', strtotime($appt['appointment_date'])) ?></div>
              <div class="appt-time-val">🕐 <?= date('h:i A', strtotime($appt['time_slot'])) ?></div>
              <?php if ($appt['reason']): ?>
              <div class="appt-time-val" style="margin-top:4px">📝 <?= htmlspecialchars($appt['reason']) ?></div>
              <?php endif; ?>
            </div>
            <span class="status-badge <?= $appt['status'] ?>"><?= ucfirst($appt['status']) ?></span>
            <?php if (in_array($appt['status'], ['pending','confirmed'])): ?>
            <form method="POST">
              <input type="hidden" name="appt_id" value="<?= $appt['id'] ?>">
              <button type="submit" name="cancel_appt" class="btn-cancel"
                      onclick="return confirm('Cancel this appointment?')">Cancel</button>
            </form>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>

  </div>
</div>

<script>
  // Filter appointments
  function filterAppts(status, btn) {
    document.querySelectorAll('.appt-filters .filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#apptList .appt-card').forEach(card => {
      card.style.display = (status === 'all' || card.dataset.status === status) ? '' : 'none';
    });
  }

  // Search + filter doctors
  function filterDoctors() {
    const search = document.getElementById('docSearch').value.toLowerCase();
    const spec   = document.getElementById('specFilter').value.toLowerCase();
    document.querySelectorAll('.doctor-card').forEach(card => {
      const name   = card.dataset.name   || '';
      const clinic = card.dataset.clinic || '';
      const cardSpec = card.dataset.spec.toLowerCase();
      const matchSearch = !search || name.includes(search) || clinic.includes(search);
      const matchSpec   = !spec   || cardSpec === spec;
      card.style.display = (matchSearch && matchSpec) ? '' : 'none';
    });
  }
</script>
</body>
</html>
