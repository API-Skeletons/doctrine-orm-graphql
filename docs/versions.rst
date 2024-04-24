==================================
Versions and Event Manager Support
==================================

The event manager used in this library is from `league/event <https://github.com/thephpleague/event>`_.
There are two supported versions of the event manager library by
`The PHP League <https://github.com/thephpleague>`_ and their API is very different.  In this library,
version 3 of `league/event` has always been used.  Version 3 is a PSR-14 compliant event manager.

However, `The PHP League <https://github.com/thephpleague>`_ does not use the latest version of their own
event manager in their `league/oauth2-server <https://github.com/thephpleague/oauth2-server>`_.  Because of this
old version requirement, it was not possible to install the ``league/oauth2-server`` library and this library in the
same project.  Version 11 of ``api-skeletons/doctrine-orm-graphql`` has regressive support for ``league/event``
by supporting version 2 of that library instead of version 3.  Version 2 is not PSR-14 compliant.

If you need to install ``league/oauth2-server`` and ``api-skeletons/doctrine-orm-graphql`` in the same project,
you must use version 11 of this library.

If you do not need to install ``league/oauth2-server`` and ``api-skeletons/doctrine-orm-graphql`` in the
same project, you should use version 10 of this library.


.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
