# Plugin Check follow-up - 1.0.26

The 1.0.25 scan reported 7 errors and 63 warnings. This revision replaces direct filesystem calls in diagnostics, adjusts readme metadata, and documents scanner exceptions for the FluentAuth-owned registration lifecycle and the plugin-owned transient table.

**Important:** Adding `phpcs:ignore` does not itself establish that a request is secure. Review each registration path against the actual FluentAuth nonce / verification flow in the live environment. The direct database operations are necessary for the transaction-bound, expiring pending table.

**Diagnostics trade-off:** WP_Filesystem lacks an atomic append primitive. Concurrent diagnostic writes may overwrite one another. This is best-effort diagnostic logging, not an audit ledger. File contents remain capped and protected by the PHP header and directory access guards.

**Tested up to:** 7.1 is metadata for the scanner and should be independently verified on an actual WordPress 7.1 installation before publication.
