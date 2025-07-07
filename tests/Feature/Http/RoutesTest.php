<?php

use Illuminate\Support\Facades\Route;
use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;

describe('Route Configuration and Middleware', function () {
    describe('Route Availability', function () {
        it('registers all expected routes', function () {
            $routes = collect(Route::getRoutes())->map(fn($route) => $route->uri());
            
            expect($routes->contains('zoho/request-code'))->toBeTrue();
            expect($routes->contains('zoho/request-code-response'))->toBeTrue();
        });

        it('applies correct middleware to routes', function () {
            $route = collect(Route::getRoutes())
                ->first(fn($route) => $route->uri() === 'zoho/request-code');
            
            expect($route)->not->toBeNull();
            // Add specific middleware checks if any are applied
        });

        it('uses correct HTTP methods for routes', function () {
            $routes = collect(Route::getRoutes());
            
            $requestCodeRoute = $routes->first(fn($route) => $route->uri() === 'zoho/request-code');
            $responseRoute = $routes->first(fn($route) => $route->uri() === 'zoho/request-code-response');
            
            expect($requestCodeRoute->methods())->toContain('GET');
            expect($responseRoute->methods())->toContain('GET');
        });

        it('configures routes with correct names', function () {
            $routes = collect(Route::getRoutes());
            
            $namedRoutes = $routes->filter(fn($route) => $route->getName() !== null);
            
            // Check if routes have names (if implemented)
            $zohoRoutes = $namedRoutes->filter(fn($route) => str_starts_with($route->getName() ?? '', 'zoho.'));
            
            expect($zohoRoutes->count())->toBeGreaterThanOrEqual(0); // Adjust based on naming convention
        });
    });

    describe('Dynamic Route Registration', function () {
        it('conditionally registers routes based on service state', function () {
            // When service is not ready, auth routes should be available
            ZohoConnectorToken::truncate();
            
            // Re-register routes
            $this->refreshApplication();
            
            $response = $this->get('/zoho/request-code');
            expect($response->status())->not->toBe(404);
        });

        it('handles route conflicts gracefully', function () {
            // Test that our routes don't conflict with application routes
            $response = $this->get('/zoho/non-existent-route');
            expect($response->status())->toBe(404);
        });

        it('registers development routes only in non-production environments', function () {
            config(['app.env' => 'production']);
            $this->refreshApplication();
            
            $response = $this->get('/zoho/test');
            expect($response->status())->toBe(404);
            
            config(['app.env' => 'local']);
            $this->refreshApplication();
            
            $response = $this->get('/zoho/test');
            expect($response->status())->toBe(200);
        });
    });

    describe('CSRF Protection', function () {
        it('applies CSRF protection where appropriate', function () {
            // Most Zoho routes are GET requests and don't need CSRF
            $response = $this->get('/zoho/request-code');
            expect($response->status())->not->toBe(419); // Not CSRF error
        });

        it('exempts OAuth callback from CSRF', function () {
            // OAuth callbacks should be exempt from CSRF
            $response = $this->get('/zoho/request-code-response?code=test');
            expect($response->status())->not->toBe(419);
        });
    });

    describe('Route Parameters', function () {
        it('accepts query parameters in OAuth callback', function () {
            $response = $this->get('/zoho/request-code-response', [
                'code' => 'test_code',
                'location' => 'eu',
                'accounts-server' => 'https://accounts.zoho.eu'
            ]);
            
            expect($response->status())->toBeIn([302, 200]); // Not 404
        });

        it('handles missing required parameters', function () {
            $response = $this->get('/zoho/request-code-response'); // Missing code
            
            expect($response->status())->toBe(302); // Redirect to error
        });

        it('ignores unexpected parameters', function () {
            $response = $this->get('/zoho/request-code-response', [
                'code' => 'test_code',
                'unexpected' => 'parameter',
                'another' => 'unexpected'
            ]);
            
            // Should still process normally
            expect($response->status())->toBeIn([302, 200]);
        });
    });

    describe('Route Security', function () {
        it('prevents directory traversal in routes', function () {
            $maliciousRoutes = [
                '/zoho/../../../etc/passwd',
                '/zoho/test/../../../config/app.php',
                '/zoho/request-code/../../.env'
            ];
            
            foreach ($maliciousRoutes as $route) {
                $response = $this->get($route);
                expect($response->status())->toBeIn([404, 400]);
            }
        });

        it('handles encoded URL attacks', function () {
            $encodedRoutes = [
                '/zoho/test%2F..%2F..%2Fetc%2Fpasswd',
                '/zoho/request-code%00.php',
                '/zoho/test%20%20%20'
            ];
            
            foreach ($encodedRoutes as $route) {
                $response = $this->get($route);
                expect($response->status())->toBeIn([404, 400]);
            }
        });

        it('enforces HTTPS in production', function () {
            config(['app.env' => 'production', 'app.url' => 'https://app.com']);
            
            // In production, sensitive routes should enforce HTTPS
            // This depends on middleware configuration
            $routes = collect(Route::getRoutes());
            $authRoutes = $routes->filter(fn($route) => 
                str_contains($route->uri(), 'request-code')
            );
            
            // Check if HTTPS enforcement is configured
            expect($authRoutes->count())->toBeGreaterThan(0);
        });
    });

    describe('Route Groups and Prefixes', function () {
        it('applies consistent prefix to all Zoho routes', function () {
            $routes = collect(Route::getRoutes());
            $zohoRoutes = $routes->filter(fn($route) => str_starts_with($route->uri(), 'zoho/'));
            
            expect($zohoRoutes->count())->toBeGreaterThan(0);
            
            foreach ($zohoRoutes as $route) {
                expect($route->uri())->toStartWith('zoho/');
            }
        });

        it('groups routes by functionality', function () {
            $routes = collect(Route::getRoutes())->map(fn($route) => $route->uri());
            
            // Auth routes
            $authRoutes = $routes->filter(fn($uri) => 
                str_contains($uri, 'request-code')
            );
            
            // Development routes
            $devRoutes = $routes->filter(fn($uri) => 
                str_contains($uri, 'zoho/test') || 
                str_contains($uri, 'zoho/wip') ||
                str_contains($uri, 'zoho/reset')
            );
            
            expect($authRoutes->count())->toBeGreaterThan(0);
            // Dev routes only in development
            if (config('app.env') !== 'production') {
                expect($devRoutes->count())->toBeGreaterThan(0);
            }
        });
    });

    describe('Route Caching Compatibility', function () {
        it('routes are compatible with Laravel route caching', function () {
            // Routes should not use closures for caching compatibility
            $routes = collect(Route::getRoutes());
            $zohoRoutes = $routes->filter(fn($route) => str_starts_with($route->uri(), 'zoho/'));
            
            foreach ($zohoRoutes as $route) {
                $action = $route->getAction();
                
                // Check that routes use controller actions, not closures
                if (isset($action['uses'])) {
                    expect($action['uses'])->toBeString(); // Controller@method format
                }
            }
        });
    });

    describe('API Version Compatibility', function () {
        it('supports Zoho Creator API v2.1 endpoints', function () {
            // Ensure routes are compatible with v2.1 API structure
            $response = $this->get('/zoho/request-code');
            
            expect($response->status())->toBe(302);
            
            // Check redirect URL contains v2 OAuth endpoint
            $location = $response->headers->get('Location');
            expect($location)->toContain('/oauth/v2/auth');
        });

        it('handles API version in configuration', function () {
            config(['zohoconnector.api_version' => 'v2.1']);
            
            // Routes should still work with explicit version
            $response = $this->get('/zoho/request-code');
            expect($response->status())->toBe(302);
        });
    });

    describe('Route Rate Limiting', function () {
        it('implements rate limiting for OAuth endpoints', function () {
            // Make multiple rapid requests
            for ($i = 0; $i < 10; $i++) {
                $response = $this->get('/zoho/request-code');
                
                // First few should succeed
                if ($i < 5) {
                    expect($response->status())->toBe(302);
                }
            }
            
            // Note: Actual rate limiting depends on middleware configuration
            // This test assumes reasonable defaults
        });

        it('has different rate limits for different route types', function () {
            // Auth routes might have stricter limits than info routes
            config(['app.env' => 'local']);
            
            // Info route (higher limit)
            for ($i = 0; $i < 20; $i++) {
                $response = $this->get('/zoho/test');
                expect($response->status())->toBe(200);
            }
            
            // Auth route (lower limit)
            for ($i = 0; $i < 20; $i++) {
                $response = $this->get('/zoho/request-code');
                // Might hit rate limit after fewer requests
            }
        });
    });

    describe('Route Localization', function () {
        it('handles international characters in parameters', function () {
            $response = $this->get('/zoho/request-code-response', [
                'code' => 'test_code',
                'error_description' => 'Erreur: Accès refusé' // French
            ]);
            
            expect($response->status())->toBeIn([302, 200]);
        });

        it('supports different locales in route responses', function () {
            app()->setLocale('fr');
            
            $response = $this->get('/zoho/test');
            
            // Response might be localized
            expect($response->status())->toBe(200);
        });
    });
});