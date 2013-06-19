<?php

namespace Oro\Bundle\EntityExtendBundle\Extend;

use Oro\Bundle\EntityExtendBundle\DependencyInjection\Lazy\LazyEntityManager;
use Oro\Bundle\EntityExtendBundle\Config\ExtendConfigProvider;
use Oro\Bundle\EntityExtendBundle\Tools\Generator\Generator;

class ExtendManager
{
    /**
     * @var ProxyObjectFactory
     */
    protected $proxyFactory;

    /**
     * @var ExtendObjectFactory
     */
    protected $extendFactory;

    /**
     * @var ExtendConfigProvider
     */
    protected $configProvider;

    /**
     * @var Generator
     */
    protected $generator;

    /**
     * @var LazyEntityManager
     */
    protected $lazyEm;

    function __construct(LazyEntityManager $lazyEm, ExtendConfigProvider $configProvider)
    {
        $this->lazyEm         = $lazyEm;
        $this->configProvider = $configProvider;

        $this->proxyFactory  = new ProxyObjectFactory($this);
        $this->extendFactory = new ExtendObjectFactory($this);
        $this->generator     = new Generator($configProvider, $mode = 'Dynamic');
    }

    /**
     * @return ExtendConfigProvider
     */
    public function getConfigProvider()
    {
        return $this->configProvider;
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        return $this->lazyEm->getEntityManager();
    }

    /**
     * @return ProxyObjectFactory
     */
    public function getProxyFactory()
    {
        return $this->proxyFactory;
    }

    /**
     * @return ExtendObjectFactory
     */
    public function getExtendFactory()
    {
        return $this->extendFactory;
    }

    /**
     * @return Generator
     */
    public function getClassGenerator()
    {
        return $this->generator;
    }

    /**
     * @param $entityName
     * @return bool|string
     */
    public function isExtend($entityName)
    {
        if ($this->configProvider->isExtend($entityName)) {
            //$this->checkEntityCache($this->configProvider->getClassName($entityName));

            return true;
        }

        return false;
    }

    /**
     * @param $entityName
     * @return null|string
     */
    public function getExtendClass($entityName)
    {
        return $this->configProvider->getExtendClass($entityName);
    }

    /**
     * @param $entity
     * @return null|\Oro\Bundle\EntityExtendBundle\Entity\ExtendProxyInterface
     */
    public function getProxyObject($entity)
    {
        return $this->proxyFactory->getProxyObject($entity);
    }

    /**
     * @param $entity
     */
    public function load($entity)
    {
        $this->getExtendFactory()->getExtendObject($entity);
    }

    /**
     * @param $entity
     */
    public function persist($entity)
    {
        if ($this->isExtend($entity)) {
            $extend = $this->getExtendFactory()->getExtendObject($entity);

            $this->getEntityManager()->persist($extend);
        }
    }

    /**
     * @param $entity
     */
    public function remove($entity)
    {
        if ($this->isExtend($entity)) {
            $extend = $this->getExtendFactory()->getExtendObject($entity);
            $this->getEntityManager()->remove($extend);
        }
    }

    /**
     * @param $entity
     */
    protected function checkEntityCache($entity)
    {
        $this->generator->checkEntityCache($entity);
    }
}
