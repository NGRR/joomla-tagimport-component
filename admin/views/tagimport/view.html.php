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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Router\Route;

/**
 * View class for importing categories.
 *
 * @since  1.0.0
 */
class CategoryImportViewImport extends BaseHtmlView
{
	/**
	 * Constructor
	 *
	 * @param   array  $config  An optional associative array of configuration settings.
	 */
	public function __construct($config = [])
	{
		parent::__construct($config);
		
		// Ensure path is registered
		$this->addTemplatePath(JPATH_COMPONENT_ADMINISTRATOR . '/views/import/tmpl');
	}	/**
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
		// Skip toolbar for now to avoid ACL conflicts
		// $this->addToolbar();
		
		// Display the template - if template not found, show basic HTML
		try {
			parent::display($tpl);
		} catch (Exception $e) {
			// Fallback to basic HTML if template not found
			$this->displayBasicInterface();
		}
	}
	
	/**
	 * Display a basic interface when template is not available
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function displayBasicInterface()
	{
		echo '<div class="container-fluid">';
		echo '<div class="row">';
		echo '<div class="col-md-12">';
		echo '<div class="card">';
		echo '<div class="card-header">';
		echo '<h3>' . Text::_('COM_CATEGORYIMPORT_IMPORT_CATEGORIES') . '</h3>';
		echo '</div>';
		echo '<div class="card-body">';
		echo '<div class="alert alert-info">';
		echo '<h4>' . Text::_('COM_CATEGORYIMPORT_READY') . '</h4>';
		echo '<p>' . Text::_('COM_CATEGORYIMPORT_DESCRIPTION') . '</p>';
		echo '</div>';
		
		echo '<form action="' . Route::_('index.php?option=com_categoryimport&task=import.upload') . '" method="post" enctype="multipart/form-data" class="form-validate">';
		echo '<div class="form-group">';
		echo '<label for="import_file">' . Text::_('COM_CATEGORYIMPORT_SELECT_FILE') . '</label>';
		echo '<input type="file" name="import_file" id="import_file" class="form-control" accept=".json" required>';
		echo '<small class="form-text text-muted">' . Text::_('COM_CATEGORYIMPORT_FILE_HELP') . '</small>';
		echo '</div>';
		
		echo '<div class="form-group">';
		echo '<button type="submit" class="btn btn-primary">';
		echo '<i class="fa fa-upload"></i> ' . Text::_('COM_CATEGORYIMPORT_UPLOAD_PREVIEW');
		echo '</button>';
		echo '</div>';
		
		echo Session::getFormToken() . '=1';
		echo '</form>';
		
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}
	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	protected function addToolbar()
	{
		// Use our own component permissions instead of com_categories
		$canDo = ContentHelper::getActions('com_categoryimport');
		
		// Get the layout
		$layout = $this->getLayout();
		
		ToolbarHelper::title(Text::_('COM_CATEGORYIMPORT_TITLE'), 'folder category');

		// Different toolbars based on layout
		if ($layout === 'preview') {
			if ($canDo->get('core.create')) {
				ToolbarHelper::custom('import.import', 'upload', '', 'COM_CATEGORYIMPORT_IMPORT', false);
			}
			
			ToolbarHelper::cancel('import.cancel');
		} else {
			// Default toolbar
			if ($canDo->get('core.manage')) {
				ToolbarHelper::custom('import.reset', 'refresh', '', 'COM_CATEGORYIMPORT_RESET', false);
				ToolbarHelper::preferences('com_categoryimport');
			}
		}
	}
}
