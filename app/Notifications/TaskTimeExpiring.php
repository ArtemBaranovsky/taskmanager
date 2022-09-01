<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskTimeExpiring extends Notification implements ShouldQueue
{
    use Queueable;
    /**
     * The name of the queue connection to use when queueing the notification.
     *
     * @var string
     */

    /**
     * Create a new notification instance.
     *
     * @param Task $tasks
     */
    public function __construct(
        private Task $task
    ) {
        $this->delay($task->delay);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        if($this->dontSend($notifiable)) {
            return [];
        }

        return ['mail'];
    }

    /**
     * Checks is notification time changed do avoid sending no longer necessary one.
     *
     * @param $notifiable
     * @return bool
     */
    public function dontSend($notifiable)
    {
        return $this->task->notification_time === 'changed';
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $url = url(base_path(). '/' .$this->task->id);

        return (new MailMessage)
                    ->subject('Task deadline ending up notification.')
                    ->greeting('Hello!')
                    ->line('Your task ' . $this->task->title . ' is coming to deadline!')
                    ->line('In ' . $this->task->notify_before . ' minutes it will end up.')
                    ->action('View Task', $url)
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'task_id'         => $this->task->id,
            'title'           => $this->task->title,
            'status'          => $this->task->status,
            'description'     => $this->task->description,
            'priority'        => $this->task->priority,
            'expiration_time' => $this->task->expiration_time,
        ];
    }

    /**
     * Determine which queues should be used for each notification channel.
     *
     * @return array
     */
    public function viaQueues()
    {
        return [
            'mail' => 'mail-queue',
        ];
    }
}
