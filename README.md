# Fanfiction Manager (WP Fanfic Plugin)

Plugin de WordPress que transforma um site WordPress numa plataforma dedicada à publicação de fanfiction, com uma interface pensada para autores e leitores no *frontend*.

## Funcionalidades

- Histórias e capítulos como tipos de conteúdo próprios (`fanfiction_story`, `fanfiction_chapter`), com taxonomias (por exemplo género e estado) e taxonomias personalizadas.
- Painel do autor no *frontend*, formulários de história e de capítulo, edição de perfil e páginas de membros.
- Coautores, seguidores, avaliações, contagem de visualizações e progresso de leitura.
- Pesquisa com filtros (fandoms, idiomas, estado), com tabelas de índice de pesquisa.
- Comentários, notificações na aplicação e por correio eletrónico (fila de envio).
- Moderação: denúncias, bloqueios, mensagens e registo de moderação.
- Funções e permissões próprias (autor, moderador), com limitação de pedidos e camada de segurança para AJAX.
- Assistente de configuração inicial, gestão de URLs/*slugs* e da página inicial, cache baseada em *transients*, SEO, *widgets* e *shortcodes*.
- Exportação e importação de dados.

## Requisitos

- WordPress 5.8 ou superior
- PHP 7.4 ou superior

## Instalação

1. Copie a pasta do repositório para `wp-content/plugins/` (por exemplo `wp-content/plugins/fanfiction-manager/`).
2. No painel de administração do WordPress, active **Fanfiction Manager** em *Plugins*.
3. Siga o assistente de configuração inicial (página inicial, URLs, funções de utilizador, taxonomias).

## Estrutura das pastas

- `fanfiction-manager.php`: ficheiro principal do plugin (cabeçalho WordPress).
- `includes/`: classes PHP do plugin; subpastas `admin/`, `handlers/`, `shortcodes/`, `widgets/`, `helpers/` e `cache/`.
- `templates/`: modelos de página e de correio eletrónico.
- `assets/`: CSS, JavaScript e imagens.
- `database/`: dados de apoio (fandoms, idiomas).
- `docs/`: documentação técnica interna (funcionalidades, modelo de dados, *shortcodes*, etc.).
- `scripts/`: ferramentas de desenvolvimento (sessão de navegador com Playwright).

## Estado do projeto

Em desenvolvimento ativo. Versão do plugin: 1.0.17. O repositório inclui ficheiros de trabalho (`AGENTS.md`, `CLAUDE.md`, `Bugs.txt`) usados no desenvolvimento assistido por IA. Não há testes automatizados configurados.

## Licença

O cabeçalho do plugin indica GPL v2 ou posterior.
