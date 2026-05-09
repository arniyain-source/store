<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Create your DesiVastra account to enjoy exclusive deals and faster checkout.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Register - DesiVastra</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body>
    <div class="app-container">
        <div class="auth-container">
            <div class="auth-left-panel bg-img" style="background-image: url('https://images.unsplash.com/photo-1517292987719-0369a794ec0f?auto=format&fit=crop&w=800&q=80');">
                <div class="auth-left-overlay">
                    <a href="index.php" class="logo">Desi<span>Vastra</span></a>
                    <div class="auth-left-content">
                        <h2>Join the Elite</h2>
                        <p>Create an account to personalize your shopping experience and get access to members-only drops.</p>
                    </div>
                </div>
            </div>
            <div class="auth-right-panel">
                <a href="index.php" class="close-btn" aria-label="Close"><i class="fa-solid fa-xmark"></i></a>
                <div class="auth-form-wrapper">
                    <h2>Create Account</h2>
                    <p class="auth-subtitle">Join the Inner Circle. Curated for the discerning.</p>

                    <div id="auth-error-global" class="auth-error-global"></div>

                    <form id="register-form" novalidate onsubmit="event.preventDefault(); handleRegister();">
                        <div class="form-group">
                            <label class="auth-label" for="reg-name">Full Name</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fa-regular fa-user"></i></span>
                                <input type="text" id="reg-name" placeholder="Your full name" autocomplete="name" required>
                            </div>
                            <div class="auth-error" id="err-reg-name"></div>
                        </div>

                        <div class="form-group">
                            <label class="auth-label" for="reg-email">Email Address</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fa-regular fa-envelope"></i></span>
                                <input type="email" id="reg-email" placeholder="you@example.com" autocomplete="email" required>
                            </div>
                            <div class="auth-error" id="err-reg-email"></div>
                        </div>

                        <div class="form-group">
                            <label class="auth-label" for="reg-phone">Mobile Number</label>
                            <div class="input-group">
                                <span class="prefix">+91</span>
                                <input type="tel" id="reg-phone" placeholder="10-digit mobile" autocomplete="tel" maxlength="10" required>
                            </div>
                            <div class="auth-error" id="err-reg-phone"></div>
                        </div>

                        <div class="form-group">
                            <label class="auth-label" for="reg-password">Password</label>
                            <div class="input-group">
                                <span class="prefix"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" id="reg-password" placeholder="Min 6 characters" autocomplete="new-password" required>
                                <button type="button" class="eye-btn" aria-label="Show password" onclick="togglePassword('reg-password', this)"><i class="fa-regular fa-eye"></i></button>
                            </div>
                            <div class="auth-error" id="err-reg-password"></div>
                        </div>

                        <p class="terms">By continuing, I agree to the <a href="#" class="gold-link">Terms of Use</a> & <a href="#" class="gold-link">Privacy Policy</a>.</p>

                        <button type="submit" class="gold-btn" id="register-btn">Register</button>
                    </form>

                    <div class="auth-divider"><span>Already have an account?</span></div>
                    <a href="login.php" class="outline-btn full-width auth-link-btn">Sign In Instead</a>
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
        }

        function setError(id, msg) {
            const el = document.getElementById(id);
            if(id === 'auth-error-global') {
                el.textContent = msg || '';
                el.style.display = msg ? 'block' : 'none';
                return;
            }
            const field = document.getElementById(id.replace('err-',''));
            if(el) {
                el.textContent = msg || '';
                el.style.display = msg ? 'block' : 'none';
            }
            if(field) {
                if(msg) field.parentElement.classList.add('error');
                else field.parentElement.classList.remove('error');
            }
        }

        function clearErrors() {
            ['err-reg-name', 'err-reg-email', 'err-reg-phone', 'err-reg-password', 'auth-error-global'].forEach(id => setError(id, ''));
        }

        function isValidEmail(email) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email); }
        function isValidPhone(phone) { return /^\d{10}$/.test(phone); }

        async function handleRegister() {
            clearErrors();
            const name = document.getElementById('reg-name').value.trim();
            const email = document.getElementById('reg-email').value.trim();
            const phone = document.getElementById('reg-phone').value.trim();
            const password = document.getElementById('reg-password').value;
            const registerBtn = document.getElementById('register-btn');

            let valid = true;
            if (!name) {
                setError('err-reg-name', 'Full name is required.');
                valid = false;
            }
            if (!email || !isValidEmail(email)) {
                setError('err-reg-email', 'Please enter a valid email.');
                valid = false;
            }
            if (!phone || !isValidPhone(phone)) {
                setError('err-reg-phone', 'Please enter a valid 10-digit phone number.');
                valid = false;
            }
            if (!password || password.length < 6) {
                setError('err-reg-password', 'Password must be at least 6 characters.');
                valid = false;
            }
            if (!valid) return;

            registerBtn.disabled = true;
            registerBtn.innerHTML = 'Creating Account...';

            try {
                const response = await fetch('/api/user/login.php?action=register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, email, phone, password })
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    showToast('Account created! Redirecting to login...');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 1000);
                } else {
                     setError('auth-error-global', result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                console.error('Registration error:', error);
                setError('auth-error-global', 'Could not connect to the server. Please try again.');
            } finally {
                registerBtn.disabled = false;
                registerBtn.innerHTML = 'Register';
            }
        }
    </script>
</body>
</html>