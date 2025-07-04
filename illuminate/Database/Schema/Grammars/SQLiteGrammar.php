<?php

namespace Illuminate\Database\Schema\Grammars;

use Doctrine\DBAL\Schema\Index;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use RuntimeException;

class SQLiteGrammar extends Grammar
{
    /**
     * The possible column modifiers.
     *
     * @var string[]
     */
    protected $modifiers = ['Increment', 'Nullable', 'Default', 'VirtualAs', 'StoredAs'];

    /**
     * The columns available as serials.
     *
     * @var string[]
     */
    protected $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * Compile the query to determine if a table exists.
     *
     * @return string
     * @deprecated Will be removed in a future Laravel version.
     *
     */
    public function compileTableExists()
    {
        return "select * from sqlite_master where type = 'table' and name = ?";
    }

    /**
     * Compile the query to determine if the dbstat table is available.
     *
     * @return string
     */
    public function compileDbstatExists()
    {
        return "select exists (select 1 from pragma_compile_options where compile_options = 'ENABLE_DBSTAT_VTAB') " .
            "as enabled";
    }

    /**
     * Compile the query to determine the tables.
     *
     * @param bool $withSize
     * @return string
     */
    public function compileTables($withSize = false)
    {
        return $withSize
            ? 'select m.tbl_name as name, sum(s.pgsize) as size from sqlite_master as m '
            . 'join dbstat as s on s.name = m.name '
            . "where m.type in ('table', 'index') and m.tbl_name not like 'sqlite_%' "
            . 'group by m.tbl_name '
            . 'order by m.tbl_name'
            : "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name";
    }

    /**
     * Compile the query to determine the views.
     *
     * @return string
     */
    public function compileViews()
    {
        return "select name, sql as definition from sqlite_master where type = 'view' order by name";
    }

    /**
     * Compile the SQL needed to retrieve all table names.
     *
     * @return string
     * @deprecated Will be removed in a future Laravel version.
     *
     */
    public function compileGetAllTables()
    {
        return 'select type, name from sqlite_master where type = \'table\' and name not like \'sqlite_%\'';
    }

    /**
     * Compile the SQL needed to retrieve all view names.
     *
     * @return string
     * @deprecated Will be removed in a future Laravel version.
     *
     */
    public function compileGetAllViews()
    {
        return 'select type, name from sqlite_master where type = \'view\'';
    }

    /**
     * Compile the query to determine the list of columns.
     *
     * @param string $table
     * @return string
     * @deprecated Will be removed in a future Laravel version.
     *
     */
    public function compileColumnListing($table)
    {
        return 'pragma table_info(' . $this->wrap(str_replace('.', '__', $table)) . ')';
    }

    /**
     * Compile the query to determine the columns.
     *
     * @param string $table
     * @return string
     */
    public function compileColumns($table)
    {
        return sprintf(
            'select name, type, not "notnull" as "nullable", dflt_value as "default", pk as "primary" '
            . 'from pragma_table_info(%s) order by cid asc',
            $this->quoteString(str_replace('.', '__', $table))
        );
    }

    /**
     * Compile the query to determine the indexes.
     *
     * @param string $table
     * @return string
     */
    public function compileIndexes($table)
    {
        return sprintf(
            'select \'primary\' as name, group_concat(col) as columns, 1 as "unique", 1 as "primary" '
            . 'from (select name as col from pragma_table_info(%s) where pk > 0 order by pk, cid) group by name '
            . 'union select name, group_concat(col) as columns, "unique", origin = \'pk\' as "primary" '
            . 'from (select il.*, ii.name as col from pragma_index_list(%s) il, pragma_index_info(il.name) ii '
            . 'order by il.seq, ii.seqno) '
            . 'group by name, "unique", "primary"',
            $table = $this->quoteString(str_replace('.', '__', $table)),
            $table
        );
    }

