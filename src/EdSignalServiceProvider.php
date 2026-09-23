<?php

namespace ExpertDev\EdSignalLaravel;

use ExpertDev\EdSignalLaravel\Console\Commands\EdSignalInstallCommand;
use ExpertDev\EdSignalLaravel\Console\Commands\EdSignalTestCommand;
use ExpertDev\EdSignalLaravel\Contracts\ConsentResolver;
use ExpertDev\EdSignalLaravel\Http\Middleware\TrackEdSignalRequest;
use ExpertDev\EdSignalLaravel\Support\BrowserSnippetRenderer;
use ExpertDev\EdSignalLaravel\Support\CountryResolver;
use ExpertDev\EdSignalLaravel\Support\DataSanitizer;
use ExpertDev\EdSignalLaravel\Support\DefaultConsentResolver;
use ExpertDev\EdSignalLaravel\Support\SdkInjector;
use ExpertDev\EdSignalLaravel\Support\SignedServerEventClient;
use ExpertDev\EdSignalLaravel\Support\VisitorSessionManager;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class EdSignalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ed-signal.php', 'ed-signal');

        $this->app->singleton(ConsentResolver::class, function (): ConsentResolver {
            return new DefaultConsentResolver((array) config('ed-signal.consent', []));
        });

        $this->app->singleton(CountryResolver::class, function (): CountryResolver {
            return new CountryResolver((array) config('ed-signal.country.header_keys', []));
        });

        $this->app->singleton(DataSanitizer::class, DataSanitizer::class);
        $this->app->singleton(VisitorSessionManager::class, VisitorSessionManager::class);
        $this->app->singleton(SdkInjector::class, SdkInjector::class);
        $this->app->singleton(BrowserSnippetRenderer::class, BrowserSnippetRenderer::class);
        $this->app->singleton(SignedServerEventClient::class, SignedServerEventClient::class);

        $this->app->singleton('ed-signal', function ($app): EdSignal {
            return new EdSignal(
                $app->make(ConsentResolver::class),
                $app->make(CountryResolver::class),
                $app->make(DataSanitizer::class),
                $app->make(SignedServerEventClient::class),
            );
        });
    }

    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__ . '/../config/ed-signal.php' => config_path('ed-signal.php'),
        ], 'ed-signal-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                EdSignalInstallCommand::class,
                EdSignalTestCommand::class,
            ]);
        }

        $router->aliasMiddleware('ed-signal', TrackEdSignalRequest::class);

        if (config('ed-signal.tracking.use_middleware', true)) {
            $router->pushMiddlewareToGroup('web', TrackEdSignalRequest::class);
        }

        Blade::directive('edSignalScripts', function (): string {
            return '<?php echo app(' . var_export(BrowserSnippetRenderer::class, true) . ')->render(); ?>';
        });

        if (config('ed-signal.tracking.track_auth_events_server', true)) {
            $this->app['events']->listen(Login::class, function (Login $event): void {
                app('ed-signal')->event('login', [
                    'auth_provider' => 'laravel',
                    'guard' => $event->guard,
                ], [
                    'request' => request(),
                    'session' => (array) request()->attributes->get('ed_signal.session', []),
                    'consent' => (array) request()->attributes->get('ed_signal.consent', []),
                ]);
            });

            $this->app['events']->listen(Registered::class, function (Registered $event): void {
                app('ed-signal')->event('sign_up', [
                    'auth_provider' => 'laravel',
                    'user_type' => get_class($event->user),
                ], [
                    'request' => request(),
                    'session' => (array) request()->attributes->get('ed_signal.session', []),
                    'consent' => (array) request()->attributes->get('ed_signal.consent', []),
                ]);
            });
        }
    }
}
