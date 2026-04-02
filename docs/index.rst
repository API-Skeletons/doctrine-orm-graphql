====================================
GraphQL Type Driver for Doctrine ORM
====================================

.. image:: banner.png
   :align: center
   :scale: 25 %

This is the documentation for
`API-Skeletons/doctrine-orm-graphql <https://github.com/API-Skeletons/doctrine-orm-graphql>`_

This project builds GraphQL types, filters, and resolvers for Doctrine entities.  To select which
entities, fields, and associations are available for querying via GraphQL,
PHP attributes are used.

Other GraphQL libraries for Doctrine ORM are available.
Some of these such as `overblog/graphql-bundle <https://github.com/overblog/GraphQLBundle/tree/master>`_
and `API Platform <https://api-platform.com/>`_ are integrations into frameworks.  But all of these libraries
use the same underlying library, `webonyx/graphql-php <https://github.com/webonyx/graphql-php>`_ and that library
has its own way of doing things.  This library is a driver for that library and together they are framework agnostic.

The goal of this project is to make creating GraphQL types simple and uncomplicated, but as
you'll see, there's a lot of customizable power built in too.

.. toctree::

    :caption: User Documentation

    install
    just-the-basics
    queries
    attributes
    driver
    mutations
    types
    computed-fields
    events
    extending-entity-types
    containers
    metadata
    strategies
    tips
    custom-doctrine-types
    versions
    upgrade
    about


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
