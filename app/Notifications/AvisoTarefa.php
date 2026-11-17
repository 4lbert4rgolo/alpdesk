<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail curto avisando que algo aconteceu em uma tarefa.
 */
class AvisoTarefa extends Notification
{
    use Queueable;

    /**
     * @param  string  $assunto  Ex.: "nova tarefa aberta"
     * @param  array<int, string>  $linhas  Parágrafos do e-mail
     */
    public function __construct(
        public Task $tarefa,
        public string $assunto,
        public array $linhas = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensagem = (new MailMessage)
            ->subject('['.config('app.name').'] Tarefa #'.$this->tarefa->id.': '.$this->assunto)
            ->greeting('Olá, '.strtok((string) $notifiable->name, ' ').'!')
            ->line('**#'.$this->tarefa->id.' · '.$this->tarefa->title.'**');

        foreach ($this->linhas as $linha) {
            $mensagem->line($linha);
        }

        return $mensagem
            ->action('Abrir a tarefa', url('/tasks/'.$this->tarefa->id))
            ->salutation('Equipe '.config('app.name'));
    }
}
