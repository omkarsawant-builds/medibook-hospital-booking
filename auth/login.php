<?php
require_once '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = clean($conn, $_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $sql    = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                redirectByRole($user['role']);
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Login — MediBook</title>
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
  }

  body {
    min-height: 100vh;
    display: flex;
    font-family: 'DM Sans', sans-serif;
    background: var(--cream);
    overflow: hidden;
  }

  /* ── Left panel ── */
  .left {
    width: 48%;
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
    width: 500px; height: 500px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(13,124,115,0.4) 0%, transparent 70%);
    top: -120px; left: -120px;
  }

  .left::after {
    content: '';
    position: absolute;
    width: 350px; height: 350px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(13,184,170,0.2) 0%, transparent 70%);
    bottom: -80px; right: -80px;
  }

  .brand {
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 1;
  }

  .brand-icon {
    width: 40px; height: 40px;
    background: var(--teal);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
  }

  .brand-name {
    font-family: 'Instrument Serif', serif;
    font-size: 1.6rem;
    color: var(--white);
    letter-spacing: -0.5px;
  }

  .left-content {
    z-index: 1;
  }

  .left-content h1 {
    font-family: 'Instrument Serif', serif;
    font-size: 3rem;
    color: var(--white);
    line-height: 1.15;
    margin-bottom: 20px;
  }

  .left-content h1 em {
    color: var(--teal2);
    font-style: italic;
  }

  .left-content p {
    color: rgba(255,255,255,0.55);
    font-size: 0.97rem;
    line-height: 1.7;
    max-width: 340px;
  }

  .stats {
    display: flex;
    gap: 36px;
    z-index: 1;
  }

  .stat h3 {
    font-family: 'Instrument Serif', serif;
    font-size: 1.9rem;
    color: var(--teal2);
  }

  .stat p {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.45);
    margin-top: 2px;
  }

  /* ── Right panel ── */
  .right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 60px;
  }

  .form-box {
    width: 100%;
    max-width: 400px;
    animation: fadeUp 0.5s ease both;
  }

  @keyframes fadeUp {
    from { opacity:0; transform: translateY(24px); }
    to   { opacity:1; transform: translateY(0); }
  }

  .form-box h2 {
    font-family: 'Instrument Serif', serif;
    font-size: 2rem;
    color: var(--navy);
    margin-bottom: 6px;
  }

  .form-box > p {
    color: var(--gray);
    font-size: 0.9rem;
    margin-bottom: 36px;
  }

  .form-box > p a {
    color: var(--teal);
    text-decoration: none;
    font-weight: 500;
  }

  /* Error */
  .alert-error {
    background: #fef0f0;
    border: 1px solid #f8c8c8;
    color: var(--red);
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.875rem;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* Form group */
  .form-group {
    margin-bottom: 20px;
  }

  label {
    display: block;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--navy);
    letter-spacing: 0.4px;
    text-transform: uppercase;
    margin-bottom: 8px;
  }

  .input-wrap {
    position: relative;
  }

  .input-wrap .icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1rem;
    opacity: 0.4;
  }

  input[type="email"],
  input[type="password"],
  input[type="text"] {
    width: 100%;
    padding: 13px 14px 13px 42px;
    border: 1.5px solid #dde2e8;
    border-radius: 12px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.95rem;
    color: var(--navy);
    background: var(--white);
    transition: border-color 0.2s, box-shadow 0.2s;
    outline: none;
  }

  input:focus {
    border-color: var(--teal);
    box-shadow: 0 0 0 4px rgba(13,124,115,0.08);
  }

  .toggle-pass {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 1rem;
    opacity: 0.4;
    user-select: none;
    transition: opacity 0.2s;
  }
  .toggle-pass:hover { opacity: 0.8; }

  /* Role selector */
  .role-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 28px;
  }

  .role-tab {
    flex: 1;
    padding: 10px 6px;
    border: 1.5px solid #dde2e8;
    border-radius: 10px;
    text-align: center;
    font-size: 0.82rem;
    font-weight: 500;
    color: var(--gray);
    cursor: pointer;
    background: var(--white);
    transition: all 0.2s;
    user-select: none;
  }

  .role-tab.active {
    border-color: var(--teal);
    background: rgba(13,124,115,0.06);
    color: var(--teal);
    font-weight: 600;
  }

  .role-tab span { display: block; font-size: 1.2rem; margin-bottom: 3px; }

  input[name="role"] { display: none; }

  /* Submit button */
  .btn-primary {
    width: 100%;
    padding: 14px;
    background: var(--navy);
    color: var(--white);
    border: none;
    border-radius: 12px;
    font-family: 'DM Sans', sans-serif;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, transform 0.15s;
    margin-top: 8px;
  }

  .btn-primary:hover {
    background: var(--teal);
    transform: translateY(-1px);
  }

  .btn-primary:active { transform: translateY(0); }

  .divider {
    text-align: center;
    color: var(--gray);
    font-size: 0.82rem;
    margin: 22px 0;
    position: relative;
  }

  .divider::before, .divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 38%;
    height: 1px;
    background: #dde2e8;
  }
  .divider::before { left: 0; }
  .divider::after  { right: 0; }

  .register-link {
    text-align: center;
    font-size: 0.9rem;
    color: var(--gray);
    margin-top: 20px;
  }

  .register-link a {
    color: var(--teal);
    font-weight: 600;
    text-decoration: none;
  }

  @media (max-width: 768px) {
    .left { display: none; }
    .right { padding: 30px 24px; }
  }
