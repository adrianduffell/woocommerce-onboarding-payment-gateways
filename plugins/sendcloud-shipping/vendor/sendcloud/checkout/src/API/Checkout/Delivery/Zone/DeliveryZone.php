<?php

namespace SendCloud\Checkout\API\Checkout\Delivery\Zone;

use SendCloud\Checkout\API\Checkout\Delivery\Method\DeliveryMethod;
use SendCloud\Checkout\DTO\DataTransferObject;

/**
 * Class DeliveryZone
 *
 * @package SendCloud\Checkout\API\Checkout\Delivery\Zone
 */
class DeliveryZone extends DataTransferObject
{
    /**
     * @var string
     */
    protected $id;
    /**
     * @var Location
     */
    protected $location;
    /**
     * @var DeliveryMethod[]
     */
    protected $deliveryMethods;

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return Location
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * @param Location $location
     */
    public function setLocation($location)
    {
        $this->location = $location;
    }


    /**
     * @return DeliveryMethod[]
     */
    public function getDeliveryMethods()
    {
        return $this->deliveryMethods;
    }

    /**
     * @param DeliveryMethod[] $deliveryMethods
     */
    public function setDeliveryMethods($deliveryMethods)
    {
        $this->deliveryMethods = $deliveryMethods;
    }

    /**
     * Provides array representation of a dto.
     *
     * @return array Array representation.
     */
    public function toArray()
    {
        return array(
            'id' => $this->getId(),
            'location' => $this->getLocation()->toArray(),
            'delivery_methods' => static::toArrayBatch($this->getDeliveryMethods()),
        );
    }

    /**
     * Instantiates data transfer object from an array.
     *
     * @param array $rawData Raw data used for instantiation.
     *
     * @return DeliveryZone DTO instance.
     */
    public static function fromArray(array $rawData)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::fromArray($rawData);
    }

    /**
     * Factory template method used to instantiate data transfer object from an array of data.
     *
     * @param array $rawData Raw data used for instantiation.
     *
     * @return DeliveryZone
     */
    protected static function instantiate(array $rawData)
    {
        $entity = new static();
        $entity->setId($rawData['id']);
        $entity->setLocation(Location::fromArray($rawData['location']));
        /** @noinspection PhpParamsInspection */
        $entity->setDeliveryMethods(DeliveryMethod::fromArrayBatch(self::getValue($rawData, 'delivery_methods', array())));

        return $entity;
    }
}