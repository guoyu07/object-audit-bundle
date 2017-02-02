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

namespace DreamCommerce\Bundle\ObjectAuditBundle;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ObjectManager;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectDeletedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Exception\ObjectNotFoundException;
use DreamCommerce\Component\ObjectAudit\Exception\ResourceDeletedException;
use DreamCommerce\Component\ObjectAudit\Exception\ResourceNotAuditedException;
use DreamCommerce\Component\ObjectAudit\Exception\ResourceNotFoundException;
use DreamCommerce\Component\ObjectAudit\Model\ChangedResource;
use DreamCommerce\Component\ObjectAudit\Model\RevisionInterface;
use DreamCommerce\Component\ObjectAudit\ObjectAuditManagerInterface;
use DreamCommerce\Component\ObjectAudit\ResourceAuditConfiguration;
use DreamCommerce\Component\ObjectAudit\ResourceAuditManagerInterface;
use Sylius\Component\Resource\Metadata\RegistryInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ResourceAuditManager implements ResourceAuditManagerInterface
{
    /**
     * @var ObjectAuditManagerInterface
     */
    protected $objectAuditManager;

    /**
     * @var ResourceAuditConfiguration
     */
    protected $configuration;

    /**
     * @var RegistryInterface
     */
    protected $resourceRegistry;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @param ObjectAuditManagerInterface $objectAuditManager
     * @param ResourceAuditConfiguration  $configuration
     * @param RegistryInterface           $resourceRegistry
     * @param ContainerInterface          $container
     */
    public function __construct(ObjectAuditManagerInterface $objectAuditManager,
                                ResourceAuditConfiguration $configuration,
                                RegistryInterface $resourceRegistry,
                                ContainerInterface $container
    ) {
        $this->objectAuditManager = $objectAuditManager;
        $this->configuration = $configuration;
        $this->resourceRegistry = $resourceRegistry;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function findResourceByRevision(string $resourceName, int $resourceId, RevisionInterface $revision, array $options = array())
    {
        if (!$this->configuration->isResourceAudited($resourceName)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        try {
            $object = $this->objectAuditManager->findObjectByRevision($className, $resourceId, $revision, $objectManager, $options);
        } catch (ObjectDeletedException $exception) {
            throw ResourceDeletedException::forObjectDeletedException($exception, $resourceName);
        } catch (ObjectNotFoundException $exception) {
            throw ResourceNotFoundException::forObjectNotFoundException($exception, $resourceName);
        }

        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function findResourcesByFieldsAndRevision(string $resourceName, array $fields, RevisionInterface $revision, array $options = array()): array
    {
        if (!$this->configuration->isResourceAudited($resourceName)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        return $this->objectAuditManager->findObjectsByFieldsAndRevision($className, $fields, $revision, $objectManager, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function findAllResourcesChangedAtRevision(RevisionInterface $revision, array $options = array()): array
    {
        $result = array();
        foreach ($this->configuration->getAuditedResources() as $auditedResource) {
            $result = array_merge(
                $result,
                $this->findResourcesChangedAtRevision($auditedResource, $revision, $options)
            );
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function findResourcesChangedAtRevision(string $resourceName, RevisionInterface $revision, array $options = array()): array
    {
        if (!$this->configuration->isResourceAudited($resourceName)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        try {
            $rows = $this->objectAuditManager->findObjectsChangedAtRevision($className, $revision, $objectManager, $options);
        } catch (ObjectNotAuditedException $exception) {
            throw ResourceNotAuditedException::forResource($resourceName, $className);
        }

        foreach ($rows as $k => $row) {
            /** @var ResourceInterface $object */
            $object = $row->getObject();

            $rows[$k] = new ChangedResource(
                $object,
                $className,
                $object->getId(),
                $resourceName,
                $row->getRevision(),
                $objectManager,
                $row->getRevisionData(),
                $row->getRevisionType()
            );
        }

        return $rows;
    }

    /**
     * {@inheritdoc}
     */
    public function findResourceRevisions(string $resourceName, int $resourceId): Collection
    {
        if (!$this->configuration->isResourceAudited($resourceName)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        try {
            $revisions = $this->objectAuditManager->findObjectRevisions($className, $resourceId, $objectManager);
        } catch (ObjectNotAuditedException $exception) {
            throw ResourceNotAuditedException::forResource($resourceName, $className);
        }

        return $revisions;
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceHistory(string $resourceName, int $resourceId, array $options = array()): array
    {
        if (!$this->configuration->isResourceAudited($resourceName)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        try {
            $revisions = $this->objectAuditManager->getObjectHistory($className, $resourceId, $objectManager);
        } catch (ObjectNotAuditedException $exception) {
            throw ResourceNotAuditedException::forResource($resourceName, $className);
        } catch (ObjectNotFoundException $exception) {
            throw ResourceNotFoundException::forObjectNotFoundException($exception, $resourceName);
        }

        return $revisions;
    }

    /**
     * {@inheritdoc}
     */
    public function getInitializeResourceRevision(string $resourceName, int $resourceId)
    {
        if (!$this->configuration->isResourceAudited($resourceName)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        try {
            $revision = $this->objectAuditManager->getInitializeObjectRevision($className, $resourceId, $objectManager);
        } catch (ObjectNotFoundException $exception) {
            throw ResourceNotFoundException::forObjectNotFoundException($exception, $resourceName);
        } catch (ObjectNotAuditedException $exception) {
            throw ResourceNotAuditedException::forResource($resourceName, $className);
        }

        return $revision;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentResourceRevision(string $resourceName, int $resourceId)
    {
        if (!$this->configuration->isResourceAudited($resourceName)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        try {
            $revision = $this->objectAuditManager->getCurrentObjectRevision($className, $resourceId, $objectManager);
        } catch (ObjectNotFoundException $exception) {
            throw ResourceNotFoundException::forObjectNotFoundException($exception, $resourceName);
        } catch (ObjectNotAuditedException $exception) {
            throw ResourceNotAuditedException::forResource($resourceName, $className);
        }

        return $revision;
    }

    /**
     * {@inheritdoc}
     */
    public function saveResourceRevisionData(ChangedResource $changedResource)
    {
        return $this->objectAuditManager->saveObjectRevisionData($changedResource);
    }

    /**
     * {@inheritdoc}
     */
    public function diffResourceRevisions(string $resourceName, int $resourceId, RevisionInterface $oldRevision, RevisionInterface $newRevision): array
    {
        if (!$this->configuration->isResourceAudited($resourceName)) {
            throw ResourceNotAuditedException::forResource($resourceName);
        }

        $className = $this->getResourceModelClass($resourceName);
        $objectManager = $this->getResourceObjectManager($resourceName);

        return $this->objectAuditManager->diffObjectRevisions($className, $resourceId, $oldRevision, $newRevision, $objectManager);
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceValues(ResourceInterface $resource): array
    {
        $className = get_class($resource);
        $serviceId = $this->resourceRegistry->getByClass($className)->getServiceId('manager');

        return $this->objectAuditManager->getObjectValues($resource, $this->container->get($serviceId));
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectAuditManager(): ObjectAuditManagerInterface
    {
        return $this->objectAuditManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration(): ResourceAuditConfiguration
    {
        return $this->configuration;
    }

    /**
     * @param string $resourceName
     *
     * @return string
     */
    private function getResourceModelClass(string $resourceName): string
    {
        return $this->resourceRegistry->get($resourceName)->getClass('model');
    }

    /**
     * @param string $resourceName
     *
     * @return ObjectManager
     */
    private function getResourceObjectManager(string $resourceName): ObjectManager
    {
        $serviceId = $this->resourceRegistry->get($resourceName)->getServiceId('manager');
        /** @var ObjectManager $objectManager */
        $objectManager = $this->container->get($serviceId);

        return $objectManager;
    }
}
