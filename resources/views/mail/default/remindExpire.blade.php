<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Expiration Notice</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 20px; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%); padding: 40px 30px; text-align: center; position: relative; }
        .header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="3" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="2" fill="rgba(255,255,255,0.1)"/></svg>'); }
        .logo { width: 80px; height: 80px; margin: 0 auto 20px; border-radius: 50%; overflow: hidden; border: 4px solid rgba(255,255,255,0.3); position: relative; z-index: 1; }
        .logo img { width: 100%; height: 100%; object-fit: cover; }
        .header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; position: relative; z-index: 1; }
        .warning-icon { display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 20px auto; position: relative; z-index: 1; }
        .content { padding: 40px 30px; }
        .content-header { text-align: center; margin-bottom: 30px; }
        .content-header h2 { color: #2d3748; font-size: 24px; font-weight: 600; margin-bottom: 10px; }
        .content-header .subtitle { color: #718096; font-size: 16px; }
        .expiration-alert { background: linear-gradient(135deg, #fed7d7 0%, #fbb6ce 100%); border-radius: 16px; padding: 30px; margin: 30px 0; border: 2px solid #f56565; position: relative; text-align: center; }
        .expiration-alert::before { content: '⚠️'; position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: #f56565; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .countdown-timer { display: inline-flex; align-items: center; background: #c53030; color: white; padding: 15px 25px; border-radius: 50px; font-size: 18px; font-weight: 700; margin: 20px 0; box-shadow: 0 4px 15px rgba(197, 48, 48, 0.3); }
        .countdown-timer svg { margin-right: 10px; }
        .renewal-section { background: #f0fff4; border-radius: 16px; padding: 30px; margin: 30px 0; border: 2px solid #38a169; text-align: center; }
        .renewal-button { display: inline-block; background: linear-gradient(135deg, #38a169 0%, #2f855a 100%); color: #ffffff; text-decoration: none; padding: 18px 40px; border-radius: 50px; font-weight: 600; font-size: 18px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(56, 161, 105, 0.4); margin: 20px 0; }
        .renewal-button:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(56, 161, 105, 0.6); }
        .service-details { background: #f7fafc; border-radius: 12px; padding: 20px; margin: 20px 0; }
        .service-details h3 { color: #2d3748; font-size: 16px; margin-bottom: 15px; display: flex; align-items: center; }
        .service-details h3 svg { margin-right: 8px; }
        .detail-item { display: flex; justify-content: space-between; margin: 10px 0; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .detail-item:last-child { border-bottom: none; }
        .detail-label { color: #718096; font-weight: 500; }
        .detail-value { color: #2d3748; font-weight: 600; }
        .footer { background: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer-text { color: #718096; font-size: 14px; margin-bottom: 15px; }
        .social-links { margin: 20px 0; }
        .social-links a { display: inline-block; margin: 0 10px; width: 40px; height: 40px; background: #e2e8f0; border-radius: 50%; text-decoration: none; line-height: 40px; color: #4a5568; transition: all 0.3s ease; }
        .social-links a:hover { background: #667eea; color: white; transform: translateY(-2px); }
        .divider { height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent); margin: 20px 0; }
        @media (max-width: 600px) { .email-container { margin: 10px; border-radius: 12px; } .header, .content, .footer { padding: 20px; } .header h1 { font-size: 24px; } .content-header h2 { font-size: 20px; } .expiration-alert, .renewal-section { padding: 20px; } .renewal-button { padding: 15px 30px; font-size: 16px; } .countdown-timer { font-size: 16px; padding: 12px 20px; } }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">
                <img src="https://proxybd.com/images/logo.png" alt="{{$name}} Logo">
            </div>
            <h1>{{$name}}</h1>
            <div class="warning-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L1 21H23L12 2ZM12 18C11.45 18 11 17.55 11 17C11 16.45 11.45 16 12 16C12.55 16 13 16.45 13 17C13 17.55 12.55 18 12 18ZM13 14H11V10H13V14Z" fill="white"/>
                </svg>
            </div>
        </div>
        
        <div class="content">
            <div class="content-header">
                <h2>Service Expiration Notice</h2>
                <p class="subtitle">Your service is about to expire</p>
            </div>
            
            <div class="expiration-alert">
                <h3 style="color: #c53030; font-size: 20px; margin-bottom: 20px;">Urgent: Service Expiring Soon!</h3>
                
                <div class="countdown-timer">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V11H13V17ZM13 9H11V7H13V9Z"/>
                    </svg>
                    Expires in 24 Hours
                </div>
                
                <p style="color: #4a5568; font-size: 16px; line-height: 1.6; margin: 20px 0;">
                    <strong>Dear Valued User,</strong><br>
                    Your service will expire within the next 24 hours. To avoid any service disruption and continue enjoying uninterrupted access, please renew your subscription as soon as possible.
                </p>
            </div>
            
            <div class="service-details">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V8H19V19ZM7 10V12H9V10H7ZM11 10V12H17V10H11Z"/>
                    </svg>
                    Service Information
                </h3>
                <div class="detail-item">
                    <span class="detail-label">Service Provider:</span>
                    <span class="detail-value">{{$name}}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value" style="color: #f56565;">⚠️ Expiring Soon</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Time Remaining:</span>
                    <span class="detail-value" style="color: #f56565;">Less than 24 hours</span>
                </div>
            </div>
            
            <div class="renewal-section">
                <h3 style="color: #2f855a; font-size: 18px; margin-bottom: 15px;">Renew Your Service</h3>
                <p style="color: #4a5568; font-size: 16px; margin-bottom: 20px;">
                    Don't let your service expire! Renew now to maintain continuous access and avoid any inconvenience.
                </p>
                
                <a href="{{$url}}/#/shop" class="renewal-button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" style="display: inline-block; vertical-align: middle; margin-right: 10px;">
                        <path d="M12 2V6L16 2H12ZM21 9H15L18.5 5.5L17.08 4.08L12 9.16L6.92 4.08L5.5 5.5L9 9H3V11H9L5.5 14.5L6.92 15.92L12 10.84L17.08 15.92L18.5 14.5L15 11H21V9Z" fill="currentColor"/>
                    </svg>
                    Renew Service Now
                </a>
                
                <p style="color: #718096; font-size: 14px; margin-top: 15px;">
                    <strong>Note:</strong> If you have already renewed your service, please ignore this email.
                </p>
            </div>
            
            <div style="background: #fef5e7; border-left: 4px solid #ed8936; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #c05621; font-size: 16px; margin-bottom: 10px; display: flex; align-items: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 8px;">
                        <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 8V7L19 5H14.83C14.42 3.83 13.31 3 12 3S9.58 3.83 9.17 5H5L3 7V8H21ZM12 19L16 15H13V9H11V15H8L12 19Z"/>
                    </svg>
                    What Happens After Expiration?
                </h3>
                <ul style="color: #c05621; font-size: 14px; line-height: 1.5; margin-left: 20px;">
                    <li>Your service will be temporarily suspended</li>
                    <li>Access to premium features will be restricted</li>
                    <li>Data may be subject to retention policies</li>
                    <li>Service restoration may take additional time</li>
                </ul>
            </div>
            
            <div class="divider"></div>
            
            <div style="text-align: center; color: #718096; font-size: 14px;">
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>If you have any questions, please contact our support team.</p>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-text">
                Thank you for choosing {{$name}}. We're here to serve you better.
            </div>
            
            <div class="social-links">
                <a href="{{$url}}" title="Website">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM12 20C7.59 20 4 16.41 4 12C4 7.59 7.59 4 12 4C16.41 4 20 7.59 20 12C20 16.41 16.41 20 12 20ZM12 6C9.79 6 8 7.79 8 10C8 12.21 9.79 14 12 14C14.21 14 16 12.21 16 10C16 7.79 14.21 6 12 6Z"/>
                    </svg>
                </a>
                <a href="mailto:support@{{$name}}" title="Email">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 4H4C2.9 4 2.01 4.9 2.01 6L2 18C2 19.1 2.9 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 8L12 13L4 8V6L12 11L20 6V8Z"/>
                    </svg>
                </a>
            </div>
            
            <div class="divider"></div>
            
            <div class="footer-text" style="font-size: 12px; color: #a0aec0;">
                © {{ date('Y') }} {{$name}}. All rights reserved.<br>
                This email was sent to you because you have an account with us.
            </div>
        </div>
    </div>
</body>
</html>
