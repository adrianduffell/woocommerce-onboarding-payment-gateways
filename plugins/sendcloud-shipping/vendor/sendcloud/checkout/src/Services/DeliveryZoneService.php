<?php

namespace SendCloud\Checkout\Services;

use SendCloud\Checkout\Domain\Delivery\DeliveryZone;
use SendCloud\Checkout\Domain\Search\Query;
use SendCloud\Checkout\Contracts\Services\DeliveryZoneService as BaseService;
use SendCloud\Checkout\Contracts\Storage\CheckoutStorage;
use SendCloud\Checkout\Utility\ArrayToHashMap;

/**
 * Class DeliveryZoneService
 *
 * @package SendCloud\Checkout\Services
 */
class DeliveryZoneService implements BaseService
{
    /**
     * @var CheckoutStorage
     */
    protected $storage;

    /**
     * DeliveryZoneService constructor.
     * @param CheckoutStorage $storage
     */
    public function __construct(CheckoutStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Finds difference between new and existing delivery zones.
     *
     * @param DeliveryZone[] $newDeliveryZones
     *
     * @return array Returns the array with identified changes:
     *      [
     *          'new' => DeliveryZone[], // List of new zones that are not yet created in the system.
     *          'changed' => DeliveryZone[], // List of existing zones that have been changed.
     *          'deleted' => DeliveryZone[], // List of existing zones that were not present in the provided list.
     *      ]
     */
    public function findDiff(array $newDeliveryZones)
    {
        $newHashMap = ArrayToHashMap::convert($newDeliveryZones);
        $existingHashMap = ArrayToHashMap::convert($this->storage->findAllZoneConfigs());

        $new = array();
        $changed = array();
        $deleted = array();

        // Identify created and updated zones.
        foreach ($newHashMap as $zone) {
            if (!empty($existingHashMap[$zone->getId()])) {
                $existingZone = $existingHashMap[$zone->getId()];
                if ($zone->canBeUpdated($existingZone)) {
                    $zone->setSystemId($existingZone->getSystemId());
                    $changed[] = $zone;
                }
            } else {
                $new[] = $zone;
            }
        }

        // Identify deleted zones.
        foreach ($existingHashMap as $existing) {
            if (empty($newHashMap[$existing->getId()])) {
                $deleted[] = $existing;
            }
        }

        return array(
            'new' => $new,
            'changed' => $changed,
            'deleted' => $deleted,
        );
    }

    /**
     * Deletes specified zones.
     *
     * @param DeliveryZone[] $zones
     *
     * @return void
     */
    public function deleteSpecific(array $zones)
    {
        $ids = array_map(function (DeliveryZone $zone) {return $zone->getId();}, $zones);
        $this->storage->deleteSpecificZoneConfigs($ids);
    }

    /**
     * Deletes all saved zone configurations.
     *
     * @return void
     */
    public function deleteAll()
    {
        $this->storage->deleteAllZoneConfigs();
    }

    /**
     * Updates delivery zones.
     *
     * @param DeliveryZone[] $zones
     *
     * @return void
     */
    public function update(array $zones)
    {
        $this->storage->updateZoneConfigs($zones);
    }

    /**
     * Creates delivery zones.
     *
     * @param DeliveryZone[] $zones
     *
     * @return void
     */
    public function create(array $zones)
    {
        $this->storage->createZoneConfigs($zones);
    }

    /**
     * Provides zones matching given query with specified ids.
     *
     * @param Query $query
     * @return DeliveryZone[]
     */
    public function search(Query $query)
    {
        $countryFilter = null;
        $zipFilter = null;

        $result = $this->storage->findAllZoneConfigs();
        if ($query->getCountry()) {
            $result = array_filter($result, function (DeliveryZone $zone) use ($query) {
                return $zone->getCountry()->getIsoCode() === $query->getCountry();
            });
        }

        return $result;
    }

    /**
     * Delete delivery zone configs for delivery zones that no longer exist in system.
     *
     * @return void
     */
    public function deleteObsoleteConfigs()
    {
        $this->storage->deleteObsoleteZoneConfigs();
    }
}