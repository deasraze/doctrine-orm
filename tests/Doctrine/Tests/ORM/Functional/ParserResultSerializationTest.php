<?php

declare(strict_types=1);

namespace Doctrine\Tests\ORM\Functional;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Exec\SingleSelectExecutor;
use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\Tests\OrmFunctionalTestCase;
use Generator;
use ReflectionMethod;
use ReflectionProperty;

use function file_get_contents;
use function rtrim;
use function serialize;
use function unserialize;

class ParserResultSerializationTest extends OrmFunctionalTestCase
{
    protected function setUp(): void
    {
        $this->useModelSet('company');

        parent::setUp();
    }

    public function testSerializeParserResult(): void
    {
        $query = $this->_em
            ->createQuery('SELECT u FROM Doctrine\Tests\Models\Company\CompanyEmployee u WHERE u.name = :name');

        $parserResult = self::parseQuery($query);
        $serialized   = serialize($parserResult);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(ParserResult::class, $unserialized);
        $this->assertInstanceOf(ResultSetMapping::class, $unserialized->getResultSetMapping());
        $this->assertEquals(['name' => [0]], $unserialized->getParameterMappings());
        $this->assertInstanceOf(SingleSelectExecutor::class, $unserialized->getSqlExecutor());
    }

    public function testItSerializesParserResultWithAForwardCompatibleFormat(): void
    {
        $query = $this->_em
            ->createQuery('SELECT u FROM Doctrine\Tests\Models\Company\CompanyEmployee u WHERE u.name = :name');

        $parserResult = self::parseQuery($query);
        $serialized   = serialize($parserResult);
        $this->assertStringNotContainsString(
            '_sqlStatements',
            $serialized,
            'ParserResult should not contain any reference to _sqlStatements, which is a legacy property.'
        );
        $unserialized = unserialize($serialized);

        $r = new ReflectionProperty($unserialized->getSqlExecutor(), '_sqlStatements');
        $r->setAccessible(true);

        $this->assertSame(
            $r->getValue($unserialized->getSqlExecutor()),
            $unserialized->getSqlExecutor()->getSqlStatements(),
            'The legacy property should be populated with the same value as the new one.'
        );
    }

    /**
     * @dataProvider provideSerializedSingleSelectResults
     */
    public function testUnserializeSingleSelectResult(string $serialized): void
    {
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(ParserResult::class, $unserialized);
        $this->assertInstanceOf(ResultSetMapping::class, $unserialized->getResultSetMapping());
        $this->assertEquals(['name' => [0]], $unserialized->getParameterMappings());
        $this->assertInstanceOf(SingleSelectExecutor::class, $unserialized->getSqlExecutor());
    }

    /** @return Generator<string, array{string}> */
    public static function provideSerializedSingleSelectResults(): Generator
    {
        yield '2.14.3' => [rtrim(file_get_contents(__DIR__ . '/ParserResults/single_select_2_14_3.txt'), "\n")];
        yield '2.15.0' => [rtrim(file_get_contents(__DIR__ . '/ParserResults/single_select_2_15_0.txt'), "\n")];
        yield '2.17.0' => [rtrim(file_get_contents(__DIR__ . '/ParserResults/single_select_2_17_0.txt'), "\n")];
    }

    private static function parseQuery(Query $query): ParserResult
    {
        $r = new ReflectionMethod($query, 'parse');
        $r->setAccessible(true);

        return $r->invoke($query);
    }
}
