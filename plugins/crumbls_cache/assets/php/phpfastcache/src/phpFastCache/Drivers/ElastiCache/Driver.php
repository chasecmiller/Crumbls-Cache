<?php

namespace phpFastCache\Drivers\Elasticache;

use Memcached as MemcachedSoftware;
use phpFastCache\Core\DriverAbstract;
use phpFastCache\Core\MemcacheDriverCollisionDetectorTrait;
use phpFastCache\Core\StandardPsr6StructureTrait;
use phpFastCache\Entities\driverStatistic;
use phpFastCache\Exceptions\phpFastCacheDriverCheckException;
use phpFastCache\Exceptions\phpFastCacheDriverException;
use Psr\Cache\CacheItemInterface;

/**
 * Class Driver
 * @package phpFastCache\Drivers
 */
class Driver extends DriverAbstract
{
    use MemcacheDriverCollisionDetectorTrait;

    /**
     * Driver constructor.
     * @param array $config
     * @throws phpFastCacheDriverException
     */
    public function __construct(array $config = [])
    {
        self::checkCollision('Memcached');
        $this->setup($config);

        if (!$this->driverCheck()) {
            throw new phpFastCacheDriverCheckException(sprintf(self::DRIVER_CHECK_FAILURE, $this->getDriverName()));
        } else {
            $this->instance = new MemcachedSoftware();
            $this->driverConnect();
        }
    }

    /**
     * @return bool
     */
    public function driverCheck()
    {
        // How to check if we are on an Amazon instance?
        return class_exists('Memcached');
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
            $ttl = $item->getExpirationDate()->getTimestamp() - time();

            // Memcache will only allow a expiration timer less than 2592000 seconds,
            // otherwise, it will assume you're giving it a UNIX timestamp.
            if ($ttl > 2592000) {
                $ttl = time() + $ttl;
            }
            return $this->instance->set($item->getKey(), $this->driverPreWrap($item), $ttl);
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
        $val = $this->instance->get($item->getKey());
        if ($val === false) {
            return null;
        } else {
            return $val;
        }
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
            return $this->instance->delete($item->getKey());
        } else {
            throw new \InvalidArgumentException('Cross-Driver type confusion detected');
        }
    }

    /**
     * @return bool
     */
    protected function driverClear()
    {
        return $this->instance->flush();
    }

    /**
     * @return bool
     */
    protected function driverConnect()
    {
        if (
        !array_key_exists('configuration_endpoint', $this->config)
        ||
        !$this->config['configuration_endpoint']
            ||
        !preg_match('#^(.*?):(\d+)$#', $this->config['configuration_endpoint'], $this->config['configuration_endpoint'])
        ) {
            return false;
        }

            try {
                if (!$this->instance->addServer($this->config['configuration_endpoint'][1], $this->config['configuration_endpoint'][2])) {
                    $this->fallback = true;
                }
//                if (!empty($server['sasl_user']) && !empty($server['sasl_password'])) {
  //                  $this->instance->setSaslAuthData($server['sasl_user'], $server['sasl_password']);
    //            }
            } catch (\Exception $e) {
                $this->fallback = true;
            }

        // Settings?
        $this->instance->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 10);
        $this->instance->setOption(\Memcached::OPT_DISTRIBUTION, \Memcached::DISTRIBUTION_CONSISTENT);
        $this->instance->setOption(\Memcached::OPT_SERVER_FAILURE_LIMIT, 2);
        $this->instance->setOption(\Memcached::OPT_REMOVE_FAILED_SERVERS, true);
        $this->instance->setOption(\Memcached::OPT_RETRY_TIMEOUT, 1);
    }

    /********************
     *
     * PSR-6 Extended Methods
     *
     *******************/

    /**
     * @return driverStatistic
     */
    public function getStats()
    {
        $stats = (array)$this->instance->getStats();
        $stats['uptime'] = (isset($stats['uptime']) ? $stats['uptime'] : 0);
        $stats['version'] = (isset($stats['version']) ? $stats['version'] : 'UnknownVersion');
        $stats['bytes'] = (isset($stats['bytes']) ? $stats['version'] : 0);

        $date = (new \DateTime())->setTimestamp(time() - $stats['uptime']);

        return (new driverStatistic())
            ->setData(implode(', ', array_keys($this->itemInstances)))
            ->setInfo(sprintf("The elasticache daemon v%s is up since %s.\n For more information see RawData.", $stats['version'], $date->format(DATE_RFC2822)))
            ->setRawData($stats)
            ->setSize($stats['bytes']);
    }

    /**
     * Added by Chase C. Miller
     */
    public static function getValidOptions()
    {
        return [
            'configuration_endpoint'
        ];
    }
}