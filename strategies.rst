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


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
