<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Sign in to your DesiVastra account to track orders and manage your wishlist.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - ARNiya Smart Hub</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body>
    <div class="app-container" style="background: var(--bg-dark);">

        <div class="login-container">
            <a href="index.php" class="close-btn" aria-label="Close"><i class="fa-solid fa-xmark"></i></a>
            <div class="login-header-img bg-img" style="background-image: url('https://images.unsplash.com/photo-1617305988165-27f940898eb2?auto=format&fit=crop&w=800&q=80')">
            </div>
            <div class="login-form">
                <h2>Welcome <span style="font-weight: 300;">Back</span></h2>
                <p class="login-subtitle">Enter your details to access the Inner Circle.</p>

                <form id="login-form" novalidate onsubmit="event.preventDefault(); handleLogin();">

                    <!-- Mobile / Email -->
                    <label class="auth-label" for="login-id">Mobile Number or Email</label>
                    <div class="input-group">
                        <span class="prefix"><i class="fa-solid fa-user"></i></span>
                        <input type="text" id="login-id" name="loginId" autocomplete="username" placeholder="Mobile number or email">
                    </div>
                    <div class="auth-error" id="err-login-id"></div>

                    <!-- Password -->
                    <label class="auth-label" for="login-password">Password</label>
                    <div class="input-group">
                        <span class="prefix"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" id="login-password" name="password" autocomplete="current-password" placeholder="Enter password">
                        <button type="button" class="eye-btn" aria-label="Show password" onclick="togglePassword('login-password', this)"><i class="fa-regular fa-eye"></i></button>
                    </div>
                    <div class="auth-error" id="err-login-password"></div>

                    <!-- Remember Me + Forgot Password -->
                    <div class="auth-row">
                        <label class="auth-checkbox">
                            <input type="checkbox" id="remember-me">
                            <span>Remember Me</span>
                        </label>
                        <a href="forgot.php" class="gold-link auth-forgot">Forgot Password?</a>
                    </div>

                    <p class="terms">By continuing, I agree to the <a href="#" class="gold-link">Terms of Use</a> & <a href="#" class="gold-link">Privacy Policy</a></p>

                    <button type="submit" class="gold-btn">Login to Arniya</button>

                    <button type="button" class="auth-secondary-btn" onclick="loginWithOtp()">
                        <i class="fa-solid fa-mobile-screen"></i> Login with OTP instead
                    </button>
                </form>

                <div class="auth-divider"><span>New to Arniya?</span></div>

                <a href="register.php" class="outline-btn full-width auth-link-btn">Create New Account</a>

                <div class="trouble-login">
                    Experience issues? <a href="#" class="gold-link" onclick="window.openSupportPopup && openSupportPopup(); return false;">Contact Concierge</a>
                </div>
            </div>
        </div>

    </div>

    <script src="assets/js/global.js"></script>
    <script>
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            const isPwd = input.type === 'password';
            input.type = isPwd ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', !isPwd);
                icon.classList.toggle('fa-eye-slash', isPwd);
            }
            btn.setAttribute('aria-label', isPwd ? 'Hide password' : 'Show password');
        }

        function setError(id, msg) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = msg || '';
            el.style.display = msg ? 'block' : 'none';
        }

        function clearErrors() {
            ['err-login-id', 'err-login-password'].forEach((id) => setError(id, ''));
        }

        function isValidEmail(v) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
        }
        function isValidMobile(v) {
            return /^\d{10}$/.test(v.replace(/\D/g, ''));
        }

        function handleLogin() {
            clearErrors();
            const id = (document.getElementById('login-id').value || '').trim();
            const pwd = document.getElementById('login-password').value || '';
            let valid = true;

            if (!id) {
                setError('err-login-id', 'Mobile number or email is required');
                valid = false;
            } else if (!isValidEmail(id) && !isValidMobile(id)) {
                setError('err-login-id', 'Enter a valid email or 10-digit mobile number');
                valid = false;
            }

            if (!pwd) {
                setError('err-login-password', 'Password is required');
                valid = false;
            } else if (pwd.length < 6) {
                setError('err-login-password', 'Password must be at least 6 characters');
                valid = false;
            }

            if (!valid) return;

            const remember = document.getElementById('remember-me').checked;
            localStorage.setItem('isLoggedIn', 'true');
            if (isValidMobile(id)) localStorage.setItem('arniyaPhone', id.replace(/\D/g, ''));
            else localStorage.setItem('arniyaEmail', id);
            if (remember) localStorage.setItem('arniyaRemember', '1');
            else localStorage.removeItem('arniyaRemember');

            if (!localStorage.getItem('arniyaUserType')) {
                localStorage.setItem('arniyaUserType', 'customer');
            }
            if (!localStorage.getItem('arniyaUserName')) {
                localStorage.setItem('arniyaUserName', 'Arniya Member');
            }

            showToast('Logged in successfully!');
            setTimeout(() => { window.location.href = 'dashboard.php'; }, 700);
        }

        function loginWithOtp() {
            clearErrors();
            const id = (document.getElementById('login-id').value || '').trim();
            if (!isValidMobile(id)) {
                setError('err-login-id', 'Enter a 10-digit mobile number to receive OTP');
                return;
            }
            showToast('Sending secure OTP...');
            setTimeout(() => {
                localStorage.setItem('isLoggedIn', 'true');
                localStorage.setItem('arniyaPhone', id.replace(/\D/g, ''));
                if (!localStorage.getItem('arniyaUserType')) localStorage.setItem('arniyaUserType', 'customer');
                if (!localStorage.getItem('arniyaUserName')) localStorage.setItem('arniyaUserName', 'Arniya Member');
                showToast('Logged in successfully!');
                setTimeout(() => { window.location.href = 'dashboard.php'; }, 700);
            }, 700);
        }
    </script>
</body>
</html>
