<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Expiry Reminder</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Georgia', 'Times New Roman', serif; line-height: 1.7; background: #fafafa; margin: 0; padding: 30px 20px; }
        .email-wrapper { max-width: 650px; margin: 0 auto; background: #ffffff; border: 1px solid #e1e8ed; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .header-stripe { height: 6px; background: linear-gradient(90deg, #ef4444, #f97316, #f59e0b); }
        .header { background: #ffffff; padding: 45px 40px 35px; text-align: center; border-bottom: 2px solid #f1f5f9; position: relative; }
        .header::after { content: ''; position: absolute; bottom: -2px; left: 50%; transform: translateX(-50%); width: 80px; height: 2px; background: #f97316; }
        .logo-container { display: inline-block; width: 90px; height: 90px; border: 3px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .logo-container img { width: 100%; height: 100%; object-fit: cover; }
        .company-name { color: #1e293b; font-size: 26px; font-weight: 600; margin: 0; letter-spacing: -0.5px; }
        .content { padding: 45px 40px; }
        .warning-badge { display: inline-flex; align-items: center; background: #fef3c7; color: #92400e; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 500; margin-bottom: 25px; }
        .warning-badge::before { content: '⚠️'; margin-right: 8px; font-size: 16px; }
        .title { color: #0f172a; font-size: 28px; font-weight: 700; margin-bottom: 15px; line-height: 1.3; }
        .subtitle { color: #64748b; font-size: 18px; margin-bottom: 35px; }
        .expiry-section { background: linear-gradient(135deg, #fff7ed 0%, #fed7aa 100%); border: 2px solid #f97316; border-radius: 12px; padding: 40px; margin: 35px 0; text-align: center; position: relative; }
        .expiry-section::before { content: '⏰'; position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: #ffffff; padding: 0 12px; font-size: 24px; }
        .expiry-label { color: #ea580c; font-size: 16px; font-weight: 600; margin-bottom: 20px; }
        .expiry-message { color: #4b5563; font-size: 16px; line-height: 1.8; margin-bottom: 30px; }
        .expiry-message strong { color: #374151; }
        .countdown-timer { display: inline-flex; align-items: center; background: #fee2e2; color: #dc2626; padding: 12px 20px; border-radius: 25px; font-size: 14px; font-weight: 600; margin: 20px 0; }
        .countdown-timer::before { content: '⏳'; margin-right: 8px; }
        .action-area { margin: 40px 0; }
        .primary-button { display: inline-block; background: #f97316; color: #ffffff; text-decoration: none; padding: 18px 40px; border-radius: 6px; font-weight: 600; font-size: 18px; transition: all 0.2s ease; border: 2px solid #f97316; }
        .primary-button:hover { background: #ea580c; border-color: #ea580c; }
        .primary-button svg { margin-right: 10px; vertical-align: middle; }
        .features-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 30px; margin: 30px 0; }
        .features-section h4 { color: #374151; font-size: 18px; margin-bottom: 20px; text-align: center; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
        .feature-item { text-align: center; }
        .feature-icon { font-size: 32px; margin-bottom: 10px; }
        .feature-title { color: #374151; font-size: 14px; font-weight: 600; margin-bottom: 5px; }
        .feature-desc { color: #6b7280; font-size: 12px; }
        .urgency-notice { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 25px; margin: 30px 0; }
        .urgency-notice h4 { color: #dc2626; font-size: 16px; margin-bottom: 12px; display: flex; align-items: center; }
        .urgency-notice h4::before { content: '🚨'; margin-right: 8px; }
        .urgency-notice p { color: #ef4444; font-size: 14px; line-height: 1.6; }
        .footer { background: #f8fafc; padding: 40px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer-content { margin-bottom: 25px; }
        .footer-text { color: #6b7280; font-size: 15px; margin-bottom: 20px; }
        .links-section { margin: 25px 0; }
        .footer-link { color: #f97316; text-decoration: none; margin: 0 15px; font-size: 14px; padding: 8px 12px; border-radius: 4px; transition: all 0.2s; }
        .footer-link:hover { background: #fed7aa; }
        .divider { border: none; height: 1px; background: linear-gradient(90deg, transparent, #d1d5db, transparent); margin: 25px 0; }
        .copyright { color: #9ca3af; font-size: 13px; }
        @media (max-width: 640px) { 
            body { padding: 15px 10px; }
            .email-wrapper { border: none; box-shadow: none; }
            .header, .content, .footer { padding: 25px 20px; }
            .title { font-size: 24px; }
            .expiry-section { padding: 30px 20px; }
            .primary-button { padding: 16px 30px; font-size: 16px; }
            .features-grid { grid-template-columns: 1fr; gap: 15px; }
            .features-section { padding: 20px 15px; }
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
            <div class="warning-badge">Subscription Expiry Alert</div>
            
            <h1 class="title">Renewal Required</h1>
            <p class="subtitle">Your subscription will expire soon</p>
            
            <div class="expiry-section">
                <div class="expiry-label">Expiration Warning</div>
                <div class="expiry-message">
                    <strong>Dear Valued Customer,</strong><br>
                    Your {{$name}} subscription is approaching its expiration date. To continue enjoying uninterrupted service and premium features, please renew your subscription as soon as possible.
                </div>
                <div class="countdown-timer">Action required before expiry</div>
                
                <div class="action-area">
                    <a href="{{$url}}/#/shop" class="primary-button">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M4 15H6L8 21H16L18 15H20C21.1 15 22 14.1 22 13V11C22 9.9 21.1 9 20 9H4C2.9 9 2 9.9 2 11V13C2 14.1 2.9 15 4 15Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        Renew Subscription
                    </a>
                </div>
            </div>
            
            <div class="features-section">
                <h4>Don't Lose Access to These Premium Features</h4>
                <div class="features-grid">
                    <div class="feature-item">
                        <div class="feature-icon">🚀</div>
                        <div class="feature-title">High-Speed Proxies</div>
                        <div class="feature-desc">Lightning-fast connections</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🔒</div>
                        <div class="feature-title">Premium Security</div>
                        <div class="feature-desc">Enterprise-grade protection</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">🌍</div>
                        <div class="feature-title">BDIX Locations</div>
                        <div class="feature-desc">Access to BDIX server locations</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">📞</div>
                        <div class="feature-title">Priority Support</div>
                        <div class="feature-desc">24/7 dedicated assistance</div>
                    </div>
                </div>
            </div>
            
            <div class="urgency-notice">
                <h4>Time-Sensitive Renewal</h4>
                <p><strong>Important:</strong> To avoid service interruption, please renew your subscription before the expiration date. After expiry, you may experience service limitations until renewal is completed. Renew now to maintain seamless access to all premium features.</p>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-content">
                <div class="footer-text">
                    Thank you for being a loyal {{$name}} customer. We value your continued trust in our services.
                </div>
                
                <div class="links-section">
                    <a href="{{$url}}/#/shop" class="footer-link">My Subscription</a>
                    <a href="{{$url}}/#/tickets" class="footer-link">Help Center</a>
                    <a href="{{$url}}" class="footer-link">Contact Support</a>
                </div>
            </div>
            
            <hr class="divider">
            
            <div class="copyright">
                © {{ date('Y') }} {{$name}}. All rights reserved.<br>
                This renewal reminder was sent to help you maintain continuous service.
            </div>
        </div>
    </div>
</body>
</html>
