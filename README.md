# AlpDesk

Sistema interno de **chamados (tarefas) de TI** e **inventário de equipamentos**, feito em Laravel.

## O que o sistema faz

| Módulo | Resumo |
|---|---|
| **Tarefas** | Abrir chamados com prioridade (Baixa, Média, Alta, Urgente), prazo, contexto, imagem e equipamento ligado. Situações: Aberta, Em andamento, Aguardando, Concluída, Cancelada. |
| **Linha do tempo** | Cada tarefa mostra comentários, autorizações e tudo o que aconteceu (quem assumiu, mudanças de situação, edições). |
| **Participantes** | Pessoas adicionadas a uma tarefa para acompanhar, comentar e registrar **Autorizo / Não autorizo** (ex.: gerente que precisa aprovar uma troca). |
| **Avisos por e-mail** | Nova tarefa (para os técnicos), tarefa assumida, comentário, mudança de situação e novo participante. |
| **Minhas tarefas** | As tarefas que a pessoa abriu, atende ou participa. |
| **Painel** | Indicadores das tarefas em aberto, vencimentos, carga por técnico, tarefas por loja e por tipo de equipamento. |
| **Equipamentos** | Inventário por loja e setor, importação/exportação por planilha, histórico de mudanças e etiqueta com QR Code. |
| **Administração** | Usuários e grupos de acesso com permissões. |

## Papéis em uma tarefa

| Ação | Quem abriu | Responsável | Participante | Técnico (pode assumir) | Gerencia todas |
|---|:-:|:-:|:-:|:-:|:-:|
| Ver a tarefa | ✅ | ✅ | ✅ | ✅ | ✅ |
| Comentar | ✅ | ✅ | ✅ | ✅ | ✅ |
| Autorizo / Não autorizo | – | – | ✅ | – | ✅ |
| Assumir | – | – | – | ✅ | ✅* |
| Concluir, aguardar, retomar | – | ✅ | – | – | ✅ |
| Cancelar, editar, excluir | ✅ | – | – | – | ✅ |
| Reabrir | ✅ | ✅ | – | – | ✅ |
| Adicionar/remover participantes | ✅ | ✅ | só sair | – | ✅ |

\* se também tiver a permissão "Assumir tarefas".

Com `TASKS_VISIBILITY_RESTRICTED=true`, quem não pode assumir tarefas (grupo Solicitante) só vê as tarefas que abriu, atende ou participa.

## Rodando no computador (Laragon)

```
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan alpdesk:admin seu@email.com.br   # depois de criar a conta pela tela de cadastro
```

Comandos úteis:

```
php artisan test                               # roda os testes
php artisan alpdesk:importar-equipamentos      # importa database/imports/equipamentos_inicial.csv
php artisan alpdesk:migrar-imagens             # move imagens antigas para a pasta privada
```

## Configuração

- `config/tarefas.php`: situações, prioridades e avisos por e-mail.
- `config/permissoes.php`: permissões que aparecem na tela de grupos.
- `config/equipamentos.php`: tipos e situações dos equipamentos.
- `config/alpdesk.php`: cadastro, visibilidade das tarefas, HTTPS e logo.

## Publicação

Veja **[docs/DEPLOY.md](docs/DEPLOY.md)** (Ubuntu + Nginx + PHP 8.3 + MariaDB, ou servidor na empresa com Cloudflare Tunnel).

Nunca envie para o Git: `.env`, banco de dados, backups e imagens dos chamados.
