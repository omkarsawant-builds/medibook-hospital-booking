<?php
require_once '../includes/db.php';
requireLogin();

// Only for patients
if ($_SESSION['user_role'] !== 'patient') {
    redirectByRole($_SESSION['user_role']);
}

// Save location to session and redirect to dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['city'])) {
    $_SESSION['patient_city'] = clean($conn, $_POST['city']);
    $_SESSION['patient_area'] = isset($_POST['area']) ? clean($conn, $_POST['area']) : '';
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Select Location — MediBook</title>
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
    --light: #f0ebe0;
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

  .brand {
    display: flex; align-items: center; gap: 10px;
  }

  .brand-icon {
    width: 34px; height: 34px; background: var(--teal);
    border-radius: 8px; display: flex; align-items: center;
    justify-content: center; font-size: 17px;
  }

  .brand-name {
    font-family: 'Instrument Serif', serif;
    font-size: 1.4rem; color: var(--white);
  }

  .user-greet {
    color: rgba(255,255,255,0.6);
    font-size: 0.88rem;
  }

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

  .card {
    background: var(--white);
    border-radius: 24px;
    padding: 48px 52px;
    width: 100%;
    max-width: 580px;
    box-shadow: 0 8px 40px rgba(11,30,61,0.08);
    animation: fadeUp 0.5s ease both;
  }

  @keyframes fadeUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
  }

  .card-icon {
    width: 64px; height: 64px;
    background: rgba(13,124,115,0.1);
    border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem;
    margin-bottom: 20px;
  }

  .card h2 {
    font-family: 'Instrument Serif', serif;
    font-size: 1.9rem; color: var(--navy);
    margin-bottom: 8px;
  }

  .card p {
    color: var(--gray); font-size: 0.92rem;
    line-height: 1.6; margin-bottom: 32px;
  }

  /* ── City Grid ── */
  .city-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 28px;
  }

  .city-card {
    border: 1.5px solid #e2ddd4;
    border-radius: 14px;
    padding: 16px 10px;
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

  .city-card .city-emoji { font-size: 1.6rem; display: block; margin-bottom: 6px; }
  .city-card .city-name  { font-size: 0.85rem; font-weight: 600; color: var(--navy); }
  .city-card .city-docs  { font-size: 0.72rem; color: var(--gray); margin-top: 2px; }

  .city-card.selected .city-name { color: var(--teal); }

  /* ── OR Divider ── */
  .divider {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 20px;
  }

  .divider::before, .divider::after {
    content: ''; flex: 1; height: 1px; background: #e2ddd4;
  }

  .divider span { color: var(--gray); font-size: 0.8rem; white-space: nowrap; }

  /* ── Search box ── */
  .search-wrap {
    position: relative;
    margin-bottom: 28px;
  }

  .search-wrap .ico {
    position: absolute; left: 14px; top: 50%;
    transform: translateY(-50%); font-size: 1rem; opacity: 0.4;
  }

  .search-wrap input {
    width: 100%; padding: 13px 14px 13px 44px;
    border: 1.5px solid #e2ddd4; border-radius: 12px;
    font-family: 'DM Sans', sans-serif; font-size: 0.95rem;
    color: var(--navy); background: var(--cream); outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  .search-wrap input:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 4px rgba(13,124,115,0.08);
    background: var(--white);
  }

  /* ── Area chips ── */
  .area-section { margin-bottom: 28px; display: none; }
  .area-section.show { display: block; }

  .area-label {
    font-size: 0.8rem; font-weight: 600; color: var(--navy);
    text-transform: uppercase; letter-spacing: 0.4px;
    margin-bottom: 12px;
  }

  .area-chips {
    display: flex; flex-wrap: wrap; gap: 8px;
  }

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
    background: var(--teal); color: var(--white);
    border-color: var(--teal);
  }

  /* ── Selected display ── */
  .selected-display {
    background: rgba(13,124,115,0.06);
    border: 1.5px solid rgba(13,124,115,0.2);
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 24px;
    font-size: 0.88rem;
    color: var(--teal);
    font-weight: 500;
    display: none;
    align-items: center;
    gap: 8px;
  }

  .selected-display.show { display: flex; }

  /* ── Button ── */
  .btn-primary {
    width: 100%; padding: 14px;
    background: var(--navy); color: var(--white);
    border: none; border-radius: 12px;
    font-family: 'DM Sans', sans-serif;
    font-size: 1rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
    opacity: 0.4; pointer-events: none;
  }

  .btn-primary.ready {
    opacity: 1; pointer-events: all;
  }

  .btn-primary.ready:hover {
    background: var(--teal); transform: translateY(-1px);
  }

  .skip-link {
    text-align: center; margin-top: 16px;
    font-size: 0.85rem; color: var(--gray);
  }

  .skip-link a {
    color: var(--gray); text-decoration: underline;
    cursor: pointer;
  }

  @media (max-width: 600px) {
    .card { padding: 32px 24px; }
    .city-grid { grid-template-columns: repeat(2, 1fr); }
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
    Welcome, <span><?= htmlspecialchars($_SESSION['user_name']) ?></span> 👋
  </div>
</div>

<!-- MAIN -->
<div class="main">
  <div class="card">
    <div class="card-icon">📍</div>
    <h2>Where are you located?</h2>
    <p>Select your city to find the best doctors near you and book appointments easily.</p>

    <form method="POST" action="" id="locationForm">
      <input type="hidden" name="city" id="cityInput" value="">
      <input type="hidden" name="area" id="areaInput" value="">

      <!-- City Grid -->
      <div class="city-grid">
        <div class="city-card" onclick="selectCity('Mumbai', '🏙️', this)">
          <span class="city-emoji">🏙️</span>
          <div class="city-name">Mumbai</div>
          <div class="city-docs">120+ Doctors</div>
        </div>
        <div class="city-card" onclick="selectCity('Pune', '🌿', this)">
          <span class="city-emoji">🌿</span>
          <div class="city-name">Pune</div>
          <div class="city-docs">85+ Doctors</div>
        </div>
        <div class="city-card" onclick="selectCity('Delhi', '🏛️', this)">
          <span class="city-emoji">🏛️</span>
          <div class="city-name">Delhi</div>
          <div class="city-docs">150+ Doctors</div>
        </div>
        <div class="city-card" onclick="selectCity('Bangalore', '💻', this)">
          <span class="city-emoji">💻</span>
          <div class="city-name">Bangalore</div>
          <div class="city-docs">110+ Doctors</div>
        </div>
        <div class="city-card" onclick="selectCity('Chennai', '🌊', this)">
          <span class="city-emoji">🌊</span>
          <div class="city-name">Chennai</div>
          <div class="city-docs">75+ Doctors</div>
        </div>
        <div class="city-card" onclick="selectCity('Hyderabad', '🕌', this)">
          <span class="city-emoji">🕌</span>
          <div class="city-name">Hyderabad</div>
          <div class="city-docs">90+ Doctors</div>
        </div>
      </div>

      <!-- OR type your city -->
      <div class="divider"><span>OR type your city</span></div>

      <div class="search-wrap">
        <span class="ico">🔍</span>
        <input type="text" id="citySearch" placeholder="Search your city..."
               oninput="handleSearch(this.value)">
      </div>

      <!-- Area selection -->
      <div class="area-section" id="areaSection">
        <div class="area-label">Select Area / Locality</div>
        <div class="area-chips" id="areaChips"></div>
      </div>

      <!-- Selected display -->
      <div class="selected-display" id="selectedDisplay">
        <span>📍</span>
        <span id="selectedText"></span>
      </div>

      <button type="submit" class="btn-primary" id="submitBtn">
        Find Doctors Near Me →
      </button>
    </form>

    <div class="skip-link">
      <a onclick="skipLocation()">Skip for now, show all doctors</a>
    </div>
  </div>
</div>

<script>
  // Areas per city
  const cityAreas = {
    'Mumbai':    ['Andheri', 'Bandra', 'Dadar', 'Borivali', 'Thane', 'Navi Mumbai'],
    'Pune':      ['Kothrud', 'Hadapsar', 'Shivajinagar', 'Wakad', 'Hinjewadi', 'Baner'],
    'Delhi':     ['Connaught Place', 'Dwarka', 'Rohini', 'Lajpat Nagar', 'Saket', 'Noida'],
    'Bangalore': ['Koramangala', 'Indiranagar', 'Whitefield', 'HSR Layout', 'JP Nagar', 'Marathahalli'],
    'Chennai':   ['T. Nagar', 'Anna Nagar', 'Adyar', 'Velachery', 'Porur', 'Tambaram'],
    'Hyderabad': ['Banjara Hills', 'Jubilee Hills', 'Hitech City', 'Kondapur', 'Secunderabad', 'Uppal'],
  };

  let selectedCity = '';
  let selectedArea = '';

  function selectCity(city, emoji, el) {
    // Deselect all
    document.querySelectorAll('.city-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');

    // Clear search
    document.getElementById('citySearch').value = '';

    selectedCity = city;
    selectedArea = '';
    document.getElementById('cityInput').value = city;
    document.getElementById('areaInput').value = '';

    showAreas(city);
    updateDisplay();
  }

  function handleSearch(val) {
    // Deselect grid cards
    document.querySelectorAll('.city-card').forEach(c => c.classList.remove('selected'));

    if (val.trim().length >= 2) {
      selectedCity = val.trim();
      selectedArea = '';
      document.getElementById('cityInput').value = selectedCity;
      document.getElementById('areaInput').value = '';

      // Show areas if known city
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

    if (areas.length === 0) { section.classList.remove('show'); return; }

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
      const loc = selectedArea ? `${selectedArea}, ${selectedCity}` : selectedCity;
      text.textContent = loc;
      display.classList.add('show');
      btn.classList.add('ready');
    } else {
      display.classList.remove('show');
      btn.classList.remove('ready');
    }
  }

  function skipLocation() {
    document.getElementById('cityInput').value = 'All';
    document.getElementById('areaInput').value = '';
    document.getElementById('locationForm').submit();
  }
</script>
</body>
</html>
