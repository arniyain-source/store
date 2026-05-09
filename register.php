<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Create your DesiVastra account to enjoy exclusive deals and faster checkout.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Register - ARNiya Smart Hub</title>
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
            <a href="login.php" class="close-btn" aria-label="Close"><i class="fa-solid fa-xmark"></i></a>
            <div class="login-header-img bg-img" style="background-image: url('https://images.unsplash.com/photo-1517292987719-0369a794ec0f?auto=format&fit=crop&w=800&q=80')">
            </div>
            <div class="login-form">
                <h2>Create <span style="font-weight: 300;">Account</span></h2>
                <p class="login-subtitle">Join the Inner Circle. Curated for the discerning.</p>

                <form id="register-form" novalidate onsubmit="event.preventDefault(); handleRegister();">

                    <label class="auth-label" for="reg-name">Full Name</label>
                    <div class="input-group">
                        <span class="prefix"><i class="fa-regular fa-user"></i></span>
                        <input type="text" id="reg-name" placeholder="Your full name" autocomplete="name">
                    </div>
                    <div class="auth-error" id="err-reg-name"></div>

                    <label class="auth-label" for="reg-mobile">Mobile Number</label>
                    <div class="input-group">
                        <span class="prefix">+91</span>
                        <input type="tel" id="reg-mobile" placeholder="10-digit mobile" autocomplete="tel" maxlength="10">
                    </div>
                    <div class="auth-error" id="err-reg-mobile"></div>

                    <label class="auth-label" for="reg-email">Email</label>
                    <div class="input-group">
                        <span class="prefix"><i class="fa-regular fa-envelope"></i></span>
                        <input type="email" id="reg-email" placeholder="you@example.com" autocomplete="email">
                    </div>
                    <div class="auth-error" id="err-reg-email"></div>

                    <label class="auth-label" for="reg-password">Password</label>
                    <div class="input-group">
                        <span class="prefix"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" id="reg-password" placeholder="Min 6 characters" autocomplete="new-password">
                        <button type="button" class="eye-btn" aria-label="Show password" onclick="togglePassword('reg-password', this)"><i class="fa-regular fa-eye"></i></button>
                    </div>
                    <div class="auth-error" id="err-reg-password"></div>

                    <label class="auth-label" for="reg-confirm">Confirm Password</label>
                    <div class="input-group">
                        <span class="prefix"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" id="reg-confirm" placeholder="Re-enter password" autocomplete="new-password">
                        <button type="button" class="eye-btn" aria-label="Show password" onclick="togglePassword('reg-confirm', this)"><i class="fa-regular fa-eye"></i></button>
                    </div>
                    <div class="auth-error" id="err-reg-confirm"></div>

                    <label class="auth-label" for="reg-usertype">User Type</label>
                    <div class="input-group">
                        <span class="prefix"><i class="fa-solid fa-user-tag"></i></span>
                        <select id="reg-usertype" class="auth-select">
                            <option value="">Select user type</option>
                            <option value="wholesale">Wholesale User</option>
                            <option value="retailer">Retailer User</option>
                            <option value="reseller">Reseller User</option>
                            <option value="customer">Customer User</option>
                        </select>
                    </div>
                    <div class="auth-error" id="err-reg-usertype"></div>

                    <div class="auth-grid-2">
                        <div>
                            <label class="auth-label" for="reg-city">City</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fa-solid fa-city"></i></span>
                                <input type="text" id="reg-city" placeholder="City" autocomplete="address-level2">
                            </div>
                            <div class="auth-error" id="err-reg-city"></div>
                        </div>
                        <div>
                            <label class="auth-label" for="reg-state">State</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fa-solid fa-map-location-dot"></i></span>
                                <input type="text" id="reg-state" placeholder="State" autocomplete="address-level1">
                            </div>
                            <div class="auth-error" id="err-reg-state"></div>
                        </div>
                    </div>

                    <label class="auth-label" for="reg-business">Business Name <span class="auth-optional">(optional)</span></label>
                    <div class="input-group">
                        <span class="prefix"><i class="fa-solid fa-briefcase"></i></span>
                        <input type="text" id="reg-business" placeholder="Business / brand name">
                    </div>

                    <p class="terms">By continuing, I agree to the <a href="#" class="gold-link">Terms of Use</a> & <a href="#" class="gold-link">Privacy Policy</a></p>

                    <button type="submit" class="gold-btn">Register</button>
                </form>

                <div class="auth-divider"><span>Already have an account?</span></div>
                <a href="login.php" class="outline-btn full-width auth-link-btn">Sign In Instead</a>
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
            ['err-reg-name','err-reg-mobile','err-reg-email','err-reg-password','err-reg-confirm','err-reg-usertype','err-reg-city','err-reg-state']
                .forEach((id) => setError(id, ''));
        }

        function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
        function isValidMobile(v) { return /^\d{10}$/.test(v); }

        function handleRegister() {
            clearErrors();
            const name = document.getElementById('reg-name').value.trim();
            const mobile = document.getElementById('reg-mobile').value.replace(/\D/g, '');
            const email = document.getElementById('reg-email').value.trim();
            const pwd = document.getElementById('reg-password').value;
            const confirm = document.getElementById('reg-confirm').value;
            const userType = document.getElementById('reg-usertype').value;
            const city = document.getElementById('reg-city').value.trim();
            const state = document.getElementById('reg-state').value.trim();
            const business = document.getElementById('reg-business').value.trim();
            let valid = true;

            if (!name) { setError('err-reg-name', 'Full name is required'); valid = false; }
            if (!mobile) { setError('err-reg-mobile', 'Mobile number is required'); valid = false; }
            else if (!isValidMobile(mobile)) { setError('err-reg-mobile', 'Enter a valid 10-digit mobile number'); valid = false; }
            if (!email) { setError('err-reg-email', 'Email is required'); valid = false; }
            else if (!isValidEmail(email)) { setError('err-reg-email', 'Enter a valid email address'); valid = false; }
            if (!pwd) { setError('err-reg-password', 'Password is required'); valid = false; }
            else if (pwd.length < 6) { setError('err-reg-password', 'Password must be at least 6 characters'); valid = false; }
            if (!confirm) { setError('err-reg-confirm', 'Please confirm your password'); valid = false; }
            else if (pwd && confirm !== pwd) { setError('err-reg-confirm', 'Passwords do not match'); valid = false; }
            if (!userType) { setError('err-reg-usertype', 'Please select user type'); valid = false; }
            if (!city) { setError('err-reg-city', 'City is required'); valid = false; }
            if (!state) { setError('err-reg-state', 'State is required'); valid = false; }

            if (!valid) return;

            localStorage.setItem('isLoggedIn', 'true');
            localStorage.setItem('arniyaPhone', mobile);
            localStorage.setItem('arniyaEmail', email);
            localStorage.setItem('arniyaUserName', name);
            localStorage.setItem('arniyaUserType', userType);
            localStorage.setItem('arniyaCity', city);
            localStorage.setItem('arniyaState', state);
            if (business) localStorage.setItem('arniyaBusiness', business);

            showToast('Account created successfully!');
            setTimeout(() => { window.location.href = 'dashboard.php'; }, 800);
        }
    </script>
</body>
</html>
