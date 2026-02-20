# Release Notes for Order Importer

## 1.0.4 - 2026-02-20

### Fixed

- Removed unused variables (`$lineItemsObjects`, `$transactionsObjects`)
- Removed dead code in `parseUid()` fetching a value that was never used
- Various code inconsitencys

## 1.0.3 - 2026-02-19

### Fixed

- Transaction field attributes not being extracted due to prefix mismatch
- Line items and transactions all receiving the same values instead of per-item values
- Transaction hash using weak randomness, now uses Craft's secure random string
- Hardcoded `userId` in transactions now uses the order's customer ID
- Incomplete date format string missing minutes and seconds
- Description value leaking between adjustment types when field not mapped
- Misspelled `$gaetway` variable in `parseGatewayId`
- Email typo in composer.json support contact
- Added error logging for failed database operations

### Improved

- Tax and Shipping Category defaults now dynamically populated from Commerce

## 1.0.2 - 2026-02-19

### Added

- Automatically create users/customers from the order email address if they don't already exist in the CMS [#4](https://github.com/bymayo/craft-order-importer/issues/4)

## 1.0.1 - 2026-02-19

### Fixed
- Billing address first name not importing [#3](https://github.com/bymayo/craft-order-importer/issues/3)
- Error when using the CP and interacting with orders [#2](https://github.com/bymayo/craft-order-importer/issues/2)

## 1.0.0
- Initial release
