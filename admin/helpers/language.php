<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_tagimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Tag Import Language Helper
 *
 * @since  1.1.0
 */
class TagImportLanguageHelper
{
	/**
	 * Load component language files
	 *
	 * @return  boolean  True on success
	 *
	 * @since   1.1.0
	 */
	public static function loadLanguage()
	{
		$lang = Factory::getApplication()->getLanguage();
		
		// Load main component language file
		$loaded1 = $lang->load('com_tagimport', JPATH_ADMINISTRATOR, null, false, true);
		
		// Load system language file
		$loaded2 = $lang->load('com_tagimport.sys', JPATH_ADMINISTRATOR, null, false, true);
		
		// If default language didn't load, try loading the fallback (en-GB)
		if (!$loaded1) {
			$lang->load('com_tagimport', JPATH_ADMINISTRATOR, 'en-GB', false, true);
		}
		
		if (!$loaded2) {
			$lang->load('com_tagimport.sys', JPATH_ADMINISTRATOR, 'en-GB', false, true);
		}
		
		return true;
	}
	
	/**
	 * Get the current language tag
	 *
	 * @return  string  The language tag (e.g., 'es-ES', 'en-GB')
	 *
	 * @since   1.1.0
	 */
	public static function getCurrentLanguage()
	{
		return Factory::getLanguage()->getTag();
	}
	
	/**
	 * Check if a specific language is available for the component
	 *
	 * @param   string  $langTag  The language tag to check
	 *
	 * @return  boolean  True if language is available
	 *
	 * @since   1.1.0
	 */
	public static function isLanguageAvailable($langTag)
	{
		$languageFile = JPATH_ADMINISTRATOR . '/components/com_tagimport/language/' . $langTag . '/com_tagimport.ini';
		return file_exists($languageFile);
	}
	
	/**
	 * Get available languages for this component
	 *
	 * @return  array  Array of available language tags
	 *
	 * @since   1.1.0
	 */
	public static function getAvailableLanguages()
	{
		$languages = [];
		$languageDir = JPATH_ADMINISTRATOR . '/components/com_tagimport/language';
		
		if (is_dir($languageDir)) {
			$dirs = scandir($languageDir);
			foreach ($dirs as $dir) {
				if ($dir !== '.' && $dir !== '..' && is_dir($languageDir . '/' . $dir)) {
					$iniFile = $languageDir . '/' . $dir . '/com_tagimport.ini';
					if (file_exists($iniFile)) {
						$languages[] = $dir;
					}
				}
			}
		}
		
		return $languages;
	}
}
