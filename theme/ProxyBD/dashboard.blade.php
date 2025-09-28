<!DOCTYPE html>
<html lang="{{ $theme_config['default_language'] ?? 'en-US' }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <link rel="icon" href="/theme/{{ $theme }}/images/logo.png">
  
  <!-- Dynamic title from Laravel settings -->
  <title>{{ $title }}</title>
  
  <!-- Preload fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Global styles -->
  <style>
    @media (min-width: 769px) {html, body, div, main, section, article, aside, nav {scrollbar-width: none !important;-ms-overflow-style: none !important;}::-webkit-scrollbar, ::-webkit-scrollbar-button, ::-webkit-scrollbar-track, ::-webkit-scrollbar-track-piece, ::-webkit-scrollbar-thumb, ::-webkit-scrollbar-corner, ::-webkit-resizer {display: none !important;width: 0 !important;height: 0 !important;background: transparent !important;}}html, body {width: 100%;height: 100%;}@media (min-width: 769px) {* {scrollbar-width: none !important;-ms-overflow-style: none !important;}} html, body {margin: 0;padding: 0;width: 100%;height: 100%;overflow-x: hidden;}#app {width: 100%;height: 100%;}html.preloader-active, body.preloader-active {overflow: hidden !important;}
    
    /* Custom CSS from theme configuration */
    {!! $theme_config['custom_css'] ?? '' !!}
  </style>
  
  <!-- Version badge styles -->
  <style>
    .app-version {position: fixed;left: 6px;bottom: 4px;font-size: 9px;color: rgba(0, 0, 0, 0.35);user-select: none;pointer-events: none;z-index: 5;}
  </style>
  
  <!-- Laravel to ProxyBD Configuration Bridge -->
  <script>
    // Laravel-managed configuration override for ProxyBD theme
    window.PB_CONFIG_OVERRIDE = {
      // Panel type - always Xboard for this integration
      PANEL_TYPE: 'Xboard',

      // API configuration based on theme settings
      API_CONFIG: {
        urlMode: '{{ $theme_config['api_mode'] ?? 'auto' }}',
        @if(($theme_config['api_mode'] ?? 'auto') === 'static' && !empty($theme_config['static_api_url']))
        staticBaseUrl: ['{{ $theme_config['static_api_url'] }}'],
        @endif
        autoConfig: {
          useSameProtocol: true,
          appendApiPath: true,
          apiPath: '/api/v1'
        }
      },
      
      // Disable middleware since we're using Laravel directly
      API_MIDDLEWARE_ENABLED: false,

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
        popup: { enabled: false }
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

      // Standard configurations that work well with Xboard
      AUTH_LAYOUT_CONFIG: { layoutType: 'center' },
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
        popup: { enabled: false }
      },
      CLIENT_CONFIG: {
        showDownloadCard: true,
        showIOS: true, showAndroid: true, showMacOS: true, showWindows: true, showLinux: true, showOpenWrt: true,
        showShadowrocket: true, showSurge: true, showStash: true, showQuantumultX: true, showSingboxIOS: true, showLoon: true,
        showV2rayNG: true, showClashAndroid: true, showSurfboard: true, showClashMetaAndroid: true, showNekobox: true,
        showSingboxAndroid: true, showHiddifyAndroid: true,
        showClashWindows: true, showNekoray: true, showSingboxWindows: true, showHiddifyWindows: true,
        showClashX: true, showClashMetaX: true, showSurgeMac: true, showStashMac: true, showQuantumultXMac: true,
        showSingboxMac: true, showHiddifyMac: true
      },
      PROFILE_CONFIG: {
        showGiftCardRedeem: false,
        showRecentDevices: true
      },
      CAPTCHA_CONFIG: { captchaType: 'google' },
      CUSTOM_HEADERS: { enabled: false },
      WALLET_CONFIG: {
        presetAmounts: [6, 30, 68, 128, 256, 328, 648, 1280],
        defaultSelectedAmount: null,
        minimumDepositAmount: 1
      },
      INVITE_CONFIG: {
        showCommissionBadge: false,
        recordsPerPage: 10,
        inviteLinkConfig: { linkMode: 'auto' }
      },
      TICKET_CONFIG: {
        includeUserInfoInTicket: true,
        popup: { enabled: false }
      },
      TRAFFICLOG_CONFIG: {
        enableTrafficLog: true,
        daysToShow: 30
      },
      NODES_CONFIG: {
        showNodeRate: true,
        showNodeDetails: false,
        allowViewNodeInfo: true
      },
      CUSTOMER_SERVICE_CONFIG: { enabled: false },
      MORE_PAGE_CONFIG: { enableCustomCards: false }
    };
  </script>

  <!-- Load ProxyBD theme assets -->
  <script type="module" crossorigin src="/theme/{{ $theme }}/assets/index-sC8Xq0xY.js"></script>
</head>
<body>
  <div id="app"></div>
  
  <!-- Version badge -->
  <div class="app-version">{{ $version }}</div>

  <!-- Custom HTML from theme configuration -->
  {!! $theme_config['custom_html'] ?? '' !!}
</body>
</html>