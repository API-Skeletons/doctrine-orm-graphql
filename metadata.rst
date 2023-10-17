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


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
