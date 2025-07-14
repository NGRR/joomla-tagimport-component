<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_categoryimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * Redirect view - this is a fallback if the system tries to load "categoryimport" view
 * It simply redirects to the "import" view
 *
 * @since  1.0.0
 */
class CategoryImportViewCategoryimport extends HtmlView
{
	/**
	 * Display the view
	 *
	 * @param   string  $tpl  The name of the template file to parse
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function display($tpl = null)
	{
		// Redirect to the import view
		$app = Factory::getApplication();
		$app->redirect('index.php?option=com_categoryimport&view=import');
	}
}
