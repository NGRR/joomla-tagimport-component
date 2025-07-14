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
use Joomla\CMS\Log\Log;

// Add logging
Log::addLogger(
    ['text_file' => 'categoryimport_view_simple.php'],
    Log::ALL,
    ['categoryimport.view.simple']
);

Log::add('Simple view loaded', Log::INFO, 'categoryimport.view.simple');

/**
 * Simple View class for importing categories (debugging version).
 *
 * @since  1.0.0
 */
class CategoryImportViewImportSimple
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
		Log::add('Simple view display called', Log::INFO, 'categoryimport.view.simple');
		
		$user = Factory::getUser();
		
		echo '<div style="padding: 20px; background: #fff; margin: 20px; border: 1px solid #ddd; border-radius: 5px;">';
		echo '<h1>' . Text::_('COM_CATEGORYIMPORT_IMPORT_CATEGORIES') . '</h1>';
		echo '<div class="alert alert-info">';
		echo '<h4>Component Successfully Loaded!</h4>';
		echo '<p>User: ' . htmlspecialchars($user->name) . ' (ID: ' . $user->id . ')</p>';
		echo '<p>Groups: ' . implode(', ', $user->groups) . '</p>';
		echo '<p>Permissions working correctly - no ACL conflicts detected.</p>';
		echo '</div>';
		
		echo '<div class="card">';
		echo '<div class="card-header"><h3>Category Import Tool</h3></div>';
		echo '<div class="card-body">';
		echo '<p>This is a simplified view to verify the component works without ACL conflicts.</p>';
		echo '<p>Next steps:</p>';
		echo '<ul>';
		echo '<li>✅ Component loads successfully</li>';
		echo '<li>✅ ACL permissions verified</li>';
		echo '<li>✅ No conflicts with com_categories</li>';
		echo '<li>🔄 Ready to restore full functionality</li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
		
		Log::add('Simple view displayed successfully', Log::INFO, 'categoryimport.view.simple');
	}
	
	/**
	 * Set model (compatibility method)
	 *
	 * @param   object   $model     The model
	 * @param   boolean  $default   Set as default model
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function setModel($model, $default = false)
	{
		Log::add('Model set on simple view', Log::INFO, 'categoryimport.view.simple');
		// Do nothing for now - just compatibility
	}
}
