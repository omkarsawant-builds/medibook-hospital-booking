<?php
require_once '../includes/db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = clean($conn, $_POST['name']);
    $email    = clean($conn, $_POST['email']);
    $phone    = clean($conn, $_POST['phone']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];
    $role     = clean($conn, $_POST['role']);

    // Doctor-specific fields
    $specialization = isset($_POST['specialization']) ? clean($conn, $_POST['specialization']) : '';
    $experience     = isset($_POST['experience'])     ? (int)$_POST['experience'] : 0;
    $fees           = isset($_POST['fees'])           ? (float)$_POST['fees']     : 0;

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (!in_array($role, ['patient', 'doctor'])) {
        $error = "Invalid role selected.";
    } else {
        // Check if email exists
        $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
        if (mysqli_num_rows($check) > 0) {
            $error = "This email is already registered. Please login.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users
            $sql = "INSERT INTO users (name, email, phone, password, role, created_at)
                    VALUES ('$name', '$email', '$phone', '$hashed', '$role', NOW())";

            if (mysqli_query($conn, $sql)) {
                $userId = mysqli_insert_id($conn);

                // If doctor, insert into doctors table
                if ($role === 'doctor') {
                    $docSql = "INSERT INTO doctors (user_id, specialization, experience, fees, bio)
                               VALUES ('$userId', '$specialization', '$experience', '$fees', '')";
                    mysqli_query($conn, $docSql);
                }

                $success = "Account created successfully! <a href='login.php'>Login now →</a>";
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Register — MediBook</title>
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
  }

  body {
    min-height: 100vh;
    display: flex;
    font-family: 'DM Sans', sans-serif;
    background: var(--cream);
  }

  /* ── Left panel ── */
  .left {
    width: 42%;
    background: var(--navy);
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 48px 52px;
    overflow: hidden;
  }

  .left::before {
    content: '';
    position: absolute;
    width: 500px; height: 500px; border-radius: 50%;
    background: radial-gradient(circle, rgba(13,124,115,0.4) 0%, transparent 70%);
    top: -120px; left: -120px;
  }

  .left::after {
    content: '';
    position: absolute;
    width: 350px; height: 350px; border-radius: 50%;
    background: radial-gradient(circle, rgba(13,184,170,0.2) 0%, transparent 70%);
    bottom: -80px; right: -80px;
  }

  .brand {
    display: flex; align-items: center; gap: 10px; z-index: 1;
  }

  .brand-icon {
    width: 40px; height: 40px; background: var(--teal);
    border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px;
  }

  .brand-name {
    font-family: 'Instrument Serif', serif; font-size: 1.6rem;
    color: var(--white); letter-spacing: -0.5px;
  }

  .left-content { z-index: 1; }

  .left-content h1 {
    font-family: 'Instrument Serif', serif;
    font-size: 2.6rem; color: var(--white); line-height: 1.2; margin-bottom: 20px;
  }

  .left-content h1 em { color: var(--teal2); font-style: italic; }

  .perks { display: flex; flex-direction: column; gap: 16px; z-index: 1; }

  .perk {
    display: flex; align-items: flex-start; gap: 14px;
    background: rgba(255,255,255,0.05); border-radius: 12px; padding: 14px 16px;
  }

  .perk-icon {
    width: 36px; height: 36px; background: rgba(13,124,115,0.3);
    border-radius: 8px; display: flex; align-items: center;
    justify-content: center; font-size: 1rem; flex-shrink: 0;
  }

  .perk h4 { font-size: 0.88rem; font-weight: 600; color: var(--white); margin-bottom: 2px; }
  .perk p  { font-size: 0.78rem; color: rgba(255,255,255,0.45); line-height: 1.5; }

  /* ── Right panel ── */
  .right {
    flex: 1; overflow-y: auto;
    display: flex; align-items: flex-start; justify-content: center;
    padding: 48px 56px;
  }

  .form-box {
    width: 100%; max-width: 460px;
    animation: fadeUp 0.5s ease both;
  }

  @keyframes fadeUp {
    from { opacity:0; transform: translateY(24px); }
    to   { opacity:1; transform: translateY(0); }
  }

  .form-box h2 {
    font-family: 'Instrument Serif', serif;
    font-size: 2rem; color: var(--navy); margin-bottom: 6px;
  }

  .form-box > p {
    color: var(--gray); font-size: 0.9rem; margin-bottom: 30px;
  }
  .form-box > p a { color: var(--teal); text-decoration: none; font-weight: 500; }

  /* Alerts */
  .alert-error {
    background: #fef0f0; border: 1px solid #f8c8c8; color: var(--red);
    padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 22px;
  }

  .alert-success {
    background: #edfaf4; border: 1px solid #a8e6ce; color: var(--green);
    padding: 12px 16px; border-radius: 10px; font-size: 0.875rem; margin-bottom: 22px;
  }

  .alert-success a { color: var(--green); font-weight: 600; }

  /* Role selector */
  .role-select {
    display: flex; gap: 10px; margin-bottom: 28px;
  }

  .role-btn {
    flex: 1; padding: 12px 8px; border: 1.5px solid #dde2e8; border-radius: 12px;
    text-align: center; cursor: pointer; background: var(--white);
    transition: all 0.2s; user-select: none;
  }

  .role-btn.active {
    border-color: var(--teal); background: rgba(13,124,115,0.06); color: var(--teal);
  }

  .role-btn span { display: block; font-size: 1.5rem; margin-bottom: 4px; }
  .role-btn p { font-size: 0.82rem; font-weight: 600; color: var(--navy); }
  .role-btn.active p { color: var(--teal); }

  /* Form grid */
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .full { grid-column: 1 / -1; }

  .form-group { margin-bottom: 0; }

  label {
    display: block; font-size: 0.8rem; font-weight: 600; color: var(--navy);
    letter-spacing: 0.4px; text-transform: uppercase; margin-bottom: 7px;
  }

  .input-wrap { position: relative; }

  .input-wrap .icon {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
    font-size: 0.9rem; opacity: 0.4;
  }

  input[type="text"], input[type="email"],
  input[type="password"], input[type="tel"],
  input[type="number"], select {
    width: 100%; padding: 12px 12px 12px 40px;
    border: 1.5px solid #dde2e8; border-radius: 11px;
    font-family: 'DM Sans', sans-serif; font-size: 0.9rem;
    color: var(--navy); background: var(--white); outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  select { padding-left: 40px; appearance: none; cursor: pointer; }

  input:focus, select:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 4px rgba(13,124,115,0.08);
  }

  .toggle-pass {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    cursor: pointer; font-size: 0.9rem; opacity: 0.4;
    user-select: none; transition: opacity 0.2s;
  }
  .toggle-pass:hover { opacity: 0.8; }

  /* Doctor fields */
  .doctor-fields {
    display: none; grid-column: 1 / -1;
    display: none;
  }

  .doctor-fields.show { display: contents; }

  .section-title {
    grid-column: 1 / -1;
    font-size: 0.78rem; font-weight: 600; color: var(--teal);
    text-transform: uppercase; letter-spacing: 1px;
    padding: 6px 0 2px; border-bottom: 1px solid rgba(13,124,115,0.15);
    margin-bottom: 4px;
  }

  /* Submit */
  .btn-primary {
    width: 100%; padding: 14px; margin-top: 24px;
    background: var(--navy); color: var(--white); border: none;
    border-radius: 12px; font-family: 'DM Sans', sans-serif;
    font-size: 1rem; font-weight: 600; cursor: pointer;
    transition: background 0.2s, transform 0.15s;
  }

  .btn-primary:hover { background: var(--teal); transform: translateY(-1px); }
  .btn-primary:active { transform: translateY(0); }

  .terms {
    text-align: center; font-size: 0.78rem; color: var(--gray);
    margin-top: 14px; line-height: 1.6;
  }

  .terms a { color: var(--teal); text-decoration: none; }

  @media (max-width: 768px) {
    .left { display: none; }
    .right { padding: 28px 20px; }
    .form-grid { grid-template-columns: 1fr; }
    .full { grid-column: auto; }
  }
</style>
</head>
<body>

<!-- LEFT -->
<div class="left">
  <div class="brand">
    <div class="brand-icon">🏥</div>
    <span class="brand-name">MediBook</span>
  </div>

  <div class="left-content">
    <h1>Join <em>thousands</em><br>of patients &<br>doctors today</h1>
  </div>

  <div class="perks">
    <div class="perk">
      <div class="perk-icon">🔍</div>
      <div>
        <h4>Find Specialists Easily</h4>
        <p>Browse verified doctors by specialization, ratings, and availability.</p>
      </div>
    </div>
    <div class="perk">
      <div class="perk-icon">📅</div>
      <div>
        <h4>Instant Booking</h4>
        <p>Book same-day appointments with real-time slot availability.</p>
      </div>
    </div>
    <div class="perk">
      <div class="perk-icon">💼</div>
      <div>
        <h4>Grow Your Practice</h4>
        <p>Doctors get listed, manage slots & access patient analytics.</p>
      </div>
    </div>
  </div>
</div>

<!-- RIGHT -->
<div class="right">
  <div class="form-box">
    <h2>Create Account</h2>
    <p>Already registered? <a href="login.php">Sign in here</a></p>

    <?php if ($error):   ?><div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert-success">✅ <?= $success ?></div><?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="role" id="roleInput" value="patient">

      <!-- Role buttons -->
      <div class="role-select">
        <div class="role-btn active" onclick="setRole('patient', this)">
          <span>🧑‍⚕️</span><p>Patient</p>
        </div>
        <div class="role-btn" onclick="setRole('doctor', this)">
          <span>👨‍⚕️</span><p>Doctor</p>
        </div>
      </div>

      <!-- Fields grid -->
      <div class="form-grid">

        <!-- Full Name -->
        <div class="form-group full">
          <label>Full Name</label>
          <div class="input-wrap">
            <span class="icon">👤</span>
            <input type="text" name="name" placeholder="Dr. / Mr. / Ms. Your Name"
                   value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
          </div>
        </div>

        <!-- Email -->
        <div class="form-group full">
          <label>Email Address</label>
          <div class="input-wrap">
            <span class="icon">✉️</span>
            <input type="email" name="email" placeholder="you@example.com"
                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
          </div>
        </div>

        <!-- Phone -->
        <div class="form-group">
          <label>Phone Number</label>
          <div class="input-wrap">
            <span class="icon">📱</span>
            <input type="tel" name="phone" placeholder="+91 98765 43210"
                   value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" required>
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label>Password</label>
          <div class="input-wrap">
            <span class="icon">🔒</span>
            <input type="password" name="password" id="passInput" placeholder="Min. 6 characters" required>
            <span class="toggle-pass" onclick="togglePass('passInput')">👁️</span>
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="form-group full">
          <label>Confirm Password</label>
          <div class="input-wrap">
            <span class="icon">🔒</span>
            <input type="password" name="confirm_password" id="confirmInput" placeholder="Re-enter password" required>
            <span class="toggle-pass" onclick="togglePass('confirmInput')">👁️</span>
          </div>
        </div>

        <!-- Doctor-only fields -->
        <div id="doctorFields" class="doctor-fields">
          <div class="section-title">Doctor Details</div>

          <div class="form-group full">
            <label>Specialization</label>
            <div class="input-wrap">
              <span class="icon">🩺</span>
              <select name="specialization">
                <option value="">-- Select Specialization --</option>
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
          </div>

          <div class="form-group">
            <label>Experience (Years)</label>
            <div class="input-wrap">
              <span class="icon">📋</span>
              <input type="number" name="experience" placeholder="e.g. 5" min="0" max="60">
            </div>
          </div>

          <div class="form-group">
            <label>Consultation Fee (₹)</label>
            <div class="input-wrap">
              <span class="icon">💰</span>
              <input type="number" name="fees" placeholder="e.g. 500" min="0">
            </div>
          </div>
        </div>

      </div><!-- /form-grid -->

      <button type="submit" class="btn-primary">Create Account →</button>

      <p class="terms">
        By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
      </p>
    </form>
  </div>
</div>

<script>
  function setRole(role, el) {
    document.getElementById('roleInput').value = role;
    document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');

    const df = document.getElementById('doctorFields');
    df.classList.toggle('show', role === 'doctor');
  }

  function togglePass(id) {
    const inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
  }
</script>
</body>
</html>
