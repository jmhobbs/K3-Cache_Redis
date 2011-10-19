<?php defined('SYSPATH') or die('No direct script access.');

	return array(
		'redis' => array(
			'driver' => 'redis',
			'servers' => array(
				array(
					'host' => 'localhost',
					'port' => 6379,
				),
			),
		),
	);

