<?php

namespace App\Http\Requests;

use App\Enum\TasksStatusEnum;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreTasksRequest extends FormRequest
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
            'title'           => 'required|string|min:5',
            'description'     => 'required|string|min:5',
            'priority'        => 'required|numeric|min:1|max:5',
            'expiration_time' => 'date|after:now',
            'notify_before'   => [
                'required_with:expiration_time|numeric',
                'between:1,1440',
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
                    if ($value > 0 && !Task::where('parent_id', '=', $value)->first()) {
                        $fail("The $param is invalid.");
                    }
                },
            ],
            'attachment'      => 'nullable|file|image|mimes:jpg,bmp,png',
        ];
    }
}
