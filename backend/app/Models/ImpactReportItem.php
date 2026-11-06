<?php

namespace BlastSpec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ImpactReportItem extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'impact_report_items';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'report_id' => 'string',
        'service_id' => 'string',
        'change_ids' => 'array',
        'depth' => 'integer',
    ];

    /** @var array<int, string> */
    protected $fillable = ['id', 'report_id', 'service_id', 'owner_team', 'relation', 'depth', 'change_ids', 'reason'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ImpactReport::class, 'report_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}

final class ImpactReportWave extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'impact_report_waves';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'report_id' => 'string',
        'service_id' => 'string',
        'wave_index' => 'integer',
    ];

    /** @var array<int, string> */
    protected $fillable = ['id', 'report_id', 'wave_index', 'service_id'];
}

final class ImpactReportCycle extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'impact_report_cycles';

    /** @var array<string, mixed> */
    protected $casts = [
        'id' => 'string',
        'report_id' => 'string',
        'service_ids' => 'array',
        'recommended_break_service_id' => 'string',
    ];

    /** @var array<int, string> */
    protected $fillable = ['id', 'report_id', 'service_ids', 'recommended_break_service_id', 'reason'];
}
