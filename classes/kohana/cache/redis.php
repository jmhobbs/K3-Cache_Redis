<?php defined('SYSPATH') or die('No direct script access.');

	/**
	 * [Kohana Cache](api/Kohana_Cache) Redis driver,
	 * 
	 * ### Supported cache engines
	 * 
	 * *  [Redis](http://redis.io/)
	 * 
	 * ### Configuration example
	 * 
	 * Below is an example of a _redis_ server configuration.
	 * 
	 *     return array(
	 *          'default'   => array(                          // Default group
	 *                  'driver'         => 'redis'            // using Redis driver
	 *                  'servers'        => array(             // Available server definitions
	 *                         // First redis server
	 *                         array(
	 *                              'host'             => 'localhost',
	 *                              'port'             => 6379,
	 *                              'alias'            => 'local',
	 *                         ),
	 *                         // Second redis server
	 *                         array(
	 *                              'host'             => 'redis.domain.tld',
	 *                              'port'             => 6379,
	 *                              'alias'            => 'remote',
	 *                         ),
	 *                  ),
	 *           ),
	 *     )
	 * 
	 * In cases where only one cache group is required, if the group is named `default` there is
	 * no need to pass the group name when instantiating a cache instance.
	 * 
	 * #### General cache group configuration settings
	 * 
	 * Below are the settings available to all types of cache driver.
	 * 
	 * Name           | Required | Description
	 * -------------- | -------- | ---------------------------------------------------------------
	 * driver         | __YES__  | (_string_) The driver type to use
	 * servers        | __YES__  | (_array_) Associative array of server details, must include a __host__ key. (see _Redis server configuration_ below)
	 * 
	 * #### Redis server configuration
	 * 
	 * The following settings should be used when defining each redis server
	 * 
	 * Name             | Required | Description
	 * ---------------- | -------- | ---------------------------------------------------------------
	 * host             | __YES__  | (_string_) The host of the redis server, i.e. __localhost__; or __127.0.0.1__; or __redis.domain.tld__
	 * port             | __NO__   | (_integer_) Point to the port where redis is listening for connections. Default __6379__
	 * alias            | __NO__   | (_string_)  Name for direct reference to this server. Default __NULL__
	 * 
	 * ### System requirements
	 * 
	 * *  Kohana 3.0.x
	 * *  PHP 5.2.4 or greater
	 * 
	 * @package    Kohana/Cache
	 * @category   Module
	 * @version    1.0
	 * @author     John Hobbs
	 * @copyright  (c) 2011 John Hobbs
	 * @license    http://www.opensource.org/licenses/mit-license.php
	 */
	class Kohana_Cache_Redis extends Cache {

		/**
		 * Used as a prefix on all cache keys to make Cache_Redis::delete_all safer.
		 */
		protected static $KEY_PREFIX = 'kohana-cache-redis-';

		/**
		 * Redis resource
		 *
		 * @var Redis
		 */
		protected $_redis;

		/**
		 * The default configuration for the redis server
		 *
		 * @var array
		 */
		protected $_default_config = array(
			'host'  => 'localhost',
			'port'  => 6379,
			'alias' => NULL,
		);

		/**
		 * Constructs the redis Kohana_Cache object
		 *
		 * @param   array     configuration
		 * @throws  Kohana_Cache_Exception
		 */
		protected function __construct(array $config)
		{
			if( ! defined( 'KOHANA_CACHE_REDIS_LOADED' ) ) {
				$path = Kohana::find_file( 'vendor', 'redisent/redisent_cluster' );
				if( false === $path ) {
					throw new Kohana_Cache_Exception('Redisent vendor code not found');
				}
				require_once( $path );
				define( 'KOHANA_CACHE_REDIS_LOADED', true );
			}

			parent::__construct($config);

			// Load servers from configuration
			$_servers = Arr::get( $this->_config, 'servers', NULL );

			if ( ! $_servers) {
				// Throw an exception if no server found
				throw new Kohana_Cache_Exception('No Redis servers defined in configuration');
			}

			$servers = array();
			
			// Normalize the settings into redisent format.
			foreach( $_servers as $server ) {

				$server += $this->_default_config;

				if( is_null( $server['alias'] ) ) {
					$servers[] = array( 'host' => $server['host'], 'port' => $server['port'] );
				}
				else {
					$servers[$server['alias']] = array( 'host' => $server['host'], 'port' => $server['port'] );
				}

			}

			// Setup Redis
			$this->_redis = new RedisentCluster( $servers );
		}

		/**
		 * Retrieve a cached value entry by id.
		 * 
		 *     // Retrieve cache entry from redis group
		 *     $data = Cache::instance('redis')->get('foo');
		 * 
		 *     // Retrieve cache entry from redis group and return 'bar' if miss
		 *     $data = Cache::instance('redis')->get('foo', 'bar');
		 *
		 * @param   string   id of cache to entry
		 * @param   string   default value to return if cache miss
		 * @return  mixed
		 * @throws  Kohana_Cache_Exception
		 */
		public function get($id, $default = NULL)
		{
			// Get the value from Redis
			$value = $this->_redis->get($this->_sanitize_id($id));

			// If the value wasn't found, normalise it. Otherwise, unpack it.
			if ( is_null( $value ) ) {
				$value = $default;
			}
			else {
				$value = unserialize( $value );
			}

			// Return the value
			return $value;
		}

		/**
		 * Set a value to cache with id and lifetime
		 * 
		 *     $data = 'bar';
		 * 
		 *     // Set 'bar' to 'foo' in redis group for 10 minutes
		 *     if (Cache::instance('redis')->set('foo', $data, 600))
		 *     {
		 *          // Cache was set successfully
		 *          return
		 *     }
		 *
		 * @param   string   id of cache entry
		 * @param   mixed    data to set to cache
		 * @param   integer  lifetime in seconds, default __3600__
		 * @return  boolean
		 */
		public function set($id, $data, $lifetime = 3600)
		{
			// Set the data to redis
			return 'OK' === $this->_redis->setex( $this->_sanitize_id( $id ), $lifetime, serialize( $data ) );
		}

		/**
		 * Delete a cache entry based on id
		 * 
		 *     // Delete the 'foo' cache entry immediately
		 *     Cache::instance('redis')->delete('foo');
		 * 
		 *     // Delete the 'bar' cache entry after 30 seconds
		 *     Cache::instance('redis')->delete('bar', 30);
		 *
		 * @param   string   id of entry to delete
		 * @param   integer  timeout of entry, if zero item is deleted immediately, otherwise the item will delete after the specified value in seconds
		 * @return  boolean
		 */
		public function delete($id, $timeout = 0)
		{
			if( $timeout > 0 ) {
				return 1 === $this->_redis->expire( $this->_sanitize_id( $id ), $timeout ); 
			}
			else {
				return 0 != $this->_redis->del( $this->_sanitize_id( $id ) );
			}
		}

		/**
		 * Delete all cache entries.
		 * 
		 * Beware of using this method when
		 * using shared memory cache systems, as it will wipe every
		 * entry within the system for all clients.
		 * 
		 *     // Delete all cache entries in the default group
		 *     Cache::instance('redis')->delete_all();
		 *
		 * @return  boolean
		 */
		public function delete_all()
		{
			// TODO: Un-loop this. DEL can handle multiple keys.
			foreach( $this->_redis->keys( self::$KEY_PREFIX . '*' ) as $key ) {
				$this->_redis->del( $key );
			}
			return true;
		}

		/**
		 * Replaces troublesome characters with underscores.
		 *
		 * For Redis cache we take the extra step of pre-pending an identifier to make
		 * Cache_Redis::delete_all safer for mixed use redis servers.
		 *
		 *     // Sanitize a cache id
		 *     $id = $this->_sanitize_id($id);
		 * 
		 * @param   string   id of cache to sanitize
		 * @return  string
		 */
		protected function _sanitize_id($id)
		{
			// Change slashes and spaces to underscores
			return self::$KEY_PREFIX . parent::_sanitize_id( $id );
		}

	}
