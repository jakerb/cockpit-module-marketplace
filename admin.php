<?php

$app->on('admin.init', function() {


    if (!$this->module('cockpit')->getGroupRights('marketplace') && !$this->module('marketplace')->getMarketplaceInGroup()) {

        $this->bind('/marketplace/*', function() {
            return $this('admin')->denyRequest();
        });

        return;
    }

    // bind admin routes /marketplace/*
    $this->bindClass('Marketplace\\Controller\\Admin', 'marketplace');

    // add to modules menu
    $this('admin')->addMenuItem('modules', [
        'label' => 'Marketplace',
        'icon'  => 'marketplace:icon.svg',
        'route' => '/marketplace',
        'active' => strpos($this['route'], '/marketplace') === 0
    ]);

    /**
     * listen to app search to filter marketplace
     */
    $this->on('cockpit.search', function($search, $list) {

        foreach ($this->module('marketplace')->getMarketplaceInGroup() as $region => $meta) {

            if (stripos($region, $search)!==false || stripos($meta['label'], $search)!==false) {

                $list[] = [
                    'icon'  => 'th',
                    'title' => $meta['label'] ? $meta['label'] : $meta['name'],
                    'url'   => $this->routeUrl('/marketplace/region/'.$meta['name'])
                ];
            }
        }
    });

});
