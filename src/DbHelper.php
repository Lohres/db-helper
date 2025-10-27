<?php declare(strict_types=1);

namespace Lohres\DbHelper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use RuntimeException;

/**
 * Class DbHelper
 * @package Lohres\DbHelper
 */
class DbHelper
{
    private Connection $connection {
        get {
            return $this->connection;
        }
        set {
            $this->connection = $value;
        }
    }

    public function __construct()
    {
        $this->checkConfig();
        $this->connection = DriverManager::getConnection(params: [
            "dbname" => LOHRES_DB_NAME,
            "user" => LOHRES_DB_USER,
            "password" => LOHRES_DB_PWD,
            "host" => LOHRES_DB_HOST,
            "driver" => LOHRES_DB_DRIVER
        ]);
    }

    /**
     * @param string $tableName
     * @return bool
     * @throws Exception
     */
    public function checkIfTableExist(string $tableName): bool
    {
        return $this->connection->createSchemaManager()->tablesExist(names: [$tableName]);
    }

    /**
     * @param string $table
     * @param array $where
     * @return int|string
     * @throws Exception
     */
    public function countEntries(string $table, array $where = []): int|string
    {
        $qb = $this->getQueryBuilder()->select(expressions: "t.*")->from(table: $table, alias: "t");
        return $this->setWhereConditions(qb: $qb, where: $where)->executeQuery()->rowCount();
    }

    /**
     * @param string $table
     * @param array $where
     * @return int|string
     * @throws Exception
     */
    public function deleteEntry(string $table, array $where): int|string
    {
        $qb = $this->getQueryBuilder()->delete(table: "$table t");
        return $this->setWhereConditions(qb: $qb, where: $where)->executeStatement();
    }

    /**
     * @param string $table
     * @param array $where
     * @return array
     * @throws Exception
     */
    public function findBy(string $table, array $where = []): array
    {
        $qb = $this->getQueryBuilder()->select(expressions: "t.*")->from(table: $table, alias: "t");
        return $this->setWhereConditions(qb: $qb, where: $where)->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param string $table
     * @param string $column
     * @param array $where
     * @return array|bool
     * @throws Exception
     */
    public function getColumnValueBy(string $table, string $column, array $where = []): array|bool
    {
        $qb = $this->getQueryBuilder()->select(expressions: "t.$column")->from(table: $table, alias: "t");
        return $this->setWhereConditions(qb: $qb, where: $where)->executeQuery()->fetchAssociative();
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder();
    }

    /**
     * @param string $table
     * @param array $input
     * @return int|string
     * @throws Exception
     */
    public function insertEntry(string $table, array $input): int|string
    {
        $count = count(value: $input);
        $keys = [];
        $values = [];
        foreach ($input as $key => $value) {
            $keys[$key] = "?";
            $values[] = $value;
        }
        $qb = $this->getQueryBuilder()->insert(table: $table)->values(values: $keys);
        for ($i = 0; $i < $count; $i++) {
            $qb->setParameter(key: $i, value: $values[$i]);
        }
        return $qb->executeStatement();
    }

    /**
     * @return int|string
     * @throws Exception
     */
    public function getLastInsertedId(): int|string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * @param string $table
     * @param array $columns
     * @param array $where
     * @return int|string
     * @throws Exception
     */
    public function updateEntry(string $table, array $columns, array $where): int|string
    {
        $count = count(value: $columns);
        $values = [];
        $qb = $this->getQueryBuilder()->update(table: "$table t");
        foreach ($columns as $key => $value) {
            $qb->set(key: "t." . $key, value: "?");
            $values[] = $value;
        }
        for ($i = 0; $i < $count; $i++) {
            $qb->setParameter(key: $i, value: $values[$i]);
        }
        return $this->setWhereConditions(qb: $qb, where: $where, count: $count)->executeStatement();
    }

    /**
     * @return void
     */
    private function checkConfig(): void
    {
        if (
            !defined(constant_name: "LOHRES_DB_NAME") ||
            !defined(constant_name: "LOHRES_DB_USER") ||
            !defined(constant_name: "LOHRES_DB_PWD") ||
            !defined(constant_name: "LOHRES_DB_HOST") ||
            !defined(constant_name: "LOHRES_DB_DRIVER")
        ) {
            throw new RuntimeException(message: "config for db invalid!");
        }
    }

    /**
     * @param QueryBuilder $qb
     * @param array $where
     * @param int|null $count
     * @return QueryBuilder
     */
    private function setWhereConditions(QueryBuilder $qb, array $where = [], ?int $count = null): QueryBuilder
    {
        $counter = 0;
        foreach ($where as $expression => $value) {
            $whereKey = $counter;
            $andWhereKey = $counter;
            if (!is_null(value: $count)) {
                $whereKey = $count;
                $andWhereKey = $count + $counter;
            }
            if ($counter === 0) {
                $qb->where(predicate: "t." . $expression)->setParameter(key: $whereKey, value: $value);
            } else {
                $qb->andWhere(predicate: "t." . $expression)->setParameter(key: $andWhereKey, value: $value);
            }
            $counter++;
        }
        return $qb;
    }
}
