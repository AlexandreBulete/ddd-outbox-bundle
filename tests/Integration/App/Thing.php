<?php

declare(strict_types=1);

namespace AlexandreBulete\DddOutboxBundle\Tests\Integration\App;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'outbox_test_thing')]
class Thing
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 50)]
        public string $name,
    ) {}
}
