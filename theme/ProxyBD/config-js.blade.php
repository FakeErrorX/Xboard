/**
 * Dynamic configuration file for ProxyBD Theme
 * Generated automatically by Laravel Xboard based on admin settings
 * Last updated: {{ date('Y-m-d H:i:s') }}
 * 
 * This file is dynamically generated. Do not edit manually.
 * Changes should be made through the Laravel admin panel.
 */

window.PB_CONFIG = {
  // Panel type - fixed for Laravel Xboard integration
  PANEL_TYPE: 'Xboard',

  // API configuration - managed by Laravel
  API_CONFIG: {
    urlMode: '{{ $theme_config['api_mode'] ?? 'auto' }}',
    @if(($theme_config['api_mode'] ?? 'auto') === 'static' && !empty($theme_config['static_api_url']))
    staticBaseUrl: ['{{ $theme_config['static_api_url'] }}'],
    @else
    staticBaseUrl: ['/api/v1'],
    @endif
    autoConfig: {
      useSameProtocol: true,
      appendApiPath: true,
      apiPath: '/api/v1'
    }
  },
  
  // Disable middleware for Laravel integration
  API_MIDDLEWARE_ENABLED: false,
  
  // Middleware configuration (not used in Laravel integration)
  API_MIDDLEWARE_URL: '{{ request()->getSchemeAndHttpHost() }}',
  API_MIDDLEWARE_PATH: '/pb/pb',

  // Site configuration from Laravel admin settings
  SITE_CONFIG: {
    siteName: '{{ $theme_config['site_name'] ?? $title }}',
    siteDescription: '{{ $theme_config['site_description'] ?? $description }}',
    copyright: `© ${new Date().getFullYear()} {{ $theme_config['site_name'] ?? $title }}. All Rights Reserved.`,
    showLogo: true,
    landingText: {
      'en-US': '{{ $theme_config['landing_text_en'] ?? 'Use ProxyBD BDIX Bypass Service' }}',
      'bn-BD': '{{ $theme_config['landing_text_bn'] ?? 'ProxyBD BDIX বাইপাস সেবা ব্যবহার করুন' }}'
    },
    customLandingPage: ''
  },

  // Default configuration from theme settings
  DEFAULT_CONFIG: {
    defaultLanguage: '{{ $theme_config['default_language'] ?? 'en-US' }}',
    defaultTheme: '{{ $theme_config['default_theme'] ?? 'light' }}',
    primaryColor: '{{ $theme_config['primary_color'] ?? '#355cc2' }}',
    enableLandingPage: {{ ($theme_config['enable_landing_page'] ?? 'true') === 'true' ? 'true' : 'false' }}
  },

  // Auth configuration from theme settings
  AUTH_CONFIG: {
    autoAgreeTerms: {{ ($theme_config['auto_agree_terms'] ?? 'true') === 'true' ? 'true' : 'false' }},
    verificationCode: {
      showCheckSpamTip: true,
      checkSpamTipDelay: 1000
    },
    popup: {
      enabled: false,
      title: "User Notice",
      content: "<p><strong>Welcome to {{ $theme_config['site_name'] ?? $title }}!</strong></p>",
      cooldownHours: 24,
      closeWaitSeconds: 3
    }
  },

  // Auth page layout configuration
  AUTH_LAYOUT_CONFIG: {
    layoutType: 'center',
    splitLayout: {
      leftContent: {
        backgroundImage: '',
        siteName: {
          show: true,
          color: 'white'
        },
        greeting: {
          show: true,
          color: 'white'
        }
      }
    }
  },

  // Shop page configuration
  SHOP_CONFIG: {
    showHotSaleBadge: false,
    showPlanFeatureCards: true,
    autoSelectMaxPeriod: false,
    hidePeriodTabs: false,
    lowStockThreshold: 5,
    enableDiscountCalculation: true,
    periodOrder: [
      'three_year_price', 'two_year_price', 'year_price',
      'half_year_price', 'quarter_price', 'month_price', 'onetime_price'
    ],
    popup: {
      enabled: false,
      title: "Shop Notice",
      content: "<p>Welcome to our shop!</p>",
      cooldownHours: 24,
      closeWaitSeconds: 0
    }
  },

  // Dashboard configuration from theme settings
  DASHBOARD_CONFIG: {
    showUserEmail: {{ ($theme_config['show_user_email'] ?? 'false') === 'true' ? 'true' : 'false' }},
    importButtonHighlightBtnbgcolor: false,
    enableResetTraffic: {{ ($theme_config['enable_reset_traffic'] ?? 'true') === 'true' ? 'true' : 'false' }},
    resetTrafficDisplayMode: '{{ $theme_config['reset_traffic_mode'] ?? 'low' }}',
    lowTrafficThreshold: {{ $theme_config['low_traffic_threshold'] ?? '10' }},
    enableRenewPlan: {{ ($theme_config['enable_renew_plan'] ?? 'true') === 'true' ? 'true' : 'false' }},
    renewPlanDisplayMode: 'always',
    expiringThreshold: 7,
    showOnlineDevicesLimit: true
  },

  // Client download configuration
  CLIENT_CONFIG: {
    showDownloadCard: true,
    showIOS: true,
    showAndroid: true,
    showMacOS: true,
    showWindows: true,
    showLinux: true,
    showOpenWrt: true,
    
    // Client download links
    clientLinks: {
      ios: 'https://apps.apple.com/app/shadowrocket/id932747118',
      android: 'https://play.google.com/store/apps/details?id=com.v2ray.ang',
      macos: 'https://github.com/yichengchen/clashX/releases/latest',
      windows: 'https://github.com/Fndroid/clash_for_windows_pkg/releases/latest',
      linux: 'https://github.com/Dreamacro/clash/releases/latest',
      openwrt: 'https://github.com/vernesong/OpenClash/releases/latest'
    },
    
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

  // User profile page configuration
  PROFILE_CONFIG: {
    showGiftCardRedeem: false,
    showRecentDevices: true
  },

  // Captcha configuration
  CAPTCHA_CONFIG: {
    captchaType: 'google',
    google: {
      verifyUrl: 'https://www.google.com/recaptcha/api/siteverify'
    },
    cloudflare: {
      verifyUrl: 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
    }
  },

  // Custom request headers configuration
  CUSTOM_HEADERS: {
    enabled: false,
    headers: {}
  },

  // Payment configuration from theme settings
  PAYMENT_CONFIG: {
    openPaymentInNewTab: {{ ($theme_config['payment_new_tab'] ?? 'true') === 'true' ? 'true' : 'false' }},
    qrcodeSize: 200,
    qrcodeColor: '#000000',
    qrcodeBackground: '#ffffff',
    autoCheckPayment: true,
    autoCheckInterval: 5000,
    autoCheckMaxTimes: 60,
    useSafariPaymentModal: true,
    autoSelectFirstMethod: true
  },

  // Wallet deposit configuration
  WALLET_CONFIG: {
    presetAmounts: [6, 30, 68, 128, 256, 328, 648, 1280],
    defaultSelectedAmount: null,
    minimumDepositAmount: 1
  },

  // Invite page configuration
  INVITE_CONFIG: {
    showCommissionBadge: false,
    recordsPerPage: 10,
    inviteLinkConfig: {
      linkMode: 'auto',
      customDomain: '{{ request()->getSchemeAndHttpHost() }}'
    }
  },

  // Ticket configuration
  TICKET_CONFIG: {
    includeUserInfoInTicket: true,
    popup: {
      enabled: false,
      title: "Ticket Notice",
      content: "<p>Please describe your issue accurately.</p>",
      cooldownHours: 24,
      closeWaitSeconds: 0
    }
  },

  // Traffic log configuration
  TRAFFICLOG_CONFIG: {
    enableTrafficLog: true,
    daysToShow: 30
  },
  
  // Node list configuration
  NODES_CONFIG: {
    showNodeRate: true,
    showNodeDetails: false,
    allowViewNodeInfo: true 
  },

  // Customer service configuration
  CUSTOMER_SERVICE_CONFIG: {
    enabled: false,
    type: 'crisp',
    customHtml: `{!! $theme_config['custom_html'] ?? '' !!}`,
    embedMode: 'embed',
    showWhenNotLoggedIn: true,
    iconPosition: {
      desktop: {
        left: '20px',
        bottom: '20px'
      },
      mobile: {
        right: '20px',
        bottom: '100px'
      }
    }
  },
  
  // More page custom cards configuration
  MORE_PAGE_CONFIG: {
    enableCustomCards: false,
    customCards: []
  }
};