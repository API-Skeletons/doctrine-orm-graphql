========
Metadata
========

This library uses metadata that can be modified with the
`BuildMetadata event <events.html>`_.
Modifying the metadata is an advanced feature.

The metadata is an array with a key for each enabled entity class name.
See this unit test
https://github.com/API-Skeletons/doctrine-orm-graphql/blob/14.0.x/test/Feature/Metadata/CachingTest.php

Caching Metadata
================

The process of attributing your entities results in an array of metadata that
is used internal to this library.  If you have a very large number of
attributed entities it may be faster to cache your metadata instead of
rebuilding it with each request.

.. code-block:: php

  use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
  use ApiSkeletons\Doctrine\ORM\GraphQL\Metadata;

  $metadata = $cache->get('GraphQLMetadata');

  if (! $metadata) {
      $driver = new Driver($entityManager);

      $cache->set('GraphQLMetadata', $driver->get(Metadata::class)->toArray());
  } else {
      // The second parameter is the Config object
      $driver = new Driver($entityManager, null, $metadata);
  }

``toArray()`` exports the metadata as an array of scalars and arrays, so any
cache can store it, including ``var_export()`` to a PHP file.  The export
includes a format version, ``Metadata::FORMAT_VERSION``.  The driver rejects a
cache written in another format, or without a version, with
``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata``; regenerate the cache
when that happens.  Use ``toArray()`` rather than ``getArrayCopy()``, which does
not include the version.

The export also records, under a ``__config`` key, the config values the
metadata depends on: ``group``, ``groupSuffix``, ``entityPrefix`` and
``extractByValue``.  A driver given cached metadata built with other values
throws ``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Metadata`` naming them,
rather than serving another group's types.  Cache the metadata of each config
separately, for example with the group in the cache key.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst

