<?php

namespace App\Http\Requests;

use App\Enum\TasksStatusEnum;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateTasksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * @return bool
     */
    public function authorize()
    {
        return auth()->guest() ? false : true;
    }

    /**
     * Get the validation rules that apply to the request.
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'id'              => [
                'required',
                'numeric',
                function ($param, $value, $fail) {
                    if (!Task::findOrFail($value)) {
                        $fail("The $param is invalid.");
                    }
                },
            ],
            'status'          => fn($status) => in_array($status, TasksStatusEnum::cases()),
            'title'           => 'string|min:5',
            'description'     => 'string|min:5',
            'priority'        => 'nullable|numeric|min:1|max:5',
            'expiration_time' => 'nullable|required|date|after:now',
            'notify_before'   => [
                'required_with:expiration_time|numeric|min:1|max:1440',
                function ($param, $value, $fail) {
                    if ($value > Carbon::createFromTimeString($this['expiration_time'])
                            ->subMinutes($value)->diffInMinutes(Carbon::now())) {
                        $fail("The $param is invalid.");
                    }
                },
            ],
            'parent_id'       => [
                'required',
                'numeric',
                function ($param, $value, $fail) {
                    if (!Task::where($param, '=', $value)) {
                        $fail("The $param is invalid.");
                    }
                },
            ],
            'attachment'      => 'nullable|file|image|mimes:jpg,bmp,png',
        ];
    }
}
