<?php
/**
 * Reload Plugin file monitor
 * @author Bence Meszaros
 * @link http://bencemeszaros.com
 * @link http://wordpress.org/extend/plugins/reload/
 * @version 1.1.3
 */

if (!class_exists('LiveMonitor'))
{
    class ReloadMonitor
    {
        /** list of directories to check for changes */
        protected $dirs = array();
        /** ignore these files or directories */
        protected $ignore = array();

        /** default time limit in seconds */
        protected $timeLimit = 125;

        /** Refresh css files without reloading the page */
        protected $cssOnTheFly = true;

        /** the time to die */
        protected $deadLine;

        /**
         * Constructor
         */
        public function __construct()
        {
            // check that $_GET['s'] is a valid unix timestamp
            if (!empty($_GET['s']) && is_numeric($_GET['s']))
            {
                // find the content and upload directories
                $this->getDirs();

                // $_GET['s'] is in millisec, but we need only seconds
                $start = (int) ($_GET['s'] / 1000);

                $this->headers();
                $this->setDeadLine();
                $this->main($start);
            }
            else
            {
                header('HTTP/1.1 400 Bad Request');
                die;
            }
        }

        protected function getDirs()
        {
            if (function_exists('wp_upload_dir')) {
                // wp-content/uploads
                $upload_dir = wp_upload_dir();
                if (!empty($upload_dir['basedir'])) {
                    $this->dirs[] = $upload_dir['basedir'];
                }
                // skip theme and plugin dir check
//                // wp-content/themes
//                $theme_root = get_theme_root();
//                if (!empty($theme_root)) {
//                    $this->dirs[] = $theme_root;
//                }
//                // wp-content/plugins
//                $plugin_dir = plugin_dir_path( __FILE__ );
//                if (!empty($plugin_dir)) {
//                    $this->dirs[] = $plugin_dir . '../';
//                }
            }
            else {
                $this->dirs[] = realpath(dirname(__FILE__) . '/../../uploads');
            }
        }

        /**
         * Output the no-cache headers
         */
        protected function headers()
        {
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: -1');
        }

        /**
         * Sets the time limit if possible
         */
        protected function setDeadLine()
        {
            // in safe mode there is no way to set the time limit
            if (!ini_get('safe_mode'))
            {
                set_time_limit($this->timeLimit);
            }
            // lets check what the actual limit is
            $limit = ini_get('max_execution_time');

            if (empty($limit) || $limit < 1)
            {
                // in case of unsuccesful ini_get, (or unlimited execution), we fall back to the default 30 sec
                $limit = 30;
            }
            // we stop the loop 5 sec befor the time limit, just for sure
            $this->deadLine = time() + $limit - 5;
        }

        /**
         * Main function
         * @param int $start start date in unix timestamp
         */
        protected function main($start)
        {
            // clear file state cache
            clearstatcache();
            // look for the changes
            foreach ($this->dirs as $root)
            {
                $result = $this->checkFile(realpath($root), $start);
                //$result = $this->checkDir(realpath($root), $start);
                if ($result)
                {
                    // if we find modified files in any of the directories, we can skip the rest
                    echo json_encode($result);

                    die;
                }
            }
        }

        /**
         * Check only a single file for changes
         *
         * @param string $root directory path
         * @param int $start (unix timestamp) to find newer files of
         * @return bool true if modified file found, false otherwise
         */
        protected function checkFile($root, $start)
        {
            $file = $root . '/reload-contentcheck.txt';

            if (file_exists($file)) {
                $mtime = filemtime($file);
                if ($mtime && $start < $mtime)
                {
                    return true;
                }
            }
        }

        /**
         * A fast (and non-recursive) function to check for modified files in a directory structure
         *
         * @param string $root directory path
         * @param int $start (unix timestamp) to find newer files of
         * @return bool true if modified file found, false otherwise
         */
        protected function checkDir($root, $start)
        {
            $stack[] = $root;
            // walk through the stack
            while (!empty($stack))
            {
                $dir = array_shift($stack);
                $files = glob($dir . '/*');
                // make sure that we have an array (glob can return false in some cases)
                if (!empty($files) && is_array($files))
                {
                    foreach ($files as $file)
                    {
                        if (empty($this->ignore) || !in_array(basename($file), $this->ignore))
                        {
                            if (is_dir($file))
                            {
                                // we add the directories to the stack to check them later
                                $stack[] = $file;
                            }
                            elseif (is_file($file))
                            {
                                // and check the modification times of the files
                                $mtime = filemtime($file);
                                if ($mtime && $start < $mtime)
                                {
                                    $pinfo = pathinfo($file);
                                    // return true at the first positive match
                                    if ($this->cssOnTheFly && $pinfo['extension'] == 'css') {
                                        // if the file is a css then then we send the whole path back
                                        return $mtime * 1000;
                                    }
                                    else {
                                        // otherwise return true
                                        return true;
                                    }
                                }
                            }
                        }
                    } // end foreach
                }
            } // end while

            return false;
        }

    } // end LiveMonitor

    new ReloadMonitor();


} // end class check if