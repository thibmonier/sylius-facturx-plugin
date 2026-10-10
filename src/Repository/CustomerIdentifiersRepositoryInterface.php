<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Repository;

use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiersInterface;

/**
 * @extends RepositoryInterface<CustomerIdentifiersInterface>
 */
interface CustomerIdentifiersRepositoryInterface extends RepositoryInterface
{
    public function findOneByCustomer(CustomerInterface $customer): ?CustomerIdentifiersInterface;
}
