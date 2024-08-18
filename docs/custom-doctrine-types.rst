=====================
Custom Doctrine Types
=====================

To implement non-standard Doctrine types as GraphQL types,
you must implement ``GraphQL\Type\Definition\ScalarType`` and add it to the
``TypeContainer``.

This example implements a Uuid type for ``ramsey/uuid-doctrine``.

  .. code-block:: php

    use GraphQL\Error\Error;
    use GraphQL\Language\AST\Node as ASTNode;
    use GraphQL\Language\AST\StringValueNode;
    use GraphQL\Type\Definition\ScalarType;
    use Ramsey\Uuid\Uuid as RamseyUuid;
    use Ramsey\Uuid\UuidInterface;

    use function preg_match;

    /**
     * This class is used to create a Uuid type
     */
    class Uuid extends ScalarType
    {
        // phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint
        public string|null $description = 'A universally unique identifier.';

        public function parseLiteral(ASTNode $valueNode, array|null $variables = null): string
        {
            if (! $valueNode instanceof StringValueNode) {
                throw new Error('Query error: Uuid can only parse strings got: ' . $valueNode->kind, $valueNode);
            }

            return $valueNode->value;
        }

        public function parseValue(mixed $value): UuidInterface|null
        {
            if ($value instanceof UuidInterface) {
                return $value;
            }

            if (! preg_match('/^[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}$/', $value)) {
                throw new Error('Uuid is invalid.');
            }

            return RamseyUuid::fromString($value);
        }

        public function serialize(mixed $value): string|null
        {
            if ($value instanceof UuidInterface) {
                return $value->toString();
            }

            return $value;
        }
    }

Then add that type to the type container

  .. code-block:: php

   use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;

   $driver->get(TypeContainer::class)->set('uuid', static fn () => new Uuid();


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
