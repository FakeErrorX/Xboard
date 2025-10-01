<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Notification</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Georgia', 'Times New Roman', serif; line-height: 1.7; background: #f8f9fa; margin: 0; padding: 30px 20px; }
        .email-wrapper { max-width: 650px; margin: 0 auto; background: #ffffff; border: 1px solid #e1e8ed; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .header-stripe { height: 6px; background: linear-gradient(90deg, #1e3a8a, #3b82f6, #06b6d4); }
        .header { background: #ffffff; padding: 45px 40px 35px; text-align: center; border-bottom: 2px solid #f1f5f9; position: relative; }
        .header::after { content: ''; position: absolute; bottom: -2px; left: 50%; transform: translateX(-50%); width: 80px; height: 2px; background: #3b82f6; }
        .logo-container { display: inline-block; width: 90px; height: 90px; border: 3px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .logo-container img { width: 100%; height: 100%; object-fit: cover; }
        .company-name { color: #1e293b; font-size: 26px; font-weight: 600; margin: 0; letter-spacing: -0.5px; }
        .content { padding: 45px 40px; }
        .notification-badge { display: inline-flex; align-items: center; background: #dbeafe; color: #1e40af; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 500; margin-bottom: 25px; }
        .notification-badge::before { content: '📢'; margin-right: 8px; font-size: 16px; }
        .title { color: #0f172a; font-size: 28px; font-weight: 700; margin-bottom: 15px; line-height: 1.3; }
        .subtitle { color: #64748b; font-size: 18px; margin-bottom: 35px; }
        .message-card { background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #3b82f6; padding: 35px; margin: 30px 0; position: relative; }
        .message-card::before { content: '✉️'; position: absolute; top: -12px; left: 30px; background: #ffffff; padding: 0 8px; font-size: 20px; }
        .greeting { color: #374151; font-size: 18px; font-weight: 600; margin-bottom: 20px; }
        .message-body { color: #4b5563; font-size: 16px; line-height: 1.8; }
        .action-area { text-align: center; margin: 40px 0; }
        .primary-button { display: inline-block; background: #1e40af; color: #ffffff; text-decoration: none; padding: 16px 32px; border-radius: 6px; font-weight: 600; font-size: 16px; transition: all 0.2s ease; border: 2px solid #1e40af; }
        .primary-button:hover { background: #1e3a8a; border-color: #1e3a8a; }
        .primary-button svg { margin-right: 8px; vertical-align: middle; }
        .info-section { background: #fffbeb; border: 1px solid #fed7aa; border-radius: 8px; padding: 25px; margin: 30px 0; }
        .info-section h4 { color: #92400e; font-size: 16px; margin-bottom: 12px; display: flex; align-items: center; }
        .info-section h4::before { content: 'ℹ️'; margin-right: 8px; }
        .info-section p { color: #a16207; font-size: 14px; line-height: 1.6; }
        .footer { background: #f8fafc; padding: 40px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer-content { margin-bottom: 25px; }
        .footer-text { color: #6b7280; font-size: 15px; margin-bottom: 20px; }
        .links-section { margin: 25px 0; }
        .footer-link { color: #3b82f6; text-decoration: none; margin: 0 15px; font-size: 14px; padding: 8px 12px; border-radius: 4px; transition: all 0.2s; }
        .footer-link:hover { background: #dbeafe; }
        .divider { border: none; height: 1px; background: linear-gradient(90deg, transparent, #d1d5db, transparent); margin: 25px 0; }
        .copyright { color: #9ca3af; font-size: 13px; }
        @media (max-width: 640px) { 
            body { padding: 15px 10px; }
            .email-wrapper { border: none; box-shadow: none; }
            .header, .content, .footer { padding: 25px 20px; }
            .title { font-size: 24px; }
            .message-card { padding: 25px 20px; }
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
            <div class="notification-badge">System Notification</div>
            
            <h1 class="title">Important Message</h1>
            <p class="subtitle">We have an important update to share with you</p>
            
            <div class="message-card">
                <div class="greeting">Dear Valued Customer,</div>
                <div class="message-body">
                    {!! nl2br($content) !!}
                </div>
            </div>
            
            <div class="action-area">
                <a href="{{$url}}" class="primary-button">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2L2 7V10C2 16 6 21.5 12 23C18 21.5 22 16 22 10V7L12 2Z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    Access {{$name}}
                </a>
            </div>
            
            <div class="info-section">
                <h4>Automated Message Notice</h4>
                <p>This email is sent automatically by our system. Please do not reply to this email address. If you need assistance, please contact our support team through the official channels.</p>
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