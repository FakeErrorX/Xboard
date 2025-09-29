/**
 * External configuration file for ProxyBD Theme
 * This file provides fallback configuration when not integrated with Laravel Xboard
 * When integrated with Laravel, most settings are managed through dashboard.blade.php
 * Logo path: images/logo.png
 * 
 * IMPORTANT NOTES FOR LARAVEL XBOARD INTEGRATION:
 * ===============================================
 * 
 * 1. When using this theme with Laravel Xboard, configurations are managed through:
 *    - Laravel admin panel (theme configuration section)
 *    - dashboard.blade.php file (PB_CONFIG_OVERRIDE object)
 * 
 * 2. Most settings in this file serve as fallbacks only
 * 
 * 3. To modify settings for Laravel integration:
 *    - Use the theme configuration in Laravel admin panel
 *    - Edit the dashboard.blade.php file for advanced customization
 * 
 * 4. Laravel integration automatically:
 *    - Handles API routing (/api/v1)
 *    - Manages user authentication
 *    - Provides real-time configuration updates
 * 
 * 5. This config.js is still loaded but Laravel config takes precedence
 */

window.PB_CONFIG = {
  // Panel type configuration - Xboard for Laravel integration
  PANEL_TYPE: 'Xboard', // Fixed for Laravel Xboard integration
  // Notes:
  // 1. V2board: Standard V2board, uses default request format
  // 2. Xiao-V2board: Xiao modified version, uses special request parameters
  // 3. Xboard: Xboard panel

  // =======================================================

  // API configuration - Auto-configured for Laravel integration
  // Note: When using Laravel dashboard.blade.php, these settings are overridden by PB_CONFIG_OVERRIDE
  API_CONFIG: {
    // API URL mode: 'static' = use static URL, 'auto' = derive from current domain
    urlMode: 'auto', // Laravel integration uses auto mode by default
    
    // Base URLs for static mode - fallback configuration
    // Note: Laravel integration overrides this with dynamic configuration
    staticBaseUrl: [
      '/api/v1' // Relative path works best with Laravel
    ],
    
    // Auto derive mode config (used when urlMode = 'auto')
    autoConfig: {
      // Use same protocol (http/https)
      useSameProtocol: true,
      
      // Append API path
      appendApiPath: true,
      
      // API path
      apiPath: '/api/v1'
    }
  },
  
  // Disable middleware for Laravel integration (Laravel handles API routing)
  API_MIDDLEWARE_ENABLED: false,
  
  // Middleware server URL (without path)
  API_MIDDLEWARE_URL: 'https://app.proxybd.com',
  
  // Middleware route prefix (keep consistent with middleware server)
  API_MIDDLEWARE_PATH: '/pb/pb',

  // ====================  Site basic configuration  ====================
  // Note: When using Laravel integration, these are managed through theme configuration
  SITE_CONFIG: {
    siteName: 'ProxyBD', // Fallback - Laravel overrides this
    siteDescription: 'ProxyBD BDIX Bypass Service', // Fallback - Laravel overrides this
    // copyright will automatically use the current year
    copyright: `© ${new Date().getFullYear()} ProxyBD. All Rights Reserved.`,
    
    // Whether to show site logo in title (true=show, false=hide)
    showLogo: true,
    
    // Landing page multi-language slogan
    landingText: {
      'en-US': 'Use ProxyBD BDIX Bypass Service'
    },
    
    // Custom landing page path (relative to public directory)
    // e.g. 'testlandingpage.html'
    // Leave empty to disable custom landing page
    customLandingPage: ''
  },

  // Default language and theme configuration
  DEFAULT_CONFIG: {
    // Default language ('en-US')
    defaultLanguage: 'en-US',
    
    // Default theme ('light' or 'dark')
    defaultTheme: 'light',

    // Primary color (hex)
    primaryColor: '#355cc2',

    // Enable landing page (true=enable, false=disable)
    enableLandingPage: true // enabled by default
  },

  // Auth page feature configuration
  AUTH_CONFIG: {
    // Auto-check agree to terms checkbox (true=auto, false=not checked)
    autoAgreeTerms: true,
    
    // Verification code config
    verificationCode: {
      // Show tip to check spam after sending code (true=show, false=hide)
      showCheckSpamTip: true,
      
      // Delay before showing the tip (ms)
      checkSpamTipDelay: 1000
    },
    
    // Auth page popup announcement config
    popup: {
      // Enable popup
      enabled: false,
      
      // Popup title
      title: "User Notice (configurable)",
      
      // Popup content (HTML supported)
      content: "<p><strong>Welcome to our service!</strong></p><p>Please note:</p><ul><li>Keep your account secure</li><li>Contact support if you need help</li></ul>",
      
      // Cooldown hours before showing again
      cooldownHours: 0,
      
      // Seconds to wait before user can close the popup (0 = no wait)
      closeWaitSeconds: 3
    }
  },

  // Auth page layout configuration
  AUTH_LAYOUT_CONFIG: {
    // Layout type: 'center' = centered card, 'split' = split columns
    layoutType: 'center',
    
    // Split layout config (effective when layoutType = 'split')
    splitLayout: {
      // Left section content config
      leftContent: {
        // Background image URL or path for the left side (optional)
        backgroundImage: 'https://www.loliapi.com/acg',
        
        // Site name (top-left)
        siteName: {
          // Show site name
          show: true,
          // Text color (white or black)
          color: 'white'
        },
        
        // Greeting (bottom-left)
        greeting: {
          // Show greeting
          show: true,
          // Text color (white or black)
          color: 'white'
        }
      }
    }
  },

  // Shop page configuration
  SHOP_CONFIG: {
    // Show hot sale badge in shop nav
    showHotSaleBadge: false,

    // Show plan feature cards (true=show, false=hide)
    showPlanFeatureCards: true, // shown by default

    // Auto-select the largest period tab; set to false to disable
    autoSelectMaxPeriod: false, // disabled by default
    
    // Hide period selection tabs (true=hide, false=show)
    hidePeriodTabs: false, // show by default
    
    // Low stock threshold (show low stock when quantity <= threshold and > 0)
    lowStockThreshold: 5,
    
    // Enable period discount calculation display (true=enable, false=disable)
    enableDiscountCalculation: true, // enabled by default
    
    // Display order of price periods (desc)
    periodOrder: [
      'three_year_price', // three years
      'two_year_price',   // two years
      'year_price',       // one year
      'half_year_price',  // half year
      'quarter_price',    // quarter
      'month_price',      // monthly
      'onetime_price'     // one-time
    ],

    // Shop popup configuration
    popup: {
      // Enable popup
      enabled: false,
      
      // Popup title
      title: "User Notice",
      
      // Popup content (HTML supported)
      content: "<p><strong>For standard plans, data resets on your monthly billing day. Unused data does not roll over.</strong></p>",
      
      // Cooldown hours before showing again
      cooldownHours: 0,
      
      // Seconds to wait before user can close the popup (0 = no wait)
      closeWaitSeconds: 0
    }
  },

  // Dashboard page configuration
  DASHBOARD_CONFIG: {
    // Show user email in welcome card (true=show, false=hide)
    showUserEmail: false,
    
    // Add highlight and background to "Import Subscription" button (true=add, false=not)
    importButtonHighlightBtnbgcolor: false,

    // ===============================

    // Enable reset traffic feature (true=enable, false=disable)
    enableResetTraffic: true,
    
    // When to show reset traffic button ('always', 'low', 'depleted')
    resetTrafficDisplayMode: 'low',
    
    // Low traffic threshold percentage (1-100)
    lowTrafficThreshold: 10,

    // ===============================
    
    // Enable renew plan feature (true=enable, false=disable)
    enableRenewPlan: true,
    
    // When to show renew button ('always', 'expiring', 'expired')
    renewPlanDisplayMode: 'always',
    
    // Expiring threshold in days (1-30)
    expiringThreshold: 7,

    // ===============================

    // Show online devices limit (true=show, false=hide; Xiao-V2board only)
    showOnlineDevicesLimit: true
  },

  // Client download configuration
  CLIENT_CONFIG: {
    // Show entire download card
    showDownloadCard: true,

    // Platform visibility (true=show, false=hide)
    showIOS: true,
    showAndroid: true,
    showMacOS: true,
    showWindows: true,
    showLinux: true,
    showOpenWrt: true,

    // Client download links (you can change to docs links to open in new tab)
    clientLinks: {
      ios: 'https://apps.apple.com/app/xxx',
      android: 'https://play.google.com/store/apps/xxx',
      macos: 'https://github.com/xxx/releases/latest',
      windows: 'https://github.com/xxx/releases/latest',
      linux: 'https://github.com/xxx/releases/latest',
      openwrt: 'https://github.com/xxx/releases/latest'
    },
    
    // Subscription import clients (note: some panels do not support SingBox import)

    // iOS clients
    showShadowrocket: true,
    showSurge: true,
    showStash: true,
    showQuantumultX: true,
    showSingboxIOS: true,
    showLoon: true,
    
    // Android clients
    showV2rayNG: true,
    showClashAndroid: true,
    showSurfboard: true,
    showClashMetaAndroid: true,
    showNekobox: true,
    showSingboxAndroid: true,
    showHiddifyAndroid: true,
    
    // Windows clients
    showClashWindows: true,
    showNekoray: true,
    showSingboxWindows: true,
    showHiddifyWindows: true,
    
    // macOS clients
    showClashX: true,
    showClashMetaX: true,
    showSurgeMac: true,
    showStashMac: true,
    showQuantumultXMac: true,
    showSingboxMac: true,
    showHiddifyMac: true
  },

  // ================ Xiao version configuration =======================

  // User profile page configuration
  PROFILE_CONFIG: {
    // Show gift card redeem section (true=show, false=hide)
    showGiftCardRedeem: false, // Only Xiao-V2board supports gift card redeem
    
    // Show recent login devices section (true=show, false=hide)
    showRecentDevices: true
  },

  // =======================================================

  // Captcha configuration
  CAPTCHA_CONFIG: {
    // Verification type: 'google' or 'cloudflare'
    captchaType: 'google',
    
    // Google reCAPTCHA configuration (v2 by default)
    google: {
      // Verify API URL (optional; uses official by default)
      verifyUrl: 'https://www.google.com/recaptcha/api/siteverify'
    },
    
    // Cloudflare Turnstile configuration
    cloudflare: {
      // Verify API URL (optional; uses official by default)
      verifyUrl: 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
    }
  },

  // Custom request headers configuration
  // Add global custom headers here; they will be included in all API requests. Can be used with firewall to block bad requests.
  // Configure only if you know what you're doing.
  CUSTOM_HEADERS: {
    // Enable custom headers
    enabled: false, // Disabled by default; ensure correct CORS server config before enabling
    
    // ⚠️ CORS Warning: Adding custom headers triggers browser preflight (OPTIONS)
    // Server must include Access-Control-Allow-Headers in response and list your custom headers
    // Example: Access-Control-Allow-Headers: "Content-Type, Authorization, X-Custom-Header, test"
    
    // Custom header list
    // Format: { "Header-Name": "Header-Value" }
    // Example: { "X-Custom-Header": "CustomValue" }
    headers: {
      // "test": "test123"
    }
  },

  // =======================================================

  // Payment configuration
  PAYMENT_CONFIG: {
    // Open payment link in new tab (true=new tab, false=current)
    openPaymentInNewTab: true, // enabled by default
    
    // Payment QR code size (px)
    qrcodeSize: 200,
    
    // Payment QR color
    qrcodeColor: '#000000',
    
    // Payment QR background
    qrcodeBackground: '#ffffff',
    
    // Auto check payment status (true=auto, false=manual)
    autoCheckPayment: true, // enabled by default
    
    // Interval for auto check (ms)
    autoCheckInterval: 5000, // default 5s
    
    // Max auto check times (0 = unlimited)
    autoCheckMaxTimes: 60, // default 60
    
    // Use payment modal for Safari instead of redirect (true=use modal)
    useSafariPaymentModal: true, // enabled by default
    
    // Auto select first payment method on page load
    autoSelectFirstMethod: true  // enabled by default
  },

  // Wallet deposit configuration
  WALLET_CONFIG: {
    // Preset deposit amounts
    presetAmounts: [6, 30, 68, 128, 256, 328, 648, 1280],
    
    // Default selected amount (null = none)
    defaultSelectedAmount: null,
    
    // Minimum deposit amount
    minimumDepositAmount: 1
  },

  // =======================================================
  
  // Invite page configuration
  INVITE_CONFIG: {
    // Show commission badge on invite button
    showCommissionBadge: false,

    // Records per page for commission history (min 10 per API limit)
    recordsPerPage: 10,

    // Invite link configuration
    inviteLinkConfig: {
      // Link mode: 'auto' = use current domain, 'custom' = use custom domain
      linkMode: 'auto',
      // Custom domain when linkMode = 'custom'
      customDomain: 'https://app.proxybd.com'
    }
  },

  // =======================================================

  // Ticket configuration
  TICKET_CONFIG: {
    // Include basic user info when creating a ticket (true=include)
    includeUserInfoInTicket: true, // include by default
    // Popup configuration
    popup: {
      // Enable popup
      enabled: false,
      // Popup title
      title: "Ticket Notice",
      // Popup content (HTML supported)
      content: "<p>Please describe your issue accurately before submitting a ticket so we can help you faster.</p>",
      // Cooldown hours before showing again
      cooldownHours: 24,
      // Seconds to wait before user can close the popup (0 = no wait)
      closeWaitSeconds: 0
    }
  },

  // Traffic log configuration
  TRAFFICLOG_CONFIG: {
    // Enable traffic log page (true=enable, false=disable)
    enableTrafficLog: true, // enabled by default
    
    // How many days of traffic records to show
    daysToShow: 30 // default 30 days
  },
  
  // Node list configuration
  NODES_CONFIG: {
    // Show node rate (true=show, false=hide; if false, allowViewNodeInfo will also be false)
    showNodeRate: true,
    
    // Show node details (host and port)
    showNodeDetails: false,
    
    // Allow viewing node details (controls detail button and modal)
    allowViewNodeInfo: true 
  },

  // Customer service configuration
  CUSTOMER_SERVICE_CONFIG: {
    // Enable customer service system
    enabled: false,
    
    // Customer service type: 'crisp' or 'other'
    // Note: When type is 'crisp', the system will automatically pass user data
    // including: user email, plan name, expiry date, available traffic, user balance
    type: 'crisp',
    
    // Customer service JS code. Paste your provider's embed code here
    customHtml: '',
    
    // Embed mode: 'popup' = separate page, 'embed' = embed on each page
    // 'popup': clicking the icon goes to a dedicated page
    // 'embed': window is embedded on each page (Crisp only)
    embedMode: 'embed',
    
    // Show customer service icon when not logged in
    showWhenNotLoggedIn: true,

    // Icon position configuration
    iconPosition: {
      // Desktop: distance from bottom-left
      desktop: {
        left: '20px',
        bottom: '20px'
      },
      // Mobile: distance from bottom-right
      mobile: {
        right: '20px',
        bottom: '100px'
      }
    }
  },
  
  // More page custom cards configuration
  MORE_PAGE_CONFIG: {
    // Enable custom cards feature
    enableCustomCards: false,
    
    // Custom card list
    // Notes:
    // 1. Each card must have unique id, title, description and URL
    // 2. Icon supports two ways:
    //    - svgIcon: inline SVG code (recommended)
    //    - icon: predefined @tabler/icons-vue icon names (only imported ones)
    //    - Imported names: IconFileText, IconShoppingCart, IconUser, IconDevices, IconSettings, IconTicket, IconLogout, IconBrandTelegram, IconBrandGithub, IconBrandDiscord, IconBrandTwitter, IconMailForward, IconChevronRight, IconServer, IconMessages, IconChartBar, IconWallet
    // 3. SVG should use currentColor to follow theme color
    // 4. You can get SVGs from https://tabler.io/icons
    customCards: [
      // Example card
      {
        id: 'github',                  // unique card id
        title: 'GitHub',               // card title
        description: 'Visit our GitHub', // card description
        svgIcon: '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-brand-github" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 19c-4.3 1.4 -4.3 -2.5 -6 -3m12 5v-3.5c0 -1 .1 -1.4 -.5 -2c2.8 -.3 5.5 -1.4 5.5 -6a4.6 4.6 0 0 0 -1.3 -3.2a4.2 4.2 0 0 0 -.1 -3.2s-1.1 -.3 -3.5 1.3a12.3 12.3 0 0 0 -6.2 0c-2.4 -1.6 -3.5 -1.3 -3.5 -1.3a4.2 4.2 0 0 0 -.1 3.2a4.6 4.6 0 0 0 -1.3 3.2c0 4.6 2.7 5.7 5.5 6c-.6 .6 -.6 1.2 -.5 2v3.5" /></svg>',
        url: 'https://github.com',     // URL to open when clicking the card
        openInNewTab: true             // Open in new tab
      },
      {
        id: 'telegram',
        title: 'Telegram',
        description: 'Join our Telegram channel',
        svgIcon: '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-brand-telegram" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 10l-4 4l6 6l4 -16l-18 7l4 2l2 6l3 -4" /></svg>',
        url: 'https://t.me/your_group',
        openInNewTab: true
      }
      // You can add more cards...
      // Note the trailing comma rules
      // Use svgIcon to insert custom SVG and ensure it uses currentColor
    ]
  }
};
