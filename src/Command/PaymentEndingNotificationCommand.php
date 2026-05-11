<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

#[AsCommand(
    name: 'payment:ending:notification',
    description: 'Отправляет уведомления пользователям об окончании аренды курсов.',
)]
class PaymentEndingNotificationCommand extends Command
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MailerInterface $mailer,
        private readonly string $mailerFromEmail,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $start = new \DateTimeImmutable('tomorrow 00:00:00');
        $end = new \DateTimeImmutable('tomorrow 23:59:59');

        $transactions = $this->transactionRepository->findRentPaymentsExpiringBetween($start, $end);

        $transactionsByEmail = [];

        foreach ($transactions as $transaction) {
            $email = $transaction->getUserBilling()->getEmail();

            if ($email !== null) {
                $transactionsByEmail[$email][] = $transaction;
            }
        }

        foreach ($transactionsByEmail as $email => $transactions) {
            $message = (new TemplatedEmail())
                ->from($this->mailerFromEmail)
                ->to($email)
                ->subject('Срок аренды курсов подходит к концу')
                ->htmlTemplate('emails/rent_ending_notification.html.twig')
                ->context([
                    'transactions' => $transactions,
                ]);

            try {
                $this->mailer->send($message);
            } catch (TransportExceptionInterface $e) {
                $io->error($e->getMessage());
            }
        }

        $io->success('Отправлено писем: ' . count($transactionsByEmail));

        return Command::SUCCESS;
    }
}