</style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left">
  <div class="brand">
    <div class="brand-icon">🏥</div>
    <span class="brand-name">MediBook</span>
  </div>

  <div class="left-content">
    <h1>Your health,<br><em>our priority.</em></h1>
    <p>Connect with top doctors, book appointments in minutes, and manage your healthcare — all in one place.</p>
  </div>

  <div class="stats">
    <div class="stat">
      <h3>500+</h3>
      <p>Verified Doctors</p>
    </div>
    <div class="stat">
      <h3>20k+</h3>
      <p>Appointments</p>
    </div>
    <div class="stat">
      <h3>50+</h3>
      <p>Specializations</p>
    </div>
  </div>
</div>

<!-- RIGHT PANEL -->
<div class="right">
  <div class="form-box">
    <h2>Welcome back</h2>
    <p>Don't have an account? <a href="register.php">Sign up free</a></p>

    <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">

      <!-- Role Selector -->
      <div class="role-tabs">
        <div class="role-tab active" onclick="setRole('patient', this)">
          <span>🧑‍⚕️</span> Patient
        </div>
        <div class="role-tab" onclick="setRole('doctor', this)">
          <span>👨‍⚕️</span> Doctor
        </div>
        <div class="role-tab" onclick="setRole('admin', this)">
          <span>🛡️</span> Admin
        </div>
      </div>
      <input type="hidden" name="role" id="roleInput" value="patient">

      <!-- Email -->
      <div class="form-group">
        <label>Email Address</label>
        <div class="input-wrap">
          <span class="icon">✉️</span>
          <input type="email" name="email" placeholder="you@example.com"
                 value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
        </div>
      </div>

      <!-- Password -->
      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap">
          <span class="icon">🔒</span>
          <input type="password" name="password" id="passInput" placeholder="Enter your password" required>
          <span class="toggle-pass" onclick="togglePass()">👁️</span>
        </div>
      </div>

      <button type="submit" class="btn-primary">Sign In →</button>
    </form>

    <div class="register-link">
      New to MediBook? <a href="register.php">Create an account</a>
    </div>
  </div>
</div>

<script>
  function setRole(role, el) {
    document.getElementById('roleInput').value = role;
    document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
  }

  function togglePass() {
    const inp = document.getElementById('passInput');
    inp.type = inp.type === 'password' ? 'text' : 'password';
  }
</script>
</body>
</html>
