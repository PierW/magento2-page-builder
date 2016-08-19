<?php

namespace Gene\BlueFoot\Controller\Adminhtml\Entity\Attribute;

use \Magento\Framework\Exception\AlreadyExistsException;

/**
 * Class Save
 *
 * @package Gene\BlueFoot\Controller\Adminhtml\Entity\Attribute
 *
 * @author Dave Macaulay <dave@gene.co.uk>
 */
class Save extends \Gene\BlueFoot\Controller\Adminhtml\Entity\Attribute
{
    /**
     * @var \Gene\BlueFoot\Model\Attribute\ContentBlock\BuildFactory
     */
    protected $buildFactory;

    /**
     * @var \Magento\Framework\Filter\FilterManager
     */
    protected $filterManager;

    /**
     * @var \Magento\Catalog\Helper\Product
     */
    protected $productHelper;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory
     */
    protected $attributeFactory;

    /**
     * @var \Magento\Eav\Model\Adminhtml\System\Config\Source\Inputtype\ValidatorFactory
     */
    protected $validatorFactory;

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory
     */
    protected $groupCollectionFactory;

    /**
     * @var \Magento\Framework\Cache\FrontendInterface
     */
    protected $attributeLabelCache;

    /**
     * Save constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Gene\BlueFoot\Model\Attribute\ContentBlock\BuildFactory $buildFactory
     * @param \Gene\BlueFoot\Model\AttributeFactory $attributeFactory
     * @param \Magento\Eav\Model\Adminhtml\System\Config\Source\Inputtype\ValidatorFactory $validatorFactory
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory $groupCollectionFactory
     * @param \Magento\Framework\Filter\FilterManager $filterManager
     * @param \Magento\Catalog\Helper\Product $productHelper
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\Registry $coreRegistry,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Gene\BlueFoot\Model\Attribute\ContentBlock\BuildFactory $buildFactory,
        \Gene\BlueFoot\Model\AttributeFactory $attributeFactory,
        \Magento\Eav\Model\Adminhtml\System\Config\Source\Inputtype\ValidatorFactory $validatorFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Group\CollectionFactory $groupCollectionFactory,
        \Magento\Framework\Filter\FilterManager $filterManager,
        \Magento\Catalog\Helper\Product $productHelper,
        \Magento\Framework\Cache\FrontendInterface $attributeLabelCache
    ) {
        parent::__construct($context, $coreRegistry, $resultPageFactory);
        $this->buildFactory = $buildFactory;
        $this->filterManager = $filterManager;
        $this->productHelper = $productHelper;
        $this->attributeFactory = $attributeFactory;
        $this->validatorFactory = $validatorFactory;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->attributeLabelCache = $attributeLabelCache;
    }

    /**
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        $resultRedirect = $this->resultRedirectFactory->create();
        if ($data) {
            $setId = $this->getRequest()->getParam('set');

            $attributeSet = null;
            if (!empty($data['new_attribute_set_name'])) {
                $name = $this->filterManager->stripTags($data['new_attribute_set_name']);
                $name = trim($name);

                try {
                    /* @var $attributeSet \Magento\Eav\Model\Entity\Attribute\Set */
                    $attributeSet = $this->buildFactory->create()
                        ->setEntityTypeId($this->entityTypeId)
                        ->setSkeletonId($setId)
                        ->setName($name)
                        ->getAttributeSet();
                } catch (AlreadyExistsException $alreadyExists) {
                    $this->messageManager->addErrorMessage(
                        __("An attribute set named '%1' already exists.", $name)
                    );
                    $this->messageManager->setAttributeData($data);

                    return $resultRedirect->setPath(
                        'bluefoot/*/edit',
                        ['_current' => true]
                    );
                } catch (\Magento\Framework\Exception\LocalizedException $e) {
                    $this->messageManager->addErrorMessage($e->getMessage());
                } catch (\Exception $e) {
                    $this->messageManager->addExceptionaddExceptionMessage(
                        $e,
                        __('Something went wrong while saving the attribute.')
                    );
                }
            }

            $redirectBack = $this->getRequest()->getParam('back', false);
            /* @var $model \Magento\Catalog\Model\ResourceModel\Eav\Attribute */
            $model = $this->attributeFactory->create();

            $attributeId = $this->getRequest()->getParam('attribute_id');

            $attributeCode = $this->getRequest()->getParam('attribute_code');
            $frontendLabel = $this->getRequest()->getParam('frontend_label');
            $attributeCode = $attributeCode ?: $this->generateCode($frontendLabel[0]);

            if (strlen($this->getRequest()->getParam('attribute_code')) > 0) {
                $validatorAttrCode = new \Zend_Validate_Regex(
                    ['pattern' => '/^[a-z][a-z_0-9]{0,30}$/']
                );

                if (!$validatorAttrCode->isValid($attributeCode)) {
                    $this->messageManager->addErrorMessage(
                        __(
                            'Attribute code "%1" is invalid. Please use only letters (a-z), ' .
                            'numbers (0-9) or underscore(_) in this field, first character should be a letter.',
                            $attributeCode
                        )
                    );

                    return $resultRedirect->setPath(
                        'bluefoot/*/edit',
                        ['attribute_id' => $attributeId, '_current' => true]
                    );
                }
            }
            $data['attribute_code'] = $attributeCode;

            //validate frontend_input
            if (isset($data['frontend_input'])) {
                /* @var $inputType \Magento\Eav\Model\Adminhtml\System\Config\Source\Inputtype\Validator */
                $inputType = $this->validatorFactory->create();
                if (!$inputType->isValid($data['frontend_input'])) {
                    foreach ($inputType->getMessages() as $message) {
                        $this->messageManager->addErrorMessage($message);
                    }

                    return $resultRedirect->setPath(
                        'bluefoot/*/edit',
                        ['attribute_id' => $attributeId, '_current' => true]
                    );
                }
            }

            if ($attributeId) {
                $model->getResource()->load($model, $attributeId);

                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(
                        __('This attribute no longer exists.')
                    );

                    return $resultRedirect->setPath('bluefoot/*/');
                }
                // entity type check
                if ($model->getEntityTypeId() != $this->entityTypeId) {
                    $this->messageManager->addErrorMessage(
                        __("We can't update the attribute.")
                    );

                    $this->_session->setAttributeData($data);
                    return $resultRedirect->setPath('bluefoot/*/');
                }

                $data['attribute_code'] = $model->getAttributeCode();
                $data['is_user_defined'] = $model->getIsUserDefined();
                $data['frontend_input'] = $model->getFrontendInput();
            } else {
                $data['source_model'] = $this->productHelper->getAttributeSourceModelByInputType(
                    $data['frontend_input']
                );
                $data['backend_model'] = $this->productHelper->getAttributeBackendModelByInputType(
                    $data['frontend_input']
                );
            }

            $data += [
                'is_filterable' => 0,
                'is_filterable_in_search' => 0,
                'apply_to' => []
            ];

            if (is_null($model->getIsUserDefined()) || $model->getIsUserDefined() != 0) {
                $data['backend_type'] = $model->getBackendTypeByInput($data['frontend_input']);
            }

            $defaultValueField = $model->getDefaultValueByInput($data['frontend_input']);
            if ($defaultValueField) {
                $data['default_value'] = $this->getRequest()->getParam($defaultValueField);
            }

            if (!$model->getIsUserDefined() && $model->getId()) {
                // Unset attribute field for system attributes
                unset($data['apply_to']);
            }

            $model->addData($data);

            if (!$attributeId) {
                $model->setEntityTypeId($this->entityTypeId);
                $model->setIsUserDefined(1);
            }

            $groupCode = $this->getRequest()->getParam('group');
            if ($setId && $groupCode) {
                // For creating product attribute on product page we need specify attribute set and group
                $attributeSetId = $attributeSet ? $attributeSet->getId() : $setId;
                $groupCollection = $attributeSet
                    ? $attributeSet->getGroups()
                    : $this->groupCollectionFactory->create()->setAttributeSetFilter($attributeSetId)->load();
                foreach ($groupCollection as $group) {
                    if ($group->getAttributeGroupCode() == $groupCode) {
                        $attributeGroupId = $group->getAttributeGroupId();
                        break;
                    }
                }
                $model->setAttributeSetId($attributeSetId);
                $model->setAttributeGroupId($attributeGroupId);
            }

            try {
                $model->getResource()->save($model);
                $this->messageManager->addSuccessMessage(
                    __('You have saved the BlueFoot content attribute.')
                );

                $this->attributeLabelCache->clean();
                $this->_session->setAttributeData(false);
                if ($this->getRequest()->getParam('popup')) {
                    $requestParams = [
                        'attributeId' => $this->getRequest()->getParam('product'),
                        'attribute' => $model->getId(),
                        '_current' => true,
                        'product_tab' => $this->getRequest()->getParam('product_tab'),
                    ];
                    if (!is_null($attributeSet)) {
                        $requestParams['new_attribute_set_id'] = $attributeSet->getId();
                    }
                    $resultRedirect->setPath('bluefoot/product/addAttribute', $requestParams);
                } elseif ($redirectBack) {
                    $resultRedirect->setPath('bluefoot/*/edit', ['attribute_id' => $model->getId(), '_current' => true]);
                } else {
                    $resultRedirect->setPath('bluefoot/*/');
                }

                return $resultRedirect;
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
                $this->_session->setAttributeData($data);
                return $resultRedirect->setPath('bluefoot/*/edit', ['attribute_id' => $attributeId, '_current' => true]);
            }
        }
        return $resultRedirect->setPath('bluefoot/*/');
    }
}
