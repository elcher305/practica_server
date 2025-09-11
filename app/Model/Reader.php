<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reader extends Model
{
    use HasFactory;


    protected $table = 'readers';


    protected $fillable = [
        'library_card_id',
        'name',
        'address',
        'phone'
    ];


    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];


    public function issues(): HasMany
    {
        return $this->hasMany(BookIssue::class, 'reader_id');
    }


    public function activeIssues()
    {
        return $this->issues()->where('status', 'issued');
    }


    public function returnedIssues()
    {
        return $this->issues()->where('status', 'returned');
    }


    public function hasActiveIssues(): bool
    {
        return $this->activeIssues()->count() > 0;
    }


    public function getActiveIssuesCountAttribute(): int
    {
        return $this->activeIssues()->count();
    }


    public function getTotalIssuesCountAttribute(): int
    {
        return $this->issues()->count();
    }


    public function scopeByLibraryCard($query, $cardId)
    {
        return $query->where('library_card_id', 'like', '%' . $cardId . '%');
    }


    public function scopeByName($query, $name)
    {
        return $query->where('name', 'like', '%' . $name . '%');
    }


    public function scopeWithActiveIssues($query)
    {
        return $query->whereHas('issues', function ($q) {
            $q->where('status', 'issued');
        });
    }


    public function scopeWithoutActiveIssues($query)
    {
        return $query->whereDoesntHave('issues', function ($q) {
            $q->where('status', 'issued');
        });
    }


    public function getFormattedPhoneAttribute(): string
    {
        $phone = $this->phone;
        // Простое форматирование номера телефона
        if (preg_match('/^(\d{1})(\d{3})(\d{3})(\d{2})(\d{2})$/', preg_replace('/\D/', '', $phone), $matches)) {
            return "+$matches[1] ($matches[2]) $matches[3]-$matches[4]-$matches[5]";
        }
        return $phone;
    }


    public function getShortAddressAttribute(): string
    {
        $address = $this->address;
        // Простая логика для извлечения города и улицы
        if (preg_match('/г\.\s*[^,]+/', $address, $cityMatch)) {
            $city = $cityMatch[0];
            if (preg_match('/ул\.\s*[^,]+/', $address, $streetMatch)) {
                return $city . ', ' . $streetMatch[0];
            }
            return $city;
        }
        return substr($address, 0, 50) . (strlen($address) > 50 ? '...' : '');
    }



    public function setLibraryCardIdAttribute($value)
    {
        $this->attributes['library_card_id'] = strtoupper(trim($value));
    }


    public function setPhoneAttribute($value)
    {
        $this->attributes['phone'] = preg_replace('/\D/', '', $value);
    }


    public function getStatsAttribute(): array
    {
        return [
            'active_issues' => $this->active_issues_count,
            'total_issues' => $this->total_issues_count,
            'returned_issues' => $this->returnedIssues()->count()
        ];
    }
}