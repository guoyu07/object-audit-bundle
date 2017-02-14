<?php

/*
 * (c) 2017 DreamCommerce
 *
 * @package DreamCommerce\Component\ObjectAudit
 * @author Michał Korus <michal.korus@dreamcommerce.com>
 * @link https://www.dreamcommerce.com
 *
 * (c) 2011 SimpleThings GmbH
 *
 * @package SimpleThings\EntityAudit
 * @author Benjamin Eberlei <eberlei@simplethings.de>
 * @link http://www.simplethings.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

namespace DreamCommerce\Component\ObjectAudit\Doctrine\ORM\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Tools\Event\GenerateSchemaTableEventArgs;
use Doctrine\ORM\Tools\ToolEvents;
use DreamCommerce\Component\ObjectAudit\Configuration\ORMAuditConfiguration;
use DreamCommerce\Component\ObjectAudit\Manager\ORMAuditManager;
use DreamCommerce\Component\ObjectAudit\Metadata\ObjectAuditMetadataFactory;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ObjectAuditRegistry;
use RuntimeException;

class CreateSchemaSubscriber implements EventSubscriber
{
    /**
     * @var ObjectAuditRegistry
     */
    private $objectAuditRegistry;

    /**
     * @var string
     */
    private $objectManagerName;

    /**
     * @param ObjectAuditRegistry $objectAuditRegistry
     * @param string              $objectManagerName
     */
    public function __construct(ObjectAuditRegistry $objectAuditRegistry, string $objectManagerName)
    {
        $this->objectAuditRegistry = $objectAuditRegistry;
        $this->objectManagerName = $objectManagerName;
    }

    public function getSubscribedEvents()
    {
        return array(
            ToolEvents::postGenerateSchemaTable,
        );
    }

    public function postGenerateSchemaTable(GenerateSchemaTableEventArgs $eventArgs)
    {
        /** @var EntityManagerInterface $objectAuditManager */
        $objectAuditManager = $this->objectAuditRegistry->getByName($this->objectManagerName);
        if ($objectAuditManager === null) {
            throw new \Exception(); // TODO
        }
        if (!($objectAuditManager instanceof ORMAuditManager)) {
            throw new \Exception(); // TODO
        }

        /** @var ORMAuditConfiguration $configuration */
        $configuration = $objectAuditManager->getConfiguration();
        $revisionManager = $objectAuditManager->getRevisionManager();
        $auditPersistManager = $revisionManager->getAuditPersistManager();
        if (!($auditPersistManager instanceof EntityManagerInterface)) {
            throw new \Exception(); // TODO
        }

        $classMetadata = $eventArgs->getClassMetadata();
        if (!$this->isAudited($objectAuditManager->getObjectAuditMetadataFactory(), $classMetadata)) {
            return;
        }

        if (!in_array($classMetadata->inheritanceType, array(ClassMetadataInfo::INHERITANCE_TYPE_NONE, ClassMetadataInfo::INHERITANCE_TYPE_JOINED, ClassMetadataInfo::INHERITANCE_TYPE_SINGLE_TABLE))) {
            throw new RuntimeException(sprintf('Inheritance type "%s" is not yet supported', $classMetadata->inheritanceType));
        }

        $auditTableName = $objectAuditManager->getAuditTableNameForClass($classMetadata->name);
        $revisionClassMetadata = $objectAuditManager->getRevisionManager()->getRevisionMetadata();

        $schemaManager = $auditPersistManager->getConnection()->getSchemaManager();
        if ($schemaManager->tablesExist($auditTableName)) {
            return;
        }

        $entityTable = $eventArgs->getClassTable();

        $auditTable = new Table($auditTableName);
        $revisionType = $configuration->getRevisionTypeFieldType();
        $auditTable->addColumn($configuration->getRevisionTypeFieldName(), $revisionType);

        foreach ($entityTable->getColumns() as $column) {
            /* @var Column $column */
            $auditTable->addColumn($column->getName(), $column->getType()->getName(), array_merge(
                $column->toArray(),
                array(
                    'notnull' => false,
                    'autoincrement' => false,
                )
            ));
        }

        $pkColumns = $entityTable->getPrimaryKey()->getColumns();
        $revPkColumns = array();

        foreach ($revisionClassMetadata->identifier as $revisionIdentifier) {
            $columnName = $revisionClassMetadata->fieldMappings[$revisionIdentifier]['columnName'];
            $columnName = $configuration->getRevisionIdFieldPrefix().$columnName.$configuration->getRevisionIdFieldSuffix();
            $type = $revisionClassMetadata->fieldMappings[$revisionIdentifier]['type'];
            $auditTable->addColumn($columnName, $type);
            $revPkColumns[] = $columnName;
        }

        $pkColumns = array_merge($pkColumns, $revPkColumns);
        $auditTable->setPrimaryKey($pkColumns);
        $auditTable->addIndex($revPkColumns);

        $schemaManager->createTable($auditTable);
    }

    /**
     * @param ObjectAuditMetadataFactory $objectAuditMetadataFactory
     * @param ClassMetadata              $classMetadata
     *
     * @return bool
     */
    private function isAudited(ObjectAuditMetadataFactory $objectAuditMetadataFactory, ClassMetadata $classMetadata): bool
    {
        $className = $classMetadata->name;

        if (in_array(RevisionInterface::class, class_implements($className))) {
            return false;
        }

        if (!$objectAuditMetadataFactory->isClassAudited($className)) {
            $audited = false;
            if ($classMetadata->isInheritanceTypeJoined() && $classMetadata->rootEntityName == $classMetadata->name) {
                foreach ($classMetadata->subClasses as $subClass) {
                    if (in_array(RevisionInterface::class, class_implements($subClass))) {
                        continue;
                    }

                    if ($objectAuditMetadataFactory->isClassAudited($subClass)) {
                        $audited = true;
                    }
                }
            }
            if (!$audited) {
                return false;
            }
        }

        return true;
    }
}