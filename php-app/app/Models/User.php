<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'time_zone', 'locale', 'alice_enabled', 'selected_plan_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use CrudTrait, HasFactory, Notifiable, HasRoles;

    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'alice_enabled' => 'boolean',
            'selected_plan_id' => 'integer',
        ];
    }

    public function ownedControllers(): HasMany
    {
        return $this->hasMany(IoTController::class, 'user_id');
    }

    public function planSubscriptions(): HasMany
    {
        return $this->hasMany(UserPlanSubscription::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function balance()
    {
        return $this->hasOne(UserBalance::class);
    }

    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class);
    }

    public function selectedPlanSubscription(): ?UserPlanSubscription
    {
        return $this->planSubscriptions()
            ->with('plan')
            ->latest('id')
            ->first();
    }

    public function selectedPlan()
    {
        return $this->belongsTo(Plan::class, 'selected_plan_id');
    }

    public function getRolesListForAdmin(): string
    {
        $roles = $this->roles()->pluck('name')->all();
        return count($roles) > 0 ? implode(', ', $roles) : '—';
    }
}
