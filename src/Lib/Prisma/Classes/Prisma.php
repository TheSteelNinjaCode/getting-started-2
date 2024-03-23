<?php

namespace Lib\Prisma\Classes;

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(\DOCUMENT_PATH);
$dotenv->load();

class Prisma
{
    private $pdo;

    public $user;
    public $userRole;
    public $post;

    public function __construct()
    {
        $this->initializePDO();

        $this->user = new User($this->pdo);
        $this->userRole = new UserRole($this->pdo);
        $this->post = new Post($this->pdo);
    }

    private function initializePDO()
    {
        $databaseUrl = $_ENV['DATABASE_URL'];
        if (!$databaseUrl) {
            throw new \Exception('DATABASE_URL not set in .env file.');
        }
        $pattern = '/:\/\/(.*?):(.*?)@/';
        preg_match($pattern, $databaseUrl, $matches);
        $dbUser = $matches[1] ?? '';
        $dbPassword = $matches[2] ?? '';
        $databaseUrlWithoutCredentials = preg_replace($pattern, '://', $databaseUrl);
        $parsedUrl = parse_url($databaseUrlWithoutCredentials);
        $dbProvider = $parsedUrl['scheme'] ?? '';
        $dbName = isset($parsedUrl['path']) ? substr($parsedUrl['path'], 1) : '';
        $dbHost = $parsedUrl['host'] ?? '';
        $dbPort = $parsedUrl['port'] ?? ($dbProvider === 'mysql' ? 3306 : 5432);
        if ($dbProvider === 'mysql') {
            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8";
        } elseif ($dbProvider === 'postgresql') {
            $dsn = "pgsql:host=$dbHost;port=$dbPort;dbname=$dbName";
        } else {
            throw new \Exception("Unsupported database provider: $dbProvider");
        }
        try {
            $this->pdo = new ExtendedPDO($dsn, $dbUser, $dbPassword);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            throw new \Exception("Connection error: " . $e->getMessage());
        }
    }

    /**
     * Executes a raw SQL command that does not return a result set.
     * 
     * This method is suitable for SQL statements like INSERT, UPDATE, DELETE.
     * It returns the number of rows affected by the SQL command.
     *
     * @param string $sql The raw SQL command to be executed.
     * @return int The number of rows affected.
     * @throws \Exception Throws an exception if the database operation fails.
     */
    public function executeRaw(string $sql): int
    {
        try {
            $affectedRows = $this->pdo->exec($sql);
            return $affectedRows;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Executes a raw SQL query and returns the result set.
     * 
     * This method is suitable for SELECT queries or when expecting a return value.
     * It returns an array containing all of the result set rows.
     *
     * @param string $sql The raw SQL query to be executed.
     * @return array The result set as an array.
     * @throws \Exception Throws an exception if the database operation fails.
     */
    public function queryRaw(string $sql): array
    {
        try {
            $stmt = $this->pdo->query($sql);
            if ($stmt === false) {
                throw new \Exception("Failed to execute query: $sql");
            }
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Executes a set of operations within a database transaction.
     *
     * This method accepts an array of callable functions, each representing a database operation.
     * These operations are executed within a single database transaction. If any operation fails,
     * the entire transaction is rolled back. If all operations succeed, the transaction is committed.
     *
     * @param callable[] $operations An array of callable functions for transactional execution.
     * @return void
     * @throws \Exception Throws an exception if the transaction fails.
     *
     * Example Usage:
     * $prisma = new Prisma();
     * $prisma->transaction([
     *     function() use ($prisma) { $prisma->UserModel->create(['name' => 'John Doe']); },
     *     function() use ($prisma) { $prisma->OrderModel->create(['userId' => 1, 'product' => 'Book']); }
     * ]);
     */
    public function transaction(array $operations): void
    {
        try {
            $this->pdo->beginTransaction();
            foreach ($operations as $operation) {
                call_user_func($operation);
            }
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

class ExtendedPDO extends \PDO
{
    private $transactionDepth = 0;
    private $rollbackRequested = false;

    public function beginTransaction(): bool
    {
        if ($this->transactionDepth == 0) {
            parent::beginTransaction();
        }
        $this->transactionDepth++;
        return true; // Transaction started or depth incremented
    }

    public function commit(): bool
    {
        if ($this->transactionDepth <= 1) {
            if ($this->rollbackRequested) {
                parent::rollBack();
            } else {
                parent::commit();
            }
            $this->resetTransactionState();
        } else {
            $this->transactionDepth--;
            if ($this->rollbackRequested && $this->transactionDepth == 1) {
                // If a rollback was requested at any depth, ensure it's executed at the outermost level
                parent::rollBack();
                $this->resetTransactionState();
            }
        }
        return true; // Transaction committed or depth decremented
    }

    public function rollBack(): bool
    {
        if ($this->transactionDepth <= 1) {
            parent::rollBack();
            $this->resetTransactionState();
        } else {
            // Mark that a rollback was requested at some level of transaction depth
            $this->rollbackRequested = true;
            $this->transactionDepth--;
        }
        return true; // Transaction rolled back or depth adjusted
    }

    private function resetTransactionState()
    {
        $this->transactionDepth = 0;
        $this->rollbackRequested = false;
    }

    public function isTransactionActive(): bool
    {
        return $this->transactionDepth > 0;
    }
}