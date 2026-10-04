<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\ComputedField;

use ApiSkeletons\Doctrine\ORM\GraphQL\Config;
use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Event\Metadata as MetadataEvent;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata as MetadataException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedExpressionArtist;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedExpressionLabel;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\ComputedExpressionRecording;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use Doctrine\ORM\Query\QueryException;
use League\Event\EventDispatcher;
use PHPUnit\Framework\Attributes\DataProvider;

use function assert;
use function is_array;

/**
 * A computed field's expression is checked when the metadata is built
 */
class ComputedFieldExpressionValidationTest extends TestCase
{
    /**
     * Build the metadata after a listener changes a computed field of the
     * artist
     *
     * @param array<string, mixed> $changes The keys to set, or null to remove
     */
    private function buildMetadata(string $fieldName, array $changes): void
    {
        $driver = new Driver($this->getEntityManager(), new Config(['group' => 'ComputedExpression']));
        $driver->get(EventDispatcher::class)->subscribeTo(
            'metadata.build',
            static function (MetadataEvent $event) use ($fieldName, $changes): void {
                $metadata = $event->getMetadata();
                $artist   = $metadata[ComputedExpressionArtist::class];
                assert(is_array($artist) && is_array($artist['computedFields']));

                $field = $artist['computedFields'][$fieldName];
                assert(is_array($field));

                foreach ($changes as $key => $value) {
                    if ($value === null) {
                        unset($field[$key]);
                    } else {
                        $field[$key] = $value;
                    }
                }

                $artist['computedFields'][$fieldName]      = $field;
                $metadata[ComputedExpressionArtist::class] = $artist;
            },
        );

        $driver->get(Metadata::class);
    }

    /** @return array<string, array{string, array<string, mixed>, string, bool}> */
    public static function invalidProvider(): array
    {
        $recording = ComputedExpressionRecording::class;

        return [
            'a syntax error' => [
                'upperName',
                ['expression' => 'UPPER({entity}.name'],
                'has an expression which is not valid',
                true,
            ],
            'an unknown field' => [
                'upperName',
                ['expression' => 'UPPER({entity}.nope)'],
                'has an expression which is not valid',
                true,
            ],
            'an unknown class' => [
                'recordingCount',
                ['expression' => '(SELECT COUNT({r}.id) FROM App\Nope {r} WHERE {r}.artist = {entity})'],
                'has an expression which is not valid',
                true,
            ],
            'an alias without a placeholder' => [
                'recordingCount',
                ['expression' => '(SELECT COUNT(r.id) FROM ' . $recording . ' r WHERE r.artist = {entity})'],
                'Every alias in an expression must be a placeholder',
                true,
            ],
            'an argument which is not a parameter' => [
                'recordingCount',
                ['expression' => '(SELECT COUNT({r}.id) FROM ' . $recording . ' {r} WHERE {r}.id > {:nope})'],
                'uses {:nope}, but nope is not a parameter of method getRecordingCount',
                false,
            ],
            'excluded filters without an expression' => [
                'upperName',
                ['expression' => null],
                'has excluded filters but no expression',
                false,
            ],
            'a list' => [
                'recordingCount',
                ['list' => true],
                'has an expression but is a list',
                false,
            ],
            'an entity type' => [
                'recordingCount',
                ['type' => ComputedExpressionLabel::class],
                'has an expression but is of an entity type',
                false,
            ],
        ];
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('invalidProvider')]
    public function testInvalid(string $fieldName, array $changes, string $message, bool $parsed): void
    {
        try {
            $this->buildMetadata($fieldName, $changes);
            $this->fail('No exception was thrown');
        } catch (MetadataException $exception) {
            $this->assertStringContainsString(
                'Computed field ' . $fieldName . ' of entity ' . ComputedExpressionArtist::class,
                $exception->getMessage(),
            );
            $this->assertStringContainsString($message, $exception->getMessage());
            $this->assertSame($parsed, $exception->getPrevious() instanceof QueryException);
        }
    }
}
