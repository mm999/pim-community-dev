<?php

namespace Oro\Bundle\EntityConfigBundle\Config\Id;

interface EntityIdInterface extends IdInterface
{
    /**
     * @return string
     */
    public function getClassName();
}
