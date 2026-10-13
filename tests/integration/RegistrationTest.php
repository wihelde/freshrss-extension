<?php

declare(strict_types=1);

$fresh = $argv[1];
$configFile = $fresh . '/data/users/test/config.php';
$config = require $configFile;
$config['extensions_enabled'] = ['Translate' => true, 'Stop Videos' => true, 'n8n Label Webhook' => true];
$config['extensions']['Translate'] = ['libretranslate_url' => 'http://127.0.0.1:5000/success', 'shortcut' => 'ü'];
file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

require $fresh . '/cli/_cli.php';
Minz_Request::init();
cliInitUser('test');

assert(Minz_Dispatcher::isRegistered('translate'), 'FreshRSS did not register the translation controller');
foreach (['Translate', 'Stop Videos', 'n8n Label Webhook'] as $name) {
	assert(Minz_ExtensionManager::findExtension($name)?->isEnabled(), "FreshRSS did not enable {$name}");
}
$vars = Minz_ExtensionManager::callHook(Minz_HookType::JsVars, []);
assert($vars['translate']['shortcut'] === 'ü', 'FreshRSS did not invoke the translation JS hook');
parse_str(parse_url($vars['translate']['endpoint'], PHP_URL_QUERY), $endpoint);
assert($endpoint['c'] === 'translate' && $endpoint['a'] === 'translate');

$_SERVER['REQUEST_METHOD'] = 'POST';
Minz_Request::forward(['c' => 'tag', 'a' => 'tagEntry', 'params' => [
	'checked' => '1', 'id_entry' => '42', 'id_tag' => '0', 'name_tag' => 'trigger',
]]);
assert(Minz_ExtensionManager::callHook(Minz_HookType::ActionExecute, new FreshRSS_tag_Controller()) === true);
$log = file_get_contents($fresh . '/data/users/test/log.txt');
assert(str_contains($log, 'n8n webhook: config.php is missing'), 'FreshRSS did not invoke the webhook action hook');
echo "FreshRSS extension registration and hooks passed\n";