    /**
     * Compile the query to determine the foreign keys.
     *
     * @param string $table
     * @return string
     */
    public function compileForeignKeys($table)
    {
        return sprintf(
            'select group_concat("from") as columns, "table" as foreign_table, '
            . 'group_concat("to") as foreign_columns, on_update, on_delete '
            . 'from (select * from pragma_foreign_key_list(%s) order by id desc, seq) '
            . 'group by id, "table", on_update, on_delete',
            $this->quoteString(str_replace('.', '__', $table))
        );
    }

    /**
     * Compile a create table command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            '%s table %s (%s%s%s)',
            $blueprint->temporary ? 'create temporary' : 'create',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint)),
            (string)$this->addForeignKeys($blueprint),
            (string)$this->addPrimaryKeys($blueprint)
        );
    }

    /**
     * Get the foreign key syntax for a table creation statement.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @return string|null
     */
    protected function addForeignKeys(Blueprint $blueprint)
    {
        $foreigns = $this->getCommandsByName($blueprint, 'foreign');

        return collect($foreigns)->reduce(function ($sql, $foreign) {
            // Once we have all the foreign key commands for the table creation statement
            // we'll loop through each of them and add them to the create table SQL we
            // are building, since SQLite needs foreign keys on the tables creation.
            $sql .= $this->getForeignKey($foreign);

            if (!is_null($foreign->onDelete)) {
                $sql .= " on delete {$foreign->onDelete}";
            }

            // If this foreign key specifies the action to be taken on update we will add
            // that to the statement here. We'll append it to this SQL and then return
            // the SQL so we can keep adding any other foreign constraints onto this.
            if (!is_null($foreign->onUpdate)) {
                $sql .= " on update {$foreign->onUpdate}";
            }

            return $sql;
        }, '');
    }

    /**
     * Get the SQL for the foreign key.
     *
     * @param \Illuminate\Support\Fluent $foreign
     * @return string
     */
    protected function getForeignKey($foreign)
    {
        // We need to columnize the columns that the foreign key is being defined for
        // so that it is a properly formatted list. Once we have done this, we can
        // return the foreign key SQL declaration to the calling method for use.
        return sprintf(
            ', foreign key(%s) references %s(%s)',
            $this->columnize($foreign->columns),
            $this->wrapTable($foreign->on),
            $this->columnize((array)$foreign->references)
        );
    }

    /**
     * Get the primary key syntax for a table creation statement.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @return string|null
     */
    protected function addPrimaryKeys(Blueprint $blueprint)
    {
        if (!is_null($primary = $this->getCommandByName($blueprint, 'primary'))) {
            return ", primary key ({$this->columnize($primary->columns)})";
        }
    }

    /**
     * Compile alter table commands for adding columns.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return array
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command)
    {
        $columns = $this->prefixArray('add column', $this->getColumns($blueprint));

        return collect($columns)->reject(function ($column) {
            return preg_match('/as \(.*\) stored/', $column) > 0;
        })->map(function ($column) use ($blueprint) {
            return 'alter table ' . $this->wrapTable($blueprint) . ' ' . $column;
        })->all();
    }

    /**
     * Compile a rename column command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @param \Illuminate\Database\Connection $connection
     * @return array|string
     */
    public function compileRenameColumn(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        return $connection->usingNativeSchemaOperations()
            ? sprintf(
                'alter table %s rename column %s to %s',
                $this->wrapTable($blueprint),
                $this->wrap($command->from),
                $this->wrap($command->to)
            )
            : parent::compileRenameColumn($blueprint, $command, $connection);
    }

    /**
     * Compile a unique key command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'create unique index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a plain index key command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command)
    {
        return sprintf(
            'create index %s on %s (%s)',
            $this->wrap($command->index),
            $this->wrapTable($blueprint),
            $this->columnize($command->columns)
        );
    }

    /**
     * Compile a spatial index key command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return void
     *
     * @throws \RuntimeException
     */
    public function compileSpatialIndex(Blueprint $blueprint, Fluent $command)
    {
        throw new RuntimeException('The database driver in use does not support spatial indexes.');
    }

