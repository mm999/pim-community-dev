<?php

namespace Oro\Bundle\LocaleBundle\Formatter;

use Oro\Bundle\LocaleBundle\Model\AddressInterface;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class AddressFormatter
{
    /**
     * @var LocaleSettings
     */
    protected $localeSettings;

    /**
     * @var NameFormatter
     */
    protected $nameFormatter;

    /**
     * @var PropertyAccessor
     */
    protected $propertyAccessor;

    /**
     * @param LocaleSettings $localeSettings
     * @param NameFormatter $nameFormatter
     */
    public function __construct(LocaleSettings $localeSettings, NameFormatter $nameFormatter)
    {
        $this->localeSettings = $localeSettings;
        $this->nameFormatter = $nameFormatter;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * Format address
     *
     * @param AddressInterface $address
     * @param null|string $country
     * @param string $newLineSeparator
     * @return string
     */
    public function format(AddressInterface $address, $country = null, $newLineSeparator = "\n")
    {
        if (!$country) {
            $country = $address->getCountry();
        }
        $format = $this->localeSettings->getAddressFormat($country);
        $countryLocale = $this->localeSettings->getLocaleByCountry($country);
        $self = $this;
        $formatted = preg_replace_callback(
            '/%(\w+)%/',
            function ($data) use ($address, $countryLocale, $newLineSeparator, $self) {
                $key = $data[1];
                $lowerCaseKey = strtolower($key);
                if ('name' === $lowerCaseKey) {
                    $value = $self->nameFormatter->format($address, $countryLocale);
                } elseif ('street' == $lowerCaseKey) {
                    $value = $self->getValue($address, 'street') . ' ' . $self->getValue($address, 'street2');
                } elseif ('street1' == $lowerCaseKey) {
                    $value = $self->getValue($address, 'street');
                } else {
                    $value = $self->getValue($address, $lowerCaseKey);
                }
                if ($value) {
                    if ($key !== $lowerCaseKey) {
                        $value = strtoupper($value);
                    }
                    return $value;
                }
                return '';
            },
            $format
        );

        $formatted = str_replace(
            $newLineSeparator . $newLineSeparator,
            $newLineSeparator,
            str_replace('\n', $newLineSeparator, $formatted)
        );
        return trim($formatted);
    }

    /**
     * @param object $obj
     * @param string $property
     * @return mixed|null
     */
    protected function getValue($obj, $property)
    {
        try {
            $value = $this->propertyAccessor->getValue($obj, $property);
        } catch (NoSuchPropertyException $e) {
            $value = null;
        }
        return $value;
    }
}
