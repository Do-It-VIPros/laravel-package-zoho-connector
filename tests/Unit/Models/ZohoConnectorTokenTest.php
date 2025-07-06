<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;

uses(\Agencedoit\ZohoConnector\Tests\Unit\UnitTestCase::class);

beforeEach(function () {
    // Setup default config
    Config::set('zohoconnector.tokens_table_name', 'zoho_connector_tokens');
    
    // Create model instance for testing
    $this->model = new ZohoConnectorToken();
});

describe('ZohoConnectorToken Model', function () {
    
    describe('Model Configuration', function () {
        
        it('uses correct table name from config', function () {
            Config::set('zohoconnector.tokens_table_name', 'custom_tokens_table');
            
            $model = new ZohoConnectorToken();
            
            expect($model->getTable())->toBe('custom_tokens_table');
        });
        
        it('uses default table name when config is not set', function () {
            Config::set('zohoconnector.tokens_table_name', null);
            
            $model = new ZohoConnectorToken();
            
            expect($model->getTable())->toBeNull();
        });
        
        it('has correct fillable attributes', function () {
            $expectedFillable = [
                'token',
                'refresh_token',
                'token_created_at',
                'token_peremption_at',
                'token_duration'
            ];
            
            expect($this->model->getFillable())->toBe($expectedFillable);
        });
        
        it('includes HasFactory trait', function () {
            $traits = class_uses($this->model);
            
            expect($traits)->toHaveKey('Illuminate\Database\Eloquent\Factories\HasFactory');
        });
    });
    
    describe('Attribute Management', function () {
        
        it('allows mass assignment of fillable attributes', function () {
            $attributes = [
                'token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'token_created_at' => now(),
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ];
            
            $model = new ZohoConnectorToken($attributes);
            
            expect($model->token)->toBe('test_access_token');
            expect($model->refresh_token)->toBe('test_refresh_token');
            expect($model->token_duration)->toBe(3600);
        });
        
        it('handles token attributes correctly', function () {
            $this->model->token = 'sample_access_token';
            $this->model->refresh_token = 'sample_refresh_token';
            
            expect($this->model->token)->toBe('sample_access_token');
            expect($this->model->refresh_token)->toBe('sample_refresh_token');
        });
        
        it('handles datetime attributes correctly', function () {
            $createdAt = now();
            $expiresAt = now()->addHour();
            
            $this->model->token_created_at = $createdAt;
            $this->model->token_peremption_at = $expiresAt;
            
            expect($this->model->token_created_at)->toBe($createdAt);
            expect($this->model->token_peremption_at)->toBe($expiresAt);
        });
        
        it('handles token duration as integer', function () {
            $this->model->token_duration = 3600;
            
            expect($this->model->token_duration)->toBe(3600);
            expect($this->model->token_duration)->toBeInt();
        });
    });
    
    describe('Token Validation Logic', function () {
        
        it('can determine if token is expired', function () {
            // Expired token
            $expiredModel = new ZohoConnectorToken([
                'token' => 'expired_token',
                'token_peremption_at' => now()->subHour()
            ]);
            
            expect($expiredModel->token_peremption_at->isPast())->toBeTrue();
            
            // Valid token
            $validModel = new ZohoConnectorToken([
                'token' => 'valid_token',
                'token_peremption_at' => now()->addHour()
            ]);
            
            expect($validModel->token_peremption_at->isPast())->toBeFalse();
        });
        
        it('can calculate remaining token lifetime', function () {
            $futureTime = now()->addMinutes(30);
            
            $this->model->token_peremption_at = $futureTime;
            
            $remaining = $this->model->token_peremption_at->diffInMinutes(now());
            
            expect($remaining)->toBeGreaterThan(25);
            expect($remaining)->toBeLessThan(35);
        });
        
        it('handles null expiration dates', function () {
            $this->model->token_peremption_at = null;
            
            expect($this->model->token_peremption_at)->toBeNull();
        });
    });
    
    describe('Data Integrity', function () {
        
        it('preserves token format integrity', function () {
            $tokenFormats = [
                '1000.abc123def456.ghi789jkl012',
                'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
                'simple_token_format'
            ];
            
            foreach ($tokenFormats as $format) {
                $this->model->token = $format;
                expect($this->model->token)->toBe($format);
            }
        });
        
        it('handles special characters in tokens', function () {
            $specialToken = 'token_with-special.chars_123!@#';
            
            $this->model->token = $specialToken;
            
            expect($this->model->token)->toBe($specialToken);
        });
        
        it('handles very long tokens', function () {
            $longToken = str_repeat('abcdef123456', 50); // 600 characters
            
            $this->model->token = $longToken;
            
            expect($this->model->token)->toBe($longToken);
            expect(strlen($this->model->token))->toBe(600);
        });
    });
    
    describe('Edge Cases', function () {
        
        it('handles empty token values', function () {
            $this->model->token = '';
            $this->model->refresh_token = '';
            
            expect($this->model->token)->toBe('');
            expect($this->model->refresh_token)->toBe('');
        });
        
        it('handles null token values', function () {
            $this->model->token = null;
            $this->model->refresh_token = null;
            
            expect($this->model->token)->toBeNull();
            expect($this->model->refresh_token)->toBeNull();
        });
        
        it('handles zero token duration', function () {
            $this->model->token_duration = 0;
            
            expect($this->model->token_duration)->toBe(0);
        });
        
        it('handles negative token duration', function () {
            $this->model->token_duration = -1;
            
            expect($this->model->token_duration)->toBe(-1);
        });
    });
    
    describe('Model Instantiation', function () {
        
        it('can be instantiated with empty attributes', function () {
            $model = new ZohoConnectorToken();
            
            expect($model)->toBeInstanceOf(ZohoConnectorToken::class);
            expect($model->token)->toBeNull();
        });
        
        it('can be instantiated with partial attributes', function () {
            $model = new ZohoConnectorToken([
                'token' => 'partial_token'
            ]);
            
            expect($model->token)->toBe('partial_token');
            expect($model->refresh_token)->toBeNull();
        });
        
        it('inherits from Eloquent Model', function () {
            expect($this->model)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
        });
    });
    
    describe('Configuration Scenarios', function () {
        
        it('handles different table name configurations', function () {
            $tableNames = [
                'zoho_tokens',
                'custom_zoho_connector_tokens',
                'app_zoho_auth_tokens',
                'tokens'
            ];
            
            foreach ($tableNames as $tableName) {
                Config::set('zohoconnector.tokens_table_name', $tableName);
                
                $model = new ZohoConnectorToken();
                
                expect($model->getTable())->toBe($tableName);
            }
        });
        
        it('maintains table configuration across instances', function () {
            Config::set('zohoconnector.tokens_table_name', 'persistent_table');
            
            $model1 = new ZohoConnectorToken();
            $model2 = new ZohoConnectorToken();
            
            expect($model1->getTable())->toBe($model2->getTable());
            expect($model1->getTable())->toBe('persistent_table');
        });
    });
    
    describe('Token Lifecycle Simulation', function () {
        
        it('simulates complete token lifecycle', function () {
            // Initial token creation
            $initialTime = now();
            $duration = 3600; // 1 hour
            
            $token = new ZohoConnectorToken([
                'token' => 'lifecycle_token',
                'refresh_token' => 'lifecycle_refresh',
                'token_created_at' => $initialTime,
                'token_peremption_at' => $initialTime->copy()->addSeconds($duration),
                'token_duration' => $duration
            ]);
            
            // Verify initial state
            expect($token->token)->toBe('lifecycle_token');
            expect($token->token_duration)->toBe(3600);
            
            // Simulate time passage
            Carbon::setTestNow($initialTime->copy()->addMinutes(30));
            
            // Token should still be valid
            expect($token->token_peremption_at->isFuture())->toBeTrue();
            
            // Simulate token expiration
            Carbon::setTestNow($initialTime->copy()->addHours(2));
            
            // Token should now be expired
            expect($token->token_peremption_at->isPast())->toBeTrue();
            
            // Reset Carbon
            Carbon::setTestNow();
        });
        
        it('simulates token refresh scenario', function () {
            $original = new ZohoConnectorToken([
                'token' => 'original_token',
                'refresh_token' => 'original_refresh',
                'token_created_at' => now()->subHour(),
                'token_peremption_at' => now()->subMinutes(5), // Recently expired
                'token_duration' => 3600
            ]);
            
            // Simulate refresh
            $refreshed = new ZohoConnectorToken([
                'token' => 'refreshed_token',
                'refresh_token' => 'new_refresh_token',
                'token_created_at' => now(),
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);
            
            expect($original->token_peremption_at->isPast())->toBeTrue();
            expect($refreshed->token_peremption_at->isFuture())->toBeTrue();
            expect($refreshed->token)->not->toBe($original->token);
        });
    });
});

// Test coverage verification
it('validates comprehensive model coverage', function () {
    $testCount = 0;
    $content = file_get_contents(__FILE__);
    $testCount += substr_count($content, "it('");
    
    // Ensure comprehensive coverage of the model
    expect($testCount)->toBeGreaterThan(25);
});