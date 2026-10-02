<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL;

use Closure;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Log\AbstractLogger;

use function count;
use function method_exists;

/**
 * A TestCase whose connection records the SQL of every statement it executes
 */
abstract class QueryCountingTestCase extends TestCase
{
    /** @var string[] */
    private static array $sql = [];

    /** Called with the SQL of each statement just before it is executed */
    private static Closure|null $beforeExecute = null;

    public function setUp(): void
    {
        self::registerTypes();
        self::$beforeExecute = null;

        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/Entity'],
            isDevMode: true,
        );
        if (method_exists($config, 'enableNativeLazyObjects')) {
            $config->enableNativeLazyObjects(true);
        }

        $logger = new class extends AbstractLogger {
            /**
             * The parameters are untyped in psr/log 1 and typed in 2 and 3;
             * mixed is compatible with all of them
             *
             * @param mixed[] $context
             */
            public function log(mixed $level, mixed $message, array $context = []): void
            {
                if (! isset($context['sql'])) {
                    return;
                }

                QueryCountingTestCase::record((string) $context['sql']);
            }
        };

        $connection = DriverManager::getConnection(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            (new Configuration())->setMiddlewares([new Middleware($logger)]),
        );

        self::$entityManager = new EntityManager($connection, $config);
        (new SchemaTool(self::$entityManager))->createSchema(self::$entityManager->getMetadataFactory()->getAllMetadata());

        $this->populateData();
        $this->resetQueries();
    }

    /** @internal Called by the logger */
    public static function record(string $sql): void
    {
        self::$sql[] = $sql;

        if (self::$beforeExecute === null) {
            return;
        }

        (self::$beforeExecute)($sql);
    }

    /**
     * Call a function with the SQL of each statement just before it is
     * executed, such as to change the data as another request would
     *
     * @param Closure(string): void|null $callback
     */
    protected static function beforeExecute(Closure|null $callback): void
    {
        self::$beforeExecute = $callback;
    }

    protected function resetQueries(): void
    {
        self::$sql = [];
    }

    protected function queryCount(): int
    {
        return count(self::$sql);
    }

    /** @return string[] */
    protected function queries(): array
    {
        return self::$sql;
    }
}
