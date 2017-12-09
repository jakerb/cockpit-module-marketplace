<?php

$this->module("marketplace")->extend([

    'createRegion' => function($name, $data = []) {

        if (!trim($name)) {
            return false;
        }

        $configpath = $this->app->path('#storage:').'/marketplace';

        if (!$this->app->path('#storage:marketplace')) {

            if (!$this->app->helper('fs')->mkdir($configpath)) {
                return false;
            }
        }

        if ($this->exists($name)) {
            return false;
        }

        $time = time();

        $region = array_replace_recursive([
            'name'      => $name,
            'label'     => $name,
            '_id'       => uniqid($name),
            'fields'    => [],
            'template'  => '',
            'data'      => null,
            '_created'  => $time,
            '_modified' => $time
        ], $data);

        $export = var_export($region, true);

        if (!$this->app->helper('fs')->write("#storage:marketplace/{$name}.region.php", "<?php\n return {$export};")) {
            return false;
        }

        $this->app->trigger('marketplace.create', [$region]);

        return $region;
    },

    'updateRegion' => function($name, $data) {

        $metapath = $this->app->path("#storage:marketplace/{$name}.region.php");

        if (!$metapath) {
            return false;
        }

        $data['_modified'] = time();

        $region  = include($metapath);
        $region  = array_merge($region, $data);
        $export  = var_export($region, true);

        if (!$this->app->helper('fs')->write($metapath, "<?php\n return {$export};")) {
            return false;
        }

        $this->app->trigger('marketplace.update', [$region]);
        $this->app->trigger("marketplace.update.{$name}", [$region]);

        return $region;
    },

    'saveRegion' => function($name, $data) {

        if (!trim($name)) {
            return false;
        }

        return isset($data['_id']) ? $this->updateRegion($name, $data) : $this->createRegion($name, $data);
    },

    'removeRegion' => function($name) {

        if ($region = $this->region($name)) {

            $region = $marketplace["_id"];

            $this->app->helper("fs")->delete("#storage:marketplace/{$name}.region.php");
            $this->app->storage->dropregion("marketplace/{$region}");

            $this->app->trigger('marketplace.remove', [$name]);

            return true;
        }

        return false;
    },

    'marketplace' => function() {

        $marketplace = [];

        foreach($this->app->helper("fs")->ls('*.region.php', '#storage:marketplace') as $path) {

            $store = include($path->getPathName());
            $marketplace[$store['name']] = $store;
        }

        return $marketplace;
    },


    'exists' => function($name) {
        return $this->app->path("#storage:marketplace/{$name}.region.php");
    },

    'get_git_url' => function($raw = false) {
        return $raw ? 'https://raw.githubusercontent.com/' : 'https://github.com/';
    },

    'get' => function() {

        $random = rand(0,255);
        $module_dir = str_replace('Marketplace/Controller', false, __DIR__);
        $module_dir_list = scandir($module_dir);
        $marketplace = json_decode(
            file_get_contents("{$this->get_git_url(true)}jakerb/cockpit-marketplace/master/marketplace.json?v={$random}")
        );

        foreach($marketplace as $index => $item) {
            $marketplace[$index]->module_installed = false;
            if(isset($item->module_name) && in_array($item->module_name, $module_dir_list)) {
                    $marketplace[$index]->module_installed = true;
            } 
        }

        return $marketplace;

    },

    'region' => function($name) {

        static $marketplace; // cache

        if (is_null($marketplace)) {
            $marketplace = [];
        }

        if (!is_string($name)) {
            return false;
        }

        if (!isset($marketplace[$name])) {

            $marketplace[$name] = false;

            if ($path = $this->exists($name)) {
                $marketplace[$name] = include($path);
            }
        }

        return $marketplace[$name];
    },

    'getRegionFieldValue' => function($region, $fieldname, $default = null) {

        $region = $this->region($region);

        return ($region && isset($region['data'][$fieldname])) ? $region['data'][$fieldname] : $default;
    },

    'render' => function($name, $params = []) {

        $region = $this->region($name);

        if (!$region) {
            return null;
        }

        $renderer = $this->app->renderer;

        $_fields  = isset($region['fields']) ? $region['fields'] : [];

        $fields = array_merge(isset($region['data']) && is_array($region['data']) ? $region['data']:[] , $params);

        $this->app->trigger('marketplace.render.before', [$name, &$region, $fields]);
        $this->app->trigger("marketplace.render.before.{$name}", [$name, &$region, $fields]);

        $output = $renderer->execute($region['template'], $fields);

        $this->app->trigger('marketplace.render.after', [$name, &$output]);
        $this->app->trigger("marketplace.render.after.{$name}", [$name, &$output]);

        return $output;
    }

]);

// ACL
$app("acl")->addResource("marketplace", ['create', 'delete']);

$this->module("marketplace")->extend([

    'getMarketplaceInGroup' => function($group = null) {

        if (!$group) {
            $group = $this->app->module('cockpit')->getGroup();
        }

        $_marketplace = $this->marketplace();
        $marketplace = [];

        if ($this->app->module('cockpit')->isSuperAdmin()) {
            return $_marketplace;
        }

        foreach ($_marketplace as $region => $meta) {

            if (isset($meta['acl'][$group]['form']) && $meta['acl'][$group]['form']) {
                $marketplace[$region] = $meta;
            }
        }

        return $marketplace;
    },

    'hasaccess' => function($region, $action, $group = null) {

        $region = $this->region($region);

        if (!$region) {
            return false;
        }

        if (!$group) {
            $group = $this->app->module('cockpit')->getGroup();
        }

        if ($this->app->module('cockpit')->isSuperAdmin($group)) {
            return true;
        }

        if (isset($region['acl'][$group][$action])) {
            return $region['acl'][$group][$action];
        }

        return false;
    }
]);


// extend app lexy parser
$app->renderer->extend(function($content){
    $content = preg_replace('/(\s*)@region\((.+?)\)/', '$1<?php echo cockpit("marketplace")->render($2); ?>', $content);
    return $content;
});

// REST
if (COCKPIT_API_REQUEST) {

    $app->on('cockpit.rest.init', function($routes) {
        $routes['marketplace'] = 'Marketplace\\Controller\\RestApi';
    });

    // allow access to public collections
    $app->on('cockpit.api.authenticate', function($data) {

        if ($data['user'] || $data['resource'] != 'marketplace') return;

        if (isset($data['query']['params'][1])) {

            $region = $this->module('marketplace')->region($data['query']['params'][1]);

            if ($region && isset($region['acl']['public'])) {
                $data['authenticated'] = true;
                $data['user'] = ['_id' => null, 'group' => 'public'];
            }
        }
    });
}

// ADMIN
if (COCKPIT_ADMIN && !COCKPIT_API_REQUEST) {
    include_once(__DIR__.'/admin.php');
}
