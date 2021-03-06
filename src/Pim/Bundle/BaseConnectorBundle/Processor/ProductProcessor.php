<?php

namespace Pim\Bundle\BaseConnectorBundle\Processor;

use Akeneo\Component\Batch\Model\StepExecution;

/**
 * Product import processor, allows to bind data into a product and validate them
 *
 * @author    Gildas Quemener <gildas@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @deprecated will be removed in 1.6
 */
class ProductProcessor extends TransformerProcessor
{
    /**
     * @var bool
     */
    protected $enabled = true;

    /**
     * @var string
     */
    protected $categoriesColumn = 'categories';

    /**
     * @var string
     */
    protected $familyColumn  = 'family';

    /**
     * @var string
     */
    protected $groupsColumn  = 'groups';

    /**
     * @var StepExecution
     */
    protected $stepExecution;

    /**
     * Set whether or not the created product should be activated or not
     *
     * @param bool $enabled
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * Whether or not the created product should be activated or not
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Set the categories column
     *
     * @param string $categoriesColumn
     */
    public function setCategoriesColumn($categoriesColumn)
    {
        $this->categoriesColumn = $categoriesColumn;
    }

    /**
     * Get the categories column
     *
     * @return string
     */
    public function getCategoriesColumn()
    {
        return $this->categoriesColumn;
    }

    /**
     * Set the groups column
     *
     * @param string $groupsColumn
     */
    public function setGroupsColumn($groupsColumn)
    {
        $this->groupsColumn = $groupsColumn;
    }

    /**
     * Get the categories column
     *
     * @return string
     */
    public function getGroupsColumn()
    {
        return $this->groupsColumn;
    }

    /**
     * Set the family column
     *
     * @param string $familyColumn
     */
    public function setFamilyColumn($familyColumn)
    {
        $this->familyColumn = $familyColumn;
    }

    /**
     * Get the family column
     *
     * @return string
     */
    public function getFamilyColumn()
    {
        return $this->familyColumn;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return [
            'enabled' => [
                'type'    => 'switch',
                'options' => [
                    'label' => 'pim_base_connector.import.enabled.label',
                    'help'  => 'pim_base_connector.import.enabled.help'
                ]
            ],
            'categoriesColumn' => [
                'options' => [
                    'label' => 'pim_base_connector.import.categoriesColumn.label',
                    'help'  => 'pim_base_connector.import.categoriesColumn.help'
                ]
            ],
            'familyColumn' => [
                'options' => [
                    'label' => 'pim_base_connector.import.familyColumn.label',
                    'help'  => 'pim_base_connector.import.familyColumn.help'
                ]
            ],
            'groupsColumn' => [
                'options' => [
                    'label' => 'pim_base_connector.import.groupsColumn.label',
                    'help'  => 'pim_base_connector.import.groupsColumn.help'
                ]
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function transform($item)
    {
        return $this->transformer->transform($this->class, $item, ['enabled' => $this->enabled]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getMapping()
    {
        return [
            $this->familyColumn     => 'family',
            $this->categoriesColumn => 'categories',
            $this->groupsColumn     => 'groups'
        ] + $this->mapping;
    }
}
