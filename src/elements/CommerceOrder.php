<?php

namespace bymayo\craftorderimporter\elements;

use craft\commerce\elements\Order;

/**
 * Import-safe Commerce Order element.
 *
 * Extends the native Commerce Order to bypass recalculation
 * during Feed Me imports, preserving imported prices and totals.
 */
class CommerceOrder extends Order
{
    public function init(): void
    {
        parent::init();

        // Preserve imported prices — skip all recalculation
        $this->setRecalculationMode(self::RECALCULATION_MODE_NONE);
    }
}
