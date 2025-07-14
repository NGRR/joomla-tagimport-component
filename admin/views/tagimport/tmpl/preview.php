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

/** @var CategoryImportViewImport $this */

// No direct access - this is redundant, already checked above
// defined('_JEXEC') or die;
?>

<style>
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
    
    .alert-warning {
        background-color: #1a1a1a !important;
        color: #ffff00 !important;
        border: 2px solid #ffff00 !important;
        box-shadow: 0 0 8px rgba(255, 255, 0, 0.3) !important;
        font-weight: bold !important;
    }
    
    .table {
        background-color: #1a1a1a !important;
        color: #00ff00 !important;
        border: 2px solid #ffffff !important;
        font-weight: bold !important;
    }
    
    .table th {
        color: #ffffff !important;
        border: 1px solid #00ff00 !important;
        padding: 10px !important;
        font-weight: bold !important;
        background-color: #000000 !important;
    }
    
    .table td {
        color: #00ff00 !important;
        border: 1px solid #ffffff !important;
        padding: 10px !important;
        font-weight: bold !important;
    }
    
    .table thead {
        background-color: #000000 !important;
        border-bottom: 2px solid #00ff00 !important;
    }
</style>

<form action="<?php echo Route::_('index.php?option=com_categoryimport&task=import.import'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div class="card">
				<div class="card-header">
					<h2><?php echo Text::_('COM_CATEGORYIMPORT_PREVIEW_IMPORT'); ?></h2>
				</div>				<div class="card-body" style="background-color: #000000 !important; color: #00ff00 !important; border: 2px solid #ffffff !important; box-shadow: 0 0 10px rgba(0, 255, 0, 0.5) !important; font-weight: bold !important;">
					<div class="alert alert-info" style="background-color: #1a1a1a !important; color: #00ff00 !important; border: 2px solid #00ff00 !important; box-shadow: 0 0 8px rgba(0, 255, 0, 0.3) !important; font-weight: bold !important;">
						<span class="icon-info-circle" aria-hidden="true"></span>
						<?php echo Text::sprintf('COM_CATEGORYIMPORT_PREVIEW_DESCRIPTION', $this->total); ?>
					</div>
					
					<table class="table table-striped" style="background-color: #1a1a1a !important; color: #00ff00 !important; border: 2px solid #ffffff !important; font-weight: bold !important;">
						<thead style="background-color: #000000 !important; color: #ffffff !important; border-bottom: 2px solid #00ff00 !important;">
							<tr>
								<th style="color: #ffffff !important; border: 1px solid #00ff00 !important; padding: 10px !important; font-weight: bold !important;"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
								<th style="color: #ffffff !important; border: 1px solid #00ff00 !important; padding: 10px !important; font-weight: bold !important;"><?php echo Text::_('JFIELD_ALIAS_LABEL'); ?></th>
								<th style="color: #ffffff !important; border: 1px solid #00ff00 !important; padding: 10px !important; font-weight: bold !important;"><?php echo Text::_('JPARENT'); ?></th>
								<th style="color: #ffffff !important; border: 1px solid #00ff00 !important; padding: 10px !important; font-weight: bold !important;"><?php echo Text::_('JSTATUS'); ?></th>
								<th style="color: #ffffff !important; border: 1px solid #00ff00 !important; padding: 10px !important; font-weight: bold !important;"><?php echo Text::_('JGRID_HEADING_LANGUAGE'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($this->categories)) : ?>
								<tr>
									<td colspan="5" class="text-center" style="color: #00ff00 !important; border: 1px solid #ffffff !important; padding: 10px !important; font-weight: bold !important;"><?php echo Text::_('JEMPTY_CATEGORY'); ?></td>
								</tr>
							<?php else : ?>
								<?php $i = 0; ?>
								<?php foreach ($this->categories as $category) : ?>
									<?php if ($i >= 10) : ?>
										<tr>
											<td colspan="5" class="text-center" style="color: #00ff00 !important; border: 1px solid #ffffff !important; padding: 10px !important; font-weight: bold !important;">
												<?php echo Text::sprintf('COM_CATEGORYIMPORT_MORE_ITEMS', $this->total - 10); ?>
											</td>
										</tr>
										<?php break; ?>
									<?php endif; ?>
									<tr>
										<td style="color: #00ff00 !important; border: 1px solid #ffffff !important; padding: 10px !important; font-weight: bold !important;"><?php echo $category['title']; ?></td>
										<td style="color: #00ff00 !important; border: 1px solid #ffffff !important; padding: 10px !important; font-weight: bold !important;"><?php echo $category['alias'] ?? ''; ?></td>
										<td style="color: #00ff00 !important; border: 1px solid #ffffff !important; padding: 10px !important; font-weight: bold !important;"><?php echo $category['parent_id'] ?? '1'; ?></td>
										<td style="color: #00ff00 !important; border: 1px solid #ffffff !important; padding: 10px !important; font-weight: bold !important;"><?php echo ($category['published'] ?? 1) ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED'); ?></td>
										<td style="color: #00ff00 !important; border: 1px solid #ffffff !important; padding: 10px !important; font-weight: bold !important;"><?php echo $category['language'] ?? '*'; ?></td>
									</tr>
									<?php $i++; ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					
					<div class="alert alert-warning mt-3" style="background-color: #1a1a1a !important; color: #ffff00 !important; border: 2px solid #ffff00 !important; box-shadow: 0 0 8px rgba(255, 255, 0, 0.3) !important; font-weight: bold !important;">
						<span class="icon-exclamation-triangle" aria-hidden="true"></span>
						<?php echo Text::_('COM_CATEGORYIMPORT_PREVIEW_WARNING'); ?>
					</div>
				</div>
			</div>
			
			<input type="hidden" name="task" value="import.import" />
			<?php echo HTMLHelper::_('form.token'); ?>
		</div>
	</div>
</form>
