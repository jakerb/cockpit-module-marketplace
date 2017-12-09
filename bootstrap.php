<?php

$this->module("marketplace")->extend([

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
