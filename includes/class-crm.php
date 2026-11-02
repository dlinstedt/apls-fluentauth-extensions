<?php
namespace APlusPlugins\FluentAuthExtensions;
if (!defined('ABSPATH')) { exit; }

/** Direct FluentCRM adapter. APLS alone owns this provider's registration sync. */
final class CRM {
    public static function sync(int $user_id, array $context): void {
        $options = Plugin::options();
        if (($options['crm_provider'] ?? 'fluentcrm') !== 'fluentcrm' || empty($options['crm']) || !Plugin::crm_active()) { return; }
        $user = get_userdata($user_id);
        if (!$user) { return; }
        $consent = !empty($context['marketing_consent']);
        try {
            $contacts = fluentCrmApi('contacts');
            $existing = $contacts->getContact($user->user_email);
            $previous_status = $existing ? (string) $existing->status : null;
            // An unchecked registration is not a withdrawal of an existing subscription.
            // For NEW contacts, use transactional until explicit marketing consent is given.
            // FluentCRM's default guarded update will not reactivate suppressed contacts.
            $requested_status = $consent ? 'subscribed' : 'transactional';
            $payload = [
                'email'      => $user->user_email,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'user_id'    => $user_id,
            ];
            if (!$existing) {
                $payload['status'] = $requested_status;
            } elseif ($consent && in_array($previous_status, ['transactional', 'pending'], true)) {
                // A new affirmative checkbox choice may upgrade an unsuppressed contact.
                $payload['status'] = 'subscribed';
            }
            $contact = $contacts->createOrUpdate($payload);
            if (!$contact) {
                Diagnostics::log('crm.sync_failed', ['user_id' => $user_id, 'reason' => 'empty_contact']);
                do_action('apls/fluentauth/crm_sync_failed', $user_id, 'empty_contact');
                return;
            }
            $registration_tags = array_values(array_unique(array_map('absint', (array) ($options['crm_tags'] ?? []))));
            $marketing_tags = $consent ? array_values(array_unique(array_map('absint', (array) ($options['crm_marketing_tags'] ?? [])))) : [];
            $registration_tags = array_values(array_filter($registration_tags));
            $marketing_tags = array_values(array_filter($marketing_tags));
            if ($registration_tags && method_exists($contact, 'attachTags')) { $contact->attachTags($registration_tags); }
            if ($marketing_tags && method_exists($contact, 'attachTags')) { $contact->attachTags($marketing_tags); }
            // Read back for diagnostic accuracy; do not report a requested state as saved.
            $verified = $contacts->getContact($user->user_email);
            $final_status = $verified ? (string) $verified->status : 'unknown';
            Diagnostics::log('crm.sync_success', [
                'user_id' => $user_id,
                'contact_created' => !$existing,
                'marketing_opt_in' => $consent,
                'previous_status' => $previous_status,
                'requested_status' => isset($payload['status']) ? $payload['status'] : 'preserved',
                'subscription_status' => $final_status,
                'tags_count' => count(array_unique(array_merge($registration_tags, $marketing_tags))),
            ]);
            do_action('apls/fluentauth/crm_sync_completed', $user_id, $contact, $context);
        } catch (\Throwable $error) {
            Diagnostics::log('crm.sync_failed', ['user_id' => $user_id, 'reason' => 'exception']);
            do_action('apls/fluentauth/crm_sync_failed', $user_id, $error->getMessage());
        }
    }
}
