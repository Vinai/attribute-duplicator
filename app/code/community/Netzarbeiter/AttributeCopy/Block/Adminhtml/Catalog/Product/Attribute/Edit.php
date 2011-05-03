<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * package    Netzarbeiter_AttributeCopy
 * copyright  Copyright (c) 2011 Vinai Kopp http://netzarbeiter.com/
 * license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Netzarbeiter_AttributeCopy_Block_Adminhtml_Catalog_Product_Attribute_Edit
	extends Mage_Adminhtml_Block_Catalog_Product_Attribute_Edit
{
	public function __construct()
	{
		$this->setModuleName('Mage_Adminhtml');
		parent::__construct();
		$attribute = Mage::registry('entity_attribute');
		if ($attribute && $attribute->getId())
		{
			$this->_addButton(
				'attributecopy',
				array(
					'label'     => Mage::helper('attributecopy')->__('Duplicate'),
					'onclick'   => 'attributeCopy()',
					'class'     => 'add'
				),
				10
			);

			$this->_formScripts[] = "
function attributeCopy() {
	var newCode = '';
	var newLabel = '';
	if (newCode = prompt('{$this->_getPromptNewCodeText()}')) {
		var url = '{$this->_getRenameActionUrl()}attribute/{$attribute->getAttributeCode()}/new/' + newCode;
		window.location.href = url;
	}
}
";
		}
	}

	protected function _getPromptNewCodeText()
	{
		return Mage::helper('attributecopy')->__("Please enter the new attribute code\\n(only lowercase characters and underscores)");
	}

	protected function _getRenameActionUrl()
	{
		$url = Mage::helper('adminhtml')->getUrl('adminhtml/attributecopy/copy');
		if (substr($url, -1) != '/') $url .= '/';
		return $url;
	}
}
