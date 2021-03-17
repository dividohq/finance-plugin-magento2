# Changelog
All notable changes to this project will now be documented in this file.

## [2.4.1] - 2021-02-03
- fix: Do not create order on referred status

## [2.4.0] - 2021-02-03
- feat: Extends language override to product widget

## [2.3.2] - 2020-12-09
- fix: Round discount to nearest cent/penny

## [2.3.1] - 2020-30-07
- fix: Rewrites euro comma fix to remove any commas in the decimal position regardless of currency 

## [2.3.0] - 2020-30-07
- fix: Adds shared secret compatibility 
- change: Updates the Application with a merchant reference

## [2.2.0] - 2020-23-07
- feat: Adds widget language override feature
- chore: Ups the widget version to v3

## [2.1.0] - 2020-15-06
- Update order logic to create within loop rather than end of loop
- Update metadata for additional logging

## [2.0.9] - 2020-05-05
- Add Euro as allowed currency
- Add helper to remove whitespace from phonenumber
- Fix Deposit amount in admin was displaying in pence.

## [2.0.8] - 2020-05-05
- Adjusted grand_total value on checkout to base_grand_total to account for tax changes
- Tested against Magento 2.3.5-p1

## [2.0.7] - 2019-12-12
- Removed unused variables from config 
- Pass Address Postcode as sanity value
- Widget Button Text
- Widget Footer
- Widget Mode option

## [2.0.6] - 2019-12-04
- Specified Country Logic Added
- Rounding Logic corrected
- Fix shipping product value issue on first page load

## [2.0.5] - 2019-11-14
- Bugfix for configurable products on sites that use multiple js libs

## [2.0.4] - 2019-11-05
Tested against magento 2.3.2 and 2.3.3

- Bugfix for js on products without select
- Bugfix Application Creation
- Debugging Adjustments
- Address Text Adjustment

## [2.0.3] - 2019-10-04

### Changed
- Bugfix for country
- Bugfix for deposit amount

## [2.0.2] - 2019-10-01

### Changed
- Bugfix for configurable products widget updating on change
- Bugfix for international 


## [2.0.1] - 2019-08-19

### Changed
- Bugfix for incorrect Deposit amount


## [2.0.0] - 2019-08-08
Tested against magento 2.3.1 and 2.3.2
### Added
- New widget [@dividoHq](https://github.com/dividohq).
- Automatic Activations.
- Automatic Cancellations.
- Custom Logger support for magento 2.3
- This changelog & github issue templates.
- Configuration helper comments.
- New SDK - fully updated with our new api.

### Changed
- Renamed to Powered By Divido.
- Adjusted configuration titles.
- Multiple small bugfixes

### Removed
- Section about "changelog" vs "CHANGELOG".
