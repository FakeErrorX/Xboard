<!DOCTYPE html>
<html lang="{{ $theme_config['default_language'] ?? 'en-US' }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <link rel="icon" href="/theme/{{$theme}}/images/logo.png">
  <!-- Set title with theme config -->
  <title>{{ $theme_config['site_name'] ?? $title }}</title>
  
  <!-- Preload fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <script>
    // config.js loader adjustable parameters
    window.PB_LOADER = {
      // config.js file name (keep default if in root)
      configFileName: 'config.js',
      // Single-load timeout (ms)
      configTimeout: 3000,
      // Max retries
      maxRetries: 2
    };

    // Cache busting for config updates - use Laravel timestamp
    window.CONFIG_TIMESTAMP = '{{ time() }}';
  </script>
  
  <!-- Global styles -->
  <style>
     @media (min-width: 769px) {html, body, div, main, section, article, aside, nav {scrollbar-width: none !important;-ms-overflow-style: none !important;}::-webkit-scrollbar, ::-webkit-scrollbar-button, ::-webkit-scrollbar-track, ::-webkit-scrollbar-track-piece, ::-webkit-scrollbar-thumb, ::-webkit-scrollbar-corner, ::-webkit-resizer {display: none !important;width: 0 !important;height: 0 !important;background: transparent !important;}}html, body {width: 100%;height: 100%;}@media (min-width: 769px) {* {scrollbar-width: none !important;-ms-overflow-style: none !important;}} html, body {margin: 0;padding: 0;width: 100%;height: 100%;overflow-x: hidden;}#app {width: 100%;height: 100%;}html.preloader-active, body.preloader-active {overflow: hidden !important;}
  </style>
  
  <!-- Version badge styles -->
  <style>
    .app-version {position: fixed;left: 6px;bottom: 4px;font-size: 9px;color: rgba(0, 0, 0, 0.35);user-select: none;pointer-events: none;z-index: 5;}
  </style>
  
  <script type="module" crossorigin src="/theme/{{$theme}}/index.js?v={{ time() }}"></script>
  <link rel="stylesheet" crossorigin href="/theme/{{$theme}}/index.css?v={{ time() }}">
</head>
<body>
  <div id="app"></div>
  
  <!-- Dynamic configuration script that integrates Laravel theme config with ProxyBD config -->
  <script>
    // Laravel integration data that can be accessed by ProxyBD
    window.LARAVEL_DATA = {
      title: '{{ $title }}',
      theme: '{{ $theme }}',
      version: '{{ $version }}',
      description: '{{ $description }}',
      logo: '{{ $logo }}',
      assets_path: '/theme/{{ $theme }}',
      routerBase: "/",
      apiBase: '{{ url('/api/v1') }}',
      baseUrl: '{{ url('/') }}'
    };

    // Override ProxyBD config with Laravel theme configuration
    window.PB_CONFIG_OVERRIDE = {
      // Panel type configuration
      PANEL_TYPE: '{{ $theme_config['panel_type'] ?? 'Xboard' }}',
      
      // API configuration - Force auto mode with current domain
      API_CONFIG: {
        urlMode: 'auto',
        autoConfig: {
          useSameProtocol: true,
          appendApiPath: true,
          apiPath: '/api/v1'
        },
        // Override staticBaseUrl to prevent external calls
        staticBaseUrl: ['{{ url('/api/v1') }}']
      },
      
      // Disable middleware since we're using Laravel directly
      API_MIDDLEWARE_ENABLED: false,
      API_MIDDLEWARE_URL: '',
      API_MIDDLEWARE_PATH: '',
      
      // Site basic configuration
      SITE_CONFIG: {
        siteName: '{{ $theme_config['site_name'] ?? $title }}',
        siteDescription: '{{ $theme_config['site_description'] ?? $description }}',
        copyright: `© ${new Date().getFullYear()} {{ $theme_config['site_name'] ?? $title }}. All Rights Reserved.`,
        showLogo: {{ ($theme_config['show_logo'] ?? 'true') === 'true' ? 'true' : 'false' }},
        landingText: {
          '{{ $theme_config['default_language'] ?? 'en-US' }}': '{{ $theme_config['landing_text'] ?? 'Use ProxyBD BDIX Bypass Service' }}'
        },
        customLandingPage: ''
      },

      // Default configuration
      DEFAULT_CONFIG: {
        defaultLanguage: '{{ $theme_config['default_language'] ?? 'en-US' }}',
        defaultTheme: '{{ $theme_config['default_theme'] ?? 'light' }}',
        primaryColor: '{{ $theme_config['primary_color'] ?? '#355cc2' }}',
        enableLandingPage: {{ ($theme_config['enable_landing_page'] ?? 'true') === 'true' ? 'true' : 'false' }}
      },

      // Auth page feature configuration
      AUTH_CONFIG: {
        autoAgreeTerms: {{ ($theme_config['auto_agree_terms'] ?? 'true') === 'true' ? 'true' : 'false' }},
        verificationCode: {
          showCheckSpamTip: true,
          checkSpamTipDelay: 1000
        },
        popup: {
          enabled: false,
          title: "User Notice",
          content: "",
          cooldownHours: 0,
          closeWaitSeconds: 3
        }
      },

      // Auth page layout configuration
      AUTH_LAYOUT_CONFIG: {
        layoutType: '{{ $theme_config['auth_layout_type'] ?? 'center' }}',
        splitLayout: {
          leftContent: {
            backgroundImage: 'https://www.loliapi.com/acg',
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
        showHotSaleBadge: {{ ($theme_config['show_hot_sale_badge'] ?? 'false') === 'true' ? 'true' : 'false' }},
        showPlanFeatureCards: {{ ($theme_config['show_plan_feature_cards'] ?? 'true') === 'true' ? 'true' : 'false' }},
        autoSelectMaxPeriod: {{ ($theme_config['auto_select_max_period'] ?? 'false') === 'true' ? 'true' : 'false' }},
        hidePeriodTabs: {{ ($theme_config['hide_period_tabs'] ?? 'false') === 'true' ? 'true' : 'false' }},
        lowStockThreshold: {{ $theme_config['low_stock_threshold'] ?? 5 }},
        enableDiscountCalculation: {{ ($theme_config['enable_discount_calculation'] ?? 'true') === 'true' ? 'true' : 'false' }},
        periodOrder: [
          'three_year_price',
          'two_year_price',
          'year_price',
          'half_year_price',
          'quarter_price',
          'month_price',
          'onetime_price'
        ],
        popup: {
          enabled: false,
          title: "User Notice",
          content: "",
          cooldownHours: 0,
          closeWaitSeconds: 0
        }
      },

      // Dashboard page configuration
      DASHBOARD_CONFIG: {
        showUserEmail: {{ ($theme_config['show_user_email'] ?? 'false') === 'true' ? 'true' : 'false' }},
        importButtonHighlightBtnbgcolor: false,
        enableResetTraffic: {{ ($theme_config['enable_reset_traffic'] ?? 'true') === 'true' ? 'true' : 'false' }},
        resetTrafficDisplayMode: '{{ $theme_config['reset_traffic_display_mode'] ?? 'low' }}',
        lowTrafficThreshold: {{ $theme_config['low_traffic_threshold'] ?? 10 }},
        enableRenewPlan: {{ ($theme_config['enable_renew_plan'] ?? 'true') === 'true' ? 'true' : 'false' }},
        renewPlanDisplayMode: '{{ $theme_config['renew_plan_display_mode'] ?? 'always' }}',
        expiringThreshold: {{ $theme_config['expiring_threshold'] ?? 7 }},
        showOnlineDevicesLimit: true
      },

      // Client download configuration
      CLIENT_CONFIG: {
        showDownloadCard: {{ ($theme_config['show_download_card'] ?? 'true') === 'true' ? 'true' : 'false' }},
        showIOS: true,
        showAndroid: true,
        showMacOS: true,
        showWindows: true,
        showLinux: true,
        showOpenWrt: true,
        clientLinks: {
          ios: 'https://apps.apple.com/app/xxx',
          android: 'https://play.google.com/store/apps/xxx',
          macos: 'https://github.com/xxx/releases/latest',
          windows: 'https://github.com/xxx/releases/latest',
          linux: 'https://github.com/xxx/releases/latest',
          openwrt: 'https://github.com/xxx/releases/latest'
        },
        showShadowrocket: true,
        showSurge: true,
        showStash: true,
        showQuantumultX: true,
        showSingboxIOS: true,
        showLoon: true,
        showV2rayNG: true,
        showClashAndroid: true,
        showSurfboard: true,
        showClashMetaAndroid: true,
        showNekobox: true,
        showSingboxAndroid: true,
        showHiddifyAndroid: true,
        showClashWindows: true,
        showNekoray: true,
        showSingboxWindows: true,
        showHiddifyWindows: true,
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

      // Payment configuration
      PAYMENT_CONFIG: {
        openPaymentInNewTab: {{ ($theme_config['open_payment_new_tab'] ?? 'true') === 'true' ? 'true' : 'false' }},
        qrcodeSize: {{ $theme_config['qrcode_size'] ?? 200 }},
        qrcodeColor: '#000000',
        qrcodeBackground: '#ffffff',
        autoCheckPayment: {{ ($theme_config['auto_check_payment'] ?? 'true') === 'true' ? 'true' : 'false' }},
        autoCheckInterval: {{ $theme_config['auto_check_interval'] ?? 5000 }},
        autoCheckMaxTimes: 60,
        useSafariPaymentModal: true,
        autoSelectFirstMethod: true
      },

      // Wallet deposit configuration
      WALLET_CONFIG: {
        presetAmounts: [{{ $theme_config['wallet_preset_amounts'] ?? '6,30,68,128,256,328,648,1280' }}],
        defaultSelectedAmount: null,
        minimumDepositAmount: {{ $theme_config['minimum_deposit_amount'] ?? 1 }}
      },

      // Invite page configuration
      INVITE_CONFIG: {
        showCommissionBadge: {{ ($theme_config['show_commission_badge'] ?? 'false') === 'true' ? 'true' : 'false' }},
        recordsPerPage: 10,
        inviteLinkConfig: {
          linkMode: 'auto',
          customDomain: '{{ url('/') }}'
        }
      },

      // Ticket configuration
      TICKET_CONFIG: {
        includeUserInfoInTicket: true,
        popup: {
          enabled: false,
          title: "Ticket Notice",
          content: "",
          cooldownHours: 24,
          closeWaitSeconds: 0
        }
      },

      // Traffic log configuration
      TRAFFICLOG_CONFIG: {
        enableTrafficLog: {{ ($theme_config['enable_traffic_log'] ?? 'true') === 'true' ? 'true' : 'false' }},
        daysToShow: {{ $theme_config['traffic_log_days'] ?? 30 }}
      },

      // Node list configuration
      NODES_CONFIG: {
        showNodeRate: {{ ($theme_config['show_node_rate'] ?? 'true') === 'true' ? 'true' : 'false' }},
        showNodeDetails: {{ ($theme_config['show_node_details'] ?? 'false') === 'true' ? 'true' : 'false' }},
        allowViewNodeInfo: {{ ($theme_config['allow_view_node_info'] ?? 'true') === 'true' ? 'true' : 'false' }}
      },

      // Customer service configuration
      CUSTOMER_SERVICE_CONFIG: {
        enabled: false,
        type: 'crisp',
        customHtml: '',
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

    // Laravel integration data that can be accessed by ProxyBD
    window.LARAVEL_DATA = {
      title: '{{ $title }}',
      theme: '{{ $theme }}',
      version: '{{ $version }}',
      description: '{{ $description }}',
      logo: '{{ $logo }}',
      assets_path: '/theme/{{ $theme }}',
      routerBase: "/"
    };

    // Force API base URL to prevent external calls
    window.API_BASE_URL = '{{ url('/api/v1') }}';
    
    // Override any external API calls - this will run before ProxyBD initializes
    (function() {
      const originalFetch = window.fetch;
      window.fetch = function(url, options) {
        // Intercept any API calls and redirect to local Laravel
        if (typeof url === 'string') {
          if (url.includes('proxybd.com') || url.includes('/user/') || url.includes('/api/')) {
            // Replace with local Laravel API
            if (url.startsWith('http')) {
              // External URL - extract the path part
              const urlObj = new URL(url);
              url = '{{ url('/') }}' + urlObj.pathname;
            } else if (!url.startsWith('{{ url('/') }}')) {
              // Relative URL - make it absolute to local server
              if (url.startsWith('/')) {
                url = '{{ url('/') }}' + url;
              } else {
                url = '{{ url('/api/v1') }}/' + url;
              }
            }
          }
        }
        console.log('API Request intercepted:', url);
        return originalFetch.call(this, url, options);
      };
    })();
    
    // Override XMLHttpRequest for axios/other HTTP libraries
    (function() {
      const originalOpen = XMLHttpRequest.prototype.open;
      XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
        if (typeof url === 'string') {
          if (url.includes('proxybd.com') || url.includes('/user/') || url.includes('/api/')) {
            if (url.startsWith('http')) {
              const urlObj = new URL(url);
              url = '{{ url('/') }}' + urlObj.pathname;
            } else if (!url.startsWith('{{ url('/') }}')) {
              if (url.startsWith('/')) {
                url = '{{ url('/') }}' + url;
              } else {
                url = '{{ url('/api/v1') }}/' + url;
              }
            }
          }
        }
        console.log('XHR Request intercepted:', url);
        return originalOpen.call(this, method, url, async, user, password);
      };
    })();
    
    // Override any external API calls
    if (typeof window.axios !== 'undefined') {
      window.axios.defaults.baseURL = '{{ url('/api/v1') }}';
    }
    
    // Debug information
    console.log('Laravel API Base:', window.LARAVEL_DATA.apiBase);
    console.log('Forced API Base:', window.API_BASE_URL);
    console.log('Config Timestamp:', window.CONFIG_TIMESTAMP);
    console.log('Theme Config Keys:', Object.keys(window.PB_CONFIG_OVERRIDE));
    
    // Global debug function for admin panel testing
    window.debugThemeConfig = function() {
      console.log('=== ProxyBD Theme Debug ===');
      console.log('Laravel Data:', window.LARAVEL_DATA);
      console.log('Config Override:', window.PB_CONFIG_OVERRIDE);
      console.log('Final Config:', window.PB_CONFIG);
      console.log('Current Time:', new Date().toLocaleString());
    };

  <!-- Version number display -->
  <div class="app-version">{{ $version }}</div>
  
  <!-- Custom HTML from theme config -->
  {!! $theme_config['custom_html'] ?? '' !!}
</body>
</html>