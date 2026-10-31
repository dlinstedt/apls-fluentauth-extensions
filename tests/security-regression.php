<?php
/** Standalone tests: php tests/security-regression.php */
define('ABSPATH','/tmp/');
class WP_Error { public function __construct($code,$message='') {} }
function sanitize_text_field($v){return trim((string)$v);}
function sanitize_textarea_field($v){return trim((string)$v);}
function __($v,$domain=''){return $v;}
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
require dirname(__DIR__).'/includes/class-community-redirect.php';
require dirname(__DIR__).'/includes/class-acf.php';
$redirects=[
 ['GET','/community/',['fcom_action'=>'auth'],'/login/'],
 ['GET','/community',['fcom_action'=>'auth','form'=>'register'],'/login/?action=register'],
 ['GET','/community',['fcom_action'=>'auth','form'=>'login'],'/login/'],
 ['POST','/community',['fcom_action'=>'auth'],''],
 ['GET','/community/members',['fcom_action'=>'auth'],''],
 ['GET','/community',['fcom_action'=>'auth','form'=>'reset_password'],''],
 ['GET','/community',['fcom_action'=>'auth','invitation_token'=>'abc'],''],
 ['GET','/community',['fcom_action'=>'auth','form'=>'register','redirect_to'=>'/other'],''],
 ['GET','/community',['fcom_action'=>['auth']],''],
 ['GET','/community',['fcom_action'=>'auth','form'=>['register']],''],
 ['GET','/community',['fcom_action'=>'auth','extra'=>'1'],''],
];
$destinations = [
 ['/login/', true], ['/login/?action=register', true], ['/register/', true], ['/my-login/?form=register', true],
 ['https://evil.test', false], ['//evil.test/path', false], ['/community/', false], ['/community?fcom_action=auth', false],
 ['/wp-admin/', false], ['/wp-login.php', false], ['/path/../community/', false], ['/login/#frag', false],
 ['/login/\\evil.test', false], ['', false],
];
$total=0;$failed=0;
foreach ($destinations as [$candidate,$expected]) {
    $total++;
    $actual = \APLS\FluentAuthExtensions\Community_Redirect::sanitize_destination($candidate);
    if (($actual !== '') !== $expected) { $failed++; fwrite(STDERR, "destination #$total failed\n"); }
}

foreach($redirects as [$method,$path,$query,$expected]){$total++;$actual=\APLS\FluentAuthExtensions\Community_Redirect::destination($method,$path,$query);if($actual!==$expected){$failed++;fwrite(STDERR,"redirect #$total failed\n");}}
$normalize=new ReflectionMethod(\APLS\FluentAuthExtensions\ACF_Adapter::class,'normalize');
$fields=[
 [['type'=>'select','choices'=>['a'=>'A','b'=>'B'],'multiple'=>0],'a',true],
 [['type'=>'select','choices'=>['a'=>'A'],'multiple'=>0],['a'],false],
 [['type'=>'select','choices'=>['a'=>'A','b'=>'B'],'multiple'=>1],['a','b'],true],
 [['type'=>'select','choices'=>['a'=>'A'],'multiple'=>1],'a',false],
 [['type'=>'select','choices'=>['a'=>'A'],'multiple'=>1],['bad'],false],
 [['type'=>'select','choices'=>['a'=>'A'],'multiple'=>1],[['a']],false],
 [['type'=>'select','choices'=>['a'=>'A'],'multiple'=>1],['a','a'],false],
 [['type'=>'radio','choices'=>['a'=>'A']],'a',true],
 [['type'=>'radio','choices'=>['a'=>'A']],['a'],false],
 [['type'=>'checkbox','choices'=>['a'=>'A']],['a'],true],
 [['type'=>'checkbox','choices'=>['a'=>'A']],['bad'],false],
 [['type'=>'number','min'=>18,'max'=>99],'30',true],
 [['type'=>'number','min'=>18,'max'=>99],'100',false],
 [['type'=>'text','minlength'=>2,'maxlength'=>8],'engineer',true],
 [['type'=>'text','minlength'=>2,'maxlength'=>8],'x',false],
 [['type'=>'url'],'javascript:alert(1)',false],
 [['type'=>'url'],'data:text/html,hi',false],
 [['type'=>'url'],'https://example.org/path',true],
 [['type'=>'true_false'],'yes',false],
 [['type'=>'true_false'],'1',true],
 [['type'=>'true_false'],'0',true],
 [['type'=>'true_false'],['1'],false],
 [['type'=>'number'],'1e9999',false],
 [['type'=>'text'],str_repeat('X',4097),false],
 [['type'=>'checkbox','choices'=>['good'=>'Good']],[str_repeat('X',257)],false],
];
foreach($fields as [$field,$value,$expected]){$total++;$valid=!($normalize->invoke(null,$field,$value) instanceof WP_Error);if($valid!==$expected){$failed++;fwrite(STDERR,"field #$total failed\n");}}
echo "Security regression: ".($total-$failed)."/$total passed\n";exit($failed?1:0);
