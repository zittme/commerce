<?php

namespace Zittme\Modules\Commerce\Models;

class Deferred
{
	protected static $tasks = [];

	protected static $registered = false;

	public static function call(string $handler, array $args = []): void
	{
		if (config('queue.enabled') && !defined('RXQUEUE_CRON'))
		{
			try
			{
				\Zittme\Framework\Queue::addTask($handler, (object)$args);
				return;
			}
			catch (\Throwable $e)
			{
			}
		}

		self::$tasks[] = [$handler, $args];
		if (!self::$registered)
		{
			self::$registered = true;
			register_shutdown_function([self::class, 'flush']);
		}
	}

	public static function flush(): void
	{
		if (!self::$tasks)
		{
			return;
		}

		if (function_exists('fastcgi_finish_request'))
		{
			@fastcgi_finish_request();
		}
		elseif (function_exists('litespeed_finish_request'))
		{
			@litespeed_finish_request();
		}
		else
		{
			while (ob_get_level())
			{
				@ob_end_flush();
			}
			@flush();
		}

		try
		{
			\Zittme\Framework\Session::close();
		}
		catch (\Throwable $e)
		{
			@session_write_close();
		}

		@ignore_user_abort(true);
		@set_time_limit(120);

		$tasks = self::$tasks;
		self::$tasks = [];
		foreach ($tasks as [$handler, $args])
		{
			try
			{
				call_user_func($handler, (object)$args);
			}
			catch (\Throwable $e)
			{
			}
		}
	}
}
