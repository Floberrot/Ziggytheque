<?php

declare(strict_types=1);

namespace App\Tests\Doubles\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use SensitiveParameter;

/** DBAL middleware feeding QueryCounter: lets a test assert there is no N+1. */
final readonly class QueryCountingMiddleware implements Middleware
{
    public function __construct(private QueryCounter $counter)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        $counter = $this->counter;

        return new class ($driver, $counter) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly QueryCounter $counter)
            {
                parent::__construct($driver);
            }

            public function connect(#[SensitiveParameter] array $params): Connection
            {
                return new class (parent::connect($params), $this->counter) extends AbstractConnectionMiddleware {
                    public function __construct(Connection $connection, private readonly QueryCounter $counter)
                    {
                        parent::__construct($connection);
                    }

                    public function prepare(string $sql): Statement
                    {
                        $counter = $this->counter;

                        return new class (parent::prepare($sql), $counter) extends AbstractStatementMiddleware {
                            public function __construct(Statement $statement, private readonly QueryCounter $counter)
                            {
                                parent::__construct($statement);
                            }

                            public function execute(): Result
                            {
                                $this->counter->increment();

                                return parent::execute();
                            }
                        };
                    }

                    public function query(string $sql): Result
                    {
                        $this->counter->increment();

                        return parent::query($sql);
                    }

                    public function exec(string $sql): int|string
                    {
                        $this->counter->increment();

                        return parent::exec($sql);
                    }
                };
            }
        };
    }
}
