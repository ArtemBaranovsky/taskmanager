<?php

namespace App\Http\Controllers\Api;

use App\Enum\TasksStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Http\Requests\StoreTasksRequest;
use App\Http\Requests\UpdateTasksRequest;
use App\Models\User;
use App\Notifications\TaskTimeExpiring;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TasksController extends Controller
{
    /**
     * @var \App\Models\User
     */
    private /*\App\Models\User*/
        $user;

    public function __construct()
    {
        $this->user = User::where('api_token', '=', request()->bearerToken());
    }

    /**
     * Display a listing of the resource.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $validated = Validator::make(request()->all(), [
            'status'   => fn($status) => in_array($status, TasksStatusEnum::cases()),
            'title'    => 'string|min:3',
            'priority' => 'required|string|max:64',
            'sort'     => 'string|max:36',
        ])->validated();

        try {
            $sort = $validated['sort'] ? explode(',', $validated['sort']) : null;
            $filter = Arr::except($validated, 'sort');

            $result = (new Task)->getFileredTasks($this->user->first(), $filter, $sort);

            return $this->getSuccessfulJsonResponse($result);
        } catch (\Throwable $exception) {
            return $this->processFailedResponse(
                $exception,
                'Task can\'t be completed since it has uncompleted children tasks'
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \App\Http\Requests\StoreTasksRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreTasksRequest $request)
    {
        $userId = $this->user->first()->id;
        $taskData = [
            'user_id'         => $userId,
            'title'           => $request->input('title'),
            'description'     => $request->input('description'),
            'status'          => TasksStatusEnum::TODO,
            'priority'        => $request->input('priority'),
            'expiration_time' => $request->input('expiration_time'),
            'notify_before'   => $request->input('notify_before'),
            'parent_id'       => $request->input('parent_id'),
        ];

        $userUpload = $request->file('attachment') ?? null;

        try {
            if ($userUpload) {
                $resource = fopen($userUpload->getRealPath(), 'r');
                $originalFileName = $userUpload->getClientOriginalName();
                $taskData = [
                    ...$taskData,
                    ...['attachment' => $originalFileName]
                ];
                Storage::disk('local')->put('images/tasks/' . $originalFileName, $resource);
            }

            $task = new Task($taskData);
            $task->saveOrFail();

            $notificationTime = Carbon::createFromTimeString($taskData['expiration_time'])
                ->subMinutes($taskData['notify_before']);
            $delayMinutes = $notificationTime->diffInMinutes(Carbon::now());

            User::findOrFail($userId)
                ->notify(
                    (new TaskTimeExpiring($task))
                        ->onQueue('mail-queue')
                        ->delay(now()->addMinutes($delayMinutes))
                );

            return $this->getSuccessfulJsonResponse(['task-id' => $task->id]);
        } catch (\Throwable $exception) {
            return $this->processFailedResponse(
                $exception,
                'Task can\'t be completed since it has uncompleted children tasks'
            );
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \App\Http\Requests\UpdateTasksRequest $request
     * @param \App\Models\Task                      $task
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateTasksRequest $request, Task $task)
    {
        $userId = $this->user->first()->id;
        $taskData = [
            ...$request->only([
                'id',
                'title',
                'description',
                'priority',
                'expiration_time',
                'notify_before',
                'parent_id',
                'status',
            ]),
            'user_id' => $userId
        ];

        $oldTask = $task->getOriginal();

        try {
            $result = $task->update($taskData);
            $changedData = $task->getChanges();
            $taskData = $this->processAttachment($changedData, $request, $taskData, $oldTask['attachment']);
            $this->processChangedTime($changedData, $taskData, $task, $userId);

            return $this->getSuccessfulJsonResponse(['task-id' => $taskData['id']]);
        } catch (\Throwable $exception) {
            return $this->processFailedResponse(
                $exception,
                'Task can\'t be completed since it has uncompleted children tasks'
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Task $tasks
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Task $task)
    {
        if ($task->isDone()) {
            return response()->json([
                'status'  => 421,
                'message' => 'You cant\'t delete already completed tasks.',
                'data'    => ['task-id' => $task->id],
            ]);
        }

        try {
            $task->delete();
            Storage::disk('local')->delete('images/tasks/' . $task->attachment);
            $task->notification_time = 'changed';

            User::findOrFail($task->user_id)
                ->notify((new TaskTimeExpiring($task)));

            return $this->getSuccessfulJsonResponse(['task-id' => $task->id]);
        } catch (\Throwable $exception) {
            return $this->processFailedResponse($exception);
        }
    }

    public function finish(Task $task)
    {
        if (!$task->isDone() && !$task->hasUncomletedSubTasks($task)) {
            try {
                $task->status = TasksStatusEnum::DONE;
                $task->save();

                if ($task->getOriginal('notify_before')) {
                    $task->notification_time = 'changed';

                    User::findOrFail($this->user->id)
                        ->notify(
                            (new TaskTimeExpiring($task))
                                ->onQueue('mail-queue')
                        );
                };

                return $this->getSuccessfulJsonResponse(['task-id' => $task->id]);
            } catch (\Throwable $exception) {
                return $this->processFailedResponse(
                    $exception,
                    'Task can\'t be completed since it has uncompleted children tasks'
                );
            }
        }
        return response()->json([
            'status'  => 421,
            'message' => 'The task is already done or has uncompleted children tasks.',
            'data'    => ['task-id' => $task->id],
        ]);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection|array $result
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSuccessfulJsonResponse(\Illuminate\Database\Eloquent\Collection|array $result): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status'  => 200,
            'message' => 'success',
            'data'    => $result,
        ]);
    }

    /**
     * @param \Throwable|\Exception $exception
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function processFailedResponse(\Throwable|\Exception $exception, string $info = ''): \Illuminate\Http\JsonResponse
    {
        Log::info($info ?? "$info " . 'Your action didn\'t successful due to ' . $exception->getMessage(), $exception->getTrace());

        return response()->json([
            'message' => $exception->getMessage() . ' at line ' . $exception->getLine() . ' of ' . $exception->getFile()
        ]);
    }

    /**
     * @param array              $changedData
     * @param UpdateTasksRequest $request
     * @param array              $taskData
     * @param                    $attachment
     *
     * @return array
     */
    public function processAttachment(array $changedData, UpdateTasksRequest $request, array $taskData, $attachment): array
    {
        if (key_exists('attachment', $changedData)) {
            $userUpload = $request->file('attachment') ?? null;

            if ($userUpload) {
                $resource = fopen($userUpload->getRealPath(), 'r');
                $originalFileName = $userUpload->getClientOriginalName();
                $taskData = [
                    ...$taskData,
                    ...['attachment' => $originalFileName]
                ];
                Storage::disk('local')->put('images/tasks/' . $originalFileName, $resource);
                Storage::disk('local')->delete('images/tasks/' . $attachment);
            }
        }
        return $taskData;
    }

    /**
     * @param array $changedData
     * @param array $taskData
     * @param Task  $task
     * @param       $userId
     *
     * @return void
     */
    public function processChangedTime(array $changedData, array $taskData, Task $task, $userId): void
    {
        if (key_exists('notify_before', $changedData) || key_exists('expiration_time', $changedData)) {
            $notificationTime = Carbon::createFromTimeString($taskData['expiration_time'])
                ->subMinutes($taskData['notify_before']);
            $delayMinutes = $notificationTime->diffInMinutes(Carbon::now());

            $task->delay = now()->addMinutes($delayMinutes);
            $task->notification_time = 'changed';

            User::findOrFail($userId)
                ->notify(
                    (new TaskTimeExpiring($task))
                        ->onQueue('mail-queue')
                        ->delay(now()->addMinutes($delayMinutes))
                );
        }
    }
}
