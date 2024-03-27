<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

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
abstract class AbstractMetadataFactory
{
    protected Config $config;

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
     */
    protected function getTypeName(string $entityClass): string
    {
        return $this->appendGroupSuffix($this->stripEntityPrefix($entityClass));
    }

    /**
     * Strip the configured entityPrefix from the type name
     */
    protected function stripEntityPrefix(string $entityClass): string
    {
        if ($this->config->getEntityPrefix() !== null) {
            if (strpos($entityClass, $this->config->getEntityPrefix()) === 0) {
                $entityClass = substr($entityClass, strlen($this->config->getEntityPrefix()));
            }
        }

        return str_replace('\\', '_', $entityClass);
    }

    /**
     * Append the configured groupSuffix to the type name
     */
    protected function appendGroupSuffix(string $entityClass): string
    {
        if ($this->config->getGroupSuffix() !== null) {
            if ($this->config->getGroupSuffix()) {
                $entityClass .= '_' . $this->config->getGroupSuffix();
            }
        } else {
            $entityClass .= '_' . $this->config->getGroup();
        }

        return $entityClass;
    }
}
