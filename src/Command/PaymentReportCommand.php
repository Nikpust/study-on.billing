<?php

namespace App\Command;

use App\Enum\CourseTypeEnum;
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
    name: 'payment:report',
    description: 'Отправляет отчёт по оплатам за последний месяц.',
)]
class PaymentReportCommand extends Command
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MailerInterface $mailer,
        private readonly string $mailerFromEmail,
        private readonly string $paymentReportEmail,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $end = new \DateTimeImmutable('tomorrow 00:00:00');
        $start = $end->modify('-1 month');

        $courses = $this->transactionRepository->getPaymentReport($start, $end);

        $totalAmount = 0;

        foreach ($courses as $key => $course) {
            $courses[$key]['courseType'] = CourseTypeEnum::from((int) $course['courseType'])->code();
            $courses[$key]['paymentsCount'] = (int) $course['paymentsCount'];
            $courses[$key]['totalAmount'] = (float) $course['totalAmount'];

            $totalAmount += $course['totalAmount'];
        }

        $message = (new TemplatedEmail())
            ->from($this->mailerFromEmail)
            ->to($this->paymentReportEmail)
            ->subject('Отчёт по оплатам за месяц')
            ->htmlTemplate('emails/payment_report.html.twig')
            ->context([
                'start' => $start,
                'end' => $end->modify('-1 second'),
                'courses' => $courses,
                'totalAmount' => $totalAmount,
            ]);

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $e) {
            $io->error($e->getMessage());
        }


        $io->success('Отчёт успешно отправлен на почту: ' . $this->paymentReportEmail);

        return Command::SUCCESS;
    }
}
