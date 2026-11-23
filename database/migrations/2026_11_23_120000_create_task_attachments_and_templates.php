<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - task_attachments: vários anexos por tarefa (imagens e PDF), guardados em pasta privada.
 *   A imagem antiga (tasks.image) continua funcionando para as tarefas que já tinham uma.
 * - task_templates: modelos de tarefa com campos que a pessoa preenche (ex.: Devolução de valores).
 * - tasks.task_template_id / tasks.respostas: de qual modelo a tarefa veio e o que foi preenchido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // quem anexou
            $table->string('nome_original');   // nome do arquivo no computador de quem enviou
            $table->string('arquivo');         // nome aleatório em storage/app/private/task-attachments
            $table->string('mime', 100);
            $table->unsignedInteger('tamanho'); // em bytes
            $table->timestamps();
        });

        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('descricao')->nullable();          // frase curta mostrada na escolha do modelo
            $table->foreignId('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->text('instrucoes')->nullable();           // avisos mostrados no formulário (IMPORTANTE, LEMBRETE...)
            $table->json('campos');                           // [{chave, rotulo, tipo, obrigatorio, opcoes}]
            $table->boolean('pede_equipamento')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('task_template_id')->nullable()->constrained('task_templates')->nullOnDelete();
            $table->json('respostas')->nullable();
        });

        $this->criarModeloDeDevolucao();
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_template_id');
            $table->dropColumn('respostas');
        });

        Schema::dropIfExists('task_templates');
        Schema::dropIfExists('task_attachments');
    }

    /** Modelo de exemplo, enviado pelo Financeiro */
    private function criarModeloDeDevolucao(): void
    {
        $financeiro = DB::table('sectors')->whereRaw('LOWER(name) = ?', ['financeiro'])->value('id');

        $campos = [
            ['chave' => 'cliente', 'rotulo' => 'Código DMS e nome do cliente', 'tipo' => 'texto', 'obrigatorio' => true, 'opcoes' => []],
            ['chave' => 'data_solicitacao', 'rotulo' => 'Data da solicitação do cliente', 'tipo' => 'data', 'obrigatorio' => true, 'opcoes' => []],
            ['chave' => 'valor', 'rotulo' => 'Valor da devolução', 'tipo' => 'valor', 'obrigatorio' => true, 'opcoes' => []],
            ['chave' => 'tipo_documento', 'rotulo' => 'Tipo de documento', 'tipo' => 'opcoes', 'obrigatorio' => true, 'opcoes' => ['Reserva', 'O.S.', 'Requisição']],
            ['chave' => 'numero_documento', 'rotulo' => 'Número do documento', 'tipo' => 'texto', 'obrigatorio' => true, 'opcoes' => []],
            ['chave' => 'dados_bancarios', 'rotulo' => 'Dados bancários', 'tipo' => 'texto_longo', 'obrigatorio' => true, 'opcoes' => []],
            ['chave' => 'motivo', 'rotulo' => 'Motivo da devolução', 'tipo' => 'texto_longo', 'obrigatorio' => true, 'opcoes' => []],
        ];

        DB::table('task_templates')->insert([
            'name' => 'Devolução de valores',
            'descricao' => 'Devolução de valor pago pelo cliente (reserva, O.S. ou requisição).',
            'sector_id' => $financeiro,
            'instrucoes' => "IMPORTANTE: apresentar a nota fiscal de devolução da peça para o estoque (caso haja).\n"
                ."LEMBRETE: anexar os seguintes documentos: comprovante de pagamento / reserva, O.S., requisição / documento de identidade / nota de devolução.",
            'campos' => json_encode($campos, JSON_UNESCAPED_UNICODE),
            'pede_equipamento' => false,
            'ativo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
