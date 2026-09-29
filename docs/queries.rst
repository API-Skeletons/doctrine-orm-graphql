===============
Running Queries
===============

This section is intended for the developer who needs to write queries
against an implementation of this repository.

Queries are not special to this repository.  The format of queries are
exactly what GraphQL is spec'd out to be.

Pagination of ``collections`` supports
`GraphQL's Complete Connection Model <https://graphql.org/learn/pagination/#complete-connection-model>`_.

An example query:

Fetch at most 100 performances in CA for each artist with 'Dead' in their name.

.. code-block:: js

  {
    artists ( filter: { name: { contains: "Dead" } } ) {
      edges {
        node {
          name
          performances (
            filter: { state: { eq: "CA" } }
            first: 100
          ) {
            edges {
              node {
                performanceDate
                venue
              }
            }
          }
        }
      }
    }
  }


Filters
=======

For each field, which is not a reference to another entity, a colletion of
filters exist. Given an entity which contains a `name` field you may directly
filter the name using

.. code-block:: js

    filter: { name: { eq: "Grateful Dead" } }

You may only use each field's filter once per filter().  Should a child record
have the same name as a parent it will share the filter names but filters are
specific to the entity they filter upon.

Provided Filters::

    eq           -  Equals; same as name: value.  DateTime not supported.  See Between.
    neq          -  Not Equals
    gt           -  Greater Than
    lt           -  Less Than
    gte          -  Greater Than or Equal To
    lte          -  Less Than or Equal To
    in           -  Filter for values in an array.  An empty array matches nothing.
    notin        -  Filter for values not in an array.  An empty array matches everything.
    between      -  Filter between `from` and `to` values.  Good substitute for DateTime Equals.
    contains     -  Strings only. Similar to a Like query as `like '%value%'`
    startswith   -  Strings only. A like query from the beginning of the value `like 'value%'`
    endswith     -  Strings only. A like query from the end of the value `like '%value'`
    isnull       -  If `true` return results where the field is null.
    sort         -  Sort the result by this field.  Value is the SortDirection enum, ASC or DESC.
    sortPriority -  Sort priority when multiple sort fields are used.  Value is an integer starting at 1.

When several fields are sorted, fields with a ``sortPriority`` are sorted
first, lowest priority first.  Fields without a ``sortPriority`` follow,
ordered by field name, as are fields with the same priority.  A
``sortPriority`` without a ``sort`` direction is an error.

A filter given ``null``, such as ``isnull: null`` or an optional variable
which is null, is not applied, as a field or filter argument given ``null`` is
not.  ``eq``, ``neq``, ``in`` and ``notin`` are the exception.  A comparison
to null matches nothing, so ``eq``, ``neq``, ``in`` or ``notin`` given null, an
``in`` or ``notin`` list containing null, and a ``between`` without both
``from`` and ``to`` are an error.  Use ``isnull`` to find null values.

A to-one association is filtered by the identifier of the entity it refers
to, with ``eq``, ``neq``, ``in``, ``notin`` and ``isnull``, such as
``filter: { artist: { in: [1, 2] } }``.  An association to an entity with a
composite identifier has no filter.

A ``bigint``, ``decimal`` or ``number`` field is a string, which keeps its
precision, but it has the filters of a number.  A value of ``eq``, ``lt``,
``between``, ``in`` or the other comparisons must be a number, such as
``"1234567890123"`` or ``"314.15"``; a ``bigint`` value must be an integer.

A JSON field has only the ``isnull`` filter; a filter value is decoded JSON,
which does not compare to the stored JSON text.  A blob field has no filters.

``contains``, ``startswith`` and ``endswith`` match their value literally.
The LIKE wildcards ``%`` and ``_`` in the value are escaped, so
``contains: "100%"`` matches only values containing ``100%``.

The format for using these filters is:

.. code-block:: js

    filter: { name: { endswith: "Dead" } }

For isnull the parameter is a boolean

.. code-block:: js

    filter: { name: { isnull: false  } }

For sort the value is an enum, so it is not quoted.  Any other value is
rejected when the query is validated.

.. code-block:: js

    filter: { name: { sort: DESC } }

For in and notin an array of values is expected

.. code-block:: js

    filter: { name: { in: ["Phish", "Legion of Mary"] } }

For the between filter two parameters are necessary.  This is very useful for
date ranges and number queries.

.. code-block:: js

    filter: { year: { between: { from: 1966 to: 1995 } } }


To select a list of years

.. code-block:: js

    {
      artists ( filter: { id: { eq: 2 } } ) {
        edges {
          node {
            performances ( filter: { year: { sort: ASC } } ) {
              edges {
                node {
                  year
                }
              }
            }
          }
        }
      }
    }


All filters are **AND** filters.  For **OR** support use multiple
queries and aggregate them.


Pagination
==========

Pagination of collections supports
`GraphQL's Complete Connection Model <https://graphql.org/learn/pagination/#complete-connection-model>`_.

The pagination arguments ``first``, ``after``, ``last`` and ``before`` are
top-level arguments of a connection, as the Complete Connection Model defines
them.  They are included with embedded collections, but for top-level
collections you must include them yourself, as you do for filters.
``$driver->completeConnection()`` includes them for you.

A complete query for all pagination data:

.. code-block:: js

  {
    artists (first: 10, after: "cursor") {
      totalCount
      pageInfo {
        endCursor
        hasNextPage
      }
      edges {
        cursor
        node {
          id
        }
      }
    }
  }

The rows are counted for ``totalCount`` only when it is requested, or when a
``last`` or ``before`` argument needs the count.  A forward page which does
not request ``totalCount`` is resolved without a count query; one row more than
the page is fetched to tell whether there is a next page.

Cursors are included with each edge.  A cursor is a base64 encoded
offset from the beginning of the result set.  ``base64_encode('0');`` is
``MA==`` to use when creating a paginated query.

A cursor is the position of a row, not the row itself.  When rows are added
or removed before a cursor's position between one page and the next, the next
page starts at the same position of the changed result set, so a row may be
skipped or returned on both pages.  The rows are ordered by the entity's
identifier after any sort, so a result set which does not change pages
consistently.  A page deep in a large result set is also slower to fetch, as
the database counts past the rows before it.  Where either matters, filter on
a sorted field instead of paging by cursor, such as asking for rows with an
``id`` greater than the last one received:

.. code-block:: js

  {
    artists (filter: { id: { gt: 120, sort: ASC } }, first: 10) {
      edges {
        node {
          id
        }
      }
    }
  }

The value of a ``bigint`` identifier is a string, such as ``gt: "120"``.


Two pairs of parameters work with the query:

* ``first`` and ``after``
* ``last`` and ``before``

* ``first`` corresponds to the items per page starting from the beginning;
* ``after`` corresponds to the cursor from which the items are returned.
* ``last`` corresponds to the items per page starting from the end;
* ``before`` corresponds to the cursor from which the items are returned, from a backwards point of view.

To get the first page specify the number of edges

.. code-block:: js

  {
    artists (first: 10) {
    }
  }

To get the next page, you would add the endCursor from the current page as the after parameter.

.. code-block:: js

  {
    artists (first: 10, after: "endCursor") {
    }
  }

For the previous page, you would add the startCursor from the current page as the before parameter.

.. code-block:: js

  {
    offers (last: 10, before: "startCursor") {
    }
  }

Combining the arguments
^^^^^^^^^^^^^^^^^^^^^^^

The four arguments narrow the same range and may be combined freely.  The
range starts as every row the query matches, then

* ``after`` moves the start of the range past the cursor it names;
* ``before`` moves the end of the range to the cursor it names;
* ``first`` moves the end of the range to ``first`` rows after the start;
* ``last`` moves the start of the range to ``last`` rows before the end.

No argument is discarded when another is present, so
``{ after: "cursor", before: "cursor" }`` returns the rows between the two
cursors.  A range which cannot match a row, such as
``{ before: "<the first cursor>" }``, returns an empty ``edges`` list rather
than a full page.  An empty page has no first or last node, so
``pageInfo.startCursor`` and ``pageInfo.endCursor`` are both null.

The configured ``limit`` remains a hard cap.  A request for more rows than the
limit allows is truncated: a forward request keeps the start of its range and a
backward (``last``) request keeps the end.

Invalid arguments
^^^^^^^^^^^^^^^^^

``first`` and ``last`` must be non-negative integers and ``after`` and
``before`` must be cursors taken from a previous result.  A negative count or a
cursor which cannot be decoded is reported to the client as a GraphQL error
rather than silently ignored.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
