<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\DbalType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;
use Override;

use function is_string;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * A type which stores a value in another form than PHP holds it, as a
 * binary UUID type does: Code('a') is stored as 'code:a'.  Like a UUID type,
 * it converts a string to the database value as well.
 */
final class CodeType extends StringType
{
    public const string NAME = 'test_code';

    private const string PREFIX = 'code:';

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string|null
    {
        if ($value instanceof Code) {
            $value = $value->value;
        }

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || str_contains($value, ':')) {
            throw new ConversionException('Not a code');
        }

        return self::PREFIX . $value;
    }

    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): Code|null
    {
        if (! is_string($value) || ! str_starts_with($value, self::PREFIX)) {
            return null;
        }

        return new Code(substr($value, strlen(self::PREFIX)));
    }

    /**
     * DBAL 3 names a type by this method
     */
    public function getName(): string
    {
        return self::NAME;
    }
}
