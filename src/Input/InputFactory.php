<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\GraphQL\Input;

use ApiSkeletons\Doctrine\GraphQL\AbstractContainer;
use ApiSkeletons\Doctrine\GraphQL\Metadata\Metadata;
use ApiSkeletons\Doctrine\GraphQL\Type\TypeManager;
use Doctrine\ORM\EntityManager;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

use function in_array;

class InputFactory extends AbstractContainer
{
    protected EntityManager $entityManager;

    protected Metadata $metadata;

    protected TypeManager $typeManager;

    public function __construct(EntityManager $entityManager, TypeManager $typeManager, Metadata $metadata)
    {
        $this->entityManager = $entityManager;
        $this->metadata      = $metadata;
        $this->typeManager   = $typeManager;
    }

    /**
     * @param string[] $requiredFields An optional list of just the required fields you want for the mutation.
     *                              This allows specific fields per mutation.
     * @param string[] $optionalFields An optional list of optional fields you want for the mutation.
     *                              This allows specific fields per mutation.
     */
    public function get(string $id, array $requiredFields = [], array $optionalFields = []): InputObjectType
    {
        $targetEntity = $this->metadata->get($id);

        return new InputObjectType([
            'name' => $targetEntity->getTypeName() . '_Input',
            'description' => $targetEntity->getDescription(),
            'fields' => function () use ($id, $targetEntity, $requiredFields, $optionalFields): array {
                $fields = [];

                foreach ($this->entityManager->getClassMetadata($id)->getFieldNames() as $fieldName) {
                    /**
                     * Do not include identifiers as input.  In the majority of cases there will be
                     * no reason to set or update an identifier.  For the case where an identifier
                     * should be set or updated this facotry is not the correct solution.
                     */
                    if ($this->entityManager->getClassMetadata($id)->isIdentifier($fieldName)) {
                        continue;
                    }

                    if ($optionalFields) {
                        // Include field as optional
                        if (in_array($fieldName, $optionalFields) || $optionalFields === ['*']) {
                            $fields[$fieldName]['description'] = $targetEntity->getMetadataConfig()['fields'][$fieldName]['description'];
                            $fields[$fieldName]['type']        = $this->typeManager->get($targetEntity->getMetadataConfig()['fields'][$fieldName]['type']);
                        }
                    } elseif ($requiredFields) {
                        // Include fields as required
                        if (in_array($fieldName, $requiredFields) || $requiredFields === ['*']) {
                            $fields[$fieldName]['description'] = $targetEntity->getMetadataConfig()['fields'][$fieldName]['description'];
                            $fields[$fieldName]['type']        = Type::nonNull($this->typeManager->get($targetEntity->getMetadataConfig()['fields'][$fieldName]['type']));
                        }
                    } else {
                        // All fields are required
                        $fields[$fieldName]['description'] = $targetEntity->getMetadataConfig()['fields'][$fieldName]['description'];
                        $fields[$fieldName]['type']        = Type::nonNull($this->typeManager->get($targetEntity->getMetadataConfig()['fields'][$fieldName]['type']));
                    }
                }

                return $fields;
            },
        ]);
    }
}
