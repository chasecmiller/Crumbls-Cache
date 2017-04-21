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
        if (
            !array_key_exists('path', $config)
        ||
            !$config['path']
        ) {
            if (defined('WP_CONTENT_DIR')) {
                $config['path'] = WP_CONTENT_DIR;
            } else if (preg_match('#^(.*?\/wp-content)\/#i', __FILE__, $m)) {
                $config['path'] = $m[0];
            }
            $config['path'] = rtrim($config['path'], '/').'/cache/crumbls/';
        }

//        print_r($config);
//        exit;

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
        return is_writable($this->getFileDir()) || @mkdir($this->getFileDir(), $this->setChmodAuto(), true);
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
            echo $file_path;
            exit;
            $data = $this->encode($this->driverPreWrap($item));

            $toWrite = true;

            /**
             * Skip if Existing Caching in Options
             */
            if (isset($this->config[ 'skipExisting' ]) && $this->config[ 'skipExisting' ] == true && file_exists($file_path)) {
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
        return (bool) Directory::rrmdir($this->getPath(true));
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
        return $this->getPath() . DIRECTORY_SEPARATOR;// . self::FILE_DIR;
    }

    /**
     * @param bool $readonly
     * @return string
     * @throws phpFastCacheDriverException
     */
    public function getPath($readonly = false)
    {
        /**
         * Get the base system temporary directory
         */
        $tmp_dir = rtrim(ini_get('upload_tmp_dir') ?: sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'phpfastcache';

        /**
         * Calculate the security key
         */
        $securityKey = array_key_exists('securityKey', $this->config) ? $this->config[ 'securityKey' ] : '';
        if (!$securityKey || $securityKey === 'auto') {
            if (function_exists('site_url')) {
                $securityKey = site_url();
            } else if (isset($_SERVER[ 'HTTP_HOST' ])) {
                $securityKey = preg_replace('/^www./', '', strtolower(str_replace(':', '_', $_SERVER[ 'HTTP_HOST' ])));
                $securityKey = preg_replace('/\W+$/', '', $securityKey);
            } else {
                $securityKey = ($this->isPHPModule() ? 'web' : 'cli');
            }
        }
        if ($securityKey !== '') {
            $securityKey .= '/';
        }
        $securityKey = static::cleanFileName($securityKey);

        /**
         * Extends the temporary directory
         * with the security key and the driver name
         */
        $tmp_dir = rtrim($tmp_dir, '/') . DIRECTORY_SEPARATOR;
        if (empty($this->config[ 'path' ]) || !is_string($this->config[ 'path' ])) {
            $path = $tmp_dir;
        } else {
            $path = rtrim($this->config[ 'path' ], '/') . DIRECTORY_SEPARATOR;
        }

//        $path_suffix = $securityKey . DIRECTORY_SEPARATOR . $this->getDriverName();
        $path_suffix = $securityKey;// . DIRECTORY_SEPARATOR . $this->getDriverName();
        $full_path = Directory::getAbsolutePath($path . $path_suffix);

        $full_path_tmp = Directory::getAbsolutePath($tmp_dir . $path_suffix);
        $full_path_hash = md5($full_path);


        /**
         * In readonly mode we only attempt
         * to verify if the directory exists
         * or not, if it does not then we
         * return the temp dir
         */
        if ($readonly === true) {
            if(!@file_exists($full_path) || !@is_writable($full_path)){
                return $full_path_tmp;
            }
            return $full_path;
        }else{
            if (!isset($this->tmp[ $full_path_hash ]) || (!@file_exists($full_path) || !@is_writable($full_path))) {
                if (!@file_exists($full_path)) {
                    @mkdir($full_path, $this->setChmodAuto(), true);
                }elseif (!@is_writable($full_path)) {
                    if (!@chmod($full_path, $this->setChmodAuto()))
                    {
                        /**
                         * Switch back to tmp dir
                         * again if the path is not writable
                         */
                        $full_path = $full_path_tmp;
                        if (!@file_exists($full_path)) {
                            @mkdir($full_path, $this->setChmodAuto(), true);
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
                $this->tmp[ $full_path_hash ] = $full_path;
                $this->htaccessGen($full_path, array_key_exists('htaccess', $this->config) ? $this->config[ 'htaccess' ] : false);
            }
        }
        return realpath($full_path);
    }


}
