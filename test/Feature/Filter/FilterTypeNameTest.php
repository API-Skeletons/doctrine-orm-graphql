<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL\Feature\Filter;

use ApiSkeletons\Doctrine\ORM\GraphQL\Driver;
use ApiSkeletons\Doctrine\ORM\GraphQL\Exception\Filter as FilterException;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\Filters;
use ApiSkeletons\Doctrine\ORM\GraphQL\Filter\InputObjectType\Field;
use ApiSkeletons\Doctrine\ORM\GraphQL\Type\TypeContainer;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\Entity\Performance;
use ApiSkeletonsTest\Doctrine\ORM\GraphQL\TestCase;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Field filter types are named by their type and a short hash of their
 * filters
 */
class FilterTypeNameTest extends TestCase
{
    public function testNameIsTheTypeAndAShortHash(): void
    {
        $filter = (new Driver($this->getEntityManager()))->filter(Performance::class);
        $this->assertInstanceOf(InputObjectType::class, $filter);

        $venue  = $filter->getField('venue')->getType();
        $artist = $filter->getField('artist')->getType();
        $this->assertInstanceOf(InputObjectType::class, $venue);
        $this->assertInstanceOf(InputObjectType::class, $artist);

        $this->assertMatchesRegularExpression('/^Filters_String_[0-9a-f]{8}$/', $venue->name());
        $this->assertMatchesRegularExpression('/^Filters_ID_[0-9a-f]{8}$/', $artist->name());
    }

    /**
     * Fields of the same type with the same filters share one filter type
     */
    public function testSameFiltersShareOneType(): void
    {
        $filter = (new Driver($this->getEntityManager()))->filter(Performance::class);
        $this->assertInstanceOf(InputObjectType::class, $filter);

        // Venue, city and state are strings with the same filters
        $this->assertSame($filter->getField('venue')->getType(), $filter->getField('city')->getType());
        $this->assertSame($filter->getField('venue')->getType(), $filter->getField('state')->getType());
    }

    /**
     * A short hash may collide; a registered type of the same name with other
     * filters is an error rather than used
     */
    public function testNameUsedForOtherFiltersThrows(): void
    {
        $driver = new Driver($this->getEntityManager());
        $name   = Field::nameFor(Type::id(), [Filters::EQ, Filters::NEQ, Filters::IN, Filters::NOTIN, Filters::ISNULL]);

        $typeContainer = $driver->get(TypeContainer::class);
        $typeContainer->set($name, new Field($typeContainer, Type::id(), [Filters::EQ]));

        $this->expectException(FilterException::class);
        $this->expectExceptionMessage('Filter type name ' . $name . ' is already used for different filters.');

        $driver->filter(Performance::class);
    }
}
