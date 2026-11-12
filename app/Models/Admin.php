<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Class Admin
 *
 * @property int $id
 * @property string|null $f_name
 * @property string|null $l_name
 * @property string|null $phone
 * @property string $email
 * @property string|null $image
 * @property string|null $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $role_id
 * @property int|null $zone_id
 * @property bool $is_logged_in
 */

class Admin extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'f_name',
        'l_name',
        'phone',
        'email',
        'image',
        'password',
        'remember_token',
        'role_id',
        'zone_id',
        'is_logged_in',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_logged_in' => 'boolean',
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_last_step' => 'integer',
    ];

    /**
     * Never let the second-factor material leave the server through a JSON
     * serialisation of the model. recovery codes are stored hashed, but the
     * TOTP secret is reversible and must not appear in an API payload.
     */
    protected $hidden = [
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_last_step',
    ];

    protected $appends = ['image_full_url'];

    public function hasConfirmedTwoFactor(): bool
    {
        if ($this->two_factor_confirmed_at === null) {
            return false;
        }

        try {
            return !empty($this->two_factor_secret);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $exception) {
            // APP_KEY rotated (or the column was tampered with). Treat it as
            // "not enrolled" so the next login re-enrols instead of 500ing on
            // every request. This fails closed to enrolment, not to access.
            return false;
        }
    }

    /**
     * @return BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class,'role_id');
    }

    /**
     * @return BelongsTo
     */
    public function zones(): BelongsTo
    {
        return $this->belongsTo(Zone::class,'zone_id');
    }

    /**
     * Stores this admin owns as a remote marketer.
     */
    public function marketerStores(): HasMany
    {
        return $this->hasMany(Store::class, 'marketer_id');
    }

    public function marketerCommissions(): HasMany
    {
        return $this->hasMany(MarketerCommission::class, 'marketer_id');
    }

    public function marketerPayouts(): HasMany
    {
        return $this->hasMany(MarketerPayout::class, 'marketer_id');
    }

    public function isMarketer(): bool
    {
        return (int) $this->role_id === (int) config('marketer.role_id', 5);
    }

    /**
     * Share of the platform commission this marketer earns, as a percentage.
     * A per-marketer value wins over the configured default.
     */
    public function marketerCommissionRate(): float
    {
        if ($this->marketer_commission_rate !== null && $this->marketer_commission_rate !== '') {
            return (float) $this->marketer_commission_rate;
        }

        return (float) config('marketer.default_rate', 20);
    }
    public function getImageFullUrlAttribute(){
        $value = $this->image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'image') {
                    return Helpers::get_full_url('admin',$value,$storage['value']);
                }
            }
        }

        return Helpers::get_full_url('admin',$value,'public');
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeZone($query): mixed
    {
        if(isset(auth('admin')->user()->zone_id))
        {
            return $query->where('zone_id', auth('admin')->user()->zone_id);
        }
        return $query;
    }
    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }
    protected static function booted()
    {
        static::addGlobalScope('storage', function ($builder) {
            $builder->with('storage');
        });
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            if($model->isDirty('image')){
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
