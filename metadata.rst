Metadata
========

This library uses metadata that can be modified with the
`BuildMetadata event <events.html>`_.  See the
`metadata caching test <https://github.com/API-Skeletons/doctrine-graphql/blob/main/test/Feature/Metadata/CachingTest.php>`_
for examples.  Modifying the metadata is an advanced feature.

The metadata is an array with a key for each enabled entity.

.. code-block:: php
  :linenos:

  <?php

  [
      'ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User' => [
          'entityClass' => 'ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\User',
          'documentation' => '',
          'byValue' => 1,
          'namingStrategy' => null,
          'fields' => [
              'name' => [
                  'strategy' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\FieldDefault',
                  'documentation' => '',
              ],
              'recordings' => [
                  'strategy' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\AssociationDefault',
                  'excludeCriteria' => ['eq'],
                  'documentation' => '',
                  'limit' => 10,
              ],
          ],

          'strategies' => [
              'name' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\FieldDefault',
              'email' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\FieldDefault',
              'id' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\ToInteger',
              'recordings' => 'ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy\AssociationDefault',
          ],
          'filters' => [],
          'typeName' => 'User',
      ],
  ];


Caching Metadata
----------------

The process of attributing your entities results in an array of metadata that
is used internal to this library.  If you have a very large number of
attributed entities it may be faster to cache your metadata instead of
rebuilding it with each request.

.. code-block:: php
  :linenos:

  <?php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

  $metadata = $cache->get('GraphQLMetadata');

  if (! $metadata) {
      $driver = new Driver($entityManager);

      $metadata = $driver->get('metadata');
      $cache->set('GraphQLMetadata', $metadataConfig->getArrayCopy());
  } else {
      // The second parameter is the Config object
      $driver = new Driver($entityManager, null, $metadata);
  }

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst

