<?php

if (!function_exists('zittme_compat_register'))
{
	function zittme_compat_register(): void
	{
		static $done = false;
		if ($done)
		{
			return;
		}
		$done = true;

		if (class_exists('\\Zittme\\Framework\\DB', false) || defined('ZITTME_VERSION'))
		{
			return;
		}

		spl_autoload_register(function (string $class_name) {
			if (strncmp($class_name, 'Zittme\\Framework\\', 17) !== 0)
			{
				return;
			}
			$target = 'Rhymix' . substr($class_name, 6);
			if (class_exists($target) || interface_exists($target) || trait_exists($target))
			{
				class_alias($target, $class_name);
			}
		}, true, true);
	}

	function zittme_compat_skin_path(string $module_path, string $skin, string $module): string
	{
		if (class_exists('\\Zittme\\Framework\\Theme'))
		{
			$path = \Zittme\Framework\Theme::resolveSkinPath($module_path, $skin, 'skins');
			if (!is_dir($path) && strpos($skin, \Zittme\Framework\Theme::SEPARATOR) === false)
			{
				foreach (array_keys(\Zittme\Framework\Theme::getModuleSkins($module, 'skins')) as $combined)
				{
					if (substr($combined, -strlen(\Zittme\Framework\Theme::SEPARATOR . $skin)) === \Zittme\Framework\Theme::SEPARATOR . $skin)
					{
						$path = \Zittme\Framework\Theme::resolveSkinPath($module_path, $combined, 'skins');
						break;
					}
				}
			}
			return $path;
		}
		$skin = preg_replace('/[^a-zA-Z0-9_.-]/', '', $skin) ?: 'default';
		$path = rtrim($module_path, '/') . '/skins/' . $skin . '/';
		return is_dir($path) ? $path : rtrim($module_path, '/') . '/skins/default/';
	}
}

zittme_compat_register();
