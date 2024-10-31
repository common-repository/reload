<?php

/*
  Plugin Name: Reload
  Description: Automatically refresh your browser if you update a post
  Author: Bence Meszaros
  Author URI: http://bencemeszaros.com
  Plugin URI: http://wordpress.org/extend/plugins/reload/
  Version: 1.1.3
  License: GPL2
 */
/*  Copyright 2011  Bence Meszaros  (email : bence@bencemeszaros.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if (!class_exists('ReloadPlugin'))
{
    class ReloadPlugin
    {
        protected $reloadInterval = 60000;
        protected $contentCheckFileName = 'reload-contentcheck.txt';
        protected $contentCheckFile;

        /** options array */
        protected $options = array();

        protected $fastCheck = false;

        /**
         * Constructor
         */
        public function __construct()
        {
            add_action('init', array(&$this, 'init'));
        }

        /**
         * Initialize the wordpress plugin
         * On the visitor side: load the javascript
         * On the admin side: add menu items and dactivation hook
         */
        public function init()
        {
            $this->getOptions();

            // support for Formidable forms (http://wordpress.org/plugins/formidable/)
            add_action('frm_after_create_entry', array(&$this, 'touchContentCheckFile'), 5);
            add_action('frm_after_update_entry', array(&$this, 'touchContentCheckFile'), 5);
            add_action('frm_before_destroy_entry', array(&$this, 'touchContentCheckFile'), 5);

            if (is_admin()) {
                // backend init
                register_deactivation_hook( __FILE__, array(&$this, 'deactivate') );
                add_action( 'save_post', array(&$this, 'touchContentCheckFile') );
                // ajax
                add_action( 'wp_ajax_nopriv_reload_monitor', array(&$this, 'startMonitor'));
                add_action( 'wp_ajax_reload_monitor', array(&$this, 'startMonitor'));
            }
            else {
                add_action('wp_head', array(&$this, 'header_scripts'));
                // frontend js
                wp_enqueue_script('reload_plugin', plugins_url('reload.js', __FILE__));
            }
        }

        /**
         * Inject some parameters in the js object
         */
        public function header_scripts()
        {
            // if we use the fast check, then the ajax url is not needed
            $setUrl = $this->fastCheck ? '' : 'ReloadPlugin.setUrl("' . admin_url('admin-ajax.php') . '");';

            echo '<script>if (typeof(ReloadPlugin) != "undefined") { ' . $setUrl . ' ReloadPlugin.setInterval(' . $this->reloadInterval . ');}</script>';
        }

        /**
         * Ajax entry point for the file monitor
         */
        function startMonitor() {
            include_once ('reload-monitor.php');
            die;
        }

        /**
         * Get the settings from wp_options table
         * Or add it if none found
         */
        protected function getOptions()
        {
            // set the content check file path
            $upload_dir = wp_upload_dir();
            $this->contentCheckFile = $upload_dir['basedir'] . '/' . $this->contentCheckFileName;

            // check if this is a standard directory structure, then we can use the fast file checker instead of the slow wp-admin way
            $std_uploads = realpath(dirname(__FILE__) . '/../../uploads');
            if ($std_uploads == realpath($upload_dir['basedir'])) {
                $this->fastCheck = true;
            }
        }

        /**
         * On deactivateing the plugin, we remove the options record from wp_options
         */
        public function deactivate()
        {
            // remove the content check file
            unlink($this->contentCheckFile);
        }

        /**
         * Content check file updater
         * This is called at the save_post hook, to trigger a refresh at every post/page save
         */
        public function touchContentCheckFile()
        {
            touch($this->contentCheckFile);
        }

    }

    new ReloadPlugin;
}