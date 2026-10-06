<?php

namespace App\Providers;

use App\Notifications\JobFailedAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Behind Railway/Cloudflare the app sees plain HTTP; generate https:// links when APP_URL is https.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Catch N+1 queries while developing.
        Model::preventLazyLoading(app()->environment('local'));

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
