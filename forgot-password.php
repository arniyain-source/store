<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Reset your DesiVastra password.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Forgot Password - DesiVastra</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/global.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
</head>
<body>
    <div class="app-container">
        <div class="auth-container">
            <div class="auth-left-panel bg-img" style="background-image: url('https://images.unsplash.com/photo-1599643478524-fb66f7f2b1d6?auto=format&fit=crop&w=800&q=80');">
                 <div class="auth-left-overlay">
                    <a href="index.php" class="logo">Desi<span>Vastra</span></a>
                    <div class="auth-left-content">
                        <h2>Password Assistance</h2>
                        <p>Enter your email to receive a secure code to reset your password.</p>
                    </div>
                 </div>
            </div>
            <div class="auth-right-panel">
                <a href="index.php" class="close-btn" aria-label="Close"><i class="fa-solid fa-xmark"></i></a>
                <div class="auth-form-wrapper">
                    
                    <div id="step1-request-otp">
                        <h2>Forgot Password</h2>
                        <p class="auth-subtitle">Enter your email and we'll send you an OTP to reset your password.</p>
                        <div id="auth-error-global-step1" class="auth-error-global"></div>
                        <form id="request-otp-form" onsubmit="event.preventDefault(); handleRequestOtp();">
                            <div class="form-group">
                                <label class="auth-label" for="reset-email">Email Address</label>
                                <div class="input-group">
                                    <span class="prefix"><i class="fa-solid fa-envelope"></i></span>
                                    <input type="email" id="reset-email" autocomplete="email" placeholder="you@example.com" required>
                                </div>
                            </div>
                            <button type="submit" class="gold-btn" id="request-otp-btn">Send OTP</button>
                        </form>
                    </div>

                    <div id="step2-reset-password" style="display: none;">
                        <h2>Reset Password</h2>
                        <p class="auth-subtitle">An OTP has been sent to <b id="user-email-display">your email</b>. Please enter it below.</p>
                        <div id="auth-error-global-step2" class="auth-error-global"></div>
                        <form id="reset-password-form" onsubmit="event.preventDefault(); handleResetPassword();">
                            <input type="hidden" id="reset-token">
                            <div class="form-group">
                                <label class="auth-label" for="reset-otp">One-Time Password (OTP)</label>
                                <div class="input-group">
                                    <span class="prefix"><i class="fa-solid fa-key"></i></span>
                                    <input type="text" id="reset-otp" placeholder="Enter 6-digit OTP" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="auth-label" for="new-password">New Password</label>
                                <div class="input-group">
                                    <span class="prefix"><i class="fa-solid fa-lock"></i></span>
                                    <input type="password" id="new-password" placeholder="Enter new password" required>
                                </div>
                            </div>
                            <button type="submit" class="gold-btn" id="reset-password-btn">Reset Password</button>
                        </form>
                    </div>
                    
                    <div class="auth-divider"></div>
                    <a href="login.php" class="outline-btn full-width auth-link-btn">Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/global.js"></script>
    <script>
        function setError(id, msg) {
            const el = document.getElementById(id);
            if(el) {
                el.textContent = msg || '';
                el.style.display = msg ? 'block' : 'none';
            }
        }

        async function handleRequestOtp() {
            setError('auth-error-global-step1', '');
            const email = document.getElementById('reset-email').value.trim();
            const btn = document.getElementById('request-otp-btn');

            if (!email) {
                showToast('Please enter your email address.', 'error');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = 'Sending...';

            try {
                const response = await fetch('/api/user/login.php?action=forgot_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    showToast(result.message);
                    document.getElementById('user-email-display').textContent = email;
                    document.getElementById('reset-token').value = result.token;
                    document.getElementById('step1-request-otp').style.display = 'none';
                    document.getElementById('step2-reset-password').style.display = 'block';
                } else {
                    setError('auth-error-global-step1', result.message || 'Failed to send OTP.');
                }
            } catch (error) {
                setError('auth-error-global-step1', 'A network error occurred. Please try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Send OTP';
            }
        }

        async function handleResetPassword() {
            setError('auth-error-global-step2', '');
            const token = document.getElementById('reset-token').value;
            const otp = document.getElementById('reset-otp').value.trim();
            const password = document.getElementById('new-password').value;
            const btn = document.getElementById('reset-password-btn');

            if (!otp || !password) {
                showToast('Please fill in all fields.', 'error');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = 'Resetting...';

            try {
                const response = await fetch('/api/user/login.php?action=reset_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ token, otp, password })
                });
                const result = await response.json();

                if (response.ok && result.success) {
                    showToast(result.message);
                    setTimeout(() => { window.location.href = 'login.php'; }, 1200);
                } else {
                    setError('auth-error-global-step2', result.message || 'Failed to reset password.');
                }
            } catch (error) {
                setError('auth-error-global-step2', 'A network error occurred. Please try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Reset Password';
            }
        }
    </script>
</body>
</html>
