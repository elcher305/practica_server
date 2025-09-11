<?php

namespace App\Controllers;

use App\Models\Book;
use Src\Request;
use Src\View;
use Src\Validator\Validator;
use Exception;

class BookController
{
    /**
     * Отображает список всех книг.
     *
     * @param Request $request
     * @return string
     */
    public function index(Request $request): string
    {
        try {
            // Получаем параметры фильтрации и поиска
            $search = $request->get('search');
            $author = $request->get('author');
            $isNew = $request->get('is_new');
            $sortBy = $request->get('sort_by', 'title');
            $sortOrder = $request->get('sort_order', 'asc');

            // Строим запрос с учетом фильтров
            $booksQuery = Book::query();

            // Поиск по названию
            if ($search) {
                $booksQuery->byTitle($search);
            }

            // Фильтр по автору
            if ($author) {
                $booksQuery->byAuthor($author);
            }

            // Фильтр по новизне
            if ($isNew !== null) {
                $booksQuery->where('is_new', (bool)$isNew);
            }

            // Сортировка
            $validSortFields = ['title', 'author', 'publish_year', 'price', 'created_at'];
            if (in_array($sortBy, $validSortFields)) {
                $booksQuery->orderBy($sortBy, $sortOrder);
            }

            $books = $booksQuery->paginate(15); // Пагинация по 15 книг на страницу

            return (new View())->render('books.index', [
                'books' => $books,
                'search' => $search,
                'author' => $author,
                'isNew' => $isNew,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder
            ]);

        } catch (Exception $e) {
            return (new View())->render('books.index', [
                'error' => 'Ошибка при загрузке списка книг: ' . $e->getMessage(),
                'books' => []
            ]);
        }
    }

