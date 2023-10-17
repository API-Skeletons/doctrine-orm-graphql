Upgrade from api-skeletons/doctrine-graphql
===========================================

This repository, ``api-skeletons/doctrine-orm-graphql`` is a continuation of
``api-skeletons/doctrine-graphql`` but there are some changes necessary to 
move from the old repository to this new one.


Namespaces
----------

The old namespace was ``ApiSkeletons\Doctrine\GraphQL`` and the new namespace
is ``ApiSkeletons\Doctrine\ORM\GraphQL``.  This is the only change between 
the repositories that should affect you.  

The namespace change was made to be more technically correct (the best kind
of correct) as each repository only supports ORM and does not support ODM.


Documentation
-------------

With the new repository the documentation was reviewed in whole and corrected
where necessary.  There is a new theme for the documentation, leaving the old ReadTheDocs default behind.  And, though the documentation is still hosted by https://readthedocs.org it has been moved to a new
domain: https://doctrine-orm-graphql.apiskeletons.dev


What to do?
-----------

As a user of the old repository version 8.1.3, change your namespaces to the
new namespace then replace your composer require to ``api-skeletons/doctrine-orm-graphql ^8.1`` and you will be upgraded to the new repository version 8.1.4.
