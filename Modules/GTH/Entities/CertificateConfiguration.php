<?php

namespace Modules\SICA\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateConfiguration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'is_default',
        'center_name',
        'center_address',
        'version_code',
        'title_line_1',
        'title_line_2',
        'intro_template',
        'expedition_template',
        'default_projected_by',
        'default_projected_role',
        'default_reviewed_by',
        'default_reviewed_role',
        'default_director_name',
        'default_director_role',
        'logo_path',
        'logo_color',
        'line_spacing',
        'font_size',
        'font_family',
        'page_size',
        'page_orientation',
        'margin_top',
        'margin_right',
        'margin_bottom',
        'margin_left',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public static function getDefault()
    {
        return self::where('is_default', true)->first() ?? self::first();
    }
}