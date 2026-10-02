<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Trait;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * The database value of a field's value
 *
 * A type such as a binary UUID stores a value in another form than PHP holds
 * it.  Doctrine converts a value bound as an entity, but not one bound as an
 * identifier in an IN list, and it never converts a scalar query result, such
 * as an identifier selected in DQL, which is the database value.  Values are
 * therefore compared, and bound, as their database values.
 */
trait DatabaseValue
{
    /**
     * The database value of a value of a field, converted by the field's
     * Doctrine type.  A field which is not a column, such as an association,
     * has no type, and its value is returned unchanged.
     *
     * @param ClassMetadata<object> $metadata
     */
    private static function databaseValueOf(
        EntityManagerInterface $entityManager,
        ClassMetadata $metadata,
        string $fieldName,
        mixed $value,
    ): mixed {
        $type = $metadata->hasField($fieldName) ? $metadata->getTypeOfField($fieldName) : null;

        if ($type === null) {
            return $value;
        }

        return Type::getType($type)->convertToDatabaseValue(
            $value,
            $entityManager->getConnection()->getDatabasePlatform(),
        );
    }
}
