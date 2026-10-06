<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

/**
 * A host that boots with `reviews.photos.enabled` off. The provider decides what to hang on the
 * configured review model at boot, so only a switch set before boot can show what a host with
 * the feature off actually gets. Media-library stays registered: its table is part of the
 * documented install whatever the switch says.
 */
abstract class PhotosDisabledTestCase extends TestCase
{
    /** @return array<string, mixed> */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'reviews.photos.enabled' => false,
        ]);
    }
}
