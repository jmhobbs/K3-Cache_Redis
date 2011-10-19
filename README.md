K3-Cache_Redis Module
==============

A Ko3 Module by [**John Hobbs**](http://twitter.com/jmhobbs)

Introduction
------------

This module provides a Redis backed cache engine for the [cache module](https://github.com/kohana/cache)

Installation
------------

K3-Cache_Redis is a simple, standard module.

1. Drop the source in your MODPATH folder.
2. Add the module to Kohana::modules in your bootstrap.php
3. Ensure you have enabled the [cache module](https://github.com/kohana/cache) as well.

Usage
-----

To use K3-Cache_Redis you need to configure your Redis servers in <tt>application/config/cache.php</tt>


Example Config:

    <?php defined('SYSPATH') or die('No direct script access.');
    
      return array(
    	  'redis' => array(
          'driver' => 'redis',
          'servers' => array(
            array(
              'host'  => 'localhost',
              'port'  => 6379,
              'alias' => 'local',
            ),
            array(
              'host'  => 'redis.domain.tld',
              'alias' => 'remote',
          ),
        ),
      );

If you provide multiple servers in your configuration the keys are hashed and safely distributed across them.

Once you have it configured, use it as you would a normal cache plugin.

Example:

    Cache::instance( 'redis' )->set( 'key', 'value' );
    echo Cache::instance( 'redis' )->get( 'key' );
    // Prints "value"

## Credits

K3-Cache_Redis uses [Redisent](https://github.com/jdp/redisent) for Redis access.

## License

K3-Cache_Redis is licensed under the MIT License. See <tt>LICENSE</tt> for more details.

