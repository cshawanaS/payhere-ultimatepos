# PayHere UltimatePOS Connector 🛡️🇱🇰

A professional-grade, "Install & Forget" integration of **PayHere** (Sri Lanka's leading payment gateway) for the **UltimatePOS** system.

This module was meticulously crafted to solve the common pain points of standard integrations: compatibility issues with other modules, rigid payment slots, and complex security configurations.

---

## ✨ Features that Make a Difference

### 🎰 1. Conflict-Free Mapping (Dynamic Slots)
UltimatePOS provides 7 "Custom Payment" slots. Standard modules often hardcode "Slot 1," causing issues if you're already using it for Bank Transfers or Cash. 
- **Our Solution**: Pick **ANY slot (1-7)** from a dropdown. It even shows you the current label for each slot so you can find an empty one easily.

### 🏷️ 2. Smart Auto-Labeling
Tired of manual database edits or digging through cryptic settings?
- **Our Solution**: When you save your settings, the module **automatically renames** the selected slot to "PayHere" (or your preference). It updates your POS screens, Invoices, and Reports instantly.

### 🚀 3. Zero-Touch Webhook (Auto-Security)
Payment gateways need a "Webhook" (Notify URL) to tell your site a payment was successful. Typically, this requires manual editing of security files (CSRF exemption).
- **Our Solution**: We used a special system pathway (`/webhook/*`) that UltimatePOS already trusts. **No manual code changes are required on new installs.**

### 💎 4. Premium Popup Modal
- **Our Solution**: Instead of bouncing your customers away to a different website, we trigger the **Official PayHere JavaScript SDK**. This opens a sleek, secure payment window directly over your invoice.

### 📊 5. Accounting Integrity
- **Our Solution**: We implemented "Session Mocking" for background payments. This ensures the **Accounting Module** doesn't crash and correctly attributes the payment to the Business Owner, maintaining perfect books.

---

## 🛠️ Installation & Activation Guide

If you have "Zero Knowledge" of coding, just follow these simple steps:

### Step 1: Upload the Module
1. Download the `PayHere` module folder.
2. Upload it to the `Modules` directory of your UltimatePOS installation (usually found at `htdocs/your-site/Modules`).
3. Ensure the folder name is exactly `PayHere`.

### Step 2: Activate & Auto-Install
1. Log in to your UltimatePOS as an **Admin**.
2. Go to **Settings** -> **Modules**.
3. Find **PayHere** in the list and click **Activate** or **Install**.
4. **Important**: Using the UI button is recommended because it automatically:
   - Creates the necessary database tables.
   - Injects the "Pay with PayHere" button into your Invoice views.
   - Sets up the secure webhook connection.

### Step 3: Manual Fallback (Optional)
If you are a developer or the UI activation didn't run migrations, you can manually run:
```bash
php artisan module:migrate PayHere
```
*Note: This only handles the database; it won't inject the view hooks automatically.*

---

## ⚙️ Configuration (The Easy Way)

1. Go to the sidebar and find **PayHere Settings** (usually at the bottom).
2. Enter your **Merchant ID** and **Merchant Secret** (Found in your PayHere Dashboard).
3. Set the **Mode** to "Sandbox" for testing or "Live" for real sales.
4. **Payment Slot Mapping**: Choose an empty slot (e.g., "Slot 2").
5. **Display Label**: Type "Pay with PayHere" or just "PayHere". 
6. Click **Update Settings**. 

---

## 💳 Payment Behavior & Visibility

### How it works for the Customer:
- When a customer views their invoice or goes to pay in the POS/Web, they will see a button labeled **"Pay with PayHere"** (or whatever you named it in Step 4).
- Clicking the button opens the **PayHere Secure Popup**.
- The customer enters their card/mobile wallet details and completes the payment **without leaving your site**.
- Once successful, the popup closes, and the invoice is automatically marked as **Paid**.

### Where to see payments in Back-Office:
- **Invoice Overview**: The status will change from "Due" to "Paid" instantly.
- **Payment History**: Under the invoice details, you will see a payment record with the method "PayHere" and the PayHere Transaction ID as the reference.
- **Reports**: All PayHere payments are recorded in your "Payment Accounts" and "Register Reports" just like Cash or Card.

---

## 🏗️ Technical Build & Core Ideas

This module was built with a **"Resilience First"** mindset:

- **Stack**: Powered by **PHP 8.x** and **Laravel 9**, following the UltimatePOS Module architecture.
- **Signature Matching**: We use the official PayHere MD5 Hashing algorithm to verify every signal coming from PayHere, ensuring no one can "fake" a payment.
- **Session Mocking**: Since Webhooks (Notify URLs) happen between servers (no user logged in), we mock the Business Owner's session so that other modules (like Accounting or Logging) don't crash when they look for a "logged-in user."
- **Clean Logs**: We suppressed noisy validation warnings during simple browser redirects to keep your `laravel.log` file focused on what matters.

---

## 📚 Resources

- [Official PayHere API Documentation](https://support.payhere.lk/api-&-mobile-sdk/checkout-api)
- [UltimatePOS Documentation](https://ultimatepos.com/docs/)
- [PayHere Merchant Dashboard](https://www.payhere.lk/account/login)

---
*Built for reliability. Powered by PayHere. Optimized for UltimatePOS.*
