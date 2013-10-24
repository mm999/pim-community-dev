<?php

namespace Oro\Bundle\EntityExtendBundle\EventListener;

use Doctrine\Common\EventSubscriber;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\Builder\ClassMetadataBuilder;

use Oro\Bundle\EntityBundle\ORM\OroEntityManager;

use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityExtendBundle\Extend\ExtendManager;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;

class DoctrineSubscriber implements EventSubscriber
{
    /**
     * @return array
     */
    public function getSubscribedEvents()
    {
        return array(
            'loadClassMetadata'
        );
    }

    /**
     * @param LoadClassMetadataEventArgs $event
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $event)
    {
        /** @var OroEntityManager $em */
        $em = $event->getEntityManager();

        $configProvider = $em->getExtendManager()->getConfigProvider();
        $className      = $event->getClassMetadata()->getName();

        if ($configProvider->hasConfig($className)) {
            $config = $configProvider->getConfig($className);
            if ($config->is('is_extend')) {
                $cmBuilder = new ClassMetadataBuilder($event->getClassMetadata());

                if ($config->is('index')) {
                    foreach ($config->get('index') as $columnName => $enabled) {
                        $fieldConfig = $configProvider->getConfig($className, $columnName);

                        if ($enabled && !$fieldConfig->is('state', ExtendManager::STATE_NEW)) {
                            $cmBuilder->addIndex(
                                array(ExtendConfigDumper::FIELD_PREFIX . $columnName),
                                'oro_idx_' . $columnName
                            );
                        }
                    }
                }

                if ($config->is('relation')) {
                    foreach ($config->get('relation') as $relation) {
                        /** @var FieldConfigId $fieldId */
                        if ($relation['assign'] && $fieldId = $relation['field_id']) {
                            /** @var FieldConfigId $targetFieldId */
                            $targetFieldId = $relation['target_field_id'];

                            $targetFieldName = $targetFieldId
                                ? ExtendConfigDumper::FIELD_PREFIX . $targetFieldId->getFieldName()
                                : null;

                            $fieldName   = ExtendConfigDumper::FIELD_PREFIX . $fieldId->getFieldName();
                            $defaultName = ExtendConfigDumper::DEFAULT_PREFIX . $fieldId->getFieldName();

                            switch ($fieldId->getFieldType()) {
                                case 'manyToOne':
                                    $cmBuilder->addManyToOne(
                                        $fieldName,
                                        $relation['target_entity'],
                                        $targetFieldName
                                    );
                                    break;
                                case 'oneToMany':
                                    $cmBuilder->addOneToMany(
                                        $fieldName,
                                        $relation['target_entity'],
                                        $targetFieldName
                                    );
                                    $cmBuilder->addOwningOneToOne(
                                        $defaultName,
                                        $relation['target_entity'],
                                        $targetFieldName
                                    );
                                    break;
                                case 'manyToMany':
                                    if ($relation['owner']) {
                                        $builder = $cmBuilder->createManyToMany($fieldName, $relation['target_entity']);

                                        if ($targetFieldName) {
                                            $builder->inversedBy($targetFieldName);
                                        }

                                        $builder->setJoinTable(
                                            ExtendHelper::generateManyToManyJoinTableName(
                                                $fieldId,
                                                $relation['target_entity']
                                            )
                                        );

                                        $cmBuilder->addOwningOneToOne(
                                            $defaultName,
                                            $relation['target_entity'],
                                            $targetFieldName
                                        );

                                        $builder->build();
                                    } else {
                                        $cmBuilder->addInverseManyToMany(
                                            $fieldName,
                                            $fieldId->getClassName(),
                                            $targetFieldName
                                        );
                                    }
                                    break;
                            }
                        }
                    }
                }
            }

            $em->getMetadataFactory()->setMetadataFor($className, $event->getClassMetadata());
        }
    }
}
