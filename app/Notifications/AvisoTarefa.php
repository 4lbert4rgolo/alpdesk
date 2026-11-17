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
            ->line('**#'.$this->tarefa->id.' · '.self::textoPuro($this->tarefa->title).'**');

        foreach ($this->linhas as $linha) {
            $mensagem->line(self::textoPuro($linha));
        }

        return $mensagem
            ->action('Abrir a tarefa', url('/tasks/'.$this->tarefa->id))
            ->salutation('Equipe '.config('app.name'));
    }

    /**
     * O e-mail é montado em Markdown. Título, comentários e nomes são digitados pelas
     * pessoas, então os símbolos do Markdown são neutralizados: ninguém consegue
     * colocar um link disfarçado (ex.: [Clique aqui](site-falso)) num aviso do sistema.
     */
    public static function textoPuro(string $texto): string
    {
        $texto = str_replace(["\r", "\n"], ' ', $texto);

        return preg_replace('/([\\\\`*_{}\[\]()#+\-.!|<>~])/', '\\\\$1', $texto);
    }
}
