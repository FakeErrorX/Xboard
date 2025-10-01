<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 20px; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center; position: relative; }
        .header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="3" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="2" fill="rgba(255,255,255,0.1)"/></svg>'); }
        .logo { width: 80px; height: 80px; margin: 0 auto 20px; border-radius: 50%; overflow: hidden; border: 4px solid rgba(255,255,255,0.3); position: relative; z-index: 1; }
        .logo img { width: 100%; height: 100%; object-fit: cover; }
        .header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; position: relative; z-index: 1; }
        .verification-icon { display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 20px auto; position: relative; z-index: 1; }
        .content { padding: 40px 30px; }
        .content-header { text-align: center; margin-bottom: 30px; }
        .content-header h2 { color: #2d3748; font-size: 24px; font-weight: 600; margin-bottom: 10px; }
        .content-header .subtitle { color: #718096; font-size: 16px; }
        .verification-code { background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); border-radius: 16px; padding: 30px; margin: 30px 0; text-align: center; border: 2px dashed #667eea; position: relative; }
        .verification-code::before { content: '🔐'; position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: #667eea; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .code-display { font-size: 36px; font-weight: 700; color: #667eea; letter-spacing: 8px; margin: 20px 0; font-family: 'Courier New', monospace; text-shadow: 0 2px 4px rgba(102, 126, 234, 0.2); }
        .timer-info { display: inline-flex; align-items: center; background: #fed7d7; color: #c53030; padding: 10px 20px; border-radius: 50px; font-size: 14px; font-weight: 600; margin: 20px 0; }
        .timer-info svg { margin-right: 8px; }
        .security-note { background: #f0fff4; border-left: 4px solid #38a169; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .security-note h3 { color: #2f855a; font-size: 16px; margin-bottom: 10px; display: flex; align-items: center; }
        .security-note h3 svg { margin-right: 8px; }
        .security-note p { color: #2f855a; font-size: 14px; line-height: 1.5; }
        .footer { background: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer-text { color: #718096; font-size: 14px; margin-bottom: 15px; }
        .social-links { margin: 20px 0; }
        .social-links a { display: inline-block; margin: 0 10px; width: 40px; height: 40px; background: #e2e8f0; border-radius: 50%; text-decoration: none; line-height: 40px; color: #4a5568; transition: all 0.3s ease; }
        .social-links a:hover { background: #667eea; color: white; transform: translateY(-2px); }
        .divider { height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent); margin: 20px 0; }
        @media (max-width: 600px) { .email-container { margin: 10px; border-radius: 12px; } .header, .content, .footer { padding: 20px; } .header h1 { font-size: 24px; } .content-header h2 { font-size: 20px; } .verification-code { padding: 20px; } .code-display { font-size: 28px; letter-spacing: 4px; } }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">
                <img src="https://proxybd.com/images/logo.png" alt="{{$name}} Logo">
            </div>
            <h1>{{$name}}</h1>
            <div class="verification-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1ZM10 17L6 13L7.41 11.59L10 14.17L16.59 7.58L18 9L10 17Z" fill="white"/>
                </svg>
            </div>
        </div>
        
        <div class="content">
            <div class="content-header">
                <h2>Email Verification Code</h2>
                <p class="subtitle">Secure your account with this verification code</p>
            </div>
            
            <div class="verification-code">
                <p style="color: #4a5568; font-size: 16px; margin-bottom: 20px;">Your verification code is:</p>
                <div class="code-display">{{$code}}</div>
                <div class="timer-info">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V11H13V17ZM13 9H11V7H13V9Z"/>
                    </svg>
                    Valid for 5 minutes only
                </div>
            </div>
            
            <div class="security-note">
                <h3>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 1L3 5V11C3 16.55 6.84 21.74 12 23C17.16 21.74 21 16.55 21 11V5L12 1ZM12 7C13.1 7 14 7.9 14 9C14 10.1 13.1 11 12 11C10.9 11 10 10.1 10 9C10 7.9 10.9 7 12 7ZM12 17C10.67 17 9.67 16.33 9.33 15.67C9.33 14.67 11.33 14.17 12 14.17C12.67 14.17 14.67 14.67 14.67 15.67C14.33 16.33 13.33 17 12 17Z"/>
                    </svg>
                    Security Notice
                </h3>
                <p><strong>Important:</strong> If you didn't request this verification code, please ignore this email and ensure your account is secure. Never share this code with anyone.</p>
            </div>
            
            <div style="text-align: center; color: #4a5568; font-size: 16px; line-height: 1.6; margin: 30px 0;">
                <p><strong>Dear User,</strong></p>
                <p>Please enter the verification code above to complete your email verification. This code will expire in 5 minutes for your security.</p>
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
