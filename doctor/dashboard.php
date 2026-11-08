<?php
require_once '../includes/db.php';
requireLogin();

if ($_SESSION['user_role'] !== 'doctor') {
    redirectByRole($_SESSION['user_role']);
}

$userId = $_SESSION['user_id'];

// Fetch doctor full info
$sql    = "SELECT u.name, u.email, u.phone, d.*
           FROM users u
           JOIN doctors d ON d.user_id = u.id
           WHERE u.id = '$userId'";
$result = mysqli_query($conn, $sql);
$doctor = mysqli_fetch_assoc($result);

// Fetch subscription info
$subSql = "SELECT ds.*, sp.plan_name, sp.price
           FROM doctor_subscriptions ds
           JOIN subscription_plans sp ON sp.id = ds.plan_id
           WHERE ds.doctor_id = '{$doctor['id']}' AND ds.status = 'active'
           ORDER BY ds.id DESC LIMIT 1";
$subResult = mysqli_query($conn, $subSql);
$subscription = $subResult ? mysqli_fetch_assoc($subResult) : null;

// Fetch appointments
$apptSql = "SELECT a.*, u.name as patient_name, u.phone as patient_phone, u.email as patient_email
            FROM appointments a
            JOIN users u ON u.id = a.patient_id
            WHERE a.doctor_id = '{$doctor['id']}'
            ORDER BY a.appointment_date DESC, a.time_slot DESC";
$apptResult  = mysqli_query($conn, $apptSql);
$appointments = [];
if ($apptResult) {
    while ($row = mysqli_fetch_assoc($apptResult)) {
        $appointments[] = $row;
    }
}

// Count stats
$totalAppts    = count($appointments);
$todayAppts    = array_filter($appointments, fn($a) => $a['appointment_date'] === date('Y-m-d'));
$pendingAppts  = array_filter($appointments, fn($a) => $a['status'] === 'pending');
$completedAppts = array_filter($appointments, fn($a) => $a['status'] === 'completed');

// Active tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';

// Handle appointment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $apptId    = (int)$_POST['appt_id'];
    $newStatus = clean($conn, $_POST['new_status']);
    mysqli_query($conn, "UPDATE appointments SET status='$newStatus' WHERE id='$apptId' AND doctor_id='{$doctor['id']}'");
    header("Location: dashboard.php?tab=appointments");
    exit();
}

// Decode timings
$timings = $doctor['office_timings'] ? json_decode($doctor['office_timings'], true) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Doctor Dashboard — MediBook</title>
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
    --green: #1a9e6c;
    --orange:#e07d2a;
  }

  body {
    min-height: 100vh;
    background: #eee9df;
    font-family: 'DM Sans', sans-serif;
  }

  /* ── Topbar ── */
  .topbar {
    background: var(--navy);
    padding: 0 32px;
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

  .topbar-right { display: flex; align-items: center; gap: 16px; }

  .doc-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--teal); display: flex; align-items: center;
    justify-content: center; font-size: 1rem; color: var(--white);
    font-weight: 700;
  }

  .doc-info { text-align: right; }
  .doc-info .name { font-size: 0.88rem; font-weight: 600; color: var(--white); }
  .doc-info .role { font-size: 0.72rem; color: rgba(255,255,255,0.5); }

  .logout-btn {
    padding: 7px 16px; border: 1px solid rgba(255,255,255,0.2);
    border-radius: 8px; color: rgba(255,255,255,0.7);
    font-size: 0.8rem; font-weight: 500; cursor: pointer;
    background: transparent; font-family: 'DM Sans', sans-serif;
    transition: all 0.2s; text-decoration: none;
  }

  .logout-btn:hover { background: rgba(255,255,255,0.1); color: var(--white); }

  /* ── Layout ── */
  .layout { display: flex; min-height: calc(100vh - 60px); }

  /* ── Sidebar ── */
  .sidebar {
    width: 240px; background: var(--white);
    padding: 28px 16px; flex-shrink: 0;
    border-right: 1px solid #e8e2d8;
    position: sticky; top: 60px; height: calc(100vh - 60px);
    overflow-y: auto;
  }

  .sidebar-profile {
    text-align: center; padding-bottom: 24px;
    border-bottom: 1px solid #f0ebe0; margin-bottom: 20px;
  }

  .sidebar-avatar {
    width: 72px; height: 72px; border-radius: 50%;
    background: linear-gradient(135deg, var(--teal), var(--teal2));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; color: var(--white); font-weight: 700;
    margin: 0 auto 10px;
  }

  .sidebar-name {
    font-family: 'Instrument Serif', serif;
    font-size: 1rem; color: var(--navy); margin-bottom: 3px;
  }

  .sidebar-spec {
    font-size: 0.75rem; color: var(--gray);
  }

  .plan-badge {
    display: inline-block; margin-top: 8px;
    padding: 3px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;
    background: rgba(13,124,115,0.1); color: var(--teal);
    text-transform: uppercase; letter-spacing: 0.5px;
  }

  .plan-badge.premium { background: rgba(224,125,42,0.1); color: var(--orange); }
  .plan-badge.standard { background: rgba(13,124,115,0.1); color: var(--teal); }
  .plan-badge.basic { background: rgba(138,149,163,0.1); color: var(--gray); }

  .nav-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 14px; border-radius: 10px;
    font-size: 0.9rem; font-weight: 500; color: var(--gray);
    cursor: pointer; transition: all 0.2s; text-decoration: none;
    margin-bottom: 4px;
  }

  .nav-item:hover { background: var(--cream); color: var(--navy); }
  .nav-item.active { background: rgba(13,124,115,0.08); color: var(--teal); font-weight: 600; }
  .nav-item .nav-icon { font-size: 1.1rem; }

  .nav-badge {
    margin-left: auto; background: var(--red); color: var(--white);
    font-size: 0.7rem; font-weight: 700; padding: 2px 7px;
    border-radius: 10px; min-width: 20px; text-align: center;
  }

  /* ── Main content ── */
  .content { flex: 1; padding: 32px 36px; overflow-y: auto; }

  /* Page header */
  .page-header { margin-bottom: 28px; }

  .page-header h1 {
    font-family: 'Instrument Serif', serif;
    font-size: 1.9rem; color: var(--navy); margin-bottom: 4px;
  }

  .page-header p { color: var(--gray); font-size: 0.9rem; }

  /* Stats row */
  .stats-row {
    display: grid; grid-template-columns: repeat(4, 1fr);
    gap: 16px; margin-bottom: 32px;
  }

  .stat-card {
    background: var(--white); border-radius: 16px;
    padding: 20px 22px; box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    display: flex; align-items: center; gap: 14px;
  }

  .stat-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
    flex-shrink: 0;
  }

  .stat-icon.teal   { background: rgba(13,124,115,0.1); }
  .stat-icon.orange { background: rgba(224,125,42,0.1); }
  .stat-icon.red    { background: rgba(224,84,84,0.1); }
  .stat-icon.green  { background: rgba(26,158,108,0.1); }

  .stat-num {
    font-family: 'Instrument Serif', serif;
    font-size: 1.6rem; color: var(--navy); line-height: 1;
  }

  .stat-label { font-size: 0.75rem; color: var(--gray); margin-top: 3px; }

  /* Section card */
  .section-card {
    background: var(--white); border-radius: 18px;
    padding: 28px 32px; box-shadow: 0 2px 12px rgba(0,0,0,0.04);
    margin-bottom: 24px;
  }

  .section-card h3 {
    font-family: 'Instrument Serif', serif;
    font-size: 1.2rem; color: var(--navy); margin-bottom: 20px;
    display: flex; align-items: center; gap: 8px;
  }

  /* Profile grid */
  .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
  .profile-field-full { grid-column: 1/-1; }

  .profile-field label {
    display: block; font-size: 0.75rem; font-weight: 700;
    color: var(--gray); text-transform: uppercase;
    letter-spacing: 0.4px; margin-bottom: 6px;
  }

  .profile-field input,
  .profile-field select,
  .profile-field textarea {
    width: 100%; padding: 11px 14px;
    border: 1.5px solid #e8e2d8; border-radius: 10px;
    font-family: 'DM Sans', sans-serif; font-size: 0.9rem;
    color: var(--navy); background: var(--cream); outline: none;
    transition: border-color 0.2s;
  }

  .profile-field textarea { resize: vertical; min-height: 80px; }

  .profile-field input:focus,
  .profile-field select:focus,
  .profile-field textarea:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 3px rgba(13,124,115,0.08);
    background: var(--white);
  }

  /* Timing display */
  .timing-grid { display: flex; flex-wrap: wrap; gap: 8px; }

  .timing-chip {
    padding: 6px 14px; border-radius: 20px;
    background: rgba(13,124,115,0.08);
    border: 1px solid rgba(13,124,115,0.15);
    font-size: 0.8rem; color: var(--teal); font-weight: 500;
  }

  .timing-chip.closed {
    background: #f5f0e8; border-color: #e8e2d8; color: var(--gray);
  }

  /* Edit button */
  .btn-edit {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px; background: var(--navy); color: var(--white);
    border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif;
    font-size: 0.88rem; font-weight: 600; cursor: pointer;
    transition: all 0.2s; text-decoration: none; margin-top: 20px;
  }

  .btn-edit:hover { background: var(--teal); transform: translateY(-1px); }

  /* Appointments table */
  .appt-filters {
    display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;
  }

  .filter-btn {
    padding: 7px 18px; border: 1.5px solid #e8e2d8;
    border-radius: 20px; font-size: 0.82rem; font-weight: 600;
    color: var(--gray); background: var(--cream);
    cursor: pointer; transition: all 0.2s; user-select: none;
  }

  .filter-btn.active { border-color: var(--teal); color: var(--teal); background: rgba(13,124,115,0.06); }

  .appt-table { width: 100%; border-collapse: collapse; }

  .appt-table th {
    font-size: 0.72rem; font-weight: 700; color: var(--gray);
    text-transform: uppercase; letter-spacing: 0.4px;
    padding: 0 14px 12px; text-align: left; white-space: nowrap;
  }

  .appt-table td {
    padding: 14px; border-top: 1px solid #f5f0e8;
    vertical-align: middle;
  }

  .appt-table tr:hover td { background: #faf8f4; }

  .patient-name { font-weight: 600; color: var(--navy); font-size: 0.9rem; }
  .patient-contact { font-size: 0.78rem; color: var(--gray); margin-top: 2px; }

  .appt-date { font-size: 0.88rem; color: var(--navy); font-weight: 500; }
  .appt-time { font-size: 0.75rem; color: var(--gray); margin-top: 2px; }

  .status-badge {
    display: inline-block; padding: 4px 12px;
    border-radius: 20px; font-size: 0.75rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.3px;
  }

  .status-badge.pending   { background: rgba(224,125,42,0.1); color: var(--orange); }
  .status-badge.confirmed { background: rgba(13,124,115,0.1); color: var(--teal); }
  .status-badge.completed { background: rgba(26,158,108,0.1); color: var(--green); }
  .status-badge.cancelled { background: rgba(224,84,84,0.1); color: var(--red); }

  /* Action dropdown */
  .action-select {
    padding: 6px 10px; border: 1.5px solid #e8e2d8;
    border-radius: 8px; font-family: 'DM Sans', sans-serif;
    font-size: 0.8rem; color: var(--navy); background: var(--cream);
    cursor: pointer; outline: none;
  }

  .action-select:focus { border-color: var(--teal); }

  .btn-update {
    padding: 6px 14px; background: var(--navy); color: var(--white);
    border: none; border-radius: 8px; font-size: 0.8rem; font-weight: 600;
    cursor: pointer; font-family: 'DM Sans', sans-serif; transition: all 0.2s;
  }

  .btn-update:hover { background: var(--teal); }

  /* Empty state */
  .empty-state {
    text-align: center; padding: 48px 24px; color: var(--gray);
  }

  .empty-state .empty-icon { font-size: 3rem; margin-bottom: 12px; }
  .empty-state h4 { font-size: 1rem; color: var(--navy); margin-bottom: 6px; }
  .empty-state p  { font-size: 0.88rem; }

  /* Hidden tab */
  .tab-content { display: none; }
  .tab-content.active { display: block; }

  @media (max-width: 900px) {
    .sidebar { display: none; }
    .stats-row { grid-template-columns: repeat(2,1fr); }
    .profile-grid { grid-template-columns: 1fr; }
    .content { padding: 20px 16px; }
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
    <div class="doc-info">
      <div class="name">Dr. <?= htmlspecialchars($doctor['name']) ?></div>
      <div class="role"><?= htmlspecialchars($doctor['specialization'] ?? 'Doctor') ?></div>
    </div>
    <div class="doc-avatar"><?= strtoupper(substr($doctor['name'], 0, 1)) ?></div>
    <a href="../auth/logout.php" class="logout-btn">Logout</a>
  </div>
</div>

<div class="layout">

  <!-- SIDEBAR -->
  <div class="sidebar">
    <div class="sidebar-profile">
      <div class="sidebar-avatar"><?= strtoupper(substr($doctor['name'], 0, 1)) ?></div>
      <div class="sidebar-name">Dr. <?= htmlspecialchars($doctor['name']) ?></div>
      <div class="sidebar-spec"><?= htmlspecialchars($doctor['specialization'] ?? '—') ?></div>
      <?php
        $planName = $subscription ? strtolower($subscription['plan_name']) : 'basic';
        $planLabel = $subscription ? $subscription['plan_name'] : 'Basic';
      ?>
      <span class="plan-badge <?= $planName ?>"><?= $planLabel ?> Plan</span>
    </div>

    <a class="nav-item <?= $tab==='profile' ? 'active' : '' ?>" href="?tab=profile">
      <span class="nav-icon">👤</span> My Profile
    </a>
    <a class="nav-item <?= $tab==='appointments' ? 'active' : '' ?>" href="?tab=appointments">
      <span class="nav-icon">📅</span> Appointments
      <?php if (count($pendingAppts) > 0): ?>
        <span class="nav-badge"><?= count($pendingAppts) ?></span>
      <?php endif; ?>
    </a>
    <a class="nav-item" href="clinic-details.php">
      <span class="nav-icon">🏥</span> Edit Clinic
    </a>
    <a class="nav-item" href="select-plan.php">
      <span class="nav-icon">💳</span> Subscription
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
    <div class="tab-content <?= $tab==='profile' ? 'active' : '' ?>">

      <div class="page-header">
        <h1>My Profile</h1>
        <p>View and manage your personal and clinic information</p>
      </div>

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon teal">📅</div>
          <div>
            <div class="stat-num"><?= $totalAppts ?></div>
            <div class="stat-label">Total Appointments</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">🕐</div>
          <div>
            <div class="stat-num"><?= count($todayAppts) ?></div>
            <div class="stat-label">Today's Appointments</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red">⏳</div>
          <div>
            <div class="stat-num"><?= count($pendingAppts) ?></div>
            <div class="stat-label">Pending</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">✅</div>
          <div>
            <div class="stat-num"><?= count($completedAppts) ?></div>
            <div class="stat-label">Completed</div>
          </div>
        </div>
      </div>

      <!-- Personal Info -->
      <div class="section-card">
        <h3>👤 Personal Information</h3>
        <div class="profile-grid">
          <div class="profile-field">
            <label>Full Name</label>
            <input type="text" value="Dr. <?= htmlspecialchars($doctor['name']) ?>" readonly>
          </div>
          <div class="profile-field">
            <label>Email</label>
            <input type="text" value="<?= htmlspecialchars($doctor['email']) ?>" readonly>
          </div>
          <div class="profile-field">
            <label>Phone</label>
            <input type="text" value="<?= htmlspecialchars($doctor['phone']) ?>" readonly>
          </div>
          <div class="profile-field">
            <label>Specialization</label>
            <input type="text" value="<?= htmlspecialchars($doctor['specialization'] ?? '—') ?>" readonly>
          </div>
          <div class="profile-field">
            <label>Experience</label>
            <input type="text" value="<?= $doctor['experience'] ?? '0' ?> years" readonly>
          </div>
          <div class="profile-field">
            <label>Consultation Fee</label>
            <input type="text" value="₹<?= $doctor['fees'] ?? '0' ?>" readonly>
          </div>
        </div>
        <a href="edit-profile.php" class="btn-edit">✏️ Edit Profile</a>
      </div>

      <!-- Clinic Info -->
      <div class="section-card">
        <h3>🏥 Clinic Information</h3>
        <div class="profile-grid">
          <div class="profile-field">
            <label>Clinic Name</label>
            <input type="text" value="<?= htmlspecialchars($doctor['clinic_name'] ?? '—') ?>" readonly>
          </div>
          <div class="profile-field">
            <label>Location</label>
            <input type="text" value="<?= htmlspecialchars(($doctor['clinic_area'] ?? '') . (isset($doctor['clinic_city']) ? ', ' . $doctor['clinic_city'] : '')) ?>" readonly>
          </div>
          <div class="profile-field profile-field-full">
            <label>Address</label>
            <input type="text" value="<?= htmlspecialchars($doctor['clinic_address'] ?? '—') ?>" readonly>
          </div>
          <?php if (!empty($doctor['clinic_desc'])): ?>
          <div class="profile-field profile-field-full">
            <label>Description</label>
            <textarea readonly><?= htmlspecialchars($doctor['clinic_desc']) ?></textarea>
          </div>
          <?php endif; ?>
        </div>

        <!-- Timings -->
        <?php if (!empty($timings)): ?>
        <div style="margin-top:20px">
          <label style="font-size:0.75rem;font-weight:700;color:var(--gray);text-transform:uppercase;letter-spacing:0.4px;display:block;margin-bottom:10px">Office Timings</label>
          <div class="timing-grid">
            <?php
            $allDays = ['Mon'=>'Mon','Tue'=>'Tue','Wed'=>'Wed','Thu'=>'Thu','Fri'=>'Fri','Sat'=>'Sat','Sun'=>'Sun'];
            foreach ($allDays as $key => $label):
              if (isset($timings[$key])):
            ?>
              <div class="timing-chip">📅 <?= $key ?>: <?= htmlspecialchars($timings[$key]) ?></div>
            <?php else: ?>
              <div class="timing-chip closed">❌ <?= $key ?>: Closed</div>
            <?php endif; endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <a href="clinic-details.php" class="btn-edit">✏️ Edit Clinic Details</a>
      </div>

      <!-- Subscription -->
      <div class="section-card">
        <h3>💳 Current Subscription</h3>
        <?php if ($subscription): ?>
          <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <span class="plan-badge <?= strtolower($subscription['plan_name']) ?>" style="font-size:0.85rem;padding:6px 18px">
              <?= $subscription['plan_name'] ?> Plan
            </span>
            <span style="font-size:0.88rem;color:var(--gray)">
              Valid till: <strong style="color:var(--navy)"><?= date('d M Y', strtotime($subscription['end_date'])) ?></strong>
            </span>
            <a href="select-plan.php" class="btn-edit" style="margin-top:0;padding:8px 18px">Upgrade Plan</a>
          </div>
        <?php else: ?>
          <p style="color:var(--gray);font-size:0.9rem;margin-bottom:14px">You are on the Free Basic plan.</p>
          <a href="select-plan.php" class="btn-edit" style="margin-top:0">Upgrade to Premium →</a>
        <?php endif; ?>
      </div>

    </div>

    <!-- ══════════════════════════ -->
    <!--  APPOINTMENTS TAB         -->
    <!-- ══════════════════════════ -->
    <div class="tab-content <?= $tab==='appointments' ? 'active' : '' ?>">

      <div class="page-header">
        <h1>Appointments</h1>
        <p>View and manage all your patient appointments</p>
      </div>

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon teal">📋</div>
          <div>
            <div class="stat-num"><?= $totalAppts ?></div>
            <div class="stat-label">Total</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange">🕐</div>
          <div>
            <div class="stat-num"><?= count($todayAppts) ?></div>
            <div class="stat-label">Today</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon red">⏳</div>
          <div>
            <div class="stat-num"><?= count($pendingAppts) ?></div>
            <div class="stat-label">Pending</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green">✅</div>
          <div>
            <div class="stat-num"><?= count($completedAppts) ?></div>
            <div class="stat-label">Completed</div>
          </div>
        </div>
      </div>

      <!-- Filter tabs -->
      <div class="appt-filters">
        <div class="filter-btn active" onclick="filterAppts('all', this)">All (<?= $totalAppts ?>)</div>
        <div class="filter-btn" onclick="filterAppts('pending', this)">⏳ Pending (<?= count($pendingAppts) ?>)</div>
        <div class="filter-btn" onclick="filterAppts('confirmed', this)">✅ Confirmed</div>
        <div class="filter-btn" onclick="filterAppts('completed', this)">🏁 Completed</div>
        <div class="filter-btn" onclick="filterAppts('cancelled', this)">❌ Cancelled</div>
      </div>

      <!-- Table -->
      <div class="section-card" style="padding:0;overflow:hidden">
        <?php if (empty($appointments)): ?>
          <div class="empty-state">
            <div class="empty-icon">📭</div>
            <h4>No appointments yet</h4>
            <p>Patients will appear here once they book with you</p>
          </div>
        <?php else: ?>
          <table class="appt-table" id="apptTable">
            <thead>
              <tr>
                <th style="padding-left:24px">#</th>
                <th>Patient</th>
                <th>Date & Time</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($appointments as $i => $appt): ?>
              <tr class="appt-row" data-status="<?= $appt['status'] ?>">
                <td style="padding-left:24px;color:var(--gray);font-size:0.8rem"><?= $i+1 ?></td>
                <td>
                  <div class="patient-name"><?= htmlspecialchars($appt['patient_name']) ?></div>
                  <div class="patient-contact">📱 <?= htmlspecialchars($appt['patient_phone']) ?></div>
                  <div class="patient-contact">✉️ <?= htmlspecialchars($appt['patient_email']) ?></div>
                </td>
                <td>
                  <div class="appt-date">📅 <?= date('d M Y', strtotime($appt['appointment_date'])) ?></div>
                  <div class="appt-time">🕐 <?= date('h:i A', strtotime($appt['time_slot'])) ?></div>
                </td>
                <td style="font-size:0.85rem;color:var(--navy);max-width:160px">
                  <?= htmlspecialchars($appt['reason'] ?: '—') ?>
                </td>
                <td>
                  <span class="status-badge <?= $appt['status'] ?>">
                    <?= ucfirst($appt['status']) ?>
                  </span>
                </td>
                <td>
                  <form method="POST" style="display:flex;gap:6px;align-items:center">
                    <input type="hidden" name="appt_id" value="<?= $appt['id'] ?>">
                    <select name="new_status" class="action-select">
                      <option value="pending"   <?= $appt['status']==='pending'   ? 'selected':'' ?>>Pending</option>
                      <option value="confirmed" <?= $appt['status']==='confirmed' ? 'selected':'' ?>>Confirm</option>
                      <option value="completed" <?= $appt['status']==='completed' ? 'selected':'' ?>>Complete</option>
                      <option value="cancelled" <?= $appt['status']==='cancelled' ? 'selected':'' ?>>Cancel</option>
                    </select>
                    <button type="submit" name="update_status" class="btn-update">Save</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script>
  function filterAppts(status, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.appt-row').forEach(row => {
      row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
  }
</script>
</body>
</html>
