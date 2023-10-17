Caching Metadata
================

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
