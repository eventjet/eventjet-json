<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\NorthStar\Kubernetes;

use Eventjet\Json\Test\Acceptance\NorthStar\HydrationCheck;

/** @internal */
final class Hydration
{
    public static function isComplete(object $value): bool
    {
        if (!$value instanceof Deployment) {
            return false;
        }

        $spec = $value->spec;
        $template = $spec->template;

        if (!$template instanceof PodTemplateSpec) {
            return false;
        }

        $podSpec = $template->spec;
        $firstContainer = $podSpec->containers[0] ?? null;

        return (
            $spec->selector instanceof LabelSelector
            && $spec->strategy instanceof DeploymentStrategy
            && $spec->strategy->rollingUpdate instanceof RollingUpdate
            && $firstContainer instanceof Container
            && HydrationCheck::allInstancesAre($podSpec->containers, Container::class)
            && HydrationCheck::allInstancesAre($firstContainer->env, EnvVar::class)
            && HydrationCheck::allInstancesAre($podSpec->volumes, Volume::class)
            && HydrationCheck::allInstancesAre($value->status->conditions, DeploymentCondition::class)
        );
    }
}
