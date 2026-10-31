# v1.0.28 registration input boundary

`Registration::submitted()` is a restricted, unslashing accessor, **not authorization**. It is used only for named registration controls. `Registration::submitted_text()` sanitizes scalar controls. ACF field arrays still require field-by-field `ACF_Adapter::normalize()`, including type, length, choice, and format validation. **First-stage pending writes are forbidden until `Registration::registration_nonce_valid()` passes.** The email-code step relies on FluentAuth verification plus HMAC-bound pending transaction instead of reusing the initial nonce. A WordPress nonce is not single-use proof of identity.

WordPress coding sniffers may flag direct input inside the centralized boundary despite caller-level lifecycle validation. The single PHPCS annotation applies ONLY to that raw boundary, not to validation decisions or database writes. Verify this premise in a real WordPress environment before release.

No live WordPress Plugin Check has been run against v1.0.28.
