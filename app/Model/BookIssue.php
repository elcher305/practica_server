<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookIssue extends Model
{
    use HasFactory;

    /**
     * Название таблицы, связанной с моделью.
     *
     * @var string
     */
    protected $table = 'book_issues';

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array
     */
    protected $fillable = [
        'book_id',
        'reader_id',
        'librarian_id',
        'date_issued',
        'date_returned'
    ];

    /**
     * Атрибуты, которые должны быть приведены к определенным типам.
     *
     * @var array
     */
    protected $casts = [
        'date_issued' => 'datetime',
        'date_returned' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Получить книгу, связанную с выдачей.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }

    /**
     * Получить читателя, связанного с выдачей.
     */
    public function reader(): BelongsTo
    {
        return $this->belongsTo(Reader::class, 'reader_id');
    }

    /**
     * Получить библиотекаря, который оформил выдачу.
     */
    public function librarian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'librarian_id');
    }

    /**
     * Scope для активных выдач (книги на руках).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'issued');
    }

    /**
     * Scope для завершенных выдач (книги возвращены).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    /**
     * Scope для выдач определенной книги.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $bookId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForBook($query, $bookId)
    {
        return $query->where('book_id', $bookId);
    }

    /**
     * Scope для выдач определенному читателю.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $readerId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForReader($query, $readerId)
    {
        return $query->where('reader_id', $readerId);
    }

    /**
     * Scope для выдач за определенный период.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIssuedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('date_issued', [$startDate, $endDate]);
    }

    /**
     * Проверить, является ли выдача активной (книга на руках).
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'issued';
    }

    /**
     * Проверить, возвращена ли книга.
     *
     * @return bool
     */
    public function isReturned(): bool
    {
        return $this->status === 'returned';
    }

    /**
     * Рассчитать количество дней, на которые книга выдана.
     *
     * @return int|null
     */
    public function getDaysIssuedAttribute(): ?int
    {
        if (!$this->date_issued) {
            return null;
        }

        $endDate = $this->date_returned ?? now();
        return $this->date_issued->diffInDays($endDate);
    }

    /**
     * Рассчитать просрочку в днях (если книга не возвращена более 30 дней).
     *
     * @return int
     */
    public function getOverdueDaysAttribute(): int
    {
        if ($this->isReturned() || !$this->date_issued) {
            return 0;
        }

        $daysIssued = $this->date_issued->diffInDays(now());
        return max(0, $daysIssued - 30); // Предполагаем, что срок выдачи - 30 дней
    }

    /**
     * Проверить, есть ли просрочка по книге.
     *
     * @return bool
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->overdue_days > 0;
    }

    /**
     * Accessor для форматированной даты выдачи.
     *
     * @return string
     */
    public function getFormattedDateIssuedAttribute(): string
    {
        return $this->date_issued ? $this->date_issued->format('d.m.Y H:i') : '';
    }

    /**
     * Accessor для форматированной даты возврата.
     *
     * @return string
     */
    public function getFormattedDateReturnedAttribute(): string
    {
        return $this->date_returned ? $this->date_returned->format('d.m.Y H:i') : '';
    }

    /**
     * Mutator для даты выдачи.
     *
     * @param string $value
     * @return void
     */
    public function setDateIssuedAttribute($value)
    {
        $this->attributes['date_issued'] = $value ?: now();
    }

    /**
     * Оформить возврат книги.
     *
     * @return bool
     */
    public function returnBook(): bool
    {
        if ($this->isReturned()) {
            return false; // Книга уже возвращена
        }

        $this->date_returned = now();
        return $this->save();
    }

    /**
     * Получить информацию о выдаче в виде массива.
     *
     * @return array
     */
    public function getIssueInfoAttribute(): array
    {
        return [
            'issue_id' => $this->id,
            'book_title' => $this->book->title,
            'book_author' => $this->book->author,
            'reader_name' => $this->reader->name,
            'reader_card' => $this->reader->library_card_id,
            'librarian_name' => $this->librarian->name,
            'date_issued' => $this->formatted_date_issued,
            'date_returned' => $this->formatted_date_returned,
            'days_issued' => $this->days_issued,
            'status' => $this->status,
            'is_overdue' => $this->is_overdue,
            'overdue_days' => $this->overdue_days
        ];
    }
}