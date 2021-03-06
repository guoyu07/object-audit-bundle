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

namespace DreamCommerce\Fixtures\ObjectAudit\Entity\Issue;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use DreamCommerce\Component\ObjectAudit\Mapping\Annotation\Auditable;

/**
 * NB! Object property order matters!
 *
 * @Auditable
 * @ORM\Entity
 */
class DuplicateRevisionFailureTestPrimaryOwner extends DuplicateRevisionFailureTestEntity
{
    /**
     * @ORM\OneToMany(targetEntity="DuplicateRevisionFailureTestOwnedElement", mappedBy="primaryOwner",
     *                                                                         cascade={"persist", "remove"},
     *                                                                         fetch="LAZY")
     */
    protected $elements;

    /**
     * @ORM\OneToMany(targetEntity="DuplicateRevisionFailureTestSecondaryOwner", mappedBy="primaryOwner",
     *                                                                           cascade={"persist", "remove"})
     */
    protected $secondaryOwners;

    public function __construct()
    {
        $this->secondaryOwners = new ArrayCollection();
        $this->elements        = new ArrayCollection();
    }

    public function addSecondaryOwner(DuplicateRevisionFailureTestSecondaryOwner $secondaryOwner)
    {
        $secondaryOwner->setPrimaryOwner($this);
        $this->secondaryOwners->add($secondaryOwner);
    }

    public function addElement(DuplicateRevisionFailureTestOwnedElement $element)
    {
        $element->setPrimaryOwner($this);
        $this->elements->add($element);
    }
}
