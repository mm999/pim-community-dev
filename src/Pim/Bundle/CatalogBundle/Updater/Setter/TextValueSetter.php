<?php

namespace Pim\Bundle\CatalogBundle\Updater\Setter;

use Pim\Bundle\CatalogBundle\Model\AttributeInterface;
use Pim\Bundle\CatalogBundle\Builder\ProductBuilder;
use Pim\Bundle\CatalogBundle\Updater\InvalidArgumentException;
use Pim\Bundle\CatalogBundle\Updater\Util\AttributeUtility;

/**
 * Sets a text value in many products
 *
 * @author    Nicolas Dupont <nicolas@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class TextValueSetter extends AbstractValueSetter
{
    /** @var ProductBuilder */
    protected $productBuilder;

    /**
     * @param ProductBuilder $builder
     * @param array          $supportedTypes
     */
    public function __construct(ProductBuilder $builder, array $supportedTypes)
    {
        $this->productBuilder = $builder;
        $this->supportedTypes = $supportedTypes;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(array $products, AttributeInterface $attribute, $data, $locale = null, $scope = null)
    {
        AttributeUtility::validateLocale($attribute, $locale);
        AttributeUtility::validateScope($attribute, $scope);

        if (!is_string($data)) {
            throw InvalidArgumentException::stringExpected($attribute->getCode(), 'setter', 'text value');
        }

        foreach ($products as $product) {
            $value = $product->getValue($attribute->getCode(), $locale, $scope);
            if (null === $value) {
                $value = $this->productBuilder->addProductValue($product, $attribute, $locale, $scope);
            }
            $value->setData($data);
        }
    }
}