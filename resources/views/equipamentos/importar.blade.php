@extends('layouts.main')

@section('title', 'Importar equipamentos')

@section('content')
<div id="admin-page" class="admin-narrow">
    <a href="{{ route('equipamentos.index') }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar aos equipamentos
    </a>

    <h1 class="admin-title">Importar equipamentos</h1>

    <form method="POST" action="{{ route('equipamentos.import.store') }}" enctype="multipart/form-data" class="admin-form">
        @csrf

        <div class="field">
            <p class="field-hint">
                Envie uma planilha {{ $xlsDisponivel ? 'CSV, XLS ou XLSX' : 'CSV' }} com os equipamentos. A primeira linha deve ter os títulos das colunas.
                Baixe o modelo para ver o formato:
                <a href="{{ route('equipamentos.template', ['formato' => 'csv']) }}">modelo CSV</a>@if($xlsDisponivel)
                    ou <a href="{{ route('equipamentos.template', ['formato' => 'xls']) }}">modelo XLS</a>@endif.
            </p>
            @unless($xlsDisponivel)
                <p class="field-hint">Para aceitar XLS e XLSX, instale o pacote <code>phpoffice/phpspreadsheet</code> com o Composer.</p>
            @endunless
        </div>

        <div class="field">
            <label for="arquivo">Arquivo</label>
            <input type="file" id="arquivo" name="arquivo" accept=".csv,.txt{{ $xlsDisponivel ? ',.xls,.xlsx' : '' }}" required>
            @error('arquivo')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <fieldset class="field">
            <legend>Opções</legend>
            <label class="check">
                <input type="checkbox" name="criar_lojas" value="1">
                <span>
                    <strong>Criar lojas e setores que ainda não existem</strong>
                    <small>Sem esta opção, linhas com loja ou setor desconhecido são recusadas (evita criar uma loja por erro de digitação).</small>
                </span>
            </label>
            <label class="check">
                <input type="checkbox" name="atualizar" value="1">
                <span>
                    <strong>Atualizar equipamentos que já existem</strong>
                    <small>O equipamento é reconhecido pelo ID ou pelo patrimônio. Os campos da planilha substituem os do sistema; sem esta opção, esses equipamentos são ignorados.</small>
                </span>
            </label>
        </fieldset>

        <div class="form-actions">
            <button type="submit" name="acao" value="simular" class="btn btn-outline-secondary">Simular (não grava nada)</button>
            <button type="submit" name="acao" value="importar" class="btn btn-primary">Importar</button>
        </div>
    </form>

    @if($relatorio)
        <div class="import-report">
            @if($relatorio['falha'])
                <p class="note-alert">
                    <ion-icon name="alert-circle-outline"></ion-icon>
                    <span>{{ $relatorio['falha'] }}</span>
                </p>
            @else
                <h2>{{ $relatorio['simulado'] ? 'Resultado da simulação' : 'Importação concluída' }}</h2>
                <p class="muted">
                    {{ $relatorio['simulado']
                        ? 'Nada foi gravado. Se estiver tudo certo, escolha o arquivo de novo e clique em "Importar".'
                        : 'As linhas sem erro já estão no sistema.' }}
                </p>

                <ul class="report-stats">
                    <li><strong>{{ $relatorio['criados'] }}</strong> equipamentos novos</li>
                    <li><strong>{{ $relatorio['atualizados'] }}</strong> atualizados</li>
                    <li><strong>{{ $relatorio['ignorados'] }}</strong> ignorados (já existem)</li>
                    <li><strong>{{ $relatorio['lojas_novas'] }}</strong> lojas novas</li>
                    <li><strong>{{ $relatorio['setores_novos'] }}</strong> setores novos</li>
                    <li class="{{ count($relatorio['erros']) > 0 ? 'is-bad' : '' }}"><strong>{{ count($relatorio['erros']) }}</strong> problemas</li>
                </ul>

                @if(count($relatorio['erros']) > 0)
                    <h3>Linhas recusadas</h3>
                    <ul class="report-list">
                        @foreach(array_slice($relatorio['erros'], 0, 100) as $erro)
                            <li><strong>Linha {{ $erro['linha'] }}:</strong> {{ $erro['mensagem'] }}</li>
                        @endforeach
                    </ul>
                    @if(count($relatorio['erros']) > 100)
                        <p class="muted">Mostrando os 100 primeiros de {{ count($relatorio['erros']) }} problemas.</p>
                    @endif
                @endif

                @if(count($relatorio['avisos']) > 0)
                    <h3>Avisos</h3>
                    <ul class="report-list">
                        @foreach(array_slice($relatorio['avisos'], 0, 100) as $aviso)
                            <li><strong>Linha {{ $aviso['linha'] }}:</strong> {{ $aviso['mensagem'] }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>
    @endif
</div>
@endsection
