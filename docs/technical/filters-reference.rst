==================
Filters Reference
==================

This document provides comprehensive documentation of the filter system, including all available filter types, how filters are generated, and how they're applied to database queries.

Overview
========

The library automatically generates GraphQL filter InputObjectTypes for all exposed entities, fields, and associations. Filters are context-aware based on field types and can be excluded at multiple levels.

**Key Features**:

- 15 built-in filter types
- Context-aware filter availability
- Database-level filtering (QueryBuilder, not in-memory)
- Multi-level filter exclusion (config, entity, field, association)
- Custom filter support via events

Filter Types
============

The library provides 15 filter types via the ``Filters`` enum:

.. code-block:: php

    namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

    enum Filters: string
    {
        case EQ           = 'eq';
        case NEQ          = 'neq';
        case LT           = 'lt';
        case LTE          = 'lte';
        case GT           = 'gt';
        case GTE          = 'gte';
        case BETWEEN      = 'between';
        case CONTAINS     = 'contains';
        case STARTSWITH   = 'startswith';
        case ENDSWITH     = 'endswith';
        case IN           = 'in';
        case NOTIN        = 'notin';
        case ISNULL       = 'isnull';
        case SORT         = 'sort';
        case SORTPRIORITY = 'sortPriority';
    }

Equality Filters
----------------

EQ (Equals)
^^^^^^^^^^^

**SQL**: ``field = value``

**Type**: Scalar matching field type

**Available For**: All field types

**Example**:

