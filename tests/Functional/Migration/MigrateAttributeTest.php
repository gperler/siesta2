<?php

namespace SiestaTest\Functional\Migration;

use Codeception\Test\Unit;
use Codeception\Util\Debug;
use Siesta\Migration\DatabaseMigrator;
use Siesta\Util\File;
use SiestaTest\TestDatabase\TestConnection;
use SiestaTest\TestDatabase\TestCreateStatementFactory;
use SiestaTest\TestUtil\DataModelHelper;

/**
 * @author Gregor Müller
 */
class MigrateAttributeTest extends Unit
{

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function testDatabase()
    {

        $testConnection = new TestConnection();
        $testConnection->setFixtureFile(new File(__DIR__ . "/schema/migrate.attribute.test.schema.json"));

        $dmh = new DataModelHelper();
        $dataModel = $dmh->readModel(__DIR__ . "/schema/migrate.attribute.test.schema.xml");

        $migrator = new DatabaseMigrator($dataModel, $testConnection);
        $migrator->createAlterStatementList(true);

        $alterStatementList = $migrator->getAlterStatementList();

        Debug::debug($alterStatementList);

        $this->assertSame(6, sizeof($alterStatementList));
        $this->assertSame(TestCreateStatementFactory::SEQUENCER_TABLE_CREATE, $alterStatementList[0]);
        $this->assertSame("table 'migrateAttribute' add column 'addColumn'", $alterStatementList[1]);
        $this->assertSame("table 'migrateAttribute' modifiy column 'changeType'", $alterStatementList[2]);
        $this->assertSame("table 'migrateAttribute' modifiy column 'changeRequired'", $alterStatementList[3]);
        $this->assertSame("table 'migrateAttribute' modifiy column 'defaultValueChange'", $alterStatementList[4]);
        $this->assertSame("table 'migrateAttribute' drop colum 'toDelete'", $alterStatementList[5]);
    }

}
