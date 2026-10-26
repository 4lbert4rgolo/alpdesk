<?php

namespace App\Support;

use App\Models\Equipment;
use Illuminate\Support\Str;

/**
 * Colunas da planilha de equipamentos (exportação e importação).
 */
class EquipmentColumns
{
    /** chave interna => título na planilha */
    public const COLUNAS = [
        'id' => 'ID',
        'patrimonio' => 'Patrimônio',
        'nome' => 'Equipamento',
        'tipo' => 'Tipo',
        'situacao' => 'Situação',
        'loja' => 'Loja',
        'setor' => 'Setor',
        'usuario' => 'Usuário',
        'numero_serie' => 'Número de série',
        'hostname' => 'Nome na rede',
        'anydesk' => 'AnyDesk',
        'especificacoes' => 'Especificações',
        'observacoes' => 'Observações',
    ];

    /** título normalizado (sem acento, espaço ou pontuação) => chave interna */
    private const ALIAS = [
        'id' => 'id',
        'legacyid' => 'legacy_id',
        'patrimonio' => 'patrimonio',
        'equipamento' => 'nome',
        'nome' => 'nome',
        'tipo' => 'tipo',
        'situacao' => 'situacao',
        'status' => 'situacao',
        'loja' => 'loja',
        'setor' => 'setor',
        'usuario' => 'usuario',
        'numerodeserie' => 'numero_serie',
        'numeroserie' => 'numero_serie',
        'serie' => 'numero_serie',
        'nomenarede' => 'hostname',
        'hostname' => 'hostname',
        'anydesk' => 'anydesk',
        'iddoanydesk' => 'anydesk',
        'especificacoes' => 'especificacoes',
        'observacoes' => 'observacoes',
    ];

    public static function normalizar(string $texto): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii(trim($texto)))) ?? '';
    }

    public static function chaveDoCabecalho(string $titulo): ?string
    {
        return self::ALIAS[self::normalizar($titulo)] ?? null;
    }

    /** @return array<int, string> */
    public static function cabecalho(): array
    {
        return array_values(self::COLUNAS);
    }

    /** @return array<int, string> */
    public static function linha(Equipment $equipamento): array
    {
        return [
            (string) $equipamento->id,
            (string) $equipamento->asset_tag,
            (string) $equipamento->name,
            (string) $equipamento->type,
            $equipamento->statusLabel(),
            (string) ($equipamento->store->name ?? ''),
            (string) ($equipamento->sector->name ?? ''),
            (string) $equipamento->user_name,
            (string) $equipamento->serial_number,
            (string) $equipamento->hostname,
            (string) $equipamento->anydesk_id,
            (string) $equipamento->specs,
            (string) $equipamento->notes,
        ];
    }

    /** Linha de exemplo usada no arquivo modelo. */
    public static function linhaDeExemplo(): array
    {
        return [
            '', '01234', 'Notebook Dell Latitude 3420', 'Notebook', 'Em uso', 'Nome da loja', 'Nome do setor',
            'Nome de quem usa', 'ABC1234', 'NOME-DO-PC', '123456789', 'Core i5, 8 GB de RAM, SSD de 256 GB', 'Observação opcional',
        ];
    }

    /**
     * Patrimônio: "0" e vazio significam "sem patrimônio". Números com menos de
     * 5 dígitos ganham zeros à esquerda (o Excel costuma remover o zero inicial).
     */
    public static function patrimonio(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);

        if ($valor === '' || preg_match('/^0+$/', $valor)) {
            return null;
        }

        if (ctype_digit($valor) && strlen($valor) < 5) {
            return str_pad($valor, 5, '0', STR_PAD_LEFT);
        }

        return $valor;
    }

    /** AnyDesk: somente os dígitos; zeros e vazio significam "sem AnyDesk". */
    public static function anydesk(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $valor);

        return ($digitos === '' || preg_match('/^0+$/', $digitos)) ? null : $digitos;
    }
}
