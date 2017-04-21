<?php
/**
 *
 * This file is part of phpFastCache.
 *
 * @license MIT License (MIT)
 *
 * For full copyright and license information, please see the docs/CREDITS.txt file.
 *
 * @author Khoa Bui (khoaofgod)  <khoaofgod@gmail.com> http://www.phpfastcache.com
 * @author Georges.L (Geolim4)  <contact@geolim4.com>
 * @author Chase C. Miller (chasecmiller)  <chase@crumbls.com>
 *
 */

namespace phpFastCache\Drivers\Files;

use phpFastCache\Core\DriverAbstract;
use phpFastCache\Core\PathSeekerTrait;
use phpFastCache\Core\StandardPsr6StructureTrait;
use phpFastCache\Entities\driverStatistic;
use phpFastCache\Exceptions\phpFastCacheDriverCheckException;
use phpFastCache\Exceptions\phpFastCacheDriverException;
use phpFastCache\Util\Directory;
use Psr\Cache\CacheItemInterface;

/**
 * Class Driver
 * @package phpFastCache\Drivers
 */
class Driver extends DriverAbstract
{
    use PathSeekerTrait;

    /**
     *
     */
    const FILE_DIR = 'files';

    /**
     * Driver constructor.
     * @param array $config
     * @throws phpFastCacheDriverException
     */
    public function __construct(array $config = [])
    {
        $this->setup($config);

        if (!$this->driverCheck()) {
            throw new phpFastCacheDriverCheckException(sprintf(self::DRIVER_CHECK_FAILURE, $this->getDriverName()));
        }
    }

    /**
     * @return bool
     */
    public function driverCheck()
    {
        $test = $this->getFileDir();
        if (!is_dir($test)) {
            @mkdir($test, 0777, true);
        } else if (!is_writable($test)) {
            exec("find ".$test." -type d -exec chmod 0777 {} +");
        }
        return is_writable($this->getFileDir())
        ||
        @mkdir($this->getFileDir(), $this->setChmodAuto(), true);
    }


    /**
     * @param \Psr\Cache\CacheItemInterface $item
     * @return mixed
     * @throws \InvalidArgumentException
     */
    protected function driverWrite(CacheItemInterface $item)
    {
        /**
         * Check for Cross-Driver type confusion
         */

        if ($item instanceof Item) {
            $file_path = $this->getFilePath($item->getKey());
            $data = $this->encode($this->driverPreWrap($item));

            $toWrite = true;

            /**
             * Skip if Existing Caching in Options
             */
            if (isset($this->config['skipExisting']) && $this->config['skipExisting'] == true && file_exists($file_path)) {
                $content = $this->readfile($file_path);
                $old = $this->decode($content);
                $toWrite = false;
                if ($old->isExpired()) {
                    $toWrite = true;
                }
            }

            /**
             * Force write
             */
            try {
                if ($toWrite == true) {
                    $f = fopen($file_path, 'w+');
                    fwrite($f, $data);
                    fclose($f);

                    return true;
                }
            } catch (\Exception $e) {
                return false;
            }
        } else {
            throw new \InvalidArgumentException('Cross-Driver type confusion detected');
        }
    }

    /**
     * @param \Psr\Cache\CacheItemInterface $item
     * @return mixed
     */
    protected function driverRead(CacheItemInterface $item)
    {


        /**
         * Check for Cross-Driver type confusion
         */
        $file_path = $this->getFilePath($item->getKey());

        if (!file_exists($file_path)) {
            return null;
        }

        $content = $this->readfile($file_path);

        return $this->decode($content);

    }

    /**
     * @param \Psr\Cache\CacheItemInterface $item
     * @return bool
     * @throws \InvalidArgumentException
     */
    protected function driverDelete(CacheItemInterface $item)
    {
        /**
         * Check for Cross-Driver type confusion
         */
        if ($item instanceof Item) {
            $file_path = $this->getFilePath($item->getKey(), true);
            if (file_exists($file_path) && @unlink($file_path)) {
                return true;
            } else {
                return false;
            }
        } else {
            throw new \InvalidArgumentException('Cross-Driver type confusion detected');
        }
    }

    /**
     * @return bool
     */
    protected function driverClear()
    {
        return (bool)Directory::rrmdir($this->getPath(true));
    }

    /**
     * @return bool
     */
    protected function driverConnect()
    {
        return true;
    }

