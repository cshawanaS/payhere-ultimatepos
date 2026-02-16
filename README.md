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

## �️ Installation Guide (For Beginners)

If you have "Zero Knowledge" of coding, just follow these simple steps:

### Step 1: Move the Folder
1. Download the `PayHere` module folder.
2. Upload it to the `Modules` directory of your UltimatePOS installation (usually found at `htdocs/your-site/Modules`).

### Step 2: Initialize the Database
1. Open your terminal (SSH) and navigate to your site folder.
2. Run this command:
   ```bash
   php artisan module:migrate PayHere
   ```
   *This sets up the special settings table for your credentials.*

### Step 3: Configure your Credentials
1. Log in to your UltimatePOS as **Admin**.
2. Go to the sidebar and find **PayHere Settings** (at the bottom).
3. Enter your **Merchant ID** and **Secret** (You can get these from your PayHere.lk Dashboard).
4. Set the **Mode** to "Sandbox" to test with fake money, or "Live" for real sales.

### Step 4: Map your Slot
1. Choose an empty **Payment Slot Mapping** (e.g., "Slot 2").
2. Type "PayHere" in the **Display Label** field.
3. Click **Update Settings**. 

**You are now ready to accept payments!** 🎉

---

## � Technical Excellence (What we fixed)
- **Signature Verification**: Implemented standard MD5 hash matching for secure, tamper-proof transactions.
- **Log Sanitation**: Suppressed noisy "Validation Failed" warnings during browser redirects to keep your server logs clean and meaningful.
- **Idempotency**: Prevents duplicate payments from being recorded if a customer refreshes the page or the webhook fires twice.

---
*Built for reliability. Powered by PayHere. Supported by you.*
