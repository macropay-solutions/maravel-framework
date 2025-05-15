<?php

namespace Illuminate\Database\PDO;

use Doctrine\DBAL\Driver\AbstractSQLServerDriver;

class SqlServerDriver extends AbstractSQLServerDriver
{
    /**
     * Create a new database connection.
     *
     * @param  mixed[]  $params
     * @param  string|null  $username
     * @param  string|null  $password
     * @param  mixed[]  $driverOptions
     * @return \Illuminate\Database\PDO\SqlServerConnection
     */
    public function connect(array $params, $username = null, $password = null, array $driverOptions = [])
    {
//        return new SqlServerConnection(
        return \app(SqlServerConnection::class, [
//            new Connection($params['pdo'])
            \app(Connection::class, [$params['pdo']])
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pdo_sqlsrv';
    }
}
