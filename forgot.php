<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Forgot Password - ARNiya Smart Hub</title>
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
            <a href="login.php" class="close-btn" aria-label="Back to login"><i class="fa-solid fa-xmark"></i></a>
            <div class="login-header-img bg-img" style="background-image: url('https://images.unsplash.com/photo-1614851099175-e5b30eb6f696?auto=format&fit=crop&w=800&q=80')">
            </div>
            <div class="login-form">
                <h2>Forgot <span style="font-weight: 300;">Password?</span></h2>
                <p class="login-subtitle">Enter your registered email or mobile and we'll send reset instructions.</p>

                <div id="forgot-success" class="auth-success" style="display:none;">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <strong>Reset instructions sent successfully.</strong>
                        <span>Check your email or SMS for the OTP / reset link.</span>
                    </div>
                </div>

                <form id="forgot-form" novalidate onsubmit="event.preventDefault(); handleForgot();">
                    <label class="auth-label" for="forgot-id">Email or Mobile Number</label>
                    <div class="input-group">
                        <span class="prefix"><i class="fa-regular fa-envelope"></i></span>
                        <input type="text" id="forgot-id" placeholder="Email or 10-digit mobile">
                    </div>
                    <div class="auth-error" id="err-forgot-id"></div>

                    <button type="submit" class="gold-btn">Send OTP / Reset Link</button>
                </form>

                <div class="auth-divider"><span>Remember your password?</span></div>
                <a href="login.php" class="outline-btn full-width auth-link-btn"><i class="fa-solid fa-arrow-left"></i> &nbsp;Back to Login</a>
            </div>
        </div>

    </div>

    <script src="assets/js/global.js"></script>
    <script>
        function setError(id, msg) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = msg || '';
            el.style.display = msg ? 'block' : 'none';
        }
        function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
        function isValidMobile(v) { return /^\d{10}$/.test(v.replace(/\D/g, '')); }

        function handleForgot() {
            setError('err-forgot-id', '');
            const id = (document.getElementById('forgot-id').value || '').trim();
            if (!id) {
                setError('err-forgot-id', 'Email or mobile number is required');
                return;
            }
            if (!isValidEmail(id) && !isValidMobile(id)) {
                setError('err-forgot-id', 'Enter a valid email or 10-digit mobile number');
                return;
            }

            const ok = document.getElementById('forgot-success');
            ok.style.display = 'flex';
            showToast('Reset instructions sent successfully.');
            document.getElementById('forgot-id').value = '';
        }
    </script>
</body>
</html>
