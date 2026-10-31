# WordPress Plugin Check Remediation (v1.0.26)

This revision addresses selected issues from the 2026-10-31 report against v1.0.24. It is **not** a clean official Plugin Check result.

- Removed the custom `Update URI` header required to be absent on WordPress.org.
- Made all Developer Hooks tab translation text domains literal.
- Added translator comments for ACF and ZeroBounce translated placeholder strings.
- Replaced PHP `parse_url()` use with `wp_parse_url()`.
- Replaced interpolated pending table identifiers with WordPress `%i` SQL identifier placeholders (WordPress minimum is 6.5).
- Used `%i` for uninstall table dropping.
- Corrected `readme.txt` Stable tag and added Tested up to metadata. The metadata should be adjusted to the actual last tested WordPress version.
- Excluded CLI test files and GitHub-only docs from the installable WordPress.org ZIP.

**Known follow-ups:** The diagnostics subsystem uses direct filesystem operations for safe append/locking/rotation; Plugin Check may flag this. Nonce warnings on submitted registration fields depend on FluentAuth's lifecycle; they must be validated in a WordPress runtime rather than blanket-suppressed. Direct database queries for the plugin-owned pending table and its uninstall process are intentional, and their parameters/identifiers are constrained. Run Plugin Check on the distribution ZIP and record each remaining warning. No public submission should be inferred from this packaging.
