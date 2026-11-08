<?php
require_once '../includes/db.php';
requireLogin();

// Only for doctors
if ($_SESSION['user_role'] !== 'doctor') {
    redirectByRole($_SESSION['user_role']);
}

// Save location to session and redirect to doctor dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['city'])) {
    $_SESSION['doctor_city'] = clean($conn, $_POST['city']);
    $_SESSION['doctor_area'] = isset($_POST['area']) ? clean($conn, $_POST['area']) : '';

    // Also update doctor's location in DB
    $city   = clean($conn, $_POST['city']);
    $area   = isset($_POST['area']) ? clean($conn, $_POST['area']) : '';
    $userId = $_SESSION['user_id'];

    // Update doctors table with clinic location
    $sql = "UPDATE doctors SET clinic_city='$city', clinic_area='$area' WHERE user_id='$userId'";
    mysqli_query($conn, $sql);

    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Select Clinic Location — MediBook</title>
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
  }

  body {
    min-height: 100vh;
    background: var(--cream);
    font-family: 'DM Sans', sans-serif;
    display: flex;
    flex-direction: column;
  }

  /* ── Topbar ── */
  .topbar {
    background: var(--navy);
    padding: 14px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
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

  /* ── Main ── */
  .main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
  }

  /* ── Steps indicator ── */
  .steps {
    display: flex;
    align-items: center;
    gap: 0;
    margin-bottom: 36px;
  }

  .step {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .step-circle {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; font-weight: 700;
    background: var(--teal); color: var(--white);
  }

  .step-circle.done { background: var(--teal); }
  .step-circle.active { background: var(--navy); }
  .step-circle.inactive { background: #dde2e8; color: var(--gray); }

  .step-label { font-size: 0.8rem; font-weight: 600; color: var(--navy); }
  .step-label.inactive { color: var(--gray); }

  .step-line {
    width: 48px; height: 2px;
    background: #dde2e8; margin: 0 8px;
  }
  .step-line.done { background: var(--teal); }

  /* ── Card ── */
  .card {
    background: var(--white);
    border-radius: 24px;
    padding: 48px 52px;
    width: 100%;
    max-width: 600px;
    box-shadow: 0 8px 40px rgba(11,30,61,0.08);
    animation: fadeUp 0.5s ease both;
  }

  @keyframes fadeUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
  }

  .card-header {
    display: flex; align-items: flex-start; gap: 16px; margin-bottom: 28px;
  }

  .card-icon {
    width: 56px; height: 56px; flex-shrink: 0;
    background: rgba(13,124,115,0.1);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.7rem;
  }

  .card-header h2 {
    font-family: 'Instrument Serif', serif;
    font-size: 1.8rem; color: var(--navy); margin-bottom: 5px;
  }

  .card-header p { color: var(--gray); font-size: 0.88rem; line-height: 1.6; }

  /* Info banner */
  .info-banner {
    background: rgba(13,124,115,0.06);
    border: 1px solid rgba(13,124,115,0.2);
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 28px;
    font-size: 0.84rem;
    color: var(--teal);
    display: flex;
    align-items: center;
    gap: 10px;
    line-height: 1.5;
  }

  /* ── City Grid ── */
  .section-label {
    font-size: 0.78rem; font-weight: 700; color: var(--navy);
    text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;
  }

  .city-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 24px;
  }

  .city-card {
    border: 1.5px solid #e2ddd4;
    border-radius: 14px;
    padding: 14px 8px;
    text-align: center;
    cursor: pointer;
    background: var(--cream);
    transition: all 0.2s;
    user-select: none;
  }

  .city-card:hover {
    border-color: var(--teal);
    background: rgba(13,124,115,0.04);
    transform: translateY(-2px);
  }

  .city-card.selected {
    border-color: var(--teal);
    background: rgba(13,124,115,0.08);
    box-shadow: 0 0 0 3px rgba(13,124,115,0.12);
  }

  .city-card .city-emoji { font-size: 1.5rem; display: block; margin-bottom: 5px; }
  .city-card .city-name  { font-size: 0.83rem; font-weight: 600; color: var(--navy); }
  .city-card .city-patients { font-size: 0.7rem; color: var(--gray); margin-top: 2px; }
  .city-card.selected .city-name { color: var(--teal); }

  /* Divider */
  .divider {
    display: flex; align-items: center; gap: 12px; margin-bottom: 18px;
  }

  .divider::before, .divider::after {
    content: ''; flex: 1; height: 1px; background: #e2ddd4;
  }

  .divider span { color: var(--gray); font-size: 0.78rem; }

  /* Search */
  .search-wrap { position: relative; margin-bottom: 24px; }

  .search-wrap .ico {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%); font-size: 1rem; opacity: 0.4;
  }

  .search-wrap input {
    width: 100%; padding: 12px 14px 12px 44px;
    border: 1.5px solid #e2ddd4; border-radius: 12px;
    font-family: 'DM Sans', sans-serif; font-size: 0.9rem;
    color: var(--navy); background: var(--cream); outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  .search-wrap input:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 4px rgba(13,124,115,0.08);
    background: var(--white);
  }

  /* Area chips */
  .area-section { margin-bottom: 24px; display: none; }
  .area-section.show { display: block; }

  .area-chips { display: flex; flex-wrap: wrap; gap: 8px; }

  .chip {
    padding: 7px 16px;
    border: 1.5px solid #e2ddd4;
    border-radius: 20px;
    font-size: 0.82rem; font-weight: 500;
    color: var(--navy); background: var(--cream);
    cursor: pointer; transition: all 0.2s; user-select: none;
  }

  .chip:hover { border-color: var(--teal); color: var(--teal); }

  .chip.selected {
    background: var(--teal); color: var(--white); border-color: var(--teal);
  }

  /* Selected display */
  .selected-display {
    background: rgba(13,124,115,0.06);
    border: 1.5px solid rgba(13,124,115,0.2);
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 24px;
    font-size: 0.88rem;
    color: var(--teal);
    font-weight: 500;
    display: none; align-items: center; gap: 8px;
  }

  .selected-display.show { display: flex; }

  /* Button */
  .btn-primary {
    width: 100%; padding: 14px;
    background: var(--navy); color: var(--white);
    border: none; border-radius: 12px;
    font-family: 'DM Sans', sans-serif;
    font-size: 1rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
    opacity: 0.4; pointer-events: none;
  }

  .btn-primary.ready { opacity: 1; pointer-events: all; }
  .btn-primary.ready:hover { background: var(--teal); transform: translateY(-1px); }

  .skip-link {
    text-align: center; margin-top: 14px;
    font-size: 0.85rem; color: var(--gray);
  }

  .skip-link a { color: var(--gray); text-decoration: underline; cursor: pointer; }

  @media (max-width: 600px) {
    .card { padding: 28px 20px; }
    .city-grid { grid-template-columns: repeat(2, 1fr); }
    .steps { display: none; }
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
  <div class="user-greet">
    Welcome, Dr. <span><?= htmlspecialchars($_SESSION['user_name']) ?></span> 👨‍⚕️
  </div>
</div>

<!-- MAIN -->
<div class="main">

  <!-- Steps -->
  <div class="steps">
    <div class="step">
      <div class="step-circle done">✓</div>
      <span class="step-label">Account Created</span>
    </div>
    <div class="step-line done"></div>
    <div class="step">
      <div class="step-circle active">2</div>
      <span class="step-label">Clinic Location</span>
    </div>
    <div class="step-line"></div>
    <div class="step">
      <div class="step-circle inactive">3</div>
      <span class="step-label inactive">Choose Plan</span>
    </div>
    <div class="step-line"></div>
    <div class="step">
      <div class="step-circle inactive">4</div>
      <span class="step-label inactive">Go Live</span>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-icon">🏥</div>
      <div>
        <h2>Where is your clinic?</h2>
        <p>Patients search for doctors by location. Setting your clinic location helps them find you easily.</p>
      </div>
    </div>

    <div class="info-banner">
      💡 Your clinic location will be shown on your public profile. Patients near your area will see you first in search results.
    </div>

    <form method="POST" action="" id="locationForm">
      <input type="hidden" name="city" id="cityInput" value="">
      <input type="hidden" name="area" id="areaInput" value="">

      <!-- City Grid -->
      <div class="section-label">Select Your City</div>
      <div class="city-grid">
        <div class="city-card" onclick="selectCity('Mumbai', '🏙️', this)">
          <span class="city-emoji">🏙️</span>
          <div class="city-name">Mumbai</div>
          <div class="city-patients">2.1k+ Patients</div>
        </div>
        <div class="city-card" onclick="selectCity('Pune', '🌿', this)">
          <span class="city-emoji">🌿</span>
          <div class="city-name">Pune</div>
          <div class="city-patients">1.4k+ Patients</div>
        </div>
        <div class="city-card" onclick="selectCity('Delhi', '🏛️', this)">
          <span class="city-emoji">🏛️</span>
          <div class="city-name">Delhi</div>
          <div class="city-patients">3.2k+ Patients</div>
        </div>
        <div class="city-card" onclick="selectCity('Bangalore', '💻', this)">
          <span class="city-emoji">💻</span>
          <div class="city-name">Bangalore</div>
          <div class="city-patients">1.8k+ Patients</div>
        </div>
        <div class="city-card" onclick="selectCity('Chennai', '🌊', this)">
          <span class="city-emoji">🌊</span>
          <div class="city-name">Chennai</div>
          <div class="city-patients">1.1k+ Patients</div>
        </div>
        <div class="city-card" onclick="selectCity('Hyderabad', '🕌', this)">
          <span class="city-emoji">🕌</span>
          <div class="city-name">Hyderabad</div>
          <div class="city-patients">1.5k+ Patients</div>
        </div>
      </div>

      <!-- OR type city -->
      <div class="divider"><span>OR type your city</span></div>

      <div class="search-wrap">
        <span class="ico">🔍</span>
        <input type="text" id="citySearch" placeholder="e.g. Nagpur, Nashik, Kolkata..."
               oninput="handleSearch(this.value)">
      </div>

      <!-- Area chips -->
      <div class="area-section" id="areaSection">
        <div class="section-label" style="margin-bottom:10px">Select Area / Locality</div>
        <div class="area-chips" id="areaChips"></div>
      </div>

      <!-- Selected display -->
      <div class="selected-display" id="selectedDisplay">
        <span>📍</span>
        <span id="selectedText"></span>
        <span style="margin-left:auto;font-size:0.78rem;opacity:0.7">Your clinic location</span>
      </div>

      <button type="submit" class="btn-primary" id="submitBtn">
        Set Clinic Location & Continue →
      </button>
    </form>

    <div class="skip-link">
      <a onclick="skipLocation()">Skip for now, set location later from dashboard</a>
    </div>
  </div>
</div>

<script>
  const cityAreas = {
    'Mumbai':    ['Andheri', 'Bandra', 'Dadar', 'Borivali', 'Thane', 'Navi Mumbai', 'Mulund', 'Kurla'],
    'Pune':      ['Kothrud', 'Hadapsar', 'Shivajinagar', 'Wakad', 'Hinjewadi', 'Baner', 'Aundh', 'Viman Nagar'],
    'Delhi':     ['Connaught Place', 'Dwarka', 'Rohini', 'Lajpat Nagar', 'Saket', 'Noida', 'Gurgaon', 'Vasant Kunj'],
    'Bangalore': ['Koramangala', 'Indiranagar', 'Whitefield', 'HSR Layout', 'JP Nagar', 'Marathahalli', 'Jayanagar', 'Malleshwaram'],
    'Chennai':   ['T. Nagar', 'Anna Nagar', 'Adyar', 'Velachery', 'Porur', 'Tambaram', 'Nungambakkam', 'Mylapore'],
    'Hyderabad': ['Banjara Hills', 'Jubilee Hills', 'Hitech City', 'Kondapur', 'Secunderabad', 'Uppal', 'Madhapur', 'Kukatpally'],
  };

  let selectedCity = '';
  let selectedArea = '';

  function selectCity(city, emoji, el) {
    document.querySelectorAll('.city-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('citySearch').value = '';
    selectedCity = city;
    selectedArea = '';
    document.getElementById('cityInput').value = city;
    document.getElementById('areaInput').value = '';
    showAreas(city);
    updateDisplay();
  }

  function handleSearch(val) {
    document.querySelectorAll('.city-card').forEach(c => c.classList.remove('selected'));
    if (val.trim().length >= 2) {
      selectedCity = val.trim();
      document.getElementById('cityInput').value = selectedCity;
      selectedArea = '';
      document.getElementById('areaInput').value = '';
      const match = Object.keys(cityAreas).find(c => c.toLowerCase() === val.toLowerCase());
      if (match) {
        selectedCity = match;
        document.getElementById('cityInput').value = match;
        showAreas(match);
      } else {
        document.getElementById('areaSection').classList.remove('show');
      }
      updateDisplay();
    } else {
      selectedCity = '';
      document.getElementById('cityInput').value = '';
      document.getElementById('areaSection').classList.remove('show');
      updateDisplay();
    }
  }

  function showAreas(city) {
    const areas = cityAreas[city] || [];
    const section = document.getElementById('areaSection');
    const chips   = document.getElementById('areaChips');
    if (!areas.length) { section.classList.remove('show'); return; }
    chips.innerHTML = areas.map(a =>
      `<div class="chip" onclick="selectArea('${a}', this)">${a}</div>`
    ).join('');
    section.classList.add('show');
  }

  function selectArea(area, el) {
    document.querySelectorAll('.chip').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selectedArea = area;
    document.getElementById('areaInput').value = area;
    updateDisplay();
  }

  function updateDisplay() {
    const display = document.getElementById('selectedDisplay');
    const text    = document.getElementById('selectedText');
    const btn     = document.getElementById('submitBtn');
    if (selectedCity) {
      text.textContent = selectedArea ? `${selectedArea}, ${selectedCity}` : selectedCity;
      display.classList.add('show');
      btn.classList.add('ready');
    } else {
      display.classList.remove('show');
      btn.classList.remove('ready');
    }
  }

  function skipLocation() {
    document.getElementById('cityInput').value = 'All';
    document.getElementById('locationForm').submit();
  }
</script>
</body>
</html>
