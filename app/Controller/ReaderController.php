<?php

namespace App\Controllers;

use App\Models\Reader;
use App\Models\BookIssue;
use Src\Request;
use Src\View;
use Src\Validator\Validator;
use Src\Session;
use Exception;

class ReaderController
{
    /**
     * Отображает список всех читателей.
     *
     * @param Request $request
     * @return string
     */
    public function index(Request $request): string
    {
        try {
            // Получаем параметры фильтрации и поиска
            $search = $request->get('search');
            $hasBooks = $request->get('has_books');
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');

            // Строим запрос с учетом фильтров
            $readersQuery = Reader::query();

            // Поиск по имени или номеру билета
            if ($search) {
                $readersQuery->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('library_card_id', 'like', '%' . $search . '%');
                });
            }

            // Фильтр по наличию книг на руках
            if ($hasBooks !== null) {
                if ($hasBooks) {
                    $readersQuery->withActiveIssues();
                } else {
                    $readersQuery->withoutActiveIssues();
                }
            }

            // Сортировка
            $validSortFields = ['name', 'library_card_id', 'created_at'];
            if (in_array($sortBy, $validSortFields)) {
                $readersQuery->orderBy($sortBy, $sortOrder);
            }

            $readers = $readersQuery->paginate(20); // Пагинация по 20 читателей на страницу

