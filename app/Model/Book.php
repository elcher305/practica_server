<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Book extends Model
{

    use HasFactory;
    protected $table = 'books';

    protected $fillable = [
        'author',
        'title',
        'publish_year',
        'price',
        'is_new',
        'annotation'
    ];

    protected $casts = [
        'publish_year' => 'integer',
        'price' => 'decimal:2',
        'is_new' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function issues(): HasMany
    {
        return $this->hasMany(BookIssue::class, 'book_id');
    }

    public function activeIssues()
    {
        return $this->issues()->where('status', 'issued');
    }

    public function isAvailable(): bool
    {
        return $this->activeIssues()->count() === 0;
    }

    public function scopeByAuthor($query, $author)
    {
        return $query->where('author', 'like', '%' . $author . '%');
    }

    public function scopeByTitle($query, $title)
    {
        return $query->where('title', 'like', '%' . $title . '%');
    }

    public function scopeNew($query)
    {
        return $query->where('is_new', true);
    }

    public function scopeOld($query)
    {
        return $query->where('is_new', false);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 2, ',', ' ') . ' ₽';
    }

    public function getAvailabilityStatusAttribute(): string
    {
        return $this->isAvailable() ? 'Доступна' : 'На руках';
    }

    public function getShortAnnotationAttribute(): string
    {
        if (strlen($this->annotation) > 100) {
            return substr($this->annotation, 0, 100) . '...';
        }
        return $this->annotation;
    }
}