<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata\Common;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy;

use function in_array;
use function str_replace;
use function strlen;
use function strpos;
use function substr;

/**
 * This ancestor class contains functions common to the MetadataFactory
 * and GlobalEnable
 */
abstract class MetadataFactory
{
    protected function getDefaultStrategy(string|null $fieldType): string
    {
        // Set default strategy based on field type
        if (in_array($fieldType, ['tinyint', 'smallint', 'integer', 'int'])) {
            return Strategy\ToInteger::class;
        }

        if (in_array($fieldType, ['decimal', 'float'])) {
            return Strategy\ToFloat::class;
        }

        if ($fieldType === 'boolean') {
            return Strategy\ToBoolean::class;
        }

        return Strategy\FieldDefault::class;
    }

    /**
     * Compute the GraphQL type name
     *
     * @param class-string $entityClass
     */
    protected function getTypeName(string $entityClass): string
    {
        return $this->appendGroupSuffix($this->stripEntityPrefix($entityClass));
    }

    /**
     * Strip the configured entityPrefix from the type name
     *
     * @param class-string $entityClass
     */
    protected function stripEntityPrefix(string $entityClass): string
    {
        $entityClassWithPrefix = $entityClass;

        if ($this->getConfig()->getEntityPrefix() !== null) {
            if (strpos($entityClass, (string) $this->getConfig()->getEntityPrefix()) === 0) {
                $entityClassWithPrefix = substr($entityClass, strlen((string) $this->getConfig()->getEntityPrefix()));
            }
        }

        return str_replace('\\', '_', $entityClassWithPrefix);
    }

    /**
     * Append the configured groupSuffix to the type name
     */
    protected function appendGroupSuffix(string $entityClass): string
    {
        if ($this->getConfig()->getGroupSuffix() !== null) {
            if ($this->getConfig()->getGroupSuffix()) {
                $entityClass .= '_' . $this->getConfig()->getGroupSuffix();
            }
        } else {
            $entityClass .= '_' . $this->getConfig()->getGroup();
        }

        return $entityClass;
    }

    /**
     * Because the Config class is not available in this class,
     * this method must be implemented in the child class
     */
    abstract protected function getConfig(): Config;
}
