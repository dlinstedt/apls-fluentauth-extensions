# Developer hooks API - v1.0.18

**Names are exact, namespaced WordPress hook strings.** Parameters reflect the actual `apply_filters` and `do_action` calls in this version; callbacks should validate input and avoid logging personal information. Events have no documented replay, transactional rollback or delivery guarantee. Do not assume FluentAuth or third-party hooks will run in the same order in future releases.

| Hook | Type | Arguments | Expected return / contract | When triggered |
| --- | --- | --- | --- | --- |
| `apls/fluentauth/validate_email` | **Filter** | `(array $result, string $email, string $phase)` | **Return array** with `allowed` (bool), `status` (string), `provider` (string), `message` (string); malformed output is rejected as an error. Phase: `live` or `server`. | Email quality validation; cached results may also be filtered. Does not run if provider mode is `none` or local email syntax fails. |
| `apls/fluentauth/email_validated` | Action | `(string $email, array $result, string $phase)` | Return ignored. | After uncached provider/filter validation finishes. **Not guaranteed on cached or local-return paths.** |
| `apls/fluentauth/registration_validated` | Action | `(array $form_data)` | Return ignored. Contains FluentAuth-submitted registration data; **may contain personal/sensitive fields**. | Shortcode signup validation after email quality check. Do not expose raw data. |
| `apls/fluentauth/consent_recorded` | Action | `(int $user_id, array $context)` | Return ignored. | Only after affirmative marketing consent recorded on verified signup. |
| `apls/fluentauth/registration_completed` | Action | `(int $user_id, array $context)` | Return ignored. | On completed verified registration, independent of CRM success. |
| `apls/fluentauth/crm_sync_completed` | Action | `(int $user_id, object $contact, array $context)` for direct FluentCRM; WPFusion's `$result` **may not be an object** | Return ignored. | After adapter reports success. **Treat second argument as mixed until normalized in a future major API revision.** |
| `apls/fluentauth/crm_sync_failed` | Action | `(int $user_id, string $reason)` | Return ignored. **Reason may include exception details**; do not print publicly. | CRM adapter failure; not a signup rollback. |
| `apls/fluentauth/acf_fields_saved` | Action | `(int $user_id, array $field_keys)` | Return ignored. Keys only, **not** submitted values. | After all selected ACF values are read back successfully and pending transaction deleted. |
| `apls/fluentauth/community_portal_path` | **Filter** | `(string $portal_path)` | **Return string** matching a safe site-relative portal path (for example `/community`). Invalid paths disable the redirect for the request. | When optional FluentCommunity auth redirect is enabled and the request is being checked. |

## Consent and registration context

Source: `includes/class-registration.php`. The context assembled at completion includes `marketing_consent` (boolean) and `source` (`fluentauth_native`, `fluentauth_shortcode`, or a fallback value depending on request). Do not assume the presence of a given CRM or ACF group. The plugin uses private APlusPlugins user metadata `_apls_faext_completed` and `_apls_faext_marketing_consent_at`; avoid depending on private keys as a supported API.

## Custom email validator

Select **Email Validation > Custom validation via filter** and enable **Enforce validation on registration submission**. The filter must return every required key and a **boolean** `allowed` value. `status='error'` interacts with the configured fail-open / fail-closed policy. Never log email addresses in unrestricted logs.

```php
add_filter('apls/fluentauth/validate_email', function (array $result, string $email, string $phase): array {
    // Replace with your own configured, authenticated, bounded validation service.
    $allowed = is_email($email) !== false;
    return [
        'allowed' => $allowed,
        'status' => $allowed ? 'valid' : 'invalid',
        'provider' => 'my-company',
        'message' => $allowed ? '' : __('Email address is not accepted.', 'my-domain'),
    ];
}, 10, 3);
```

**Caution:** The filter is called on cached responses as well as fresh results; the `email_validated` action is not emitted on all paths. Failures in third-party hook handlers can still affect signup; wrap external calls in exception handling and move slow tasks to a queue.

## Listening to completed registration

```php
add_action('apls/fluentauth/registration_completed', function (int $user_id, array $context): void {
    $opted_in = !empty($context['marketing_consent']);
    // Idempotent downstream logic only. Do not infer opt-in from user existence.
}, 10, 2);

add_action('apls/fluentauth/acf_fields_saved', function (int $user_id, array $field_keys): void {
    // ACF values can now be read through get_field($key, 'user_' . $user_id).
    // Map only explicitly approved fields to external CRM destinations.
}, 10, 2);
```

**Operational guarantee:** ACF field persistence occurs earlier in the FluentAuth user-created hook than `registration_completed`, but ACF persistence failures do not rollback the WordPress account. Consult diagnostics if the `acf_fields_saved` event does not occur; never equate `registration_completed` with successful CRM synchronization.

## Internal and third-party hooks

`fluent_auth/verify_signup_email` is currently filtered to `true` with maximum priority. FluentAuth's `fluent_auth/after_signup_validation` and `fluent_auth/after_creating_user`, WordPress's `register_form`, `register_post`, `registration_errors`, and `do_shortcode_tag`, and WPFusion's `wpf_user_created` are **upstream dependencies**, not APlusPlugins public APIs. Test compatibility after upgrading their providers.
