<?php

use MODX\Revolution\modX;
use MODX\Revolution\modCategory;
use MODX\Revolution\modSystemSetting;
use MODX\Revolution\modMenu;
use MODX\Revolution\modPlugin;
use MODX\Revolution\modPluginEvent;
use MODX\Revolution\modSnippet;
use MODX\Revolution\Transport\modPackageBuilder;
use MODX\Revolution\Transport\modTransportPackage;
use xPDO\Transport\xPDOFileVehicle;
use xPDO\Transport\xPDOTransport;
use xPDO\Transport\xPDOScriptVehicle;

class Ms3FirstTimeBuyerDiscountPackage
{
    private modX $modx;
    private array $config = [];
    private modCategory $category;
    private array $category_attributes = [];

    public modPackageBuilder $builder;

    public function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;
        $this->modx->initialize('mgr');

        $root = dirname(__FILE__, 2) . '/';
        $core = $root . 'core/components/' . $config['name_lower'] . '/';
        $assets = $root . 'assets/components/' . $config['name_lower'] . '/';

        $this->config = array_merge([
            'log_level' => modX::LOG_LEVEL_INFO,
            'log_target' => (defined('XPDO_CLI_MODE') && XPDO_CLI_MODE) ? 'ECHO' : 'HTML',
            'root' => $root,
            'build' => $root . '_build/',
            'elements' => $root . '_build/elements/',
            'resolvers' => $root . '_build/resolvers/',
            'core' => $core,
            'assets' => $assets,
        ], $config);
        $this->modx->setLogLevel($this->config['log_level']);
        $this->modx->setLogTarget($this->config['log_target']);
        $this->initialize();
    }

    public function process(): modPackageBuilder
    {
        $this->runComposerInstall();
        $this->assets();
        $this->encryptionSetup();

        $elements = scandir($this->config['elements']);
        foreach ($elements as $element) {
            if (in_array($element[0], ['_', '.'])) {
                continue;
            }
            $name = preg_replace('#\.php$#', '', $element);
            if (method_exists($this, $name)) {
                $this->{$name}();
            }
        }

        $vehicle = $this->builder->createVehicle($this->category, $this->category_attributes);
        $vehicle->resolve('file', [
            'source' => $this->config['core'],
            'target' => "return MODX_CORE_PATH . 'components/';",
        ]);
        $vehicle->resolve('file', [
            'source' => $this->config['assets'],
            'target' => "return MODX_ASSETS_PATH . 'components/';",
        ]);

        $resolvers = array_filter(
            scandir($this->config['resolvers']),
            fn(string $r): bool => !in_array($r[0], ['_', '.'], true) && substr($r, -4) === '.php'
        );
        sort($resolvers);
        foreach ($resolvers as $resolver) {
            if ($resolver === 'resolve.encryption.php') {
                continue;
            }
            if ($vehicle->resolve('php', ['source' => $this->config['resolvers'] . $resolver])) {
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Added resolver ' . preg_replace('#\.php$#', '', $resolver));
            }
        }

        $this->builder->putVehicle($vehicle);

        if (!empty($this->config['encrypt'])) {
            $this->builder->putVehicle($this->builder->createVehicle(
                ['source' => $this->config['resolvers'] . 'resolve.encryption.php'],
                ['vehicle_class' => xPDOScriptVehicle::class]
            ));
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Added encryption resolver (for uninstall)');
        }
        $this->builder->setPackageAttributes([
            'changelog' => file_get_contents($this->config['core'] . 'docs/changelog.txt'),
            'license' => file_get_contents($this->config['core'] . 'docs/license.txt'),
            'readme' => file_get_contents($this->config['core'] . 'docs/readme.txt'),
            'requires' => [
                'php' => '>=8.1.0',
                'modx' => '>=3.0.0',
            ],
        ]);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packing up transport package zip...');
        $this->builder->pack();

        if (!empty($this->config['install'])) {
            $this->install();
        }
        return $this->builder;
    }

    /**
     * Setup encryption: add EncryptedVehicle class and resolver to package.
     * Key retrieval happens inside EncryptedVehicle::put() via getKey().
     */
    private function encryptionSetup(): void
    {
        if (empty($this->config['encrypt'])) {
            return;
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, 'Encryption enabled.');

        require_once $this->config['core'] . 'src/Transport/EncryptedVehicle.php';

        $nameLower = $this->config['name_lower'];

        $this->builder->package->put(
            [
                'source' => $this->config['core'] . 'src/Transport/EncryptedVehicle.php',
                'target' => "return MODX_CORE_PATH . 'components/" . $nameLower . "/Transport/';",
            ],
            [
                'vehicle_class' => xPDOFileVehicle::class,
                xPDOTransport::UNINSTALL_FILES => false,
            ]
        );
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Added EncryptedVehicle class to package');

        $this->builder->package->put(
            [
                'source' => $this->config['core'] . 'src/Transport/encryptedvehicle.class.php',
                'target' => "return MODX_CORE_PATH . 'model/" . $nameLower . "/transport/';",
            ],
            [
                'vehicle_class' => xPDOFileVehicle::class,
                xPDOTransport::UNINSTALL_FILES => false,
            ]
        );
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Added EncryptedVehicle compatibility class to core/model');

        $this->builder->putVehicle($this->builder->createVehicle(
            ['source' => $this->config['resolvers'] . 'resolve.encryption.php'],
            ['vehicle_class' => xPDOScriptVehicle::class]
        ));
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Added encryption resolver');

        $this->category_attributes['vehicle_class'] = 'Ms3FirstTimeBuyerDiscount\\Transport\\EncryptedVehicle';
        $this->category_attributes[xPDOTransport::ABORT_INSTALL_ON_VEHICLE_FAIL] = true;
    }

    private function initialize(): void
    {
        $this->builder = new modPackageBuilder($this->modx);
        $this->builder->createPackage($this->config['name_lower'], $this->config['version'], $this->config['release']);
        $this->builder->registerNamespace($this->config['name_lower'], false, true, '{core_path}components/' . $this->config['name_lower'] . '/');
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Created Transport Package and Namespace.');

        $this->category = $this->modx->newObject(modCategory::class);
        $this->category->set('category', $this->config['name']);
        $this->category_attributes = [
            xPDOTransport::UNIQUE_KEY => 'category',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => true,
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [],
        ];
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Created main Category.');
    }

    private function runComposerInstall(): void
    {
        $composerJson = $this->config['core'] . 'composer.json';
        if (!file_exists($composerJson)) {
            return;
        }
        $cmd = 'cd ' . escapeshellarg($this->config['core']) . ' && composer install --no-dev --no-interaction 2>&1';
        $output = shell_exec($cmd);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Composer install: ' . trim((string) $output));
    }

    private function assets(): void
    {
        $source = $this->config['core'] . 'assets/';
        $target = $this->config['assets'];
        if (!is_dir($source)) {
            return;
        }
        if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not create assets dir: ' . $target);
            return;
        }
        $this->copyDir($source, $target);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Synced component assets to public assets dir');
    }

    private function copyDir(string $source, string $target): void
    {
        $items = scandir($source);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcPath = $source . $item;
            $dstPath = rtrim($target, '/\\') . '/' . $item;
            if (is_dir($srcPath)) {
                if (!is_dir($dstPath)) {
                    mkdir($dstPath, 0777, true);
                }
                $this->copyDir($srcPath . '/', $dstPath . '/');
                continue;
            }
            copy($srcPath, $dstPath);
        }
    }

    private function settings(): void
    {
        $dataDir = $this->config['build'] . 'data/';
        $transportFile = $dataDir . 'transport.settings.php';
        if (!file_exists($transportFile)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not load transport.settings.php');
            return;
        }
        $modx = $this->modx;
        $settings = include $transportFile;
        if (!is_array($settings)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in System Settings');
            return;
        }
        $attributes = [
            xPDOTransport::UNIQUE_KEY => 'key',
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['settings']),
            xPDOTransport::RELATED_OBJECTS => false,
        ];
        foreach ($settings as $setting) {
            $vehicle = $this->builder->createVehicle($setting, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($settings) . ' System Settings');
    }

    private function menus(): void
    {
        $menus = include($this->config['elements'] . 'menus.php');
        if (!is_array($menus)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Menus');
            return;
        }
        $attributes = [
            xPDOTransport::PRESERVE_KEYS => true,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['menus']),
            xPDOTransport::UNIQUE_KEY => 'text',
            xPDOTransport::RELATED_OBJECTS => true,
        ];
        foreach ($menus as $name => $data) {
            $menu = $this->modx->newObject(modMenu::class);
            $menu->fromArray(array_merge([
                'text' => $name,
                'parent' => 'components',
                'namespace' => $this->config['name_lower'],
                'icon' => '',
                'menuindex' => 0,
                'params' => '',
                'handler' => '',
            ], $data), '', true, true);
            $vehicle = $this->builder->createVehicle($menu, $attributes);
            $this->builder->putVehicle($vehicle);
        }
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($menus) . ' Menus');
    }

    private function plugins(): void
    {
        $plugins = include($this->config['elements'] . 'plugins.php');
        if (!is_array($plugins)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not package in Plugins');
            return;
        }
        $this->category_attributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Plugins'] = [
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['plugins']),
            xPDOTransport::RELATED_OBJECTS => true,
            xPDOTransport::RELATED_OBJECT_ATTRIBUTES => [
                'PluginEvents' => [
                    xPDOTransport::PRESERVE_KEYS => true,
                    xPDOTransport::UPDATE_OBJECT => true,
                    xPDOTransport::UNIQUE_KEY => ['pluginid', 'event'],
                ],
            ],
        ];
        $objects = [];
        foreach ($plugins as $name => $data) {
            $plugin = $this->modx->newObject(modPlugin::class);
            $plugin->fromArray(array_merge([
                'name' => $name,
                'category' => 0,
                'description' => $data['description'] ?? '',
                'plugincode' => self::getFileContent($this->config['core'] . 'elements/plugins/' . $data['file'] . '.php'),
                'static' => !empty($this->config['static']['plugins']),
                'source' => 1,
                'static_file' => 'core/components/' . $this->config['name_lower'] . '/elements/plugins/' . $data['file'] . '.php',
            ], $data), '', true, true);
            $events = [];
            if (!empty($data['events'])) {
                foreach ($data['events'] as $eventName => $eventData) {
                    $event = $this->modx->newObject(modPluginEvent::class);
                    $event->fromArray(array_merge([
                        'event' => $eventName,
                        'priority' => 0,
                        'propertyset' => 0,
                    ], $eventData), '', true, true);
                    $events[] = $event;
                }
            }
            if (!empty($events)) {
                $plugin->addMany($events);
            }
            $objects[] = $plugin;
        }
        $this->category->addMany($objects);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Plugins');
    }

    private function snippets(): void
    {
        $snippets = include $this->config['elements'] . 'snippets.php';
        if (!is_array($snippets)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Could not load snippets.php');
            return;
        }
        $this->category_attributes[xPDOTransport::RELATED_OBJECT_ATTRIBUTES]['Snippets'] = [
            xPDOTransport::UNIQUE_KEY => 'name',
            xPDOTransport::PRESERVE_KEYS => false,
            xPDOTransport::UPDATE_OBJECT => !empty($this->config['update']['snippets']),
            xPDOTransport::RELATED_OBJECTS => false,
        ];
        $objects = [];
        foreach ($snippets as $name => $data) {
            $snippet = $this->modx->newObject(modSnippet::class);
            $snippet->fromArray(array_merge([
                'name' => $name,
                'description' => $data['description'] ?? '',
                'snippet' => self::getFileContent($this->config['core'] . 'elements/snippets/' . ($data['file'] ?? $name) . '.php'),
                'category' => 0,
                'static' => !empty($this->config['static']['snippets']),
                'source' => 1,
                'static_file' => 'core/components/' . $this->config['name_lower'] . '/elements/snippets/' . ($data['file'] ?? $name) . '.php',
            ], $data), '', true, true);
            $objects[] = $snippet;
        }
        $this->category->addMany($objects);
        $this->modx->log(modX::LOG_LEVEL_INFO, 'Packaged in ' . count($objects) . ' Snippets');
    }

    private static function getFileContent(string $filename): string
    {
        if (file_exists($filename)) {
            $file = trim((string) file_get_contents($filename));
            return preg_match('#\<\?php(.*)#is', $file, $data)
                ? rtrim(rtrim(trim($data[1] ?? ''), '?>'))
                : $file;
        }
        return '';
    }

    private function install(): void
    {
        $signature = $this->builder->getSignature();
        $sig = explode('-', $signature);
        $versionSignature = explode('.', $sig[1]);
        $package = $this->modx->getObject(modTransportPackage::class, ['signature' => $signature]);
        if (!$package) {
            $package = $this->modx->newObject(modTransportPackage::class);
            $package->set('signature', $signature);
            $package->fromArray([
                'created' => date('Y-m-d H:i:s'),
                'updated' => null,
                'state' => 1,
                'workspace' => 1,
                'provider' => 0,
                'source' => $signature . '.transport.zip',
                'package_name' => $this->config['name'],
                'version_major' => (int) ($versionSignature[0] ?? 1),
                'version_minor' => (int) ($versionSignature[1] ?? 0),
                'version_patch' => (int) ($versionSignature[2] ?? 0),
            ]);
            if (!empty($sig[2])) {
                $r = preg_split('#([0-9]+)#', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
                if (is_array($r) && !empty($r)) {
                    $package->set('release', $r[0]);
                    $package->set('release_index', $r[1] ?? '0');
                } else {
                    $package->set('release', $sig[2]);
                }
            }
            $package->save();
        }
        if (isset($package->xpdo->packages['Revolution'])) {
            $package->xpdo->packages['MODX\Revolution\\'] = $package->xpdo->packages['Revolution'];
        }
        if ($package->install()) {
            $this->modx->runProcessor('System/ClearCache');
        }
    }
}

if (!file_exists(dirname(__FILE__) . '/config.inc.php')) {
    exit('Could not load MODX config. Please specify correct MODX_CORE_PATH constant in config file!');
}
$config = require(dirname(__FILE__) . '/config.inc.php');
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
$modx = new modX();
$install = new Ms3FirstTimeBuyerDiscountPackage($modx, $config);
$builder = $install->process();

if (!empty($config['download'])) {
    $name = $builder->getSignature() . '.transport.zip';
    $path = MODX_CORE_PATH . 'packages/' . $name;
    if (file_exists($path) && $content = file_get_contents($path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $name);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));
        exit($content);
    }
}
