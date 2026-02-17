# Changelog

All notable changes to the PayHere UltimatePOS Connector will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.2] - 2026-02-17

### Fixed
- **Partial Payment Bug**: Fixed incorrect amount calculation for partial payments
  - Previously, PayHere would charge the total invoice amount even when partial payments had been made
  - **Example**: Invoice of ₨1000 with ₨400 already paid would still charge ₨1000 (+ fee)
  - **Solution**: Now correctly calculates remaining balance (₨600) and charges only that amount (+ fee on ₨600)
  - **Impact**: Customers are now charged the correct remaining balance, not the full invoice amount
  - **Files Changed**: `invoice_hook.blade.php` and `guest_payment_hook.blade.php`

## [1.1.1] - 2026-02-17

### Fixed
- **Critical Bug**: Fixed duplicate payment entries when convenience fee is disabled
  - Previously, when fee was disabled, the system would create two payment entries:
    - One with the correct payment amount
    - One with ₨ 0.00 amount
  - This also incorrectly added amounts to customer advance balance
  - **Root Cause**: The `payAtOnce()` method was being called unnecessarily when `convenience_fee == 0`
  - **Solution**: Removed overpayment check logic when fee is disabled, as payment amount should match invoice balance exactly
  - **Impact**: Clean, single payment entries with no duplicate records or incorrect advance balances

## [1.1.0] - 2026-02-16

### Added
- Robust payment fee management with dedicated `PayHereFeeService`
- Server-side validation for fee settings with proper type casting
- Improved idempotency checks to prevent duplicate payment processing
- Configurable convenience fee calculation and display
- Fee percentage and maximum fee amount settings
- Real-time fee preview in payment forms

### Changed
- Enhanced payment processing logic to handle convenience fees correctly
- Improved session handling for webhook payments
- Better error logging and debugging capabilities

## [1.0.0] - 2026-02-07

### Added
- Initial release of PayHere UltimatePOS Connector
- Premium popup modal integration using PayHere JavaScript SDK
- Conflict-free payment slot mapping (supports all 7 custom payment slots)
- Smart auto-labeling for payment methods
- Zero-touch webhook configuration with auto-security
- Accounting module integration with session mocking
- MD5 signature verification for payment security
- Support for both Sandbox and Live modes
- Automatic invoice status updates
- Payment history tracking with PayHere transaction IDs
