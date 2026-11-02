<?php
/** WordPress-independent nonce boundary regression tests. */
define('ABSPATH','/tmp/');
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function wp_verify_nonce($nonce,$action) { return $nonce === 'valid-test-nonce' && $action === 'apls_faext_register' ? 1 : false; }
require dirname(__DIR__) . '/includes/class-registration.php';
$cases=[
    ['missing',[],false],
    ['array',['apls_faext_registration_nonce'=>['valid-test-nonce']],false],
    ['invalid',['apls_faext_registration_nonce'=>'forged'],false],
    ['valid',['apls_faext_registration_nonce'=>'valid-test-nonce'],true],
];
foreach ($cases as [$name,$post,$expected]) {
    $_POST=$post;
    if (\APlusPlugins\FluentAuthExtensions\Registration::registration_nonce_valid()!==$expected) {
        fwrite(STDERR,"FAIL: $name\n"); exit(1);
    }
}
echo 'Registration nonce boundary: 4/4 passed' . PHP_EOL;
