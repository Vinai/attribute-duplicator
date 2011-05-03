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
 * package    Netzarbeiter_Duplicator
 * copyright  Copyright (c) 2011 Vinai Kopp http://netzarbeiter.com/
 * license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Netzarbeiter_AttributeDuplicator_Model_Product_Attribute_Copy
{
	protected $_entityType = 'catalog_product';

	protected $_setupResource;

	/**
	 *
	 * @param string $sourceAttributeCode
	 * @param string $targetAttributeCode
	 * @param string $targetLabel
	 * @return Mage_Eav_Model_Entity_Attribute
	 */
	public function copy($sourceAttributeCode, $targetAttributeCode, $targetLabel = '')
	{
		$source = Mage::getSingleton('eav/config')->getAttribute($this->_entityType, $sourceAttributeCode);
		if (!$source || !$source->getId())
		{
			Mage::throwException(Mage::helper('attributecopy')->__('Invalid source attribute code "%s"', $sourceAttributeCode));
		}

		$this->_validateTargetCode($targetAttributeCode);

		$data = $this->_buildNewAttributeData($source);
		
		$data['user_defined'] = true;

		if ('' !== $targetLabel)
		{
			$data['label'] = $targetLabel;
		}
		else
		{
			$data['label'] = $this->_buildCopyLabel($data['label']);
		}


		$this->_getCatalogSetup()->addAttribute($this->_entityType, $targetAttributeCode, $data);

		$newAttribute = Mage::getModel('catalog/entity_attribute')->loadByCode($this->_entityType, $targetAttributeCode);

		$this->_copyAttributeSetInfo($source, $newAttribute);

		return $newAttribute;
	}

	/**
	 *
	 * @param string $targetAttributeCode
	 * @return bool
	 */
	protected function _validateTargetCode($targetAttributeCode)
	{
		$helper = Mage::helper('attributecopy');
		if (strtolower($targetAttributeCode) != $targetAttributeCode)
		{
			Mage::throwException($helper->__('Invalid target attribute code "%s" must be lower case', $targetAttributeCode));
		}

		if (! preg_match('/^[a-z][a-z0-9_]+$/', $targetAttributeCode))
		{
			Mage::throwException($helper->__('Invalid target attribute code "%s" must contain only characters and underscores', $targetAttributeCode));
		}

		$target = Mage::getSingleton('eav/config')->getAttribute($this->_entityType, $targetAttributeCode);
		if ($target && $target->getId())
		{
			Mage::throwException($helper->__('Invalid target attribute code "%s" already exists', $targetAttributeCode));
		}

		return true;
	}

	/**
	 *
	 * @param Mage_Eav_Model_Entity_Attribute $source
	 * @return array
	 */
	protected function _buildNewAttributeData(Mage_Eav_Model_Entity_Attribute $source)
	{
		$data = array();
		foreach ($source->getData() as $key => $value)
		{
			if ($property = $this->_lookupProperty($key))
			{
				$data[$property] = $value;
			}
		}
		
		if ($this->_hasOptionsTableSourceModel($data))
		{
			$data['option'] = $this->_buildOptionsData($source);
		}

		//print_r($data); exit();
		return $data;
	}

	/**
	 * Copy options
	 *	array(
	 *		'order'  => array('one' => 0, 'two' => 2, 'three' => 1),
	 *		'value'  => array(
	 *			'one'   => array(0 => 'Admin Store Catalog Value 0', 1 => 'Default Store Catalog Value 0'),
	 *			'two'   => array(0 => 'Admin Store Catalog Value 1', 1 => 'Default Store Catalog Value 1'),
	 *			'three' => array(0 => 'Admin Store Catalog Value 2', 1 => 'Default Store Catalog Value 2'),
	 *		),
	 *	);
	 * @param Mage_Eav_Model_Entity_Attribute $source
	 * @return array
	 */
	protected function _buildOptionsData(Mage_Eav_Model_Entity_Attribute $source)
	{
		$order = $values = array();

		$collection = Mage::getResourceModel('eav/entity_attribute_option_collection')
			->setStoreFilter(Mage_Core_Model_Store::ADMIN_CODE)
			->setAttributeFilter($source->getId());

		foreach ($collection as $option)
		{
			$idx = 'idx' . $option->getId();
			$order[$idx] = $option->getSortOrder();
			$value[$idx] = array(Mage::app()->getStore()->getId() => $option->getValue());
		}
		if (empty($value))
		{
			// no options available
			return array();
		}

		/*
		 * Add store values
		 */
		foreach (Mage::app()->getStores() as $store)
		{
			$collection = Mage::getResourceModel('eav/entity_attribute_option_collection')
				->setStoreFilter($store->getId())
				->setAttributeFilter($source->getId());
			foreach ($collection as $option)
			{
				if ($option->getStoreValue() != $option->getDefaultValue() && (string) $option->getStoreValue() != '')
				{
					$idx = 'idx' . $option->getId();
					$value[$idx][$store->getId()] = $option->getStoreValue();
				}
			}
		}

		return array('order' => $order, 'value' => $value);
	}

	/**
	 *
	 * @param string $key
	 * @return mixed
	 */
	protected function _lookupProperty($key)
	{
		$map = $this->_getAttributePropertyMap();
		if (isset($map[$key]))
		{
			return $map[$key];
		}
		return false;
	}

	/**
	 *
	 * @return array
	 */
	protected function _getAttributePropertyMap()
	{
		return array(
			'backend_model'             => 'backend',
			'backend_type'              => 'type',
			'backend_table'             => 'table',
			'frontend_model'            => 'frontend',
			'frontend_input'            => 'input',
			'frontend_label'            => 'label',
			'frontend_class'            => 'frontend_class',
			'source_model'              => 'source',
			'is_required'               => 'required',
			'is_user_defined'           => 'user_defined',
			'default_value'             => 'default',
			'is_unique'                 => 'unique',
			'note'                      => 'note',
			'frontend_input_renderer'   => 'input_renderer',
			'source_model'              => 'source',
			'is_global'                 => 'global',
			'is_visible'                => 'visible',
			'is_searchable'             => 'searchable',
			'is_filterable'             => 'filterable',
			'is_comparable'             => 'comparable',
			'is_visible_on_front'       => 'visible_on_front',
			'is_wysiwyg_enabled'        => 'wysiwyg_enabled',
			'is_html_allowed_on_front'  => 'is_html_allowed_on_front',
			'is_visible_in_advanced_search'
										=> 'visible_in_advanced_search',
			'is_filterable_in_search'   => 'filterable_in_search',
			'used_in_product_listing'   => 'used_in_product_listing',
			'used_for_sort_by'          => 'used_for_sort_by',
			'apply_to'                  => 'apply_to',
			'position'                  => 'position',
			'is_configurable'           => 'is_configurable',
			'is_used_for_promo_rules'   => 'used_for_promo_rules',
		);
	}

	/**
	 *
	 * @param array $data
	 * @return bool
	 */
	protected function _hasOptionsTableSourceModel(array $data)
	{
		if ($data['source'] == 'eav/entity_attribute_source_table')
		{
			return true;
		}
		if (isset($data['input']) && in_array($data['input'], array('select', 'multiselect')))
		{
			if (! isset($data['source']) || ! $data['source'])
			{
				/*
				 * Default source model eav/entity_attribute_source_table is used
				 */
				return true;
			}
		}
		return false;
	}

	/**
	 *
	 * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Setup
	 */
	protected function _getCatalogSetup()
	{
		if (! isset($this->_setupResource))
		{
			$this->_setupResource = Mage::getResourceModel('catalog/setup', 'default_write');
		}
		return $this->_setupResource;
	}

	/**
	 *
	 * @param string $oldLabel
	 * @return string
	 */
	protected function _buildCopyLabel($oldLabel)
	{
		$postfix = ' Copy';
		if (! preg_match('/(' . $postfix . '(?: ([0-9]+))?)$/', $oldLabel, $m))
		{
			$newLabel = $oldLabel . $postfix;
		}
		else
		{
			$counter = isset($m[2]) ? intval($m[2]) +1 : 2;
			$newLabel = preg_replace('/' . $m[1] . '$/', $postfix . ' ' . $counter, $oldLabel);
		}
		return $newLabel;
	}
	
	/**
	 * Add attribute sort order from 'eav/entity_attribute' table to data array
	 *
	 * @param Mage_Eav_Model_Entity_Attribute $source
	 * @param Mage_Eav_Model_Entity_Attribute $target
	 * @return Netzarbeiter_AttributeCopy_Model_Product_Attribute_Copy
	 */
	protected function _copyAttributeSetInfo(Mage_Eav_Model_Entity_Attribute $source, Mage_Eav_Model_Entity_Attribute $target)
	{
		Mage::getModel('eav/entity_attribute_set')->addSetInfo($this->_entityType, array($source));
		if ($source->getAttributeSetInfo())
		{
			foreach ($source->getAttributeSetInfo() as $setId => $groupInfo)
			{
				$sort = $groupInfo['sort']+1;
				$this->_getCatalogSetup()
					 ->addAttributeToGroup($this->_entityType, $setId, $groupInfo['group_id'], $target->getId(), $sort);
			}
		}

		return $this;
	}
}
