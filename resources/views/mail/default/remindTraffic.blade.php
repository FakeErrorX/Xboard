<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Usage Alert</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin: 0; padding: 20px; }
        .email-container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%); padding: 40px 30px; text-align: center; position: relative; }
        .header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="3" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="80" r="2" fill="rgba(255,255,255,0.1)"/></svg>'); }
        .logo { width: 80px; height: 80px; margin: 0 auto 20px; border-radius: 50%; overflow: hidden; border: 4px solid rgba(255,255,255,0.3); position: relative; z-index: 1; }
        .logo img { width: 100%; height: 100%; object-fit: cover; }
        .header h1 { color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; position: relative; z-index: 1; }
        .traffic-icon { display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 20px auto; position: relative; z-index: 1; }
        .content { padding: 40px 30px; }
        .content-header { text-align: center; margin-bottom: 30px; }
        .content-header h2 { color: #2d3748; font-size: 24px; font-weight: 600; margin-bottom: 10px; }
        .content-header .subtitle { color: #718096; font-size: 16px; }
        .usage-alert { background: linear-gradient(135deg, #fef5e7 0%, #fed7aa 100%); border-radius: 16px; padding: 30px; margin: 30px 0; border: 2px solid #ed8936; position: relative; text-align: center; }
        .usage-alert::before { content: '📊'; position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: #ed8936; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .progress-bar { width: 100%; height: 20px; background: #e2e8f0; border-radius: 10px; overflow: hidden; margin: 20px 0; position: relative; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #f6ad55, #ed8936); width: 80%; border-radius: 10px; position: relative; }
        .progress-fill::after { content: '80%'; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: white; font-weight: 600; font-size: 12px; }
        .usage-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0; }
        .stat-card { background: #f7fafc; border-radius: 12px; padding: 20px; text-align: center; border: 1px solid #e2e8f0; }
        .stat-number { font-size: 28px; font-weight: 700; color: #ed8936; margin-bottom: 5px; }
        .stat-label { color: #718096; font-size: 14px; font-weight: 500; }
        .management-tips { background: #e6fffa; border-radius: 16px; padding: 30px; margin: 30px 0; border: 2px solid #38b2ac; }
        .tip-item { display: flex; align-items: flex-start; margin: 15px 0; }
        .tip-item svg { margin-right: 12px; margin-top: 2px; flex-shrink: 0; }
        .tip-text { color: #2c7a7b; font-size: 14px; line-height: 1.5; }
        .cta-section { text-align: center; margin: 30px 0; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, #38b2ac 0%, #2c7a7b 100%); color: #ffffff; text-decoration: none; padding: 15px 30px; border-radius: 50px; font-weight: 600; font-size: 16px; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(56, 178, 172, 0.4); margin: 10px; }
        .cta-button:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(56, 178, 172, 0.6); }
        .secondary-button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); }
        .secondary-button:hover { box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6); }
        .footer { background: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer-text { color: #718096; font-size: 14px; margin-bottom: 15px; }
        .social-links { margin: 20px 0; }
        .social-links a { display: inline-block; margin: 0 10px; width: 40px; height: 40px; background: #e2e8f0; border-radius: 50%; text-decoration: none; line-height: 40px; color: #4a5568; transition: all 0.3s ease; }
        .social-links a:hover { background: #667eea; color: white; transform: translateY(-2px); }
        .divider { height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent); margin: 20px 0; }
        @media (max-width: 600px) { .email-container { margin: 10px; border-radius: 12px; } .header, .content, .footer { padding: 20px; } .header h1 { font-size: 24px; } .content-header h2 { font-size: 20px; } .usage-alert, .management-tips { padding: 20px; } .usage-stats { grid-template-columns: 1fr; gap: 15px; } .cta-button { padding: 12px 24px; font-size: 14px; margin: 5px; } }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">
                <img src="https://proxybd.com/images/logo.png" alt="{{$name}} Logo">
            </div>
            <h1>{{$name}}</h1>
            <div class="traffic-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 6L18.29 8.29L13.41 13.17L9.41 9.17L2 16.59L3.41 18L9.41 12L13.41 16L19.71 9.71L22 12V6H16Z" fill="white"/>
                </svg>
            </div>
        </div>
        
        <div class="content">
            <div class="content-header">
                <h2>Traffic Usage Alert</h2>
                <p class="subtitle">Monitor your data consumption</p>
            </div>
            
            <div class="usage-alert">
                <h3 style="color: #c05621; font-size: 20px; margin-bottom: 20px;">High Traffic Usage Detected!</h3>
                
                <p style="color: #4a5568; font-size: 16px; margin-bottom: 20px;">
                    <strong>Dear Valued User,</strong><br>
                    You have used <strong>80%</strong> of your traffic quota. To avoid any service disruption, please monitor your usage and consider managing your traffic accordingly.
                </p>
                
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
                
                <p style="color: #c05621; font-size: 14px; font-weight: 600;">
                    ⚠️ You're approaching your traffic limit
                </p>
            </div>
            
            <div class="usage-stats">
                <div class="stat-card">
                    <div class="stat-number">80%</div>
                    <div class="stat-label">Used</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">20%</div>
                    <div class="stat-label">Remaining</div>
                </div>
            </div>
            
            <div class="management-tips">
                <h3 style="color: #2c7a7b; font-size: 18px; margin-bottom: 20px; text-align: center;">Traffic Management Tips</h3>
                
                <div class="tip-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#38b2ac">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 17L5 12L6.41 10.59L10 14.17L17.59 6.58L19 8L10 17Z"/>
                    </svg>
                    <div class="tip-text">Monitor your daily usage to spread consumption evenly throughout your billing period</div>
                </div>
                
                <div class="tip-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#38b2ac">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 17L5 12L6.41 10.59L10 14.17L17.59 6.58L19 8L10 17Z"/>
                    </svg>
                    <div class="tip-text">Consider upgrading to a higher plan if you consistently exceed your current quota</div>
                </div>
                
                <div class="tip-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#38b2ac">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 17L5 12L6.41 10.59L10 14.17L17.59 6.58L19 8L10 17Z"/>
                    </svg>
                    <div class="tip-text">Optimize your applications and reduce unnecessary background traffic</div>
                </div>
                
                <div class="tip-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#38b2ac">
                        <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM10 17L5 12L6.41 10.59L10 14.17L17.59 6.58L19 8L10 17Z"/>
                    </svg>
                    <div class="tip-text">Set up usage alerts to stay informed about your consumption patterns</div>
                </div>
            </div>
            
            <div class="cta-section">
                <h3 style="color: #2d3748; font-size: 18px; margin-bottom: 20px;">Take Action</h3>
                
                <a href="{{$url}}/#/profile" class="cta-button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                        <path d="M16 6L18.29 8.29L13.41 13.17L9.41 9.17L2 16.59L3.41 18L9.41 12L13.41 16L19.71 9.71L22 12V6H16Z" fill="currentColor"/>
                    </svg>
                    View Usage Details
                </a>
                
                <a href="{{$url}}/#/shop" class="cta-button secondary-button">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                        <path d="M12 2L3.09 8.26L12 14.5L20.91 8.26L12 2ZM21 16L12 22L3 16L12 10L21 16Z" fill="currentColor"/>
                    </svg>
                    Upgrade Plan
                </a>
            </div>
            
            <div style="background: #fef5e7; border-left: 4px solid #ed8936; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3 style="color: #c05621; font-size: 16px; margin-bottom: 10px; display: flex; align-items: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 8px;">
                        <path d="M12 2L1 21H23L12 2ZM12 18C11.45 18 11 17.55 11 17C11 16.45 11.45 16 12 16C12.55 16 13 16.45 13 17C13 17.55 12.55 18 12 18ZM13 14H11V10H13V14Z"/>
                    </svg>
                    What Happens When Quota is Exceeded?
                </h3>
                <ul style="color: #c05621; font-size: 14px; line-height: 1.5; margin-left: 20px;">
                    <li>Service speed may be reduced to manage traffic</li>
                    <li>Additional charges may apply for overage usage</li>
                    <li>Service may be temporarily suspended until next billing cycle</li>
                    <li>Consider upgrading your plan for uninterrupted service</li>
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
