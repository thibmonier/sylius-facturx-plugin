<?php

declare(strict_types=1);

namespace Tmonier\SyliusFacturXPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Customer\Model\CustomerInterface;
use Tmonier\SyliusFacturXPlugin\Entity\CustomerIdentifiersInterface;

class CustomerIdentifiersRepository extends EntityRepository implements CustomerIdentifiersRepositoryInterface
{
    public function findOneByCustomer(CustomerInterface $customer): ?CustomerIdentifiersInterface
    {
        /** @var CustomerIdentifiersInterface|null $identifiers */
        $identifiers = $this->findOneBy(['customer' => $customer]);

        return $identifiers;
    }
}