    /**
     * Compile a foreign key command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return string|null
     */
    public function compileForeign(Blueprint $blueprint, Fluent $command)
    {
        // Handled on table creation...
    }

    /**
     * Compile a drop table command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table ' . $this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
    {
        return 'drop table if exists ' . $this->wrapTable($blueprint);
    }

    /**
     * Compile the SQL needed to drop all tables.
     *
     * @return string
     */
    public function compileDropAllTables()
    {
        return "delete from sqlite_master where type in ('table', 'index', 'trigger')";
    }

    /**
     * Compile the SQL needed to drop all views.
     *
     * @return string
     */
    public function compileDropAllViews()
    {
        return "delete from sqlite_master where type in ('view')";
    }

    /**
     * Compile the SQL needed to rebuild the database.
     *
     * @return string
     */
    public function compileRebuild()
    {
        return 'vacuum';
    }

    /**
     * Compile a drop column command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @param \Illuminate\Database\Connection $connection
     * @return array
     */
    public function compileDropColumn(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        if ($connection->usingNativeSchemaOperations()) {
            $table = $this->wrapTable($blueprint);

            $columns = $this->prefixArray('drop column', $this->wrapArray($command->columns));

            return collect($columns)->map(fn($column) => 'alter table ' . $table . ' ' . $column)->all();
        } else {
            $tableDiff = $this->getDoctrineTableDiff(
                $blueprint,
                $schema = $connection->getDoctrineSchemaManager()
            );

            foreach ($command->columns as $name) {
                $tableDiff->removedColumns[$name] = $connection->getDoctrineColumn(
                    $this->getTablePrefix() . $blueprint->getTable(),
                    $name
                );
            }

            return (array)$schema->getDatabasePlatform()->getAlterTableSQL($tableDiff);
        }
    }

    /**
     * Compile a drop unique key command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command)
    {
        $index = $this->wrap($command->index);

        return "drop index {$index}";
    }

    /**
     * Compile a drop index command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command)
    {
        $index = $this->wrap($command->index);

        return "drop index {$index}";
    }

    /**
     * Compile a drop spatial index command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return void
     *
     * @throws \RuntimeException
     */
    public function compileDropSpatialIndex(Blueprint $blueprint, Fluent $command)
    {
        throw new RuntimeException('The database driver in use does not support spatial indexes.');
    }

    /**
     * Compile a rename table command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @return string
     */
    public function compileRename(Blueprint $blueprint, Fluent $command)
    {
        $from = $this->wrapTable($blueprint);

        return "alter table {$from} rename to " . $this->wrapTable($command->to);
    }

    /**
     * Compile a rename index command.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $command
     * @param \Illuminate\Database\Connection $connection
     * @return array
     *
     * @throws \RuntimeException
     */
    public function compileRenameIndex(Blueprint $blueprint, Fluent $command, Connection $connection)
    {
        $schemaManager = $connection->getDoctrineSchemaManager();

        $indexes = $schemaManager->listTableIndexes($this->getTablePrefix() . $blueprint->getTable());

        $index = Arr::get($indexes, $command->from);

        if (!$index) {
            throw new RuntimeException("Index [{$command->from}] does not exist.");
        }

        $newIndex = new Index(
            $command->to,
            $index->getColumns(),
            $index->isUnique(),
            $index->isPrimary(),
            $index->getFlags(),
            $index->getOptions()
        );

        $platform = $connection->getDoctrineConnection()->getDatabasePlatform();

        return [
            $platform->getDropIndexSQL($command->from, $this->getTablePrefix() . $blueprint->getTable()),
            $platform->getCreateIndexSQL($newIndex, $this->getTablePrefix() . $blueprint->getTable()),
        ];
    }

    /**
     * Compile the command to enable foreign key constraints.
     *
     * @return string
     */
    public function compileEnableForeignKeyConstraints()
    {
        return 'PRAGMA foreign_keys = ON;';
    }

    /**
     * Compile the command to disable foreign key constraints.
     *
     * @return string
     */
    public function compileDisableForeignKeyConstraints()
    {
        return 'PRAGMA foreign_keys = OFF;';
    }

    /**
     * Compile the SQL needed to enable a writable schema.
     *
     * @return string
     */
    public function compileEnableWriteableSchema()
    {
        return 'PRAGMA writable_schema = 1;';
    }

    /**
     * Compile the SQL needed to disable a writable schema.
     *
     * @return string
     */
    public function compileDisableWriteableSchema()
    {
        return 'PRAGMA writable_schema = 0;';
    }

    /**
     * Create the column definition for a char type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeChar(Fluent $column)
    {
        return 'varchar';
    }

    /**
     * Create the column definition for a string type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeString(Fluent $column)
    {
        return 'varchar';
    }

    /**
     * Create the column definition for a tiny text type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeTinyText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a text type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a medium text type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeMediumText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a long text type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeLongText(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for an integer type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeInteger(Fluent $column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for a big integer type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeBigInteger(Fluent $column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for a medium integer type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeMediumInteger(Fluent $column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for a tiny integer type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeTinyInteger(Fluent $column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for a small integer type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeSmallInteger(Fluent $column)
    {
        return 'integer';
    }

    /**
     * Create the column definition for a float type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeFloat(Fluent $column)
    {
        return 'float';
    }

    /**
     * Create the column definition for a double type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeDouble(Fluent $column)
    {
        return 'float';
    }

    /**
     * Create the column definition for a decimal type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeDecimal(Fluent $column)
    {
        return 'numeric';
    }

    /**
     * Create the column definition for a boolean type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeBoolean(Fluent $column)
    {
        return 'tinyint(1)';
    }

    /**
     * Create the column definition for an enumeration type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeEnum(Fluent $column)
    {
        return sprintf(
            'varchar check ("%s" in (%s))',
            $column->name,
            $this->quoteString($column->allowed)
        );
    }

    /**
     * Create the column definition for a json type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeJson(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a jsonb type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeJsonb(Fluent $column)
    {
        return 'text';
    }

    /**
     * Create the column definition for a date type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeDate(Fluent $column)
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeDateTime(Fluent $column)
    {
        return $this->typeTimestamp($column);
    }

    /**
     * Create the column definition for a date-time (with time zone) type.
     *
     * Note: "SQLite does not have a storage class set aside for storing dates and/or times."
     *
     * @link https://www.sqlite.org/datatype3.html
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeDateTimeTz(Fluent $column)
    {
        return $this->typeDateTime($column);
    }

    /**
     * Create the column definition for a time type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeTime(Fluent $column)
    {
        return 'time';
    }

    /**
     * Create the column definition for a time (with time zone) type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeTimeTz(Fluent $column)
    {
        return $this->typeTime($column);
    }

    /**
     * Create the column definition for a timestamp type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeTimestamp(Fluent $column)
    {
        if ($column->useCurrent) {
            $column->default(new Expression('CURRENT_TIMESTAMP'));
        }

        return 'datetime';
    }

    /**
     * Create the column definition for a timestamp (with time zone) type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeTimestampTz(Fluent $column)
    {
        return $this->typeTimestamp($column);
    }

    /**
     * Create the column definition for a year type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeYear(Fluent $column)
    {
        return $this->typeInteger($column);
    }

    /**
     * Create the column definition for a binary type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeBinary(Fluent $column)
    {
        return 'blob';
    }

    /**
     * Create the column definition for a uuid type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeUuid(Fluent $column)
    {
        return 'varchar';
    }

    /**
     * Create the column definition for an IP address type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeIpAddress(Fluent $column)
    {
        return 'varchar';
    }

    /**
     * Create the column definition for a MAC address type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    protected function typeMacAddress(Fluent $column)
    {
        return 'varchar';
    }

    /**
     * Create the column definition for a spatial Geometry type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    public function typeGeometry(Fluent $column)
    {
        return 'geometry';
    }

    /**
     * Create the column definition for a spatial Point type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    public function typePoint(Fluent $column)
    {
        return 'point';
    }

    /**
     * Create the column definition for a spatial LineString type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    public function typeLineString(Fluent $column)
    {
        return 'linestring';
    }

    /**
     * Create the column definition for a spatial Polygon type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    public function typePolygon(Fluent $column)
    {
        return 'polygon';
    }

    /**
     * Create the column definition for a spatial GeometryCollection type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    public function typeGeometryCollection(Fluent $column)
    {
        return 'geometrycollection';
    }

    /**
     * Create the column definition for a spatial MultiPoint type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    public function typeMultiPoint(Fluent $column)
    {
        return 'multipoint';
    }

    /**
     * Create the column definition for a spatial MultiLineString type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    public function typeMultiLineString(Fluent $column)
    {
        return 'multilinestring';
    }

    /**
     * Create the column definition for a spatial MultiPolygon type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return string
     */
    public function typeMultiPolygon(Fluent $column)
    {
        return 'multipolygon';
    }

