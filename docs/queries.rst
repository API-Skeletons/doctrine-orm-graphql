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
            pagination: { first: 100 }
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

    eq         -  Equals; same as name: value.  DateTime not supported.  See Between.
    neq        -  Not Equals
    gt         -  Greater Than
    lt         -  Less Than
    gte        -  Greater Than or Equal To
    lte        -  Less Than or Equal To
    in         -  Filter for values in an array
    notin      -  Filter for values not in an array
    between    -  Filter between `from` and `to` values.  Good substitute for DateTime Equals.
    contains   -  Strings only. Similar to a Like query as `like '%value%'`
    startswith -  Strings only. A like query from the beginning of the value `like 'value%'`
    endswith   -  Strings only. A like query from the end of the value `like '%value'`
    isnull     -  If `true` return results where the field is null.
    sort       -  Sort the result by this field.  Value is 'asc' or 'desc'

The format for using these filters is:

.. code-block:: js

    filter: { name: { endswith: "Dead" } }

For isnull the parameter is a boolean

.. code-block:: js

    filter: { name: { isnull: false  } }

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
            performances ( filter: { year: { sort: "asc" } } ) {
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

A pagination argument is included with embedded collections but for top-level
collections you must include the pagination argument yourself just as you do
for filters.

A complete query for all pagination data:

.. code-block:: js

  {
    artists (pagination: {first: 10, after: "cursor"}) {
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

Cursors are included with each edge.  A cursor is a base64 encoded
offset from the beginning of the result set.  ``base64_encode('0');`` is 
``MA==`` to use when creating a paginated query.


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
    artists (pagination: { first: 10 }) {
    }
  }

To get the next page, you would add the endCursor from the current page as the after parameter.

.. code-block:: js

  {
    artists (pagination: { first: 10, after: "endCursor" }) {
    }
  }

For the previous page, you would add the startCursor from the current page as the before parameter.

.. code-block:: js

  {
    offers (pagination: { last: 10, before: "startCursor" }) {
    }
  }

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
