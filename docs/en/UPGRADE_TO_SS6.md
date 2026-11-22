# Upgrade to Silverstripe CMS 6

## Requirements

⚠️ **BREAKING CHANGE:** Silverstripe 4 and 5 are no longer supported.

- `composer.json`: change the `silverstripe/framework` constraint from `^4.0 || ^5.0` to `^6.0`.
- Projects on SS4/SS5 must stay on the previous major release of this module.

## Extension hooks

⚠️ **BREAKING CHANGE:** `DefaultPredictExtension::populateDefaults()` is renamed to `onAfterPopulateDefaults()`.

SS6 `DataObject::populateDefaults()` now calls `$this->extend('onAfterPopulateDefaults')`. An extension method called `populateDefaults()` is no longer invoked.

- If you subclass or replace `Sunnysideup\DefaultPredict\Extension\DefaultPredictExtension`, rename your `populateDefaults()` override to `onAfterPopulateDefaults()`.
- Remove any code that calls `DefaultPredictExtension::populateDefaults()` directly on the extension or through `$owner->extend('populateDefaults')`. Call `onAfterPopulateDefaults()` instead.
- Check your own extensions on the same `DataObject` classes. They need the same rename if they hook into `populateDefaults`.

## Known issue (existing bug, not caused by the upgrade)

**🚨 CRITICAL REVIEW REQUIRED / RISKY: `src/Extension/DefaultPredictExtension.php` checks `isset($fieldNames[$fieldThatMustBeEqual])`. `$fieldNames` is a list of names, not a map keyed by name, so the check is probably always false. As a result, `fields_that_must_be_equal` is likely never applied. It probably should be `in_array($fieldThatMustBeEqual, $fieldNames)`. It was left unchanged because fixing it would change prediction behaviour. Review it and fix it yourself, then test your predicted defaults.**

## Housekeeping

- `.agents/` is added to `.gitignore`.
- `composer.json` is reformatted to 4-space indentation. Nothing else in it changed apart from the framework constraint.
- `docs/en/FINAL_REVIEW.md` is added. It records the upgrade review above.
