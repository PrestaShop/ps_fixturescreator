<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Creator;

use Customer;
use CustomerMessage;
use CustomerThread;
use Faker\Generator as Faker;
use PrestaShop\PrestaShop\Core\Language\LanguageRepositoryInterface;

class CustomerThreadCreator
{
    private const STATUSES = ['open', 'open', 'open', 'closed', 'pending1', 'pending2'];

    protected LanguageRepositoryInterface $langRepository;
    protected Faker $faker;
    protected EntityPicker $entityPicker;
    protected DateSpreader $dateSpreader;

    public function __construct(
        LanguageRepositoryInterface $langRepository,
        Faker $faker,
        EntityPicker $entityPicker,
        DateSpreader $dateSpreader
    ) {
        $this->langRepository = $langRepository;
        $this->faker = $faker;
        $this->entityPicker = $entityPicker;
        $this->dateSpreader = $dateSpreader;
    }

    /**
     * @return array{threads: int, messages: int}
     */
    public function generate(int $threadsNumber, int $shopId, int $messagesPerThread = 0, bool $linkToCustomers = true, int $spreadMonths = 12): array
    {
        $languages = $this->langRepository->findAll();
        $contactIds = $this->entityPicker->getContactIds();
        $productIds = $this->entityPicker->getProductIds($shopId);
        $customerIds = $linkToCustomers ? $this->entityPicker->getCustomerIds() : [];
        $employeeIds = $this->entityPicker->getEmployeeIds();

        $counters = ['threads' => 0, 'messages' => 0];

        for ($i = 0; $i < $threadsNumber; ++$i) {
            $customer = null;
            $customerId = $this->entityPicker->pickOne($customerIds);
            if (null !== $customerId) {
                $customer = new Customer($customerId);
            }

            $thread = new CustomerThread();
            $thread->id_shop = $shopId;
            $thread->id_lang = (int) $languages[array_rand($languages)]->getId();
            $thread->id_contact = $this->entityPicker->pickOne($contactIds) ?? 0;
            $thread->id_customer = $customer ? (int) $customer->id : 0;
            $thread->id_order = 0;
            if ($customer && $this->faker->boolean(50)) {
                $thread->id_order = $this->entityPicker->pickOne($this->entityPicker->getOrderIdsForCustomer((int) $customer->id)) ?? 0;
            }
            $thread->id_product = $this->faker->boolean(40) ? ($this->entityPicker->pickOne($productIds) ?? 0) : 0;
            $thread->email = $customer ? $customer->email : $this->faker->safeEmail;
            $thread->token = substr(md5(uniqid((string) mt_rand(), true)), 0, 12);
            $thread->status = (string) $this->faker->randomElement(self::STATUSES);
            $dateAdd = $this->dateSpreader->randomPastDate($spreadMonths);
            $thread->date_add = $dateAdd;
            $thread->date_upd = $dateAdd;
            $thread->add(false);
            ++$counters['threads'];

            $counters['messages'] += $this->createMessages($thread, $messagesPerThread, $employeeIds, $dateAdd);
        }

        return $counters;
    }

    /**
     * @param int[] $employeeIds
     */
    private function createMessages(CustomerThread $thread, int $number, array $employeeIds, string $threadDate): int
    {
        $date = $threadDate;
        for ($i = 0; $i < $number; ++$i) {
            $fromEmployee = $i % 2 === 1;

            $message = new CustomerMessage();
            $message->id_customer_thread = (int) $thread->id;
            $message->id_employee = $fromEmployee ? ($this->entityPicker->pickOne($employeeIds) ?? 1) : 0;
            $message->message = implode("\n\n", $this->faker->paragraphs($this->faker->numberBetween(1, 3)));
            $message->ip_address = (string) ip2long($this->faker->ipv4);
            $message->user_agent = substr($this->faker->userAgent, 0, 255);
            $message->private = $fromEmployee && $this->faker->boolean(20);
            $message->read = $fromEmployee || $this->faker->boolean(70);
            $message->date_add = $date;
            $message->date_upd = $date;
            $message->add(false);

            $date = $this->dateSpreader->randomDateAfter($date, 48);
        }

        return $number;
    }
}
