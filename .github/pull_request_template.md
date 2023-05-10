### PR CHECKLIST

- [ ] Ensure any new strings have been translated for import
- [ ] Import new translations via `integrations-magento2`'s `make script-install-languages` command
- [ ] The plugin version (currently as a const in the `Data.php`) has been updated
- [ ] The plugin version in `composer.json` has been updated
- [ ] Tests have been added for any new functionality via `integrations-magento2`'s `make test` command
- [ ] Ensure logging is kept to a minimum, does not include PII, and is set to the pertinent level
