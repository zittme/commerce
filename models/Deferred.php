<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 응답 뒤로 미루는 작업.
 *
 * 메일·알림처럼 바깥 서버를 기다리는 일은 관리자 버튼의 응답 시간에 얹지 않는다.
 * 코어 큐가 켜져 있으면 큐에 넣고, 아니면 응답을 먼저 내보낸 뒤 같은 요청 끝에서 돌린다.
 * 메일 서버가 멈춰도 화면은 바로 돌아오고, 그 요청 하나만 뒤에서 기다린다.
 */
class Deferred
{
	/**
	 * @var array<int, array{0: string, 1: array}>
	 */
	protected static $tasks = [];

	/**
	 * @var bool
	 */
	protected static $registered = false;

	/**
	 * 작업을 등록한다. 핸들러는 정적 메서드 'Class::method' 이고 인자 객체 하나를 받는다.
	 *
	 * @param string $handler
	 * @param array $args 직렬화 가능한 값만 (큐로 갈 수 있다)
	 * @return void
	 */
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

	/**
	 * 응답을 끊고 밀린 작업을 돌린다. PHP 종료 단계에서 불린다.
	 *
	 * @return void
	 */
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
