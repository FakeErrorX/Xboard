<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login Authentication</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Georgia', 'Times New Roman', serif; line-height: 1.7; background: #fafafa; margin: 0; padding: 30px 20px; }
        .email-wrapper { max-width: 650px; margin: 0 auto; background: #ffffff; border: 1px solid #e1e8ed; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .header-stripe { height: 6px; background: linear-gradient(90deg, #7c3aed, #8b5cf6, #a78bfa); }
        .header { background: #ffffff; padding: 45px 40px 35px; text-align: center; border-bottom: 2px solid #f1f5f9; position: relative; }
        .header::after { content: ''; position: absolute; bottom: -2px; left: 50%; transform: translateX(-50%); width: 80px; height: 2px; background: #8b5cf6; }
        .logo-container { display: inline-block; width: 90px; height: 90px; border: 3px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .logo-container img { width: 100%; height: 100%; object-fit: cover; }
        .company-name { color: #1e293b; font-size: 26px; font-weight: 600; margin: 0; letter-spacing: -0.5px; }
        .content { padding: 45px 40px; }
        .login-badge { display: inline-flex; align-items: center; background: #ede9fe; color: #6d28d9; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 500; margin-bottom: 25px; }
        .login-badge::before { content: '🔑'; margin-right: 8px; font-size: 16px; }
        .title { color: #0f172a; font-size: 28px; font-weight: 700; margin-bottom: 15px; line-height: 1.3; }
        .subtitle { color: #64748b; font-size: 18px; margin-bottom: 35px; }
        .auth-section { background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border: 2px solid #8b5cf6; border-radius: 12px; padding: 40px; margin: 35px 0; text-align: center; position: relative; }
        .auth-section::before { content: '🛡️'; position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: #ffffff; padding: 0 12px; font-size: 24px; }
        .auth-label { color: #6d28d9; font-size: 16px; font-weight: 600; margin-bottom: 20px; }
        .login-instructions { color: #4b5563; font-size: 16px; line-height: 1.8; margin-bottom: 30px; }
        .login-instructions strong { color: #374151; }
        .timer-warning { display: inline-flex; align-items: center; background: #fef3c7; color: #92400e; padding: 12px 20px; border-radius: 25px; font-size: 14px; font-weight: 600; margin: 20px 0; }
        .timer-warning::before { content: '⏱️'; margin-right: 8px; }
        .action-area { margin: 40px 0; }
        .primary-button { display: inline-block; background: #7c3aed; color: #ffffff; text-decoration: none; padding: 18px 40px; border-radius: 6px; font-weight: 600; font-size: 18px; transition: all 0.2s ease; border: 2px solid #7c3aed; }
        .primary-button:hover { background: #6d28d9; border-color: #6d28d9; }
        .primary-button svg { margin-right: 10px; vertical-align: middle; }
        .link-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; margin: 30px 0; }
        .link-section h4 { color: #374151; font-size: 16px; margin-bottom: 15px; }
        .link-display { background: #ffffff; border: 2px dashed #d1d5db; border-radius: 6px; padding: 15px; font-family: 'Courier New', monospace; font-size: 12px; color: #6b7280; word-break: break-all; margin: 10px 0; }
        .link-note { color: #6b7280; font-size: 13px; }
        .security-notice { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 25px; margin: 30px 0; }
        .security-notice h4 { color: #9a3412; font-size: 16px; margin-bottom: 12px; display: flex; align-items: center; }
        .security-notice h4::before { content: '⚠️'; margin-right: 8px; }
        .security-notice p { color: #c2410c; font-size: 14px; line-height: 1.6; }
        .footer { background: #f8fafc; padding: 40px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer-content { margin-bottom: 25px; }
        .footer-text { color: #6b7280; font-size: 15px; margin-bottom: 20px; }
        .links-section { margin: 25px 0; }
        .footer-link { color: #8b5cf6; text-decoration: none; margin: 0 15px; font-size: 14px; padding: 8px 12px; border-radius: 4px; transition: all 0.2s; }
        .footer-link:hover { background: #ede9fe; }
        .divider { border: none; height: 1px; background: linear-gradient(90deg, transparent, #d1d5db, transparent); margin: 25px 0; }
        .copyright { color: #9ca3af; font-size: 13px; }
        @media (max-width: 640px) { 
            body { padding: 15px 10px; }
            .email-wrapper { border: none; box-shadow: none; }
            .header, .content, .footer { padding: 25px 20px; }
            .title { font-size: 24px; }
            .auth-section { padding: 30px 20px; }
            .primary-button { padding: 16px 30px; font-size: 16px; }
            .link-section { padding: 20px 15px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="header-stripe"></div>
        
        <div class="header">
            <div class="logo-container">
                <img src="https://proxybd.com/images/logo.png" alt="{{$name}} Logo">
            </div>
            <h1 class="company-name">{{$name}}</h1>
        </div>
        
        <div class="content">
            <div class="login-badge">Secure Authentication Request</div>
            
            <h1 class="title">Login Verification</h1>
            <p class="subtitle">Secure access to your account</p>
            
            <div class="auth-section">
                <div class="auth-label">Authentication Required</div>
                <div class="login-instructions">
                    <strong>Dear Customer,</strong><br>
                    You are attempting to log into {{$name}}. For your security, please click the secure button below within the next 5 minutes to complete your login process.
                </div>
                <div class="timer-warning">Session expires in 5 minutes</div>
                
                <div class="action-area">
                    <a href="{{$link}}" class="primary-button">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M12 2L2 7V10C2 16 6 21.5 12 23C18 21.5 22 16 22 10V7L12 2Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Authenticate & Login
                    </a>
                </div>
            </div>
            
            <div class="link-section">
                <h4>Alternative Access Method</h4>
                <p style="color: #6b7280; font-size: 14px; margin-bottom: 15px;">If the button above doesn't work, copy and paste this link into your browser:</p>
                <div class="link-display">{{$link}}</div>
                <p class="link-note">This link will expire automatically for your security.</p>
            </div>
            
            <div class="security-notice">
                <h4>Security Notice</h4>
                <p><strong>Important:</strong> This email was automatically generated by our system. If you did not authorize this login request, please ignore this email and consider updating your password. Never share this authentication link with anyone.</p>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-content">
                <div class="footer-text">
                    Thank you for being a valued member of the {{$name}} community.
                </div>
                
                <div class="links-section">
                    <a href="{{$url}}/#/profile" class="footer-link">My Account</a>
                    <a href="{{$url}}/#/tickets" class="footer-link">Help Center</a>
                    <a href="{{$url}}" class="footer-link">Website</a>
                </div>
            </div>
            
            <hr class="divider">
            
            <div class="copyright">
                © {{ date('Y') }} {{$name}}. All rights reserved.<br>
                This email was sent because you have an active account with us.
            </div>
        </div>
    </div>
</body>
</html>
