<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // Shimmer on lazy widget placeholders so the dashboard reads as "loading", not empty.
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn (): string => <<<'HTML'
                <style>
                    .fi-loading-section { position: relative; overflow: hidden; }
                    .fi-loading-section::after {
                        content: ""; position: absolute; inset: 0; transform: translateX(-100%);
                        background: linear-gradient(90deg, transparent, rgba(245, 184, 0, .10), transparent);
                        animation: kb-shimmer 1.2s ease-in-out infinite;
                    }
                    @keyframes kb-shimmer { to { transform: translateX(100%); } }
                    @media (prefers-reduced-motion: reduce) { .fi-loading-section::after { animation: none; } }
                </style>
                HTML)
            ->profile(isSimple: false)
            // Authenticator-app 2FA; required for admins once the demo is switched off.
            ->multiFactorAuthentication(
                AppAuthentication::make()->recoverable()->brandName('Kabelota'),
                isRequired: fn () => ! config('kabelota.demo_mode'),
            )
            ->brandName('Kabelota Admin')
            ->brandLogo(asset('images/brand/kabelota-hitam.webp'))
            ->darkModeBrandLogo(asset('images/brand/kabelota-putih.webp'))
            ->brandLogoHeight('1.8rem')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::Amber,
                'gray' => Color::Zinc,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