    /**
     * @param string $optionName
     * @param mixed $optionValue
     * @return bool
     * @throws \InvalidArgumentException
     */
    public static function isValidOption($optionName, $optionValue)
    {
        parent::isValidOption($optionName, $optionValue);
        switch ($optionName) {
            case 'path':
                return is_string($optionValue);
                break;

            case 'default_chmod':
                return is_numeric($optionValue);
                break;

            case 'securityKey':
                return is_string($optionValue);
                break;
            case 'htaccess':
                return is_bool($optionValue) || $optionValue == 1 || $optionValue == 0;
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * @return array
     */
    public static function getValidOptions()
    {
        // Make this simpler by removing options.
        return [];
        return ['path', 'default_chmod', 'securityKey', 'htaccess'];
    }

    /**
     * @return array
     */
    public static function getRequiredOptions()
    {
        return [];
    }

    /********************
     *
     * PSR-6 Extended Methods
     *
     *******************/

    /**
     * @return driverStatistic
     * @throws \phpFastCache\Exceptions\phpFastCacheCoreException
     * @throws \phpFastCache\Exceptions\phpFastCacheDriverException
     */
    public function getStats()
    {
        $stat = new driverStatistic();
        $path = $this->getFilePath(false);

        if (!is_dir($path)) {
            throw new phpFastCacheDriverException("Can't read PATH:" . $path, 94);
        }

        $stat->setData(implode(', ', array_keys($this->itemInstances)))
            ->setRawData([])
            ->setSize(Directory::dirSize($path))
            ->setInfo('Number of files used to build the cache: ' . Directory::getFileCount($path));

        return $stat;
    }

    /**
     * @return string
     * @throws \phpFastCache\Exceptions\phpFastCacheCoreException
     */
    public function getFileDir()
    {
        return $this->getPath() . DIRECTORY_SEPARATOR;
    }

    /**
     * @param bool $readonly
     * @return string
     * @throws phpFastCacheDriverException
     */
    public function getPath($readonly = false)
    {
        // Simplified.
        $ret = null;
        if (defined('WP_CONTENT_DIR')) {
            $ret = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache';
        } else {
            $ret = dirname(__FILE__);
            $ret = substr($ret, 0, strrpos($ret, '/plugins/crumbls_cache/'));
            $ret .= DIRECTORY_SEPARATOR . 'cache';
        }

        $ret .= DIRECTORY_SEPARATOR . 'crumbls';


        $append = null;
        if (function_exists('site_url')) {
            $append = preg_replace('#^https?:\/\/#', '', site_url());
        } else if (array_key_exists('HTTP_HOST', $_SERVER)) {
            $append = strtolower($_SERVER['HTTP_HOST']);
        } else {
            $append = ($this->isPHPModule() ? 'web' : 'cli');
        }

        if (!$append) {
            $append = 'global';
        }

        if ($append !== 'cli') {
            if ($this->is_ssl()) {
                $append = 'https'.$append;
            } else {
                $append = 'http'.$append;
            }
        }

        $append = preg_replace('#[^A-Z]#i', '', $append);
        $append = preg_replace('/\W+$/', '', $append);

        $append = static::cleanFileName($append);
        $ret .= DIRECTORY_SEPARATOR.$append;

        $ret = Directory::getAbsolutePath($ret);

        if (function_exists('is_admin') && is_admin()) {
            if (!@file_exists($ret) || !@is_writable($ret)) {
                throw new phpFastCacheDriverException('PLEASE CREATE OR CHMOD ' . $ret . ' - 0777 OR ANY WRITABLE PERMISSION!');
            }
        }

        return $ret;

        /**
         * In readonly mode we only attempt
         * to verify if the directory exists
         * or not, if it does not then we
         * return the temp dir
         */
        if ($readonly === true) {
            if (!@file_exists($ret) || !@is_writable($full_path)) {
                return false;
            }
            return $ret;
        } else {
            if (!isset($t))
            if (!isset($this->tmp[$full_path_hash]) || (!@file_exists($full_path) || !@is_writable($full_path))) {
                if (!@file_exists($full_path)) {
                    @mkdir($full_path, 0777, true);
                } elseif (!@is_writable($full_path)) {
                    if (!@chmod($full_path, $this->setChmodAuto())) {
                        /**
                         * Switch back to tmp dir
                         * again if the path is not writable
                         */
                        $full_path = $full_path_tmp;
                        if (!@file_exists($full_path)) {
                            @mkdir($full_path, 0777, true);
                        }
                    }
                }

                /**
                 * In case there is no directory
                 * writable including type temporary
                 * one, we must throw an exception
                 */
                if (!@file_exists($full_path) || !@is_writable($full_path)) {
                    throw new phpFastCacheDriverException('PLEASE CREATE OR CHMOD ' . $full_path . ' - 0777 OR ANY WRITABLE PERMISSION!');
                }
                $this->tmp[$full_path_hash] = $full_path;
                $this->htaccessGen($full_path, array_key_exists('htaccess', $this->config) ? $this->config['htaccess'] : false);
            }
        }
        return realpath($full_path);
    }

    public function is_ssl() {
        if ( isset($_SERVER['HTTPS']) ) {
            if ( 'on' == strtolower($_SERVER['HTTPS']) )
                return true;
            if ( '1' == $_SERVER['HTTPS'] )
                return true;
        } elseif ( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
            return true;
        }
        return false;
    }

}
