================
Type Inheritence
================

This library relies on the developer to implement good practices.  One of these practices is to use type inheritence.
This document shows an example of type inheritence that is just good PHP and not specific to GraphQL.
This approach does not overcompliate the code and is easy to understand.

.. code-block:: php

    use GraphQL\Type\Definition\ObjectType;
    use GraphQL\Type\Definition\Type;

    abstract class Character extends ObjectType
    {
        public function __construct(array $configuration)
        {
            parent::__construct($configuration);
        }

        protected function getFields(): array
        {
            return [
                'id' => Type::id(),
                'type' => Type::string(),
                'name' => Type::string(),
                'level' => Type::int(),
                'staminaPoints' => Type::int(),
            ];
        }
    }

    class CharacterWizard extends Character
    {
        public function __construct()
        {
            $configuration = [
                'name' => 'CharacterWizard',
                'description' => 'Wizard character',
            ];

            $fields = $this->getFields();
            $fields['magicPoints'] = Type::int();

            $configuration['fields'] = fn() => $fields;

            parent::__construct($configuration);
        }
    }

    $driver->get(TypeContainer::class)->set('CharacterWizard', static fn () => new CharacterWizard();
