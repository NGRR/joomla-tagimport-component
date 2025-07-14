<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_tagimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace TagImport\Component\TagImport\Administrator\View\Import;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Session\Session;

/**
 * View class for importing tags.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * Display the view
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  void
	 *
	 * @throws  \Exception
	 */
	public function display($tpl = null)
	{
		$this->addToolbar();
		
		// Get the active layout
		$layout = $this->getLayout();
		
		// If we're in the preview layout, get the tags from session
		if ($layout === 'preview') {
			$session = Factory::getSession();
			$this->tags = $session->get('tagimport.tags', []);
			$this->total = count($this->tags);
		}
		
		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */	protected function addToolbar()
	{
		// Load the helper file
		\JLoader::register('TagImportHelper', JPATH_ADMINISTRATOR . '/components/com_tagimport/helpers/tagimport.php');
		$canDo = \TagImportHelper::getActions();
		
		// Get the layout
		$layout = $this->getLayout();
		
		ToolbarHelper::title(Text::_('COM_TAGIMPORT_TITLE'), 'folder tag');

		// Different toolbars based on layout
		if ($layout === 'preview') {
			if ($canDo->get('tagimport.import')) {
				ToolbarHelper::custom('import.import', 'upload', '', 'COM_TAGIMPORT_IMPORT', false);
			}
			
			ToolbarHelper::cancel('import.cancel');
		} else {
			// Default toolbar
			if ($canDo->get('tagimport.import')) {
				ToolbarHelper::custom('import.reset', 'refresh', '', 'COM_TAGIMPORT_RESET', false);
			}
			
			if ($canDo->get('core.admin')) {
				ToolbarHelper::preferences('com_tagimport');
			}
		}
	}
}
