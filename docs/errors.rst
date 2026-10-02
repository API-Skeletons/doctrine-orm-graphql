======
Errors
======

Errors a Client Sees
====================

An error a client's request causes is shown to the client: an invalid filter
(``Exception\Filter``), pagination argument (``Exception\Pagination``) or
scalar value (``Exception\TypeSerialization``).  These extend
``ApiSkeletons\Doctrine\ORM\GraphQL\Exception\ClientError``, and their
messages name only the GraphQL fields and the values the client sent.

Every other error of this library is the developer's to fix, such as an
entity field without a getter, invalid metadata, a hydrator strategy which
is not a strategy, or a type which is not registered.  Its message may name
classes, methods and groups, so a client sees only webonyx's
``Internal server error``, as for any other exception.  Such an error may be
thrown while a query is validated or executed, not only while the schema is
built: an association's target type, a hydrator and fields given as a
closure are built when they are first used.

Seeing a Hidden Error
=====================

The message of a hidden error is in the result's errors, to log:

.. code-block:: php

  use GraphQL\GraphQL;

  $result = GraphQL::executeQuery($schema, $query);

  foreach ($result->errors as $error) {
      $logger->error($error->getMessage(), ['exception' => $error->getPrevious() ?? $error]);
  }

In development, ``GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE`` adds each
hidden message to the response as ``extensions.debugMessage``.  Do not use
it in production.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
