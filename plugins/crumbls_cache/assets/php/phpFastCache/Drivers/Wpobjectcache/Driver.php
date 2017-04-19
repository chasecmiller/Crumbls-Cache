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

namespace phpFastCache\Drivers\Wpobjectcache;

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
    private $cache = [];

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
            $config['path'] = rtrim($config['path'], '/') . '/cache/crumbls/';
        }

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
        return true;
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
            if (!is_admin()) {
//                print_r($item);
  //              exit;
            }
            // Build group, if needed.
            $group = $item->getTags();
            if (sizeof($group) > 1) {
                sort($group);
                $group = md5(serialize($group));
            } else {
                $group = 'default';
            }

            if (!array_key_exists($group, $this->cache)) {
                $this->cache[$group] = [];
            }

            $this->cache[$group][$item->getKey()] = $item;//$item->get();

            return true;
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
        // Build group, if needed.
        if ($group = $item->getTags()) {
            sort($group);
            $group = md5(serialize($group));
        } else {
            $group = 'default';
        }

        if (!array_key_exists($group, $this->cache)) {
            return null;
        }

        if (!array_key_exists($item->getKey(), $this->cache[$group])) {
            return null;
        }

        if (!$item->getExpirationDate() instanceof \DateTime) {
            return null;
        }

        return $this->decode($this->cache[$group][$item->getKey()]);

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
            $group = $item->getTags();
            if (sizeof($group) > 1) {
                sort($group);
                $group = md5(serialize($group));
            } else {
                $group = 'default';
            }
            if (!array_key_exists($group, $this->cache)) {
                return false;
            } else if (!array_key_exists($item->getKey(), $this->cache[$group])) {
                return false;
            }
            unset($this->cache[$item->getKey()]);
            return true;
        } else {
            throw new \InvalidArgumentException('Cross-Driver type confusion detected');
        }
    }

    /**
     * @return bool
     */
    protected function driverClear()
    {
        $this->cache = [];
        return true;
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
        return [];
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

        $size = array_sum(array_map(function($e) {
            return sizeof($e);
        }, $this->cache));

        $string = function_exists('__') ? __('Size: ', 'crumbls\plugins\fastcache') : 'Size: ';

        $stat->setRawData(['size' => $size])
        ->setSize($size)
        ->setInfo($string.' '.$size);

        return $stat;
    }

    public function getName()
    {
        return 'WP Style Object Cache';
    }

}