<?php
/* Standalone regression for WPFusion acknowledgment gating. */
namespace APLS\FluentAuthExtensions;
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__); }
require dirname(__DIR__) . '/includes/class-settings.php';
$cases = [
    [['marketing'=>1,'crm_provider'=>'fluentcrm'],true],
    [['marketing'=>1,'crm_provider'=>'wpfusion','wpf_consent_ack'=>0],false],
    [['marketing'=>1,'crm_provider'=>'wpfusion','wpf_consent_ack'=>1],true],
    [['marketing'=>0,'crm_provider'=>'wpfusion','wpf_consent_ack'=>1],false],
    [['marketing'=>1,'crm_provider'=>'none'],true],
];
foreach ($cases as $i => [$options,$expected]) {
    if (Settings::marketing_enabled($options) !== $expected) { fwrite(STDERR,"Case $i failed\n"); exit(1); }
}
echo 'WPFusion consent gate: 5/5 passed' . PHP_EOL;
