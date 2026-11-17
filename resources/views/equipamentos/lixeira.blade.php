@extends('layouts.main')

@section('title', 'Lixeira de equipamentos')

@section('content')
<div id="admin-page">
    <a href="{{ route('equipamentos.index') }}" class="task-back">
        <ion-icon name="arrow-back-outline"></ion-icon> Voltar aos equipamentos
    </a>

    <div class="admin-header">
        <div>
            <h1>Lixeira</h1>
            <p class="subtitle">Equipamentos excluídos. Eles não aparecem nas listas nem nas tarefas novas, mas podem ser restaurados.</p>
        </div>
    </div>

    @if($equipamentos->total() > 0)
        <div class="task-table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th scope="col">Patrimônio</th>
                        <th scope="col">Equipamento</th>
                        <th scope="col">Loja</th>
                        <th scope="col">Excluído em</th>
                        <th scope="col">Por / motivo</th>
                        <th scope="col"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($equipamentos as $equipamento)
                        @php $exclusao = $equipamento->movimentacoes->first(); @endphp
                        <tr>
                            <td data-label="Patrimônio">{{ $equipamento->asset_tag ?: '—' }}</td>
                            <td data-label="Equipamento" class="task-title">
                                <div>
                                    {{ $equipamento->name }}
                                    <span class="chip chip-muted">{{ $equipamento->type }}</span>
                                </div>
                            </td>
                            <td data-label="Loja">{{ $equipamento->store->name ?? 'Sem loja' }}</td>
                            <td data-label="Excluído em">{{ $equipamento->deleted_at->format('d/m/Y H:i') }}</td>
                            <td data-label="Por / motivo">
                                @if($exclusao)
                                    <strong>{{ $exclusao->user->name ?? 'Usuário removido' }}</strong>
                                    @if($exclusao->comentario)
                                        <small class="muted d-block">{{ $exclusao->comentario }}</small>
                                    @endif
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td class="task-action">
                                <form action="{{ route('equipamentos.restore', $equipamento->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Restaurar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pager">
            {{ $equipamentos->links('pagination::bootstrap-5') }}
        </div>
    @else
        <div class="tasks-empty">A lixeira está vazia.</div>
    @endif
</div>
@endsection
