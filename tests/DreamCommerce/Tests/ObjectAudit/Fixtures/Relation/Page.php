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

namespace DreamCommerce\ObjectAudit\Tests\Fixtures\Relation;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class Page
{
    /** @ORM\Id @ORM\Column(type="integer") @ORM\GeneratedValue(strategy="AUTO") */
    private $id;

    /** @ORM\OneToMany(targetEntity="PageLocalization", mappedBy="page", indexBy="locale") */
    private $localizations;


    /**
     * A page can have many aliases
     *
     * @var PageAlias[]
     * @ORM\OneToMany(targetEntity="PageAlias", mappedBy="page", cascade={"persist"})
     */
    protected $pageAliases;


    public function __construct()
    {
        $this->localizations = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getLocalizations()
    {
        return $this->localizations;
    }

    public function addLocalization(PageLocalization $localization)
    {
        $localization->setPage($this);
        $this->localizations->set($localization->getLocale(), $localization);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->id;
    }
}
