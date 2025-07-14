<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_categoryimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * The categoryimport service provider.
 *
 * @since  1.0.0
 */
return new class implements ServiceProviderInterface
{
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */	public function register(Container $container): void
	{
		$container->registerServiceProvider(new MVCFactory('\\CategoryImport\\Component\\CategoryImport'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('\\CategoryImport\\Component\\CategoryImport'));
		
		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new \CategoryImport\Component\CategoryImport\Administrator\Extension\CategoryImportComponent(
					$container->get(ComponentDispatcherFactoryInterface::class)
				);
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));
				
				return $component;
			}
		);
	}
};
