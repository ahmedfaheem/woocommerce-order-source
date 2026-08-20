# WooCommerce Order Source Tracker

**Contributors:** Ahmed Faheem  
**Tags:** woocommerce, order tracking, utm, facebook ads, tiktok ads, hpos  
**Requires at least:** 6.0 | **Tested up to:** 6.4 | **Requires PHP:** 7.4  
**Stable tag:** 1.1.0  
**License:** GPLv2 or later  
**License URI:** [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

Tracks and displays the advertising/source channel (TikTok Ads, Facebook Ads, Website Direct) for every WooCommerce order. Fully automatic UTM-based attribution with HTTP Referer fallback.

## Description

WooCommerce Order Source Tracker is an essential plugin for e-commerce store owners running paid advertising campaigns on platforms like Facebook, Instagram, and TikTok. It seamlessly tracks where your customers are coming from and attaches this information directly to their WooCommerce orders.

### Features (المميزات)
* **Automatic Source Tracking (تتبع تلقائي):** Automatically detects if a visitor came from a TikTok Ad, Facebook Ad, or organically (Website Direct).
* **UTM Support:** Perfectly captures `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, and `utm_term` parameters and saves them in the order meta.
* **HTTP Referer Fallback (نظام بديل ذكي):** If you forget to add UTM parameters to your ads, the plugin will intelligently check the HTTP Referer (e.g., if they came from facebook.com, instagram.com, or tiktok.com) and assign the correct source.
* **Last-Touch Attribution (اللمسة الأخيرة):** If a customer clicks a TikTok ad, then later clicks a Facebook ad and buys, the order will be attributed to Facebook (the last click that led to the conversion).
* **30-Day Window (فترة التتبع):** The tracking cookie lasts for 30 days. If a user clicks an ad and buys 2 weeks later, the source is still correctly tracked.
* **Clean UI Integration:** 
    - Adds a "المصدر" (Source) column to the WooCommerce orders list.
    - Integrates beautifully with the WooCommerce "Order Preview" (العرض السريع للطلب).
    - Adds a detailed meta-box inside the order edit screen containing all captured UTM data.
* **Full HPOS Compatibility:** Fully compatible with WooCommerce's High-Performance Order Storage (HPOS).
* **Fully Arabic UI:** The admin interface is fully translated into Arabic.

### How to use (طريقة الاستخدام)
To get 100% accurate tracking, simply append `utm_source` to your ad links:
* For TikTok Ads: `https://yourwebsite.com/?utm_source=tiktok`
* For Facebook Ads: `https://yourwebsite.com/?utm_source=facebook`

If you forget to add them, the plugin will use the HTTP Referer fallback to try and detect the source automatically (works ~80% of the time depending on browser privacy settings).

## Installation

1. Upload the `woocommerce-order-source` folder to the `/wp-content/plugins/` directory, or upload the ZIP file directly from your WordPress Dashboard (Plugins > Add New > Upload).
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Start using UTM parameters in your ad campaigns.
4. Go to WooCommerce > Orders to see the new "المصدر" (Source) column.

## Developer & Contact

**Plugin created by Ahmed Faheem, Software Engineer**

* WhatsApp: [+201099492053](https://wa.me/201099492053)
* Email: [a7medfaheem@gmail.com](mailto:a7medfaheem@gmail.com)

## Changelog

### 1.1.0
* Fixed order preview display in generic Backbone templates.
* Added HTTP Referer fallback for missing UTMs.
* Translated entire UI to Arabic.
* Fixed BOM characters causing fatal errors.
