# Final review

No Silverstripe 6 incompatibilities or critical security issues found. No changes were made.

## ⚠️ Upgrade work still to do
- `src/Extension/DefaultPredictExtension.php`: not an SS6 issue. `isset($fieldNames[$fieldThatMustBeEqual])` checks array keys, but `$fieldNames` is a list of names, so `fields_that_must_be_equal` is likely never applied. It should probably be `in_array(...)`. Left unchanged because it would change behaviour.
