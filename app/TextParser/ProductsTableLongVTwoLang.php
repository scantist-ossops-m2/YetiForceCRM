<?php

namespace App\TextParser;

/**
 * Products table long two lang class.
 *
 * @package TextParser
 *
 * @copyright YetiForce S.A.
 * @license   YetiForce Public License 5.0 (licenses/LicenseEN.txt or yetiforce.com)
 * @author    Arkadiusz Sołek <a.solek@yetiforce.com>
 * @author    Radosław Skrzypczak <r.skrzypczak@yetiforce.com>
 */
class ProductsTableLongVTwoLang extends Base
{
	/** @var string Class name */
	public $name = 'LBL_PRODUCTS_TABLE_LONG_TWO_LANG';

	/** @var mixed Parser type */
	public $type = 'pdf';

	/**
	 * Process.
	 *
	 * @return string
	 */
	public function process()
	{
		$html = '';
		if (!$this->textParser->recordModel->getModule()->isInventory()) {
			return $html;
		}
		$inventory = \Vtiger_Inventory_Model::getInstance($this->textParser->moduleName);
		$inventoryRows = $this->textParser->recordModel->getInventoryData();

		$currencyId = current($inventoryRows)['currency'] ?? null;
		if (!$currencyId) {
			$currencyId = \App\Fields\Currency::getDefault()['id'];
			foreach ($inventoryRows as &$row) {
				$row['currency'] = $currencyId;
			}
		}
		$currencySymbol = \App\Fields\Currency::getById($currencyId)['currency_symbol'];

		$headerStyle = 'font-size:9px;padding:0px 4px;text-align:center;';
		$bodyStyle = 'font-size:8px;border:1px solid #ddd;padding:0px 4px;';
		$html .= '<table class="products-table-long-v-two-lang" style="border-collapse:collapse;width:100%;"><thead><tr>';
		$groupModels = [];
		foreach (['ItemNumber', 'Name', 'Quantity', 'Discount', 'Currency', 'DiscountMode', 'TaxMode', 'UnitPrice', 'GrossPrice', 'NetPrice', 'Tax', 'TotalPrice', 'Value'] as $fieldType) {
			foreach ($inventory->getFieldsByType($fieldType) as $fieldModel) {
				$columnName = $fieldModel->getColumnName();
				if (!$fieldModel->isVisible()) {
					continue;
				}
				$html .= "<th class=\"col-type-{$fieldModel->getType()}\" style=\"{$headerStyle}\">" . \App\Language::translate($fieldModel->get('label'), $this->textParser->moduleName) . ' / ' . \App\Language::translate($fieldModel->get('label'), $this->textParser->moduleName, \App\Language::DEFAULT_LANG) . '</th>';
				$groupModels[$columnName] = $fieldModel;
			}
		}
		$html .= '</tr></thead>';
		if (!empty($groupModels)) {
			$groupField = $inventory->getField('grouplabel');
			$count = \count($groupModels);
			$html .= '<tbody>';
			$counter = 0;
			foreach ($inventory->transformData($inventoryRows) as $inventoryRow) {
				if (!empty($inventoryRow['add_header']) && $groupField && $groupField->isVisible() && !empty($blockLabel = $inventoryRow['grouplabel'])) {
					$html .= "<tr><td colspan=\"{$count}\" style=\"font-size:8px;border:1px solid #ddd;padding:2px 6px;font-weight:bold;\">" . \App\Purifier::encodeHtml($groupField->getDisplayValue($blockLabel, $inventoryRow, true)) . '</td></tr>';
				}
				++$counter;
				$html .= '<tr class="row-' . $counter . '">';
				foreach ($groupModels as $fieldModel) {
					$typeName = $fieldModel->getType();
					$columnName = $fieldModel->getColumnName();
					$fieldStyle = $bodyStyle;
					if ('ItemNumber' === $typeName) {
						$html .= "<td class=\"col-type-{$typeName}\" style=\"{$bodyStyle}text-align:center;\">" . $counter . '</td>';
					} elseif ('ean' === $columnName) {
						$code = $inventoryRow[$columnName];
						$html .= "<td class=\"col-type-barcode\" style=\"{$bodyStyle}\"><div data-barcode=\"EAN13\" data-code=\"$code\" data-size=\"1\" data-height=\"16\">{$code}</div></td>";
					} else {
						$itemValue = $inventoryRow[$columnName];
						if ('Name' === $typeName) {
							$fieldValue = '<strong>' . $fieldModel->getDisplayValue($itemValue, $inventoryRow) . '</strong>';
							foreach ($inventory->getFieldsByType('Comment') as $commentField) {
								if ($commentField->isVisible() && ($value = $inventoryRow[$commentField->getColumnName()]) && $comment = $commentField->getDisplayValue($value, $inventoryRow)) {
									$fieldValue .= '<br />' . $comment;
								}
							}
						} elseif (\in_array($typeName, ['TotalPrice', 'Purchase', 'NetPrice', 'GrossPrice', 'UnitPrice', 'Discount', 'Margin', 'Tax']) && !empty($currencySymbol)) {
							$fieldValue = $fieldModel->getDisplayValue($itemValue, $inventoryRow);
							$fieldStyle = $bodyStyle . 'text-align:right;white-space: nowrap;';
						} else {
							$fieldValue = $fieldModel->getDisplayValue($itemValue, $inventoryRow);
						}
						$html .= "<td class=\"col-type-{$typeName}\" style=\"{$fieldStyle}\">" . $fieldValue . '</td>';
					}
				}
				$html .= '</tr>';
			}
			$html .= '</tbody><tfoot><tr>';
			foreach ($groupModels as $fieldModel) {
				$html .= "<th class=\"col-type-{$fieldModel->getType()}\" style=\"{$headerStyle}\">";
				if ($fieldModel->isSummary()) {
					$sum = 0;
					foreach ($inventoryRows as $inventoryRow) {
						$sum += $inventoryRow[$fieldModel->getColumnName()];
					}
					$html .= \CurrencyField::appendCurrencySymbol(\CurrencyField::convertToUserFormat($sum, null, true), $currencySymbol);
				}
				$html .= '</th>';
			}
			$html .= '</tr></tfoot></table>';
		}
		return $html;
	}
}
