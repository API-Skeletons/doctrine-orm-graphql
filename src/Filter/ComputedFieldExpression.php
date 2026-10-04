<?php

declare(strict_types=1);

namespace ApiSkeletons\Doctrine\ORM\GraphQL\Filter;

use Closure;

use function array_values;
use function preg_match_all;
use function preg_replace_callback;

use const PREG_SET_ORDER;

/**
 * The placeholders of a computed field's expression
 *
 * DQL aliases must be unique across a query, subqueries included, and an
 * expression may be used more than once in a query: by two filters, or by a
 * filter and a sort.  So every alias in an expression is a {placeholder},
 * given a unique name each time the expression is used.  {entity} is the root
 * entity of the query.  {:name} is the computed field's argument $name, bound
 * as a parameter.
 */
final class ComputedFieldExpression
{
    private const string PLACEHOLDER = '/\{(:?)([A-Za-z_][A-Za-z0-9_]*)\}/';

    /** The placeholder of the root entity */
    public const string ENTITY = 'entity';

    /**
     * The names of the arguments the expression uses, each once
     *
     * @return list<string>
     */
    public static function argumentNames(string $expression): array
    {
        preg_match_all(self::PLACEHOLDER, $expression, $matches, PREG_SET_ORDER);

        $names = [];
        foreach ($matches as $match) {
            if ($match[1] !== ':') {
                continue;
            }

            $names[$match[2]] = $match[2];
        }

        return array_values($names);
    }

    /**
     * The expression for one use: {entity} is the root alias, each other
     * alias is prefixed, and each argument is the parameter the function
     * returns for its name
     *
     * @param Closure(string): string $parameter The parameter, with its colon, of an argument
     */
    public static function substitute(
        string $expression,
        string $rootAlias,
        string $aliasPrefix,
        Closure $parameter,
    ): string {
        return (string) preg_replace_callback(
            self::PLACEHOLDER,
            static fn (array $match): string => match (true) {
                $match[1] === ':' => $parameter($match[2]),
                $match[2] === self::ENTITY => $rootAlias,
                default => $aliasPrefix . $match[2],
            },
            $expression,
        );
    }
}
