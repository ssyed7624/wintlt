<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\GenerateInvoiceStatement::class,
        Commands\MasterDataUpload::class,
        Commands\HoldBookingPaymentMail::class,
        Commands\SchedularQueueRetreive::class,
        Commands\UnpaidInvoiceReminder::class,
        Commands\CurrencyExchangeRate::class,
        Commands\CancelHoldBookings::class,
        Commands\ReScheduleQueue::class,
        Commands\UpdateProfile::class,        
        Commands\IssueTicket::class,
        Commands\Hello::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */

    /*Method  Description
    ->cron(‘* * * * * *’);  Run the task on a custom Cron schedule
    ->everyMinute();    Run the task every minute
    ->everyFiveMinutes();   Run the task every five minutes
    ->everyTenMinutes();    Run the task every ten minutes
    ->everyThirtyMinutes(); Run the task every thirty minutes
    ->hourly(); Run the task every hour
    ->hourlyAt(17); Run the task every hour at 17 mins past the hour
    ->daily();  Run the task every day at midnight
    ->dailyAt(’13:00′); Run the task every day at 13:00
    ->twiceDaily(1, 13);    Run the task daily at 1:00 & 13:00
    ->weekly(); Run the task every week
    ->monthly();    Run the task every month
    ->monthlyOn(4, ’15:00′);    Run the task every month on the 4th at 15:00
    ->quarterly();  Run the task every quarter
    ->yearly(); Run the task every year
    ->timezone(‘America/New_York’); Set the timezone*/

    protected function schedule(Schedule $schedule)
    {
       // $schedule->command('inspire')
        //          ->hourly();

        $schedule->command('GenerateInvoiceStatement:generateInvoice')
                 ->everyFiveMinutes();

        $schedule->command('CancelHoldBookings:cancelHoldBookings')
                 ->everyFiveMinutes();
                 
        $schedule->command('UnpaidInvoiceReminder:unpaidinvoicereminder')
                 ->daily();

        // $schedule->command('MasterDataUpload:masterDataUpload')
        //          ->daily();

        $schedule->command('CurrencyExchangeRate:getExchangeRate')
                 ->hourly();

        $schedule->command('IssueTicket:issueTicket')
                ->everyTenMinutes();

        $schedule->command('SchedularQueueRetreive:queueRetreive')
                 ->everyFiveMinutes();

        $schedule->command('ReScheduleQueue:reScheduleQueue')
                 ->everyFiveMinutes();
        $schedule->command('PaymentHoldBooking:linkGenerator')
                ->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
