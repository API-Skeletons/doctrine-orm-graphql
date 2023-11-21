<?php

declare(strict_types=1);

namespace ApiSkeletonsTest\Doctrine\ORM\GraphQL;

use DateTime;
use DateTimeImmutable;
use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

use function date;
use function file_get_contents;
use function print_r;

require_once __DIR__ . '/../vendor/autoload.php';

abstract class AbstractTest extends TestCase
{
    protected EntityManager $entityManager;
    protected DebugStack|null $logger = null;

    public function setUp(): void
    {
        // Create a simple "default" Doctrine ORM configuration for Annotations
        $isDevMode = true;
        $config    = Setup::createAttributeMetadataConfiguration([__DIR__ . '/Entity'], $isDevMode);

        // database configuration parameters
        $conn = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];

        if (! Type::hasType('uuid')) {
            Type::addType('uuid', 'Ramsey\Uuid\Doctrine\UuidType');
        }

        // obtaining the entity manager
        $this->entityManager = EntityManager::create($conn, $config);
        $tool                = new SchemaTool($this->entityManager);
        $res                 = $tool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        $this->populateData();
    }

    public function startLogger(): void
    {
        $this->logger = new DebugStack();

        $this->getEntityManager()->getConnection()
            ->getConfiguration()
            ->setSQLLogger($this->logger);
    }

    public function tearDown(): void
    {
        if ($this->logger) {
            print_r($this->logger->queries);
        }

        parent::tearDown();
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    protected function populateData(): void
    {
        $users = [
            [
                'name' => 'User one',
                'email' => 'userOne@gmail.com',
                'password' => 'asdf',
            ],
            [
                'name' => 'User two',
                'email' => 'userTwo@gmail.com',
                'password' => 'fdsa',
            ],
        ];

        $artists = [
            'Grateful Dead' => [
                '1995-02-21T00:00:00+00:00' => [
                    'venue' => 'Delta Center',
                    'city' => 'Salt Lake City',
                    'state' => 'Utah',
                    'recordings' => [
                        'SBD> D> CD-R> EAC> SHN; via Jay Serafin, Brian '
                          . 'Walker; see info file and pub comments for notes; '
                          . 'possibly "click track" audible on a couple tracks',
                        'DSBD > 1C > DAT; Seeded to etree by Dan Stephens',
                    ],
                ],
                '1969-11-08T00:00:00+00:00' => [
                    'venue' => 'Fillmore Auditorium',
                    'city' => 'San Francisco',
                    'state' => 'California',
                ],
                '1977-05-08T00:00:00+00:00' => [
                    'venue' => 'Barton Hall, Cornell University',
                    'city' => 'Ithaca',
                    'state' => 'New York',
                ],
                '1995-07-09T00:00:00+00:00' => [
                    'venue' => 'Soldier Field',
                    'city' => 'Chicago',
                    'state' => 'Illinois',
                ],
                '1995-08-09T00:00:00+00:00' => [
                    'venue' => null,
                    'city' => null,
                    'state' => null,
                ],
            ],
            'Phish' => [
                '1998-11-02T00:00:00+00:00' => [
                    'venue' => 'E Center',
                    'city' => 'West Valley City',
                    'state' => 'Utah',
                    'recordings' => ['AKG480 > Aerco preamp > SBM-1'],
                ],
                '1999-12-31T00:00:00+00:00' => [
                    'venue' => null,
                    'city' => 'Big Cypress',
                    'state' => 'Florida',
                ],
            ],
            'String Cheese Incident' => [
                '2002-06-21T00:00:00+00:00' => [
                    'venue' => 'Bonnaroo',
                    'city' => 'Manchester',
                    'state' => 'Tennessee',
                ],
            ],
            'The Beatles' => [
                '1964-02-09T00:00:00+00:00' => [
                    'venue' => 'Studio 50',
                    'city' => 'New York City',
                    'state' => 'New York',
                ],
            ],
        ];

        foreach ($users as $userData) {
            $user = new Entity\User();
            $this->entityManager->persist($user);
            $user->setName($userData['name']);
            $user->setEmail($userData['email']);
            $user->setPassword($userData['password']);
        }

        foreach ($artists as $name => $performances) {
            $artist = (new Entity\Artist())
                ->setName($name);
            $this->entityManager->persist($artist);

            foreach ($performances as $performanceDate => $location) {
                $performance = (new Entity\Performance())
                    ->setPerformanceDate(DateTime::createFromFormat('Y-m-d\TH:i:sP', $performanceDate))
                    ->setVenue($location['venue'])
                    ->setCity($location['city'])
                    ->setState($location['state'])
                    ->setArtist($artist);
                $this->entityManager->persist($performance);

                if (! isset($location['recordings'])) {
                    continue;
                }

                foreach ($location['recordings'] as $source) {
                    $recording = (new Entity\Recording())
                        ->setSource($source)
                        ->setPerformance($performance);
                    $this->entityManager->persist($recording);
                }
            }
        }

        $immutableDateTime = new DateTimeImmutable('2022-08-07T20:10:15.123456');

        $typeTest = new Entity\TypeTest();
        $typeTest
            ->setTestBool(true)
            ->setTestDateTime(new DateTime())
            ->setTestFloat(3.14159265)
            ->setTestInt(12345)
            ->setTestText('Now is the time for all good men')
            ->setTestArray(['test', 'doctrine', 'array'])
            ->setTestBigint('1234567890123')
            ->setTestDateTimeImmutable($immutableDateTime)
            ->setTestDate(new DateTime(date('Y-m-d')))
            ->setTestDateImmutable($immutableDateTime)
            ->setTestDateTimeTZ(new DateTime())
            ->setTestDateTimeTZImmutable($immutableDateTime)
            ->setTestDecimal(314.15)
            ->setTestJson(['to' => 'json', ['test' => 'testing']])
            ->setTestSimpleArray(['one', 'two', 'three'])
            ->setTestSmallInt(123)
            ->setTestTime(new DateTime('2022-08-07T20:10:15.123456'))
            ->setTestTimeImmutable($immutableDateTime)
            ->setTestGuid(Uuid::uuid4()->toString())
            ->setTestUuid(Uuid::uuid4())
            ->setTestBlob(file_get_contents(__DIR__ . '/../banner.png'));
        $this->entityManager->persist($typeTest);

        $this->entityManager->flush();
        $this->entityManager->clear();
    }
}
