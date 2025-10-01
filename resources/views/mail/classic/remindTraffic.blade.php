<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traffic Usage Notification</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Georgia', 'Times New Roman', serif; line-height: 1.7; background: #fafafa; margin: 0; padding: 30px 20px; }
        .email-wrapper { max-width: 650px; margin: 0 auto; background: #ffffff; border: 1px solid #e1e8ed; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .header-stripe { height: 6px; background: linear-gradient(90deg, #3b82f6, #06b6d4, #10b981); }
        .header { background: #ffffff; padding: 45px 40px 35px; text-align: center; border-bottom: 2px solid #f1f5f9; position: relative; }
        .header::after { content: ''; position: absolute; bottom: -2px; left: 50%; transform: translateX(-50%); width: 80px; height: 2px; background: #06b6d4; }
        .logo-container { display: inline-block; width: 90px; height: 90px; border: 3px solid #e2e8f0; border-radius: 8px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .logo-container img { width: 100%; height: 100%; object-fit: cover; }
        .company-name { color: #1e293b; font-size: 26px; font-weight: 600; margin: 0; letter-spacing: -0.5px; }
        .content { padding: 45px 40px; }
        .usage-badge { display: inline-flex; align-items: center; background: #e0f2fe; color: #0369a1; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 500; margin-bottom: 25px; }
        .usage-badge::before { content: '📊'; margin-right: 8px; font-size: 16px; }
        .title { color: #0f172a; font-size: 28px; font-weight: 700; margin-bottom: 15px; line-height: 1.3; }
        .subtitle { color: #64748b; font-size: 18px; margin-bottom: 35px; }
        .traffic-section { background: linear-gradient(135deg, #f0f9ff 0%, #bae6fd 100%); border: 2px solid #06b6d4; border-radius: 12px; padding: 40px; margin: 35px 0; text-align: center; position: relative; }
        .traffic-section::before { content: '📈'; position: absolute; top: -15px; left: 50%; transform: translateX(-50%); background: #ffffff; padding: 0 12px; font-size: 24px; }
        .traffic-label { color: #0369a1; font-size: 16px; font-weight: 600; margin-bottom: 20px; }
        .traffic-message { color: #4b5563; font-size: 16px; line-height: 1.8; margin-bottom: 30px; }
        .traffic-message strong { color: #374151; }
        .progress-indicator { display: inline-flex; align-items: center; background: #fef3c7; color: #92400e; padding: 12px 20px; border-radius: 25px; font-size: 14px; font-weight: 600; margin: 20px 0; }
        .progress-indicator::before { content: '⚡'; margin-right: 8px; }
        .action-area { margin: 40px 0; }
        .primary-button { display: inline-block; background: #06b6d4; color: #ffffff; text-decoration: none; padding: 18px 40px; border-radius: 6px; font-weight: 600; font-size: 18px; transition: all 0.2s ease; border: 2px solid #06b6d4; }
        .primary-button:hover { background: #0891b2; border-color: #0891b2; }
        .primary-button svg { margin-right: 10px; vertical-align: middle; }
        .stats-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 30px; margin: 30px 0; }
        .stats-section h4 { color: #374151; font-size: 18px; margin-bottom: 20px; text-align: center; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-top: 20px; }
        .stat-item { text-align: center; padding: 20px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; }
        .stat-number { font-size: 24px; font-weight: 700; color: #1f2937; margin-bottom: 5px; }
        .stat-label { color: #6b7280; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .recommendations { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 25px; margin: 30px 0; }
        .recommendations h4 { color: #166534; font-size: 16px; margin-bottom: 12px; display: flex; align-items: center; }
        .recommendations h4::before { content: '💡'; margin-right: 8px; }
        .recommendations ul { color: #15803d; font-size: 14px; line-height: 1.6; margin-left: 20px; }
        .recommendations li { margin-bottom: 8px; }
        .footer { background: #f8fafc; padding: 40px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer-content { margin-bottom: 25px; }
        .footer-text { color: #6b7280; font-size: 15px; margin-bottom: 20px; }
        .links-section { margin: 25px 0; }
        .footer-link { color: #06b6d4; text-decoration: none; margin: 0 15px; font-size: 14px; padding: 8px 12px; border-radius: 4px; transition: all 0.2s; }
        .footer-link:hover { background: #bae6fd; }
        .divider { border: none; height: 1px; background: linear-gradient(90deg, transparent, #d1d5db, transparent); margin: 25px 0; }
        .copyright { color: #9ca3af; font-size: 13px; }
        @media (max-width: 640px) { 
            body { padding: 15px 10px; }
            .email-wrapper { border: none; box-shadow: none; }
            .header, .content, .footer { padding: 25px 20px; }
            .title { font-size: 24px; }
            .traffic-section { padding: 30px 20px; }
            .primary-button { padding: 16px 30px; font-size: 16px; }
            .stats-grid { grid-template-columns: 1fr; gap: 15px; }
            .stats-section { padding: 20px 15px; }
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
            <div class="usage-badge">Traffic Usage Report</div>
            
            <h1 class="title">Data Usage Alert</h1>
            <p class="subtitle">Monitor your bandwidth consumption</p>
            
            <div class="traffic-section">
                <div class="traffic-label">Traffic Monitoring Alert</div>
                <div class="traffic-message">
                    <strong>Dear Valued Customer,</strong><br>
                    We're writing to inform you about your current traffic usage on {{$name}}. Monitoring your data consumption helps ensure optimal service performance and helps you plan your usage accordingly.
                </div>
                <div class="progress-indicator">Current usage status updated</div>
                
                <div class="action-area">
                    <a href="{{$url}}/#/profile" class="primary-button">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path d="M3 13H11L9 15L12 18L15 15L13 13H21V11H13L15 9L12 6L9 9L11 11H3V13Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        View Usage Details
                    </a>
                </div>
            </div>
            
            <div class="stats-section">
                <h4>Your Usage Overview</h4>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number">{{$percent}}%</div>
                        <div class="stat-label">Usage Level</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">{{$transfer}}</div>
                        <div class="stat-label">Data Used</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">Active</div>
                        <div class="stat-label">Service Status</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">{{$name}}</div>
                        <div class="stat-label">Service Plan</div>
                    </div>
                </div>
            </div>
            
            <div class="recommendations">
                <h4>Optimization Recommendations</h4>
                <ul>
                    <li>Monitor your daily usage patterns to optimize bandwidth allocation</li>
                    <li>Consider upgrading your plan if you frequently reach high usage levels</li>
                    <li>Use our analytics dashboard to track detailed usage statistics</li>
                    <li>Set up usage alerts to receive notifications at custom thresholds</li>
                    <li>Contact our support team for personalized usage optimization advice</li>
                </ul>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-content">
                <div class="footer-text">
                    Thank you for choosing {{$name}} as your trusted service provider. We're committed to delivering exceptional performance.
                </div>
                
                <div class="links-section">
                    <a href="{{$url}}/#/profile" class="footer-link">Usage Dashboard</a>
                    <a href="{{$url}}/#/tickets" class="footer-link">Help Center</a>
                    <a href="{{$url}}" class="footer-link">Support</a>
                </div>
            </div>
            
            <hr class="divider">
            
            <div class="copyright">
                © {{ date('Y') }} {{$name}}. All rights reserved.<br>
                This usage report was generated automatically to keep you informed.
            </div>
        </div>
    </div>
</body>
</html>
