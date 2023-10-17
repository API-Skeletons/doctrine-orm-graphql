Hydrator Strategies
===================

Some hydrator strategies are supplied with this library.  You may also add your own hydrator
strategies if you desire.

All strategies are in the namespace ``ApiSkeletons\Doctrine\ORM\GraphQL\Hydrator\Strategy``

FieldDefault
------------

This strategy is applied to most field values.  It will return the exact value of the field.


ToInteger
---------

This strategy will convert the field value to an integer to be handled as an integer internal to PHP.


ToFloat
-------

Similar to ``ToInteger``, this will convert the field value to a float to be handled as a float internal to PHP.


ToBoolean
---------

Similar to ``ToInteger``, this will convert the field value to a boolean to be handled as a boolean internal to PHP.


NullifyOwningAssociation
------------------------

Nullify an association.

In a many to many relationship from a known starting point it is possible
to backwards-query the owning relationship to gather data the user should
not be privileged to.

For instance in a User <> Role relationship a user may have many roles.  But
a role may have many users.  So in a query where a user is fetched then their
roles are fetched you could then reverse the query to fetch all users with the
same role

This query would return all user names with the same roles as the user who
created the artist.

``{ artists { users { roles { users { name } } } } }``

This hydrator strategy is used to prevent the reverse lookup by nullifying
the response when queried from the owning side of a many to many relationship

Ideally the developer will not add the owning relationship at all or 
add the owning relation to a filter so the field is not queryable at all.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
