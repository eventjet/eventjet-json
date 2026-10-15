<?php

declare(strict_types=1);

use Eventjet\Json\Test\Acceptance\Cases\CollectionDeclarationFixture;

require __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(CollectionDeclarationFixture::autoload(...));