    /**
     * Отображает форму создания новой книги.
     *
     * @return string
     */
    public function create(): string
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/books');
        }

        return (new View())->render('books.create', [
            'currentYear' => date('Y')
        ]);
    }

    /**
     * Сохраняет новую книгу в базе данных.
     *
     * @param Request $request
     * @return string
     */
    public function store(Request $request): string
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/books');
        }

        try {
            $validator = new Validator($request->all(), [
                'author' => ['required'],
                'title' => ['required'],
                'publish_year' => ['required', 'numeric', 'min:1800', 'max:' . (date('Y') + 1)],
                'price' => ['required', 'numeric', 'min:0'],
                'is_new' => ['boolean'],
                'annotation' => ['max:1000']
            ], [
                'required' => 'Поле :field обязательно для заполнения',
                'numeric' => 'Поле :field должно быть числом',
                'min' => 'Поле :field должно быть не менее :min',
                'max' => 'Поле :field должно быть не более :max',
                'boolean' => 'Поле :field должно быть логическим значением'
            ]);

            if ($validator->fails()) {
                return (new View())->render('books.create', [
                    'errors' => $validator->errors(),
                    'bookData' => $request->all(),
                    'currentYear' => date('Y')
                ]);
            }

            $bookData = $request->all();
            $bookData['is_new'] = (bool)($bookData['is_new'] ?? false);

            $book = Book::create($bookData);

            if ($book) {
                // Сообщение об успехе через сессию
                Session::set('success', 'Книга "' . $book->title . '" успешно добавлена!');
                app()->route->redirect('/books');
            }

            throw new Exception('Не удалось создать книгу');

        } catch (Exception $e) {
            return (new View())->render('books.create', [
                'error' => 'Ошибка при создании книги: ' . $e->getMessage(),
                'bookData' => $request->all(),
                'currentYear' => date('Y')
            ]);
        }
    }

    /**
     * Отображает информацию о конкретной книге.
     *
     * @param Request $request
     * @param int $id
     * @return string
     */
    public function show(Request $request, int $id): string
    {
        try {
            $book = Book::findOrFail($id);

            // Загружаем связанные данные о выдачах
            $book->load(['issues.reader', 'activeIssues.reader']);

            return (new View())->render('books.show', [
                'book' => $book,
                'activeTab' => $request->get('tab', 'info')
            ]);

        } catch (Exception $e) {
            Session::set('error', 'Книга не найдена');
            app()->route->redirect('/books');
        }
    }

    /**
     * Отображает форму редактирования книги.
     *
     * @param int $id
     * @return string
     */
    public function edit(int $id): string
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/books');
        }

        try {
            $book = Book::findOrFail($id);

            return (new View())->render('books.edit', [
                'book' => $book,
                'currentYear' => date('Y')
            ]);

        } catch (Exception $e) {
            Session::set('error', 'Книга не найдена');
            app()->route->redirect('/books');
        }
    }

    /**
     * Обновляет информацию о книге.
     *
     * @param Request $request
     * @param int $id
     * @return string
     */
    public function update(Request $request, int $id): string
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/books');
        }

        try {
            $book = Book::findOrFail($id);

            $validator = new Validator($request->all(), [
                'author' => ['required'],
                'title' => ['required'],
                'publish_year' => ['required', 'numeric', 'min:1800', 'max:' . (date('Y') + 1)],
                'price' => ['required', 'numeric', 'min:0'],
                'is_new' => ['boolean'],
                'annotation' => ['max:1000']
            ], [
                'required' => 'Поле :field обязательно для заполнения',
                'numeric' => 'Поле :field должно быть числом',
                'min' => 'Поле :field должно быть не менее :min',
                'max' => 'Поле :field должно быть не более :max',
                'boolean' => 'Поле :field должно быть логическим значением'
            ]);

            if ($validator->fails()) {
                return (new View())->render('books.edit', [
                    'errors' => $validator->errors(),
                    'book' => $book,
                    'currentYear' => date('Y')
                ]);
            }

            $bookData = $request->all();
            $bookData['is_new'] = (bool)($bookData['is_new'] ?? false);

            if ($book->update($bookData)) {
                Session::set('success', 'Информация о книге "' . $book->title . '" успешно обновлена!');
                app()->route->redirect('/books/' . $id);
            }

            throw new Exception('Не удалось обновить информацию о книге');

        } catch (Exception $e) {
            return (new View())->render('books.edit', [
                'error' => 'Ошибка при обновлении книги: ' . $e->getMessage(),
                'book' => Book::find($id),
                'currentYear' => date('Y')
            ]);
        }
    }

    /**
     * Удаляет книгу из базы данных.
     *
     * @param int $id
     * @return void
     */
    public function destroy(int $id): void
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/books');
        }

        try {
            $book = Book::findOrFail($id);

            // Проверяем, нет ли активных выдач этой книги
            if ($book->activeIssues()->count() > 0) {
                Session::set('error', 'Нельзя удалить книку, которая находится на руках у читателей');
                app()->route->redirect('/books/' . $id);
            }

            if ($book->delete()) {
                Session::set('success', 'Книга "' . $book->title . '" успешно удалена!');
                app()->route->redirect('/books');
            }

            throw new Exception('Не удалось удалить книгу');

        } catch (Exception $e) {
            Session::set('error', 'Ошибка при удалении книги: ' . $e->getMessage());
            app()->route->redirect('/books/' . $id);
        }
    }

    /**
     * Поиск книг для автозаполнения.
     *
     * @param Request $request
     * @return string
     */
    public function search(Request $request): string
    {
        try {
            $query = $request->get('q');
            $field = $request->get('field', 'title');

            if (!$query) {
                return (new View())->toJSON([]);
            }

            $results = [];

            switch ($field) {
                case 'author':
                    $results = Book::select('author as value', 'author as label')
                        ->where('author', 'like', '%' . $query . '%')
                        ->distinct()
                        ->limit(10)
                        ->get()
                        ->toArray();
                    break;

                case 'title':
                    $results = Book::select('title as value', 'title as label')
                        ->where('title', 'like', '%' . $query . '%')
                        ->distinct()
                        ->limit(10)
                        ->get()
                        ->toArray();
                    break;

                default:
                    $results = Book::select('id', 'author', 'title')
                        ->where('author', 'like', '%' . $query . '%')
                        ->orWhere('title', 'like', '%' . $query . '%')
                        ->limit(10)
                        ->get()
                        ->map(function ($book) {
                            return [
                                'value' => $book->id,
                                'label' => $book->author . ' - "' . $book->title . '"'
                            ];
                        })
                        ->toArray();
            }

            return (new View())->toJSON($results);

        } catch (Exception $e) {
            return (new View())->toJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Получение статистики по книгам.
     *
     * @return string
     */
    public function stats(): string
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/books');
        }

        try {
            $stats = [
                'total_books' => Book::count(),
                'new_books' => Book::where('is_new', true)->count(),
                'old_books' => Book::where('is_new', false)->count(),
                'most_popular' => Book::withCount('issues')
                    ->orderBy('issues_count', 'desc')
                    ->limit(5)
                    ->get(),
                'recently_added' => Book::orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get()
            ];

            return (new View())->render('books.stats', [
                'stats' => $stats
            ]);

        } catch (Exception $e) {
            return (new View())->render('books.stats', [
                'error' => 'Ошибка при загрузке статистики: ' . $e->getMessage(),
                'stats' => []
            ]);
        }
    }
}