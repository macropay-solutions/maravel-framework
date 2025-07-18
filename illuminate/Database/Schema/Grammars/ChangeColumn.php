<?php

namespace Illuminate\Database\Schema\Grammars;

use Doctrine\DBAL\Schema\AbstractSchemaManager as SchemaManager;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;
use RuntimeException;

class ChangeColumn
{
    /**
     * Compile a change column command into a series of SQL statements.
     *
     * @param \Illuminate\Database\Schema\Grammars\Grammar $grammar
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @param \Illuminate\Database\Connection $connection
     * @return array
     *
     * @throws \RuntimeException
     */
    public static function compile($grammar, Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        if (!$connection->isDoctrineAvailable()) {
            throw new RuntimeException(
                sprintf(
                    'Changing columns for table "%s" requires Doctrine DBAL. Please install the doctrine/dbal package.',
                    $blueprint->getTable()
                )
            );
        }

        $schema = $connection->getDoctrineSchemaManager();
        $databasePlatform = $connection->getDoctrineConnection()->getDatabasePlatform();
        $databasePlatform->registerDoctrineTypeMapping('enum', 'string');

        $tableDiff = static::getChangedDiff(
            $grammar,
            $blueprint,
            $schema
        );

        if (!$tableDiff->isEmpty()) {
            return (array)$databasePlatform->getAlterTableSQL($tableDiff);
        }

        return [];
    }

    /**
     * Get the Doctrine table difference for the given changes.
     *
     * @param \Illuminate\Database\Schema\Grammars\Grammar $grammar
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Doctrine\DBAL\Schema\AbstractSchemaManager $schema
     * @return \Doctrine\DBAL\Schema\TableDiff
     */
    protected static function getChangedDiff($grammar, Blueprint $blueprint, SchemaManager $schema)
    {
        $current = $schema->introspectTable($grammar->getTablePrefix() . $blueprint->getTable());

        return $schema->createComparator()->compareTables(
            $current,
            static::getTableWithColumnChanges($blueprint, $current)
        );
    }

    /**
     * Get a copy of the given Doctrine table after making the column changes.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Doctrine\DBAL\Schema\Table $table
     * @return \Doctrine\DBAL\Schema\Table
     */
    protected static function getTableWithColumnChanges(Blueprint $blueprint, Table $table)
    {
        $table = clone $table;

        foreach ($blueprint->getChangedColumns() as $fluent) {
            $column = static::getDoctrineColumn($table, $fluent);

            // Here we will spin through each fluent column definition and map it to the proper
            // Doctrine column definitions - which is necessary because Laravel and Doctrine
            // use some different terminology for various column attributes on the tables.
            foreach ($fluent->getAttributes() as $key => $value) {
                if (!is_null($option = static::mapFluentOptionToDoctrine($key))) {
                    if (method_exists($column, $method = 'set' . ucfirst($option))) {
                        $column->{$method}(static::mapFluentValueToDoctrine($option, $value));
                        continue;
                    }

                    $column->setPlatformOption($option, static::mapFluentValueToDoctrine($option, $value));
                }
            }
        }

        return $table;
    }

    /**
     * Get the Doctrine column instance for a column change.
     *
     * @param \Doctrine\DBAL\Schema\Table $table
     * @param \Illuminate\Support\Fluent $fluent
     * @return \Doctrine\DBAL\Schema\Column
     */
    protected static function getDoctrineColumn(Table $table, Fluent $fluent)
    {
        return $table->modifyColumn(
            $fluent['name'],
            static::getDoctrineColumnChangeOptions($fluent)
        )->getColumn($fluent['name']);
    }

    /**
     * Get the Doctrine column change options.
     *
     * @param \Illuminate\Support\Fluent $fluent
     * @return array
     */
    protected static function getDoctrineColumnChangeOptions(Fluent $fluent)
    {
        $options = ['type' => static::getDoctrineColumnType($fluent['type'])];

        if (!in_array($fluent['type'], ['smallint', 'integer', 'bigint'])) {
            $options['autoincrement'] = false;
        }

        if (in_array($fluent['type'], ['tinyText', 'text', 'mediumText', 'longText'])) {
            $options['length'] = static::calculateDoctrineTextLength($fluent['type']);
        }

        if ($fluent['type'] === 'char') {
            $options['fixed'] = true;
        }

        if (static::doesntNeedCharacterOptions($fluent['type'])) {
            $options['customSchemaOptions'] = [
                'collation' => '',
                'charset' => '',
            ];
        }

        return $options;
    }

    /**
     * Get the doctrine column type.
     *
     * @param string $type
     * @return \Doctrine\DBAL\Types\Type
     */
    protected static function getDoctrineColumnType($type)
    {
        $type = strtolower($type);

        return Type::getType(
            match ($type) {
                'biginteger' => 'bigint',
                'smallinteger' => 'smallint',
                'tinytext', 'mediumtext', 'longtext' => 'text',
                'binary' => 'blob',
                'uuid' => 'guid',
                'char' => 'string',
                'double' => 'float',
                default => $type,
            }
        );
    }

    /**
     * Calculate the proper column length to force the Doctrine text type.
     *
     * @param string $type
     * @return int
     */
    protected static function calculateDoctrineTextLength($type)
    {
        return match ($type) {
            'tinyText' => 1,
            'mediumText' => 65535 + 1,
            'longText' => 16777215 + 1,
            default => 255 + 1,
        };
    }

    /**
     * Determine if the given type does not need character / collation options.
     *
     * @param string $type
     * @return bool
     */
    protected static function doesntNeedCharacterOptions($type)
    {
        return in_array($type, [
            'bigInteger',
            'binary',
            'boolean',
            'date',
            'dateTime',
            'decimal',
            'double',
            'float',
            'integer',
            'json',
            'mediumInteger',
            'smallInteger',
            'time',
            'timestamp',
            'tinyInteger',
        ]);
    }

    /**
     * Get the matching Doctrine option for a given Fluent attribute name.
     *
     * @param string $attribute
     * @return string|null
     */
    protected static function mapFluentOptionToDoctrine($attribute)
    {
        return match ($attribute) {
            'type', 'name' => null,
            'nullable' => 'notnull',
            'total' => 'precision',
            'places' => 'scale',
            default => $attribute,
        };
    }

    /**
     * Get the matching Doctrine value for a given Fluent attribute.
     *
     * @param string $option
     * @param mixed $value
     * @return mixed
     */
    protected static function mapFluentValueToDoctrine($option, $value)
    {
        return $option === 'notnull' ? !$value : $value;
    }
}
