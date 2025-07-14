<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_tagimport
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

class TagImportLanguageHelper
{
    /**
     * Load language files for the component
     *
     * @return void
     */
    public static function loadLanguage()
    {
        $lang = Joomla\CMS\Factory::getApplication()->getLanguage();
        $lang->load('com_tagimport', JPATH_ADMINISTRATOR);
    }
}
