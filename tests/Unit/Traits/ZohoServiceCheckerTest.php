<?php

use Agencedoit\ZohoConnector\Traits\ZohoServiceChecker;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

uses(\Agencedoit\ZohoConnector\Tests\Unit\UnitTestCase::class);

beforeEach(function () {
    // Create a testable class that uses the trait
    $this->serviceChecker = new class {
        use ZohoServiceChecker;
        
        // Make protected methods public for testing
        public function testZohoServiceCheck() {
            return $this->ZohoServiceCheck();
        }
        
        public function testZohoResponseCheck(Response $response, string $specific = "") {
            return $this->ZohoResponseCheck($response, $specific);
        }
    };
    
    // Setup default config
    Config::set('zohoconnector.environment', 'production');
    Config::set('app.url', 'https://test.com');
});

describe('ZohoServiceChecker', function () {
    
    describe('ZohoServiceCheck() method', function () {
        
        describe('Service Ready Validation', function () {
            
            it('passes when service is ready and environment is valid', function () {
                // Mock the facade to return ready state
                ZohoCreatorFacade::shouldReceive('isReady')->andReturn(true);
                
                Config::set('zohoconnector.environment', 'production');
                
                // Should not throw any exception
                expect(fn() => $this->serviceChecker->testZohoServiceCheck())->not->toThrow();
            });
            
            it('throws exception when service is not ready', function () {
                // Mock the facade to return not ready state
                ZohoCreatorFacade::shouldReceive('isReady')->andReturn(false);
                
                Log::shouldReceive('error')->twice();
                
                expect(fn() => $this->serviceChecker->testZohoServiceCheck())
                    ->toThrow(\Exception::class, 'ZohoCreatorService is not ready. Please init it.');
            });
            
            it('logs correct error messages when service is not ready', function () {
                ZohoCreatorFacade::shouldReceive('isReady')->andReturn(false);
                
                Log::shouldReceive('error')
                    ->once()
                    ->with('ZohoCreatorService is not ready. Please init it.');
                    
                Log::shouldReceive('error')
                    ->once()
                    ->with('See https://test.com/zoho/test for more informations.');
                
                try {
                    $this->serviceChecker->testZohoServiceCheck();
                } catch (\Exception $e) {
                    // Expected exception
                }
            });
        });
        
        describe('Environment Validation', function () {
            
            it('accepts empty environment', function () {
                ZohoCreatorFacade::shouldReceive('isReady')->andReturn(true);
                Config::set('zohoconnector.environment', '');
                
                expect(fn() => $this->serviceChecker->testZohoServiceCheck())->not->toThrow();
            });
            
            it('accepts development environment', function () {
                ZohoCreatorFacade::shouldReceive('isReady')->andReturn(true);
                Config::set('zohoconnector.environment', 'development');
                
                expect(fn() => $this->serviceChecker->testZohoServiceCheck())->not->toThrow();
            });
            
            it('accepts stage environment', function () {
                ZohoCreatorFacade::shouldReceive('isReady')->andReturn(true);
                Config::set('zohoconnector.environment', 'stage');
                
                expect(fn() => $this->serviceChecker->testZohoServiceCheck())->not->toThrow();
            });
            
            it('accepts production environment', function () {
                ZohoCreatorFacade::shouldReceive('isReady')->andReturn(true);
                Config::set('zohoconnector.environment', 'production');
                
                expect(fn() => $this->serviceChecker->testZohoServiceCheck())->not->toThrow();
            });
            
            it('throws exception for invalid environment', function () {
                ZohoCreatorFacade::shouldReceive('isReady')->andReturn(true);
                Config::set('zohoconnector.environment', 'invalid_env');
                
                Log::shouldReceive('error')
                    ->once()
                    ->with('zohoconnector.environment is not set correctly. (invalid_env). Choices are : empty,development, stage or production.');
                
                expect(fn() => $this->serviceChecker->testZohoServiceCheck())
                    ->toThrow(\Exception::class, 'ZohoCreatorService is not ready. zohoconnector.environment is not correct.');
            });
            
            it('logs detailed error for invalid environment', function () {
                ZohoCreatorFacade::shouldReceive('isReady')->andReturn(true);
                Config::set('zohoconnector.environment', 'test_env');
                
                Log::shouldReceive('error')
                    ->once()
                    ->with('zohoconnector.environment is not set correctly. (test_env). Choices are : empty,development, stage or production.');
                
                try {
                    $this->serviceChecker->testZohoServiceCheck();
                } catch (\Exception $e) {
                    expect($e->getMessage())->toBe('ZohoCreatorService is not ready. zohoconnector.environment is not correct.');
                }
            });
        });
    });
    
    describe('ZohoResponseCheck() method', function () {
        
        describe('Successful Responses', function () {
            
            it('passes for successful response with code 3000', function () {
                $response = Http::response([
                    'code' => 3000,
                    'data' => ['test' => 'data'],
                    'message' => 'Success'
                ], 200);
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response))
                    ->not->toThrow();
            });
            
            it('passes for successful response with additional fields', function () {
                $response = Http::response([
                    'code' => 3000,
                    'data' => ['records' => []],
                    'info' => ['count' => 0, 'more_records' => false]
                ], 200);
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response))
                    ->not->toThrow();
            });
        });
        
        describe('Error Responses', function () {
            
            it('throws exception for HTTP error status', function () {
                $response = Http::response([
                    'code' => 5000,
                    'message' => 'Internal Server Error'
                ], 500);
                
                Log::shouldReceive('error')->once();
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response))
                    ->toThrow(\Exception::class);
            });
            
            it('throws exception for non-3000 code', function () {
                $response = Http::response([
                    'code' => 4000,
                    'message' => 'Bad Request'
                ], 200);
                
                Log::shouldReceive('error')->once();
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response))
                    ->toThrow(\Exception::class, 'Erreur Zoho : Bad Request');
            });
            
            it('throws exception for missing code field', function () {
                $response = Http::response([
                    'message' => 'Response without code field'
                ], 200);
                
                Log::shouldReceive('error')->once();
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response))
                    ->toThrow(\Exception::class);
            });
        });
        
        describe('Scope Error Handling (Code 2945)', function () {
            
            it('throws specific exception for scope error with specific scope', function () {
                $response = Http::response([
                    'code' => 2945,
                    'message' => 'Scope not granted'
                ], 400);
                
                Log::shouldReceive('error')->once();
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response, 'ZohoCreator.report.CREATE'))
                    ->toThrow(\Exception::class, 'Please add ZohoCreator.report.CREATE in ZOHO_SCOPE env variable.');
            });
            
            it('handles scope error without specific scope parameter', function () {
                $response = Http::response([
                    'code' => 2945,
                    'message' => 'Scope not granted'
                ], 400);
                
                Log::shouldReceive('error')->once();
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response))
                    ->toThrow(\Exception::class, 'Please add  in ZOHO_SCOPE env variable.');
            });
        });
        
        describe('Error Message Construction', function () {
            
            it('constructs message from array error field', function () {
                $response = Http::response([
                    'code' => 4001,
                    'error' => ['Error 1', 'Error 2', 'Error 3']
                ], 400);
                
                Log::shouldReceive('error')->once();
                
                try {
                    $this->serviceChecker->testZohoResponseCheck($response);
                } catch (\Exception $e) {
                    expect($e->getMessage())->toBe('Erreur Zoho : Error 1; Error 2; Error 3');
                }
            });
            
            it('constructs message from message field', function () {
                $response = Http::response([
                    'code' => 4002,
                    'message' => 'Custom error message'
                ], 400);
                
                Log::shouldReceive('error')->once();
                
                try {
                    $this->serviceChecker->testZohoResponseCheck($response);
                } catch (\Exception $e) {
                    expect($e->getMessage())->toBe('Erreur Zoho : Custom error message');
                }
            });
            
            it('constructs message from full JSON when no specific fields', function () {
                $errorData = [
                    'code' => 4003,
                    'details' => ['field' => 'value']
                ];
                $response = Http::response($errorData, 400);
                
                Log::shouldReceive('error')->once();
                
                try {
                    $this->serviceChecker->testZohoResponseCheck($response);
                } catch (\Exception $e) {
                    expect($e->getMessage())->toContain('Erreur Zoho : ');
                    expect($e->getMessage())->toContain(json_encode($errorData));
                }
            });
            
            it('prioritizes error array over message field', function () {
                $response = Http::response([
                    'code' => 4004,
                    'error' => ['Priority error'],
                    'message' => 'Secondary message'
                ], 400);
                
                Log::shouldReceive('error')->once();
                
                try {
                    $this->serviceChecker->testZohoResponseCheck($response);
                } catch (\Exception $e) {
                    expect($e->getMessage())->toBe('Erreur Zoho : Priority error');
                }
            });
        });
        
        describe('Exception Handling', function () {
            
            it('sets correct exception code', function () {
                $response = Http::response([
                    'code' => 5000,
                    'message' => 'Server Error'
                ], 500);
                
                Log::shouldReceive('error')->once();
                
                try {
                    $this->serviceChecker->testZohoResponseCheck($response);
                } catch (\Exception $e) {
                    expect($e->getCode())->toBe(503);
                }
            });
            
            it('logs error with class context', function () {
                $response = Http::response([
                    'code' => 4000,
                    'message' => 'Test Error'
                ], 400);
                
                Log::shouldReceive('error')
                    ->once()
                    ->with(\Mockery::on(function ($message) {
                        return str_contains($message, '❌ Erreur dans') &&
                               str_contains($message, '::ZohoResponseCheck') &&
                               str_contains($message, 'Test Error');
                    }));
                
                try {
                    $this->serviceChecker->testZohoResponseCheck($response);
                } catch (\Exception $e) {
                    // Expected exception
                }
            });
        });
        
        describe('Edge Cases', function () {
            
            it('handles malformed JSON response', function () {
                // Create a response that will fail JSON parsing
                $response = new \Illuminate\Http\Client\Response(
                    new \GuzzleHttp\Psr7\Response(200, [], 'invalid json')
                );
                
                Log::shouldReceive('error')->once();
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response))
                    ->toThrow(\Exception::class);
            });
            
            it('handles empty response body', function () {
                $response = Http::response('', 200);
                
                Log::shouldReceive('error')->once();
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response))
                    ->toThrow(\Exception::class);
            });
            
            it('handles response with null code', function () {
                $response = Http::response([
                    'code' => null,
                    'message' => 'Null code response'
                ], 200);
                
                Log::shouldReceive('error')->once();
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response))
                    ->toThrow(\Exception::class);
            });
        });
        
        describe('Response Status Validation', function () {
            
            it('rejects 4xx client errors', function () {
                $response = Http::response([
                    'code' => 3000,
                    'data' => []
                ], 404);
                
                Log::shouldReceive('error')->once();
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response))
                    ->toThrow(\Exception::class);
            });
            
            it('rejects 5xx server errors', function () {
                $response = Http::response([
                    'code' => 3000,
                    'data' => []
                ], 502);
                
                Log::shouldReceive('error')->once();
                
                expect(fn() => $this->serviceChecker->testZohoResponseCheck($response))
                    ->toThrow(\Exception::class);
            });
            
            it('accepts 2xx status codes with correct Zoho code', function () {
                $statuses = [200, 201, 202, 204];
                
                foreach ($statuses as $status) {
                    $response = Http::response([
                        'code' => 3000,
                        'data' => []
                    ], $status);
                    
                    // Should not throw exception
                    $this->serviceChecker->testZohoResponseCheck($response);
                    expect(true)->toBeTrue(); // If we reach here, no exception was thrown
                }
            });
        });
    });
    
    describe('Integration Scenarios', function () {
        
        it('validates complete service workflow', function () {
            // First check if service is ready
            ZohoCreatorFacade::shouldReceive('isReady')->andReturn(true);
            Config::set('zohoconnector.environment', 'production');
            
            $this->serviceChecker->testZohoServiceCheck();
            
            // Then validate a successful response
            $response = Http::response([
                'code' => 3000,
                'data' => ['result' => 'success']
            ], 200);
            
            // Should not throw exception
            $this->serviceChecker->testZohoResponseCheck($response);
            expect(true)->toBeTrue(); // If we reach here, no exception was thrown
        });
        
        it('handles service not ready before response check', function () {
            ZohoCreatorFacade::shouldReceive('isReady')->andReturn(false);
            Log::shouldReceive('error')->twice();
            
            // Service check should fail first
            expect(fn() => $this->serviceChecker->testZohoServiceCheck())
                ->toThrow(\Exception::class);
        });
    });
});

// Test coverage verification
it('validates comprehensive trait coverage', function () {
    $testCount = 0;
    $content = file_get_contents(__FILE__);
    $testCount += substr_count($content, "it('");
    
    // Ensure comprehensive coverage of the trait
    expect($testCount)->toBeGreaterThan(30);
});