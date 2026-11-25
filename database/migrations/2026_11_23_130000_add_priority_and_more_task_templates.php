<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - task_templates.prioridade: prioridade que já vem marcada ao abrir a tarefa pelo modelo.
 * - Cadastra os modelos enviados pelo Financeiro e pela TI (só os que ainda não existem).
 *   O campo "OBS" dos modelos originais virou as "Observações" que toda tarefa já tem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->unsignedTinyInteger('prioridade')->nullable()->after('sector_id');
        });

        $financeiro = DB::table('sectors')->whereRaw('LOWER(name) = ?', ['financeiro'])->value('id');
        $ti = DB::table('sectors')->whereRaw('LOWER(name) = ?', ['ti'])->value('id');

        $texto = fn ($chave, $rotulo, $obrigatorio = true) => ['chave' => $chave, 'rotulo' => $rotulo, 'tipo' => 'texto', 'obrigatorio' => $obrigatorio, 'opcoes' => []];
        $longo = fn ($chave, $rotulo) => ['chave' => $chave, 'rotulo' => $rotulo, 'tipo' => 'texto_longo', 'obrigatorio' => true, 'opcoes' => []];
        $valor = fn ($chave, $rotulo) => ['chave' => $chave, 'rotulo' => $rotulo, 'tipo' => 'valor', 'obrigatorio' => true, 'opcoes' => []];
        $lista = fn ($chave, $rotulo, $opcoes) => ['chave' => $chave, 'rotulo' => $rotulo, 'tipo' => 'opcoes', 'obrigatorio' => true, 'opcoes' => $opcoes];
        $de = fn ($tipo, $chave, $rotulo) => ['chave' => $chave, 'rotulo' => $rotulo, 'tipo' => $tipo, 'obrigatorio' => true, 'opcoes' => []];

        $modelos = [
            [
                'name' => 'Cancelamento de nota fiscal',
                'descricao' => 'Pedido de cancelamento de NF emitida.',
                'sector_id' => $financeiro,
                'prioridade' => 2,
                'instrucoes' => null,
                'campos' => [
                    $texto('numero_nf', 'Número da NF'),
                    $texto('codigo_cliente', 'Código do cliente (DMS)'),
                    $valor('valor', 'Valor'),
                ],
            ],
            [
                'name' => 'Prestação de contas do fundo reserva',
                'descricao' => 'Prestação de contas mensal do fundo reserva.',
                'sector_id' => $financeiro,
                'prioridade' => 3,
                'instrucoes' => null,
                'campos' => [
                    $texto('mes', 'Mês (ex.: 11/2026)'),
                    $valor('valor_utilizado', 'Valor utilizado'),
                ],
            ],
            [
                'name' => 'Devolução financeira de veículos',
                'descricao' => 'Devolução de valor pago na compra de veículo.',
                'sector_id' => $financeiro,
                'prioridade' => 3,
                'instrucoes' => "IMPORTANTE: caso exista nota fiscal emitida e veículo vinculado à proposta, é necessário enviar um e-mail ao setor de Faturamento para análise de quebra de contrato e cancelamento da nota fiscal.\n"
                    ."LEMBRETE: anexar os seguintes documentos: comprovante de pagamento / pedido de venda / documento de identidade / nota de devolução.",
                'campos' => [
                    $texto('cliente', 'Código DMS e nome do cliente'),
                    $de('data', 'data_solicitacao', 'Data da solicitação do cliente'),
                    $valor('valor', 'Valor da devolução'),
                    $texto('chassi', 'Chassi do veículo'),
                    $texto('numero_nf', 'Número da NF'),
                    $longo('dados_bancarios', 'Dados bancários'),
                    $longo('motivo', 'Motivo da devolução'),
                ],
            ],
            [
                'name' => 'Transferência de adiantamento de cliente',
                'descricao' => 'Transferir adiantamento do cliente para outro documento.',
                'sector_id' => $financeiro,
                'prioridade' => 2,
                'instrucoes' => null,
                'campos' => [
                    $texto('codigo_cliente', 'Código do cliente (DMS)'),
                    $lista('tipo_documento', 'Tipo de documento', ['Requisição', 'O.S.', 'Pedido de venda']),
                    $texto('numero_documento', 'Número do documento'),
                    $valor('valor', 'Valor'),
                ],
            ],
            [
                'name' => 'Lançamento de comprovante +24h',
                'descricao' => 'Lançamento de comprovante com mais de 24 horas.',
                'sector_id' => $financeiro,
                'prioridade' => 3,
                'instrucoes' => null,
                'campos' => [
                    $texto('codigo_cliente', 'Código do cliente (DMS)'),
                    $lista('tipo_documento', 'Tipo de documento', ['Reserva', 'Requisição', 'O.S.']),
                    $texto('numero_documento', 'Número do documento'),
                    $longo('motivo', 'Motivo'),
                ],
            ],
            [
                'name' => 'Solicitação de equipamentos de informática',
                'descricao' => 'Pedido de computador, monitor, impressora e outros.',
                'sector_id' => $ti,
                'prioridade' => 3,
                'instrucoes' => null,
                'campos' => [
                    $de('loja', 'loja', 'Loja'),
                    $de('setor', 'setor', 'Setor'),
                    $texto('usuario', 'Usuário (quem vai usar)'),
                    $longo('equipamento', 'Equipamento solicitado'),
                ],
            ],
        ];

        foreach ($modelos as $modelo) {
            if (DB::table('task_templates')->where('name', $modelo['name'])->exists()) {
                continue;
            }

            DB::table('task_templates')->insert([
                'name' => $modelo['name'],
                'descricao' => $modelo['descricao'],
                'sector_id' => $modelo['sector_id'],
                'prioridade' => $modelo['prioridade'],
                'instrucoes' => $modelo['instrucoes'],
                'campos' => json_encode($modelo['campos'], JSON_UNESCAPED_UNICODE),
                'pede_equipamento' => false,
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropColumn('prioridade');
        });
    }
};
