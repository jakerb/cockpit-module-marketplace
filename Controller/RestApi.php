<?php
namespace Marketplace\Controller;

class RestApi extends \LimeExtra\Controller {

    public function get($name = null) {

        if (!$name) {
            return false;
        }

        if ($this->module('cockpit')->getUser()) {

            if (!$this->module('marketplace')->hasaccess($name, 'render') && !$this->module('marketplace')->hasaccess($name, 'form')) {
                return $this->stop('{"error": "Unauthorized"}', 401);
            }
        }

        $params  = $this->param("params", []);
        $content = $this->module("marketplace")->render($name, $params);

        return is_null($content) ? false : $content;
    }

    public function data($name = null) {

        if (!$name) {
            return false;
        }

        if ($this->module('cockpit')->getUser()) {

            if (!$this->module('marketplace')->hasaccess($name, 'data') && !$this->module('marketplace')->hasaccess($name, 'form')) {
                return $this->stop('{"error": "Unauthorized"}', 401);
            }
        }

        $region = $this->module('marketplace')->region($name);

        if (!$region) {
            return false;
        }

        return isset($region['data']) ? $region['data'] : [];
    }

    public function listMarketplace($extended = false) {

        $user = $this->module('cockpit')->getUser();

        if ($user) {
            $marketplace = $this->module('marketplace')->getMarketplaceInGroup($user['group']);
        } else {
            $marketplace = $this->module('marketplace')->marketplace();
        }

        return $extended ? $marketplace : array_keys($marketplace);
    }

}
