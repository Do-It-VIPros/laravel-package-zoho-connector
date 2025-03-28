<?php

namespace Agencedoit\ZohoConnector\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoConnectorToken extends Model
{
    use HasFactory;

    protected $table;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->table = config('zohoconnector.tokens_table_name');
    }

    protected $fillable=[
        'token',
        'refresh_token',
        'token_created_at',
        'token_peremption_at',
        'token_duration'
    ];
}
