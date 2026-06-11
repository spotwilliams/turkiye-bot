<?php

namespace App\Mail;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TaskReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public string $reminderMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reminder: '.$this->task->description,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.task-reminder',
            with: [
                'reminderMessage' => $this->reminderMessage,
                'task' => $this->task,
            ],
        );
    }
}
