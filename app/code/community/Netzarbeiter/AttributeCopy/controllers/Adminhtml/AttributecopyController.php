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

class Netzarbeiter_AttributeCopy_Adminhtml_AttributecopyController extends Mage_Adminhtml_Controller_Action
{
	public function copyAction()
	{
		try
		{
			if ($id = $this->_processCopy())
			{
				$this->_redirect('adminhtml/catalog_product_attribute/edit', array('attribute_id' => $id));
				return;
			}
		}
		catch (Exception $e)
		{
			Mage::logException($e);
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
		}
		$this->_redirectReferer();
	}

	/**
	 *
	 * @return <type> 
	 */
	protected function _processCopy()
	{
		$origCode = $this->getRequest()->getParam('attribute', '');
		$newCode = $this->getRequest()->getParam('new', '');

		$model = Mage::getModel('attributecopy/product_attribute_copy');
		$newAttribute = $model->copy($origCode, $newCode);

		if (! $newAttribute->getId())
		{
			Mage::throwException($this->__('Error creating attribute "%s" from "%s"', $newCode, $origCode));
		}
		
		Mage::getSingleton('adminhtml/session')->addSuccess(
			$this->__('New attribute "%s" with ID %d successfully created from "%s"', $newCode, $newAttribute->getId(), $origCode)
		);
		return $newAttribute->getId();

	}

	/**
	 * Attach into the attribute management acl node
	 *
	 * @return bool
	 */
	protected function _isAllowed()
	{
		return Mage::getSingleton('admin/session')->isAllowed('catalog/attributes/attributes');
	}
}
