<?php

use App\Enum\TasksStatusEnum;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->default(0);
            $table->enum('status', array_map(fn($status) => $status->value, TasksStatusEnum::cases()))
                ->default(TasksStatusEnum::TODO->value);
            $table->unsignedTinyInteger('priority');
            $table->text('title');
            $table->mediumText('description');
            $table->dateTime('expiration_time')->nullable();
            $table->unsignedSmallInteger('notify_before')->nullable();
            $table->text('attachment')->nullable();
            $table->foreignIdFor(User::class)->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tasks');
    }
};