            return (new View())->render('readers.index', [
                'readers' => $readers,
                'search' => $search,
                'hasBooks' => $hasBooks,
                'sortBy' => $sortBy,
                'sortOrder' => $sortOrder
            ]);

        } catch (Exception $e) {
            return (new View())->render('readers.index', [
                'error' => 'Ошибка при загрузке списка читателей: ' . $e->getMessage(),
                'readers' => []
            ]);
        }
    }

    /**
     * Отображает форму создания нового читателя.
     *
     * @return string
     */
    public function create(): string
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/readers');
        }

        return (new View())->render('readers.create');
    }

    /**
     * Сохраняет нового читателя в базе данных.
     *
     * @param Request $request
     * @return string
     */
    public function store(Request $request): string
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/readers');
        }

        try {
            $validator = new Validator($request->all(), [
                'library_card_id' => ['required', 'unique:readers,library_card_id'],
                'name' => ['required'],
                'address' => ['required'],
                'phone' => ['required']
            ], [
                'required' => 'Поле :field обязательно для заполнения',
                'unique' => 'Читательский билет с таким номером уже существует'
            ]);

            if ($validator->fails()) {
                return (new View())->render('readers.create', [
                    'errors' => $validator->errors(),
                    'readerData' => $request->all()
                ]);
            }

            $reader = Reader::create($request->all());

            if ($reader) {
                Session::set('success', 'Читатель "' . $reader->name . '" успешно добавлен!');
                app()->route->redirect('/readers');
            }

            throw new Exception('Не удалось создать читателя');

        } catch (Exception $e) {
            return (new View())->render('readers.create', [
                'error' => 'Ошибка при создании читателя: ' . $e->getMessage(),
                'readerData' => $request->all()
            ]);
        }
    }

    /**
     * Отображает информацию о конкретном читателе.
     *
     * @param Request $request
     * @param int $id
     * @return string
     */
    public function show(Request $request, int $id): string
    {
        try {
            $reader = Reader::findOrFail($id);

            // Загружаем связанные данные о выдачах
            $activeTab = $request->get('tab', 'info');

            $reader->load(['activeIssues.book', 'issues.book' => function ($query) {
                $query->orderBy('date_issued', 'desc');
            }]);

            // Статистика по читателю
            $stats = $reader->stats;

            return (new View())->render('readers.show', [
                'reader' => $reader,
                'stats' => $stats,
                'activeTab' => $activeTab
            ]);

        } catch (Exception $e) {
            Session::set('error', 'Читатель не найден');
            app()->route->redirect('/readers');
        }
    }

    /**
     * Отображает форму редактирования читателя.
     *
     * @param int $id
     * @return string
     */
    public function edit(int $id): string
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/readers');
        }

        try {
            $reader = Reader::findOrFail($id);

            return (new View())->render('readers.edit', [
                'reader' => $reader
            ]);

        } catch (Exception $e) {
            Session::set('error', 'Читатель не найден');
            app()->route->redirect('/readers');
        }
    }

    /**
     * Обновляет информацию о читателе.
     *
     * @param Request $request
     * @param int $id
     * @return string
     */
    public function update(Request $request, int $id): string
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/readers');
        }

        try {
            $reader = Reader::findOrFail($id);

            $validator = new Validator($request->all(), [
                'library_card_id' => ['required', 'unique:readers,library_card_id,' . $id],
                'name' => ['required'],
                'address' => ['required'],
                'phone' => ['required']
            ], [
                'required' => 'Поле :field обязательно для заполнения',
                'unique' => 'Читательский билет с таким номером уже существует'
            ]);

            if ($validator->fails()) {
                return (new View())->render('readers.edit', [
                    'errors' => $validator->errors(),
                    'reader' => $reader
                ]);
            }

            if ($reader->update($request->all())) {
                Session::set('success', 'Информация о читателе "' . $reader->name . '" успешно обновлена!');
                app()->route->redirect('/readers/' . $id);
            }

            throw new Exception('Не удалось обновить информацию о читателе');

        } catch (Exception $e) {
            return (new View())->render('readers.edit', [
                'error' => 'Ошибка при обновлении читателя: ' . $e->getMessage(),
                'reader' => Reader::find($id)
            ]);
        }
    }

    /**
     * Удаляет читателя из базы данных.
     *
     * @param int $id
     * @return void
     */
    public function destroy(int $id): void
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/readers');
        }

        try {
            $reader = Reader::findOrFail($id);

            // Проверяем, нет ли активных выдач у читателя
            if ($reader->activeIssues()->count() > 0) {
                Session::set('error', 'Нельзя удалить читателя, у которого есть книги на руках');
                app()->route->redirect('/readers/' . $id);
            }

            if ($reader->delete()) {
                Session::set('success', 'Читатель "' . $reader->name . '" успешно удален!');
                app()->route->redirect('/readers');
            }

            throw new Exception('Не удалось удалить читателя');

        } catch (Exception $e) {
            Session::set('error', 'Ошибка при удалении читателя: ' . $e->getMessage());
            app()->route->redirect('/readers/' . $id);
        }
    }

    /**
     * Поиск читателей для автозаполнения.
     *
     * @param Request $request
     * @return string
     */
    public function search(Request $request): string
    {
        try {
            $query = $request->get('q');
            $field = $request->get('field', 'name');

            if (!$query) {
                return (new View())->toJSON([]);
            }

            $readersQuery = Reader::query();

            switch ($field) {
                case 'card':
                    $readersQuery->where('library_card_id', 'like', '%' . $query . '%');
                    break;

                case 'name':
                default:
                    $readersQuery->where('name', 'like', '%' . $query . '%');
                    break;
            }

            $results = $readersQuery->limit(10)
                ->get()
                ->map(function ($reader) {
                    return [
                        'value' => $reader->id,
                        'label' => $reader->name . ' (' . $reader->library_card_id . ')',
                        'reader' => $reader->toArray()
                    ];
                })
                ->toArray();

            return (new View())->toJSON($results);

        } catch (Exception $e) {
            return (new View())->toJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Отображает читателей с книгами на руках.
     *
     * @return string
     */
    public function withBooks(): string
    {
        try {
            $readers = Reader::withActiveIssues()
                ->with(['activeIssues.book'])
                ->paginate(20);

            return (new View())->render('readers.with-books', [
                'readers' => $readers
            ]);

        } catch (Exception $e) {
            return (new View())->render('readers.with-books', [
                'error' => 'Ошибка при загрузке списка: ' . $e->getMessage(),
                'readers' => []
            ]);
        }
    }

    /**
     * Экспорт данных читателей в CSV.
     *
     * @param Request $request
     * @return void
     */
    public function export(Request $request): void
    {
        if (!app()->auth::user()->canManageLibrary()) {
            app()->route->redirect('/readers');
        }

        try {
            $readers = Reader::all();

            // Устанавливаем заголовки для скачивания файла
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=readers_' . date('Y-m-d') . '.csv');

            $output = fopen('php://output', 'w');

            // Заголовки CSV
            fputcsv($output, [
                'Номер билета',
                'ФИО',
                'Адрес',
                'Телефон',
                'Дата регистрации',
                'Книг на руках',
                'Всего книг взято'
            ], ';');

            // Данные
            foreach ($readers as $reader) {
                fputcsv($output, [
                    $reader->library_card_id,
                    $reader->name,
                    $reader->address,
                    $reader->phone,
                    $reader->created_at->format('d.m.Y'),
                    $reader->active_issues_count,
                    $reader->total_issues_count
                ], ';');
            }

            fclose($output);
            exit;

        } catch (Exception $e) {
            Session::set('error', 'Ошибка при экспорте данных: ' . $e->getMessage());
            app()->route->redirect('/readers');
        }
    }

    /**
     * Генерирует новый номер читательского билета.
     *
     * @return string
     */
    public function generateCardId(): string
    {
        try {
            // Ищем последний номер билета
            $lastReader = Reader::orderBy('library_card_id', 'desc')->first();
            $lastNumber = 0;

            if ($lastReader && preg_match('/ЧБ-(\d+)/', $lastReader->library_card_id, $matches)) {
                $lastNumber = (int)$matches[1];
            }

            $newNumber = $lastNumber + 1;
            $newCardId = 'ЧБ-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);

            return (new View())->toJSON([
                'card_id' => $newCardId
            ]);

        } catch (Exception $e) {
            return (new View())->toJSON([
                'error' => 'Ошибка при генерации номера билета'
            ]);
        }
    }
}