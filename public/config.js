/**
 * External configuration file
 * In index.html you can search PB and replace it with your site name
 * Logo path: images/logo.png
 * 
 * Note: When integrated with Laravel, these values can be overridden by
 * window.PB_CONFIG_OVERRIDE from dashboard.blade.php
 */

/**
 * ProxyBD Theme Configuration
 * Optimized for Laravel Xboard Integration
 * 
 * Most configurations are now managed through the Laravel admin panel.
 * This file contains only essential fallback values and technical settings.
 */

// Minimal default configuration - Laravel overrides will handle most settings
window.PB_CONFIG = {
  // Panel type - managed by Laravel admin
  PANEL_TYPE: 'Xboard',

  // API configuration - auto-derive from current domain for Laravel integration
  API_CONFIG: {
    urlMode: 'auto',
    autoConfig: {
      useSameProtocol: true,
      appendApiPath: true,
      apiPath: '/api/v1'
    }
  },
  
  // Disable middleware since we're using Laravel directly
  API_MIDDLEWARE_ENABLED: false,

  // Essential default configs - all managed via Laravel admin panel
  SITE_CONFIG: {
    siteName: 'ProxyBD',
    siteDescription: 'ProxyBD BDIX Bypass Service',
    showLogo: true,
    landingText: { 'en-US': 'Use ProxyBD BDIX Bypass Service' }
  },

  DEFAULT_CONFIG: {
    defaultLanguage: 'en-US',
    defaultTheme: 'light',
    primaryColor: '#355cc2',
    enableLandingPage: true
  },

  // Technical settings that rarely change
  AUTH_CONFIG: {
    autoAgreeTerms: true,
    verificationCode: {
      showCheckSpamTip: true,
      checkSpamTipDelay: 1000
    }
  },

  AUTH_LAYOUT_CONFIG: {
    layoutType: 'center'
  },

  // Client configuration - standardized for most setups
  CLIENT_CONFIG: {
    showDownloadCard: true,
    showIOS: true,
    showAndroid: true,
    showMacOS: true,
    showWindows: true,
    showLinux: true,
    showOpenWrt: true,
    
    // Standard client support
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

  // Profile settings
  PROFILE_CONFIG: {
    showGiftCardRedeem: false, // Xiao-V2board only
    showRecentDevices: true
  },

  // Technical configurations
  CAPTCHA_CONFIG: {
    captchaType: 'google'
  },

  CUSTOM_HEADERS: {
    enabled: false,
    headers: {}
  },

  // Payment defaults - fine-tuning via admin panel
  PAYMENT_CONFIG: {
    openPaymentInNewTab: true,
    qrcodeSize: 200,
    qrcodeColor: '#000000',
    qrcodeBackground: '#ffffff',
    autoCheckPayment: true,
    autoCheckInterval: 5000,
    autoCheckMaxTimes: 60,
    useSafariPaymentModal: true,
    autoSelectFirstMethod: true
  },

  // Standard ticket settings
  TICKET_CONFIG: {
    includeUserInfoInTicket: true
  },

  // Customer service - disabled by default, enable via admin if needed
  CUSTOMER_SERVICE_CONFIG: {
    enabled: false,
    type: 'crisp',
    embedMode: 'embed',
    showWhenNotLoggedIn: true,
    iconPosition: {
      desktop: { left: '20px', bottom: '20px' },
      mobile: { right: '20px', bottom: '100px' }
    }
  },

  // Custom cards - disabled by default
  MORE_PAGE_CONFIG: {
    enableCustomCards: false,
    customCards: []
  }
};

// Laravel Integration: Merge with admin panel overrides
if (typeof window.PB_CONFIG_OVERRIDE !== 'undefined') {
  // Deep merge function for nested object merging
  function deepMerge(target, source) {
    for (const key in source) {
      if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
        if (!target[key]) target[key] = {};
        deepMerge(target[key], source[key]);
      } else {
        target[key] = source[key];
      }
    }
    return target;
  }
  
  // Apply Laravel admin panel configuration
  window.PB_CONFIG = deepMerge(window.PB_CONFIG, window.PB_CONFIG_OVERRIDE);
  
  console.log('✓ ProxyBD: Laravel theme configuration applied');
}
