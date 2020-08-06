<?php

declare(strict_types=1);

namespace Voronkovich\SberbankAcquiring;

class OrderBundle
{
    private $customerEmail;
    private $customerPhone;
    private $customerContact;

    public function setCustomerEmail(string $email): void
    {
        $this->customerEmail = $email;
    }

    public function setCustomerPhone(string $phone): void
    {
        $this->customerPhone = $phone;
    }

    public function setCustomerContact(string $contact): void
    {
        $this->customerContact = $contact;
    }

    public function toArray(): array
    {
        $data = [];

        $this->addCustomerDetails($data);
        $this->addCartItems($data);

        return $data;
    }

    private function addCustomerDetails(array &$data): void
    {
        $customerDetails = [];

        if (null !== $this->customerEmail) {
            $customerDetails['email'] = $this->customerEmail;
        }

        if (null !== $this->customerPhone) {
            $customerDetails['phone'] = $this->customerPhone;
        }

        if (null !== $this->customerContact) {
            $customerDetails['contact'] = $this->customerContact;
        }

        $data['customerDetails'] = $customerDetails;
    }

    private function addCartItems(array &$data): void
    {
        $cartItems = [
            'items' => [],
        ];

        $data['cartItems'] = $cartItems;
    }
}
