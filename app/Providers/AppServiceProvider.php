<?php

namespace App\Providers;

use App\Notifications\JobFailedAlert;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Brevo over HTTPS: Railway blocks outbound SMTP below the Pro plan.
        Mail::extend('brevo', fn () => (new BrevoTransportFactory)->create(
            new Dsn('brevo+api', 'default', config('services.brevo.key'))
        ));

        // Email the ops address when a job fails, at most once per job type per hour.
        Queue::failing(function (JobFailed $event) {
            $job = class_basename($event->job->resolveName());

            if (! app()->runningUnitTests() && Cache::add('job-failed-alert:'.$job, true, now()->addHour())) {
                rescue(fn () => Notification::route('mail', config('kabelota.ops_email'))
                    ->notifyNow(new JobFailedAlert($job, Str::limit($event->exception->getMessage(), 300))));
            }
        });
    }
}
