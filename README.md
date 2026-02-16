# PayHere Payment Module for UltimatePOS

A robust, conflict-aware PayHere integration for UltimatePOS, featuring dynamic payment slot mapping and automatic labeling.

## 🚀 Key Features

- **Zero-Touch Webhook**: Automatic CSRF exemption for the notify URL.
- **Dynamic Slot Mapping**: Use any of the 7 custom payment slots to avoid conflicts.
- **Automated Labeling**: Automatically renames the selected slot to "PayHere" in POS and reports.
- **Secure Storage**: Encrypted storage of Merchant ID and Secret.
- **Accounting Support**: Full integration with the UltimatePOS Accounting module.
- **Popup Overlay**: Seamless payment experience without leaving the checkout page.

## 🔧 Installation

1. Copy this folder into your `Modules` directory.
2. Run migrations: `php artisan module:migrate PayHere`
3. Go to **PayHere Settings** in your sidebar to configure your credentials and select your preferred payment slot.

## 🛡️ Requirements

- UltimatePOS 4.x or 5.x
- PHP 8.1+
- PayHere Merchant Account

---
*Developed for seamless payment processing.*
