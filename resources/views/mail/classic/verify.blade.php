<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Georgia', 'Times New Roman', serif; line-height: 1.7; background: #f8f9fa; margin: 0; padding: 30px 20px; }
        .email-wrapper { max-width: 650px; margin: 0 auto; background: #ffffff; border: 1px solid #e1e8ed; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .header-stripe { height: 6px; background: linear-gradient(90deg, #059669, #10b981, #34d399); }
        .header { background: #ffffff; padding: 45px 40px 35px; text-align: center; border-bottom: 2px solid #f1f5f9; position: relative; }
        .header::after { content: ''; position: absolute; bottom: -2px; left: 50%; transform: translateX(-50%); width: 80px; height: 2px; background: #10b981; }
        .logo-container { display: inline-block; width: 90px; height: 90px; border: 3px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .logo-container img { width: 100%; height: 100%; object-fit: cover; }
        .company-name { color: #1e293b; font-size: 26px; font-weight: 600; margin: 0; letter-spacing: -0.5px; }
        .content { padding: 45px 40px; }
        .verification-badge { display: inline-flex; align-items: center; background: #dcfce7; color: #166534; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 500; margin-bottom: 25px; }
        .verification-badge::before { content: '🔐'; margin-right: 8px; font-size: 16px; }
        .title { color: #0f172a; font-size: 28px; font-weight: 700; margin-bottom: 15px; line-height: 1.3; }
        .subtitle { color: #64748b; font-size: 18px; margin-bottom: 35px; }
        .code-section { background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border: 2px solid #10b981; border-radius: 12px; padding: 40px; margin: 35px 0; text-align: center; position: relative; }
        .code-section::before { content: '�️'; position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: #ffffff; padding: 0 12px; font-size: 24px; }
        .code-label { color: #166534; font-size: 16px; font-weight: 600; margin-bottom: 20px; }
        .verification-code { font-family: 'Courier New', monospace; font-size: 36px; font-weight: 800; color: #059669; letter-spacing: 6px; margin: 20px 0; text-shadow: 0 2px 4px rgba(5, 150, 105, 0.2); background: #ffffff; padding: 15px 25px; border-radius: 8px; border: 1px solid #d1fae5; display: inline-block; }
        .code-timer { display: inline-flex; align-items: center; background: #fef3c7; color: #92400e; padding: 10px 20px; border-radius: 25px; font-size: 14px; font-weight: 600; margin-top: 20px; }
        .code-timer::before { content: '⏰'; margin-right: 8px; }
        .instructions { background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #10b981; padding: 30px; margin: 30px 0; }
        .instructions h4 { color: #166534; font-size: 18px; margin-bottom: 15px; }
        .instructions p { color: #4b5563; font-size: 16px; line-height: 1.8; }
        .action-area { text-align: center; margin: 40px 0; }
        .primary-button { display: inline-block; background: #059669; color: #ffffff; text-decoration: none; padding: 16px 32px; border-radius: 6px; font-weight: 600; font-size: 16px; transition: all 0.2s ease; border: 2px solid #059669; }
        .primary-button:hover { background: #047857; border-color: #047857; }
        .primary-button svg { margin-right: 8px; vertical-align: middle; }
        .security-notice { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 25px; margin: 30px 0; }
        .security-notice h4 { color: #9a3412; font-size: 16px; margin-bottom: 12px; display: flex; align-items: center; }
        .security-notice h4::before { content: '⚠️'; margin-right: 8px; }
        .security-notice p { color: #c2410c; font-size: 14px; line-height: 1.6; }
        .footer { background: #f8fafc; padding: 40px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer-content { margin-bottom: 25px; }
        .footer-text { color: #6b7280; font-size: 15px; margin-bottom: 20px; }
        .links-section { margin: 25px 0; }
        .footer-link { color: #10b981; text-decoration: none; margin: 0 15px; font-size: 14px; padding: 8px 12px; border-radius: 4px; transition: all 0.2s; }
        .footer-link:hover { background: #dcfce7; }
        .divider { border: none; height: 1px; background: linear-gradient(90deg, transparent, #d1d5db, transparent); margin: 25px 0; }
        .copyright { color: #9ca3af; font-size: 13px; }
        @media (max-width: 640px) { 
            body { padding: 15px 10px; }
            .email-wrapper { border: none; box-shadow: none; }
            .header, .content, .footer { padding: 25px 20px; }
            .title { font-size: 24px; }
            .code-section { padding: 30px 20px; }
            .verification-code { font-size: 28px; letter-spacing: 4px; padding: 12px 20px; }
            .primary-button { padding: 14px 24px; font-size: 15px; }
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
            <div class="verification-badge">Email Verification Required</div>
            
            <h1 class="title">Verify Your Email</h1>
            <p class="subtitle">Please use the verification code below to confirm your email address</p>
            
            <div class="code-section">
                <div class="code-label">Your Verification Code</div>
                <div class="verification-code">{{$code}}</div>
                <div class="code-timer">Valid for 5 minutes only</div>
            </div>
            
            <div class="instructions">
                <h4>How to Use This Code</h4>
                <p><strong>Dear Customer,</strong> Please enter the verification code shown above in the verification field on our website to complete your email verification process. This code will expire in 5 minutes for your security.</p>
            </div>
            
            <div class="action-area">
                <a href="{{$url}}" class="primary-button">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2L2 7V10C2 16 6 21.5 12 23C18 21.5 22 16 22 10V7L12 2Z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Continue to {{$name}}
                </a>
            </div>
            
            <div class="security-notice">
                <h4>Security Information</h4>
                <p><strong>Important:</strong> This email was automatically generated by our system. If you didn't request this verification, please ignore this email and ensure your account security. Never share this code with anyone.</p>
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
