<?php

namespace Marketplace\Controller;


class Admin extends \Cockpit\AuthController {


    public function index() {

        return $this->render('marketplace:views/index.php');
    }

    private function scan_dir($dir) {
        $ignored = array('.', '..', '.svn', '.htaccess');

        $files = array();    
        foreach (scandir($dir) as $file) {
            if (in_array($file, $ignored)) continue;
            $files[$file] = filemtime($dir . '/' . $file);
        }

        arsort($files);
        $files = array_keys($files);

        return ($files) ? $files : false;
    }

    public function install_module($index) {
        $module_index = intval($index);
        $git_url = $this->module("marketplace")->get_git_url();
        $module_dir = str_replace('Marketplace/Controller', false, __DIR__);
        $zip_file = '/archive/master.zip';
        $file_name = substr(sha1(rand(0,100) . date('ymd', strtotime('now'))), 0, 16) . '.zip'; 
        $tmp_path = $this->app->path('#tmp:');
        $marketplace_list = $this->module("marketplace")->get();
        $success = false;

        if(isset($marketplace_list[$module_index])) {

            $git = $git_url . str_replace('//', '/', $marketplace_list[$module_index]->module_repo . $zip_file);

            $download =  file_put_contents($tmp_path . $file_name, file_get_contents("{$git}"));

            if($download) {

                $zip = new \ZipArchive;

                $file = $zip->open($tmp_path . $file_name);
                if ($file === TRUE) {
                
                  $success = $zip->extractTo($module_dir);
                  $zip->close();

                  # Delete temp zip
                  unlink($tmp_path . $file_name);

                  $module_install_dir = $this->scan_dir($module_dir)[0];

                  # Attempt to delete if the module doesn't have a bootstrap file.
                  if($module_install_dir) {
                    if(!is_file($module_dir . $module_install_dir . '/bootstrap.php')) {
                        $success = false;
                        unlink($module_dir . $module_install_dir);
                    } else {
                        rename($module_install_dir, ($module_dir . $marketplace_list[$module_index]->module_name));
                    }
                  }

                }
            }

        }

        return json_encode(array('success' => $success));

    }

    
}
