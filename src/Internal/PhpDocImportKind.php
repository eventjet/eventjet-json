<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

/** @internal */
enum PhpDocImportKind
{
    case ClassName;
    case Constant;
}