    /**
     * Create the column definition for a generated, computed column type.
     *
     * @param \Illuminate\Support\Fluent $column
     * @return void
     *
     * @throws \RuntimeException
     */
    protected function typeComputed(Fluent $column)
    {
        throw new RuntimeException('This database driver requires a type, see the virtualAs / storedAs modifiers.');
    }

    /**
     * Get the SQL for a generated virtual column modifier.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $column
     * @return string|null
     */
    protected function modifyVirtualAs(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($virtualAs = $column->virtualAsJson)) {
            if ($this->isJsonSelector($virtualAs)) {
                $virtualAs = $this->wrapJsonSelector($virtualAs);
            }

            return " as ({$virtualAs})";
        }

        if (!is_null($virtualAs = $column->virtualAs)) {
            return " as ({$this->getValue($virtualAs)})";
        }
    }

    /**
     * Get the SQL for a generated stored column modifier.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $column
     * @return string|null
     */
    protected function modifyStoredAs(Blueprint $blueprint, Fluent $column)
    {
        if (!is_null($storedAs = $column->storedAsJson)) {
            if ($this->isJsonSelector($storedAs)) {
                $storedAs = $this->wrapJsonSelector($storedAs);
            }

            return " as ({$storedAs}) stored";
        }

        if (!is_null($storedAs = $column->storedAs)) {
            return " as ({$this->getValue($column->storedAs)}) stored";
        }
    }

    /**
     * Get the SQL for a nullable column modifier.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $column
     * @return string|null
     */
    protected function modifyNullable(Blueprint $blueprint, Fluent $column)
    {
        if (
            is_null($column->virtualAs) &&
            is_null($column->virtualAsJson) &&
            is_null($column->storedAs) &&
            is_null($column->storedAsJson)
        ) {
            return $column->nullable ? '' : ' not null';
        }

        if ($column->nullable === false) {
            return ' not null';
        }
    }

    /**
     * Get the SQL for a default column modifier.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $column
     * @return string|null
     */
    protected function modifyDefault(Blueprint $blueprint, Fluent $column)
    {
        if (
            !is_null($column->default) && is_null($column->virtualAs) && is_null($column->virtualAsJson) && is_null(
                $column->storedAs
            )
        ) {
            return ' default ' . $this->getDefaultValue($column->default);
        }
    }

    /**
     * Get the SQL for an auto-increment column modifier.
     *
     * @param \Illuminate\Database\Schema\Blueprint $blueprint
     * @param \Illuminate\Support\Fluent $column
     * @return string|null
     */
    protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
    {
        if (in_array($column->type, $this->serials) && $column->autoIncrement) {
            return ' primary key autoincrement';
        }
    }

    /**
     * Wrap the given JSON selector.
     *
     * @param string $value
     * @return string
     */
    protected function wrapJsonSelector($value)
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return 'json_extract(' . $field . $path . ')';
    }
}
