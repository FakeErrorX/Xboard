<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Notification</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 20px; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center; position: relative; }
        .header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="3" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="2" fill="rgba(255,255,255,0.1)"/></svg>'); }
        .logo { width: 80px; height: 80px; margin: 0 auto 20px; border-radius: 50%; overflow: hidden; border: 4px solid rgba(255,255,255,0.3); position: relative; z-index: 1; }
        .logo img { width: 100%; height: 100%; object-fit: cover; }
        .header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; position: relative; z-index: 1; }
        .notification-icon { display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 20px auto; position: relative; z-index: 1; }
        .content { padding: 40px 30px; }
        .content-header { text-align: center; margin-bottom: 30px; }
        .content-header h2 { color: #2d3748; font-size: 24px; font-weight: 600; margin-bottom: 10px; }
        .content-header .subtitle { color: #718096; font-size: 16px; }
        .message-content { background: #f7fafc; border-radius: 12px; padding: 30px; margin: 20px 0; border-left: 4px solid #667eea; position: relative; }
        .message-content::before { content: '💬'; position: absolute; top: -10px; left: 20px; background: #667eea; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .message-text { color: #4a5568; font-size: 16px; line-height: 1.6; }
        .cta-section { text-align: center; margin: 30px 0; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; padding: 15px 30px; border-radius: 50px; font-weight: 600; font-size: 16px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); }
        .cta-button:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6); }
        .footer { background: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer-text { color: #718096; font-size: 14px; margin-bottom: 15px; }
        .social-links { margin: 20px 0; }
        .social-links a { display: inline-block; margin: 0 10px; width: 40px; height: 40px; background: #e2e8f0; border-radius: 50%; text-decoration: none; line-height: 40px; color: #4a5568; transition: all 0.3s ease; }
        .social-links a:hover { background: #667eea; color: white; transform: translateY(-2px); }
        .divider { height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent); margin: 20px 0; }
        @media (max-width: 600px) { .email-container { margin: 10px; border-radius: 12px; } .header, .content, .footer { padding: 20px; } .header h1 { font-size: 24px; } .content-header h2 { font-size: 20px; } .message-content { padding: 20px; } }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">
                <img src="https://proxybd.com/images/logo.png" alt="{{$name}} Logo">
            </div>
            <h1>{{$name}}</h1>
            <div class="notification-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 8V7L19 5H14.83C14.42 3.83 13.31 3 12 3S9.58 3.83 9.17 5H5L3 7V8H21ZM12 19L16 15H13V9H11V15H8L12 19Z" fill="white"/>
                </svg>
            </div>
        </div>
        
        <div class="content">
            <div class="content-header">
                <h2>Website Notification</h2>
                <p class="subtitle">Important message from {{$name}}</p>
            </div>
            
            <div class="message-content">
                <div class="message-text">
                    <p style="margin-bottom: 15px; font-weight: 600; color: #2d3748;">Dear Valued User,</p>
                    <div style="color: #4a5568; line-height: 1.7;">
                        {!! nl2br($content) !!}
                    </div>
                </div>
            </div>
            
            <div class="cta-section">
                <a href="{{$url}}" class="cta-button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                        <path d="M19 3H5C3.89 3 3 3.89 3 5V19C3 20.1 3.89 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.89 20.1 3 19 3ZM19 19H5V8H19V19ZM7 10V12H9V10H7ZM11 10V12H17V10H11Z" fill="currentColor"/>
                    </svg>
                    Visit {{$name}}
                </a>
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
