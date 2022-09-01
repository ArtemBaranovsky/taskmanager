<?php

namespace App\Models;

use App\Enum\TasksStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class Task extends Model
{
    use HasFactory, HasFactory;

    /**
     * @var string
     */
    public string $notification_time = 'actual';

    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'tasks';

    /**
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that should be cast.
     * @var string[]
     */
    protected $casts = [
        'status' => TasksStatusEnum::class,
    ];

    /**
     * The attributes that are mass assignable.
     * @var string[]
     */
    protected $fillable = [
        'priority', 'title', 'description', 'expiration_time', 'notify_before', 'attachment', 'parent_id', 'user_id'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     * @var string[]
     */
    protected $hidden = [
        'parent_id', 'user_id'
    ];

    /**
     * @return HasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Scope query, including only selected status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPriority($query, $priority)
    {
        if (strpos($priority, ',') !== false) {
            $priorityRange = explode(',', $priority);
            $priorityRangeInt = array_filter($priorityRange, fn($x) => gettype((int)$x) === 'integer');

            return $query->whereBetween('priority', $priorityRangeInt);
        }

        return $query->where('priority', '=', $priority);
    }

    /**
     * Scope query, including only selected status.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, $type)
    {
        return $query->where('status', '=', $type);
    }

    /**
     * Scope query by title like search.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param                                       $needle
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTitle($query, $needle): Builder
    {
        return $query->where('title', 'like', "%$needle%");
    }

    /**
     * Checks if the task done
     * @return bool
     */
    public function isDone()
    {
        return $this->status === TasksStatusEnum::DONE;
    }

    /**
     * Checks if there is any uncompleted child task
     *
     * @param Task $task
     *
     * @return int
     */
    public function hasUncomletedSubTasks(Task $task): int
    {
        $childTasks = $this
            ->where('user_id', '=', $task->user_id)
            ->where('parent_id', '=', $task->id)
            ->get();

        return $this->getSubTasks($childTasks)->flatten()->count();
    }

    /**
     * Recursively returns uncompleted tasks
     *
     * @param Collection $tasks
     *
     * @return $this|Collection
     */
    public function getSubTasks(Collection $tasks): Collection
    {
        if ($tasks) {
            $children = new Collection();
            foreach ($tasks as $task) {
                $taskChildren = $task
                    ->where('parent_id', '=', $task->id)
                    ->where('status', '=', TasksStatusEnum::TODO)
                    ->get();
                $children->add($task);
                $children->add($taskChildren);
            }

            return $children;
        }

        return $this;
    }


    /**
     * @param array|null $filter
     * @param array|null $sort
     *
     * @return array|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Support\HigherOrderWhenProxy[]
     */
    public function getFileredTasks(User $user, ?array $filter, ?array $sort): array|\Illuminate\Database\Eloquent\Collection
    {
        return Task::query()
            ->where('user_id', '=', $user->id)
            ->when(isset($filter['status']), function ($query) use ($filter) {
                return $query->byStatus($filter['status']);
            })
            ->when(isset($filter['title']), function ($query) use ($filter) {
                return $query->byTitle($filter['title']);
            })
            ->when(isset($filter['priority']), function ($query) use ($filter) {
                return $query->byPriority($filter['priority']);
            })
            ->when(isset($sort), function ($query) use ($sort) {
                foreach ($sort as $sortItem) {
                    return $query->orderBy($sortItem);
                }
            })
            ->get();
    }
}