.. code-block:: graphql

    {
        artists(filter: { name: { eq: "Grateful Dead" } }) {
            edges { node { name } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM artist WHERE name = 'Grateful Dead'

NEQ (Not Equals)
^^^^^^^^^^^^^^^^

**SQL**: ``field != value``

**Type**: Scalar matching field type

**Available For**: All field types

**Example**:

.. code-block:: graphql

    {
        artists(filter: { name: { neq: "Unknown" } }) {
            edges { node { name } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM artist WHERE name != 'Unknown'

Comparison Filters
------------------

LT (Less Than)
^^^^^^^^^^^^^^

**SQL**: ``field < value``

**Type**: Scalar matching field type

**Available For**: Numeric, date, datetime types

**Example**:

.. code-block:: graphql

    {
        performances(filter: { date: { lt: "2020-01-01" } }) {
            edges { node { venue date } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM performance WHERE date < '2020-01-01'

LTE (Less Than or Equal)
^^^^^^^^^^^^^^^^^^^^^^^^^

**SQL**: ``field <= value``

**Type**: Scalar matching field type

**Available For**: Numeric, date, datetime types

**Example**:

.. code-block:: graphql

    {
        artists(filter: { rating: { lte: 5 } }) {
            edges { node { name rating } }
        }
    }

GT (Greater Than)
^^^^^^^^^^^^^^^^^

**SQL**: ``field > value``

**Type**: Scalar matching field type

**Available For**: Numeric, date, datetime types

**Example**:

.. code-block:: graphql

    {
        performances(filter: { attendance: { gt: 10000 } }) {
            edges { node { venue attendance } }
        }
    }

GTE (Greater Than or Equal)
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

**SQL**: ``field >= value``

**Type**: Scalar matching field type

**Available For**: Numeric, date, datetime types

**Example**:

.. code-block:: graphql

    {
        performances(filter: { date: { gte: "2020-01-01" } }) {
            edges { node { venue date } }
        }
    }

BETWEEN
^^^^^^^

**SQL**: ``field BETWEEN from AND to``

**Type**: Special InputObjectType with ``from`` and ``to`` fields

**Available For**: Numeric, date, datetime types

**Example**:

.. code-block:: graphql

    {
        performances(filter: {
            date: {
                between: {
                    from: "2020-01-01"
                    to: "2020-12-31"
                }
            }
        }) {
            edges { node { venue date } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM performance
    WHERE date BETWEEN '2020-01-01' AND '2020-12-31'

**Type Definition**:

.. code-block:: graphql

    input Filter_Between_DateTime {
        from: DateTime!
        to: DateTime!
    }

String Pattern Filters
----------------------

CONTAINS
^^^^^^^^

**SQL**: ``field LIKE '%value%'``

**Type**: String

**Available For**: String fields only

**Performance**: **Expensive** - requires full table scan, cannot use indexes efficiently

**Example**:

.. code-block:: graphql

    {
        artists(filter: { name: { contains: "Dead" } }) {
            edges { node { name } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM artist WHERE name LIKE '%Dead%'

**Optimization**: Consider full-text search for large datasets.

STARTSWITH
^^^^^^^^^^

**SQL**: ``field LIKE 'value%'``

**Type**: String

**Available For**: String fields only

**Performance**: **Moderate** - can use indexes with prefix matching

**Example**:

.. code-block:: graphql

    {
        artists(filter: { name: { startswith: "The" } }) {
            edges { node { name } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM artist WHERE name LIKE 'The%'

ENDSWITH
^^^^^^^^

**SQL**: ``field LIKE '%value'``

**Type**: String

**Available For**: String fields only

**Performance**: **Expensive** - requires full table scan

**Example**:

.. code-block:: graphql

    {
        artists(filter: { name: { endswith: "Band" } }) {
            edges { node { name } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM artist WHERE name LIKE '%Band'

Array Filters
-------------

IN
^^

**SQL**: ``field IN (value1, value2, ...)``

**Type**: ``[Scalar]`` (array of field type)

**Available For**: All field types

**Example**:

.. code-block:: graphql

    {
        artists(filter: {
            id: { in: [1, 2, 3, 5, 8] }
        }) {
            edges { node { id name } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM artist WHERE id IN (1, 2, 3, 5, 8)

**Performance**: Efficient with proper indexes

NOTIN
^^^^^

**SQL**: ``field NOT IN (value1, value2, ...)``

**Type**: ``[Scalar]`` (array of field type)

**Available For**: All field types

**Example**:

.. code-block:: graphql

    {
        artists(filter: {
            status: { notin: ["inactive", "deleted"] }
        }) {
            edges { node { name status } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM artist WHERE status NOT IN ('inactive', 'deleted')

Null Filters
------------

ISNULL
^^^^^^

**SQL**: ``field IS NULL`` or ``field IS NOT NULL``

**Type**: Boolean

**Available For**: Nullable fields only

**Example**:

.. code-block:: graphql

    # Find artists with no email
    {
        artists(filter: { email: { isnull: true } }) {
            edges { node { name email } }
        }
    }

    # Find artists with email
    {
        artists(filter: { email: { isnull: false } }) {
            edges { node { name email } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    -- isnull: true
    SELECT * FROM artist WHERE email IS NULL

    -- isnull: false
    SELECT * FROM artist WHERE email IS NOT NULL

Sorting Filters
---------------

SORT
^^^^

**SQL**: ``ORDER BY field ASC|DESC``

**Type**: String enum (``"ASC"`` or ``"DESC"``)

**Available For**: All field types

**Example**:

.. code-block:: graphql

    {
        artists(filter: {
            name: { sort: "ASC" }
        }) {
            edges { node { name } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM artist ORDER BY name ASC

**Multiple Sorts**:

.. code-block:: graphql

    {
        performances(filter: {
            date: { sort: "DESC", sortPriority: 1 }
            venue: { sort: "ASC", sortPriority: 2 }
        }) {
            edges { node { date venue } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM performance
    ORDER BY date DESC, venue ASC

SORTPRIORITY
^^^^^^^^^^^^

**SQL**: Controls order in ``ORDER BY`` clause

**Type**: Integer

**Available For**: All field types (used with SORT)

**Required**: Must be used with ``sort``

**Purpose**: Defines sort order when multiple fields are sorted

**Example**: See SORT example above

Filter Generation
=================

Automatic Generation
--------------------

The ``FilterFactory`` automatically generates filter InputObjectTypes based on entity metadata:

.. code-block:: php

    class FilterFactory
    {
        public function get(Entity $entity): InputObjectType
        {
            $fields = [];

            // Generate field filters
            foreach ($entity->getFields() as $fieldName => $fieldData) {
                $fields[$fieldName] = new Field(
                    $fieldName,
                    $fieldData,
                    $this->typeContainer,
                    $this->config,
                );
            }

            // Generate association filters
            foreach ($entity->getAssociations() as $assocName => $assocData) {
                $fields[$assocName] = new Association(
                    $assocName,
                    $assocData,
                    $targetEntity,
                    $this,
                );
            }

            return new InputObjectType([
                'name' => 'Filter_' . $entity->getTypeName(),
                'fields' => $fields,
            ]);
        }
    }

Context-Aware Filters
---------------------

Filters are context-aware based on field types:

**Integer Field**:

.. code-block:: graphql

    input Filter_Artist_id {
        eq: Int
        neq: Int
        lt: Int
        lte: Int
        gt: Int
        gte: Int
        in: [Int]
        notin: [Int]
        isnull: Boolean
        between: Filter_Between_Int
        sort: String
        sortPriority: Int
    }

**String Field**:

.. code-block:: graphql

    input Filter_Artist_name {
        eq: String
        neq: String
        contains: String      # String-specific
        startswith: String    # String-specific
        endswith: String      # String-specific
        in: [String]
        notin: [String]
        isnull: Boolean
        sort: String
        sortPriority: Int
    }

**DateTime Field**:

.. code-block:: graphql

    input Filter_Performance_date {
        eq: DateTime
        neq: DateTime
        lt: DateTime          # Comparison filters
        lte: DateTime
        gt: DateTime
        gte: DateTime
        in: [DateTime]
        notin: [DateTime]
        isnull: Boolean
        between: Filter_Between_DateTime
        sort: String
        sortPriority: Int
    }

**Boolean Field**:

.. code-block:: graphql

    input Filter_Artist_active {
        eq: Boolean
        neq: Boolean
        isnull: Boolean
        sort: String
        sortPriority: Int
        # No comparison or string filters
    }

Nested Filters
--------------

Associations have nested filters:

.. code-block:: graphql

    input Filter_Artist {
        id: Filter_Artist_id
        name: Filter_Artist_name

        # Nested association filter
        performances: Filter_Performance
    }

    input Filter_Performance {
        id: Filter_Performance_id
        venue: Filter_Performance_venue
        date: Filter_Performance_date

        # Nested association filter
        artist: Filter_Artist
    }

**Query Example**:

.. code-block:: graphql

    {
        artists(filter: {
            name: { contains: "Dead" }
            performances: {
                venue: { eq: "Madison Square Garden" }
                date: { gte: "2020-01-01" }
            }
        }) {
            edges {
                node {
                    name
                    performances {
                        edges {
                            node {
                                venue
                                date
                            }
                        }
                    }
                }
            }
        }
    }

Filter Application
==================

QueryBuilder Application
------------------------

Filters are applied to Doctrine QueryBuilder at the database level:

.. code-block:: php

    class QueryBuilder
    {
        public function apply(
            array $filters,
            DoctrineQueryBuilder $queryBuilder,
            Entity $entity
        ): void {
            foreach ($filters as $fieldName => $filterValues) {
                foreach ($filterValues as $filterType => $value) {
                    $this->applyFilter($fieldName, $filterType, $value, $queryBuilder);
                }
            }
        }

        private function applyFilter(
            string $field,
            string $filterType,
            mixed $value,
            DoctrineQueryBuilder $qb
        ): void {
            $paramName = $field . '_' . $filterType;

            match ($filterType) {
                'eq' => $qb->andWhere("entity.$field = :$paramName"),
                'neq' => $qb->andWhere("entity.$field != :$paramName"),
                'lt' => $qb->andWhere("entity.$field < :$paramName"),
                'lte' => $qb->andWhere("entity.$field <= :$paramName"),
                'gt' => $qb->andWhere("entity.$field > :$paramName"),
                'gte' => $qb->andWhere("entity.$field >= :$paramName"),
                'contains' => $qb->andWhere("entity.$field LIKE :$paramName"),
                'startswith' => $qb->andWhere("entity.$field LIKE :$paramName"),
                'endswith' => $qb->andWhere("entity.$field LIKE :$paramName"),
                'in' => $qb->andWhere("entity.$field IN (:$paramName)"),
                'notin' => $qb->andWhere("entity.$field NOT IN (:$paramName)"),
                'isnull' => $value
                    ? $qb->andWhere("entity.$field IS NULL")
                    : $qb->andWhere("entity.$field IS NOT NULL"),
                'between' => $qb->andWhere(
                    "entity.$field BETWEEN :{$paramName}_from AND :{$paramName}_to"
                ),
                'sort' => $qb->addOrderBy("entity.$field", $value),
                default => throw new Exception("Unknown filter: $filterType"),
            };

            // Set parameters
            if ($filterType === 'contains') {
                $qb->setParameter($paramName, '%' . $value . '%');
            } elseif ($filterType === 'startswith') {
                $qb->setParameter($paramName, $value . '%');
            } elseif ($filterType === 'endswith') {
                $qb->setParameter($paramName, '%' . $value);
            } elseif ($filterType === 'between') {
                $qb->setParameter($paramName . '_from', $value['from']);
                $qb->setParameter($paramName . '_to', $value['to']);
            } elseif (!in_array($filterType, ['isnull', 'sort'])) {
                $qb->setParameter($paramName, $value);
            }
        }
    }

Multiple Filters
----------------

Multiple filters on the same field are combined with AND:

.. code-block:: graphql

    {
        performances(filter: {
            date: {
                gte: "2020-01-01"
                lt: "2021-01-01"
            }
        }) {
            edges { node { date venue } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM performance
    WHERE date >= '2020-01-01'
    AND date < '2021-01-01'

Multiple Fields
---------------

Filters on different fields are also combined with AND:

.. code-block:: graphql

    {
        artists(filter: {
            name: { contains: "Dead" }
            active: { eq: true }
            rating: { gte: 4 }
        }) {
            edges { node { name } }
        }
    }

**SQL Generated**:

.. code-block:: sql

    SELECT * FROM artist
    WHERE name LIKE '%Dead%'
    AND active = 1
    AND rating >= 4

Filter Exclusion
================

Multi-Level Exclusion
---------------------

Filters can be excluded at four levels:

1. **Config Level** (global):

   .. code-block:: php

       new Config([
           'excludeFilters' => [
               Filters::CONTAINS,
               Filters::STARTSWITH,
               Filters::ENDSWITH,
           ],
       ]);

2. **Entity Level**:

   .. code-block:: php

       #[GraphQL\Entity(excludeFilters: [Filters::CONTAINS])]
       class Artist { }

3. **Field Level**:

   .. code-block:: php

       #[GraphQL\Field(excludeFilters: [Filters::SORT])]
       private string $biography;

4. **Association Level**:

   .. code-block:: php

       #[GraphQL\Association(excludeFilters: [Filters::CONTAINS])]
       private Collection $performances;

**Additive**: All exclusions are combined (merged).

**Priority**: More specific exclusions add to less specific ones.

Include Filters
---------------

Alternative to exclusion is inclusion (whitelist):

.. code-block:: php

    // Only allow equality checks
    #[GraphQL\Entity(includeFilters: [
        Filters::EQ,
        Filters::NEQ,
        Filters::ISNULL,
    ])]
    class Artist { }

**Mutually Exclusive**: Cannot use both ``includeFilters`` and ``excludeFilters`` on the same attribute.

Performance Considerations
==========================

Expensive Filters
-----------------

Some filters are more expensive than others:

**Most Expensive** (avoid on large tables):

- ``CONTAINS`` - ``LIKE '%value%'`` (full table scan)
- ``ENDSWITH`` - ``LIKE '%value'`` (full table scan)

**Moderately Expensive**:

- ``STARTSWITH`` - ``LIKE 'value%'`` (partial index use)
- ``NOTIN`` - Can be slow with large arrays

**Efficient** (with proper indexes):

- ``EQ``, ``NEQ`` - Exact matching
- ``IN`` - Array matching (with moderate array size)
- ``GT``, ``GTE``, ``LT``, ``LTE`` - Range queries
- ``ISNULL`` - Null checks

Optimization Strategies
-----------------------

1. **Exclude Expensive Filters**:

   .. code-block:: php

       #[GraphQL\Entity(excludeFilters: [
           Filters::CONTAINS,
           Filters::ENDSWITH,
       ])]

2. **Add Database Indexes**:

   .. code-block:: sql

       CREATE INDEX idx_artist_name ON artist(name);
       CREATE INDEX idx_performance_date ON performance(date);

3. **Use Full-Text Search**:

   For ``CONTAINS`` on large text fields, use database full-text search:

   .. code-block:: sql

       -- MySQL
       ALTER TABLE artist ADD FULLTEXT(name);

       -- PostgreSQL
       CREATE INDEX idx_artist_name_fts
       ON artist USING GIN(to_tsvector('english', name));

4. **Limit Result Sets**:

   .. code-block:: php

       #[GraphQL\Entity(limit: 100)]
       #[GraphQL\Association(limit: 50)]

5. **Monitor Slow Queries**:

   .. code-block:: php

       $driver->get(EventDispatcher::class)->subscribeTo(
           QueryBuilderEvent::class,
           function (QueryBuilderEvent $event) {
               $sql = $event->getQueryBuilder()->getQuery()->getSQL();
               error_log("Query: " . $sql);
           }
       );

Advanced Usage
==============

Custom Filter Logic via Events
-------------------------------

Add custom filtering via QueryBuilder events:

.. code-block:: php

    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.query',
        function (QueryBuilderEvent $event) {
            // Add custom WHERE clause
            $event->getQueryBuilder()
                ->andWhere('entity.verified = true');

            // Access user context
            $context = $event->getContext();
            if (isset($context['userId'])) {
                $event->getQueryBuilder()
                    ->andWhere('entity.userId = :userId')
                    ->setParameter('userId', $context['userId']);
            }
        }
    );

Ad-Hoc Filters
--------------

Apply filters programmatically:

.. code-block:: php

    use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\QueryBuilder as QueryBuilderFilter;

    $queryBuilder = $entityManager->createQueryBuilder()
        ->select('a')
        ->from(Artist::class, 'a');

    $filters = [
        'name' => ['contains' => 'Dead'],
        'active' => ['eq' => true],
    ];

    $queryBuilderFilter = new QueryBuilderFilter();
    $queryBuilderFilter->apply($filters, $queryBuilder, $artistEntity);

    $results = $queryBuilder->getQuery()->getResult();

Conditional Filters
-------------------

Apply filters conditionally:

.. code-block:: php

    $filters = [];

    // Add name filter if provided
    if ($searchTerm) {
        $filters['name'] = ['contains' => $searchTerm];
    }

    // Add date filter if provided
    if ($startDate) {
        $filters['date'] = ['gte' => $startDate];
    }

    // Execute query
    $result = GraphQL::executeQuery(
        $schema,
        $query,
        null,
        null,
        ['filter' => $filters]
    );

Filter Validation
-----------------

Validate filter values before execution:

.. code-block:: php

    $driver->get(EventDispatcher::class)->subscribeTo(
        'artist.query',
        function (QueryBuilderEvent $event) {
            $args = $event->getArgs();

            if (isset($args['filter']['rating']['gt'])) {
                $rating = $args['filter']['rating']['gt'];

                if ($rating < 0 || $rating > 5) {
                    throw new \InvalidArgumentException(
                        'Rating must be between 0 and 5'
                    );
                }
            }
        }
    );

Testing Filters
===============

Unit Tests
----------

.. code-block:: php

    public function testEqualityFilter(): void
    {
        $query = '{
            artists(filter: { name: { eq: "Grateful Dead" } }) {
                edges {
                    node {
                        name
                    }
                }
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $data = $result->toArray()['data'];

        $this->assertCount(1, $data['artists']['edges']);
        $this->assertEquals('Grateful Dead', $data['artists']['edges'][0]['node']['name']);
    }

Integration Tests
-----------------

.. code-block:: php

    public function testComplexFilters(): void
    {
        $query = '{
            performances(filter: {
                date: {
                    gte: "2020-01-01"
                    lt: "2021-01-01"
                }
                venue: {
                    contains: "Garden"
                }
                artist: {
                    name: { eq: "Phish" }
                }
            }) {
                totalCount
                edges {
                    node {
                        date
                        venue
                    }
                }
            }
        }';

        $result = GraphQL::executeQuery($schema, $query);
        $data = $result->toArray()['data'];

        // All results should match filters
        foreach ($data['performances']['edges'] as $edge) {
            $this->assertStringContainsString('Garden', $edge['node']['venue']);
            $this->assertGreaterThanOrEqual('2020-01-01', $edge['node']['date']);
            $this->assertLessThan('2021-01-01', $edge['node']['date']);
        }
    }

Common Patterns
===============

Date Range Queries
------------------

.. code-block:: graphql

    {
        performances(filter: {
            date: {
                between: {
                    from: "2020-01-01"
                    to: "2020-12-31"
                }
            }
        }) {
            edges { node { date venue } }
        }
    }

Search with Pagination
----------------------

.. code-block:: graphql

    {
        artists(
            filter: { name: { contains: "Dead" } }
            pagination: { first: 20 }
        ) {
            edges {
                cursor
                node { name }
            }
            pageInfo {
                hasNextPage
                endCursor
            }
        }
    }

Multi-Field Sorting
-------------------

.. code-block:: graphql

    {
        performances(filter: {
            date: { sort: "DESC", sortPriority: 1 }
            venue: { sort: "ASC", sortPriority: 2 }
            attendance: { sort: "DESC", sortPriority: 3 }
        }) {
            edges { node { date venue attendance } }
        }
    }

Status Filtering
----------------

.. code-block:: graphql

    {
        artists(filter: {
            status: { in: ["active", "featured"] }
            verified: { eq: true }
        }) {
            edges { node { name status } }
        }
    }

Summary
=======

The filter system provides:

- 15 built-in filter types
- Context-aware availability based on field types
- Database-level filtering (efficient, uses indexes)
- Multi-level exclusion (config, entity, field, association)
- Nested filters for associations
- Custom filter logic via events

For maximum performance:

- Exclude expensive filters (CONTAINS, ENDSWITH)
- Add database indexes
- Set reasonable limits
- Monitor slow queries
- Use full-text search for text searching

For additional customization, see :doc:`events-reference` and :doc:`advanced-topics`.

.. role:: raw-html(raw)
   :format: html

.. include:: footer.rst
