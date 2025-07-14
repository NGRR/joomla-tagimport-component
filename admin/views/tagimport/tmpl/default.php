<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_categoryimport
 *
 * @copyright   (C) 2025 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Factory;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('core');
$wa->useScript('form.validate');
?>

<style>
    pre {
        background-color: #1a1a1a !important;
        color: #00ff00 !important;
        border: 2px solid #ffffff !important;
        padding: 15px !important;
        border-radius: 5px !important;
        box-shadow: 0 0 10px rgba(0, 255, 0, 0.3) !important;
        font-weight: bold !important;
    }
    
    .card-body {
        background-color: #000000 !important;
        color: #00ff00 !important;
        border: 2px solid #ffffff !important;
        box-shadow: 0 0 10px rgba(0, 255, 0, 0.5) !important;
        font-weight: bold !important;
    }
    
    .alert {
        background-color: #1a1a1a !important;
        color: #00ff00 !important;
        border: 2px solid #00ff00 !important;
        box-shadow: 0 0 8px rgba(0, 255, 0, 0.3) !important;
        font-weight: bold !important;
    }
    
    input[type="file"] {
        background-color: #000000 !important;
        color: #00ff00 !important;
        border: 2px solid #ffffff !important;
        padding: 8px !important;
        font-weight: bold !important;
        box-shadow: 0 0 5px rgba(0, 255, 0, 0.3) !important;
    }
    
    label {
        color: #00ff00 !important;
        font-weight: bold !important;
    }
    
    .btn {
        background-color: #000000 !important;
        color: #00ff00 !important;
        border: 2px solid #ffffff !important;
        font-weight: bold !important;
        box-shadow: 0 0 5px rgba(0, 255, 0, 0.3) !important;
    }
    
    .btn:hover {
        background-color: #00ff00 !important;
        color: #000000 !important;
        border: 2px solid #ffffff !important;
    }
</style>

<form action="<?php echo Route::_('index.php?option=com_categoryimport&task=import.upload'); ?>" method="post" name="adminForm" id="categoryimport-form" enctype="multipart/form-data" class="form-validate">
	<div class="row">
		<div class="col-md-12">
			<div class="card">
				<div class="card-header">
					<h2><?php echo Text::_('COM_CATEGORYIMPORT_IMPORT_CATEGORIES'); ?></h2>
				</div>				<div class="card-body" style="background-color: #000000 !important; color: #00ff00 !important; border: 2px solid #ffffff !important; box-shadow: 0 0 10px rgba(0, 255, 0, 0.5) !important; font-weight: bold !important;">
					<fieldset class="adminform">
						<div class="alert alert-info" style="background-color: #1a1a1a !important; color: #00ff00 !important; border: 2px solid #00ff00 !important; box-shadow: 0 0 8px rgba(0, 255, 0, 0.3) !important; font-weight: bold !important;">
							<span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
							<?php echo Text::_('COM_CATEGORYIMPORT_IMPORT_INFO'); ?>
						</div>
						
						<div class="control-group">
							<label for="jsonfile" style="color: #00ff00 !important; font-weight: bold !important;"><?php echo Text::_('COM_CATEGORYIMPORT_FIELD_FILE_LABEL'); ?></label>
							<input type="file" name="jform[jsonfile]" id="jsonfile" accept=".json" class="required" required style="background-color: #000000 !important; color: #00ff00 !important; border: 2px solid #ffffff !important; padding: 8px !important; font-weight: bold !important; box-shadow: 0 0 5px rgba(0, 255, 0, 0.3) !important;" />
						</div>
						
						<button type="submit" class="btn btn-primary mt-3" style="background-color: #000000 !important; color: #00ff00 !important; border: 2px solid #ffffff !important; font-weight: bold !important; box-shadow: 0 0 5px rgba(0, 255, 0, 0.3) !important;"><?php echo Text::_('COM_CATEGORYIMPORT_UPLOAD_JSON'); ?></button>
					</fieldset>
				</div>
			</div>
			
			<input type="hidden" name="task" value="import.upload" />
			<?php echo HTMLHelper::_('form.token'); ?>
		</div>
	</div>
</form>

<div class="row mt-3">
	<div class="col-md-12">
		<div class="card">
			<div class="card-header">
				<h3><?php echo Text::_('COM_CATEGORYIMPORT_EXPECTED_JSON_FORMAT'); ?></h3>
			</div>			<div class="card-body" style="background-color: #000000 !important; color: #00ff00 !important; border: 2px solid #ffffff !important; box-shadow: 0 0 10px rgba(0, 255, 0, 0.5) !important; font-weight: bold !important;">
				<pre class="border p-3 bg-light" style="background-color: #1a1a1a !important; color: #00ff00 !important; border: 2px solid #ffffff !important; padding: 15px !important; border-radius: 5px !important; box-shadow: 0 0 10px rgba(0, 255, 0, 0.3) !important; font-weight: bold !important;">
{
  "categories": [
    {
      "title": "Category Title",
      "alias": "category-alias",
      "path": "path/to/category",
      "parent_id": "1",
      "level": "1",
      "extension": "com_content",
      "description": "Category description",
      "published": "1",
      "params": "{\"category_layout\":\"\",\"image\":\"\"}",
      "metadata": "{\"author\":\"\",\"robots\":\"\"}",
      "language": "*"
    },
    ...
  ]
}
				</pre>
			</div>
		</div>
	</div>
</div>

<div class="alert alert-secondary mt-3" style="background-color: #1a1a1a !important; color: #00ff00 !important; border: 2px solid #00ff00 !important; box-shadow: 0 0 8px rgba(0, 255, 0, 0.3) !important; font-weight: bold !important;">
    <strong>Debug:</strong> Asegúrate de seleccionar un archivo JSON válido antes de cargarlo.
</div>
