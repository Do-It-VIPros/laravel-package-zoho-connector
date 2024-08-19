<?php

namespace Agencedoit\ZohoConnector\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZohoBulkHistory extends Model
{
    use HasFactory;

    protected $table;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        $this->table = config('zohoconnector.bulks_table_name');
    }

    protected $fillable=[
        'bulk_id',
        'report',
        'criterias',
        'step',
        'call_back_url',
        'last_launch'
    ];
}
