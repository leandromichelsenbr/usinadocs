# ADR-001 — Núcleo PHP leve para o Usina Docs

- **Status:** Aceita
- **Data:** 2026-08-13
- **Decisores:** Usina.BR / mantenedores do Usina Docs

## Contexto

O Usina Docs nasceu a partir da experiência prática do AdvPL Guia e pretende ser um produto open source instalável em servidores próprios. Seus diferenciais são conteúdo editorial estruturado, versões, traduções, referências, mídia e treinamento nativo.

O primeiro protótipo utilizou Laravel 12. Ele confirmou regras essenciais: administração, publicação, revisões imutáveis, blocos e rotas localizadas. Porém, antes de acrescentar editor, mídia, importação/exportação e aprendizado, foi decidido reduzir o peso da fundação técnica.

O produto precisa ser simples de instalar, hospedar e manter em ambientes Linux comuns, inclusive instalações pequenas que não exigem fila, cache distribuído, SPA ou uma etapa obrigatória de compilação JavaScript.

## Decisão

O novo núcleo ativo do Usina Docs usará:

```text
PHP 8.2+
├── Slim 4                 rotas, requisições e middleware
├── Twig                   templates, temas e escaping HTML
├── PDO                    acesso portável ao banco
├── SQLite                 banco padrão de instalação
├── MySQL/MariaDB          opção oficial para instalações maiores
└── Composer               autoload e dependências PHP
```

Node.js, npm e uma SPA não serão requisitos para instalar ou operar a versão alfa. Recursos de navegador serão progressivos: HTML semântico, CSS e JavaScript pequeno quando houver ganho claro de experiência.

PostgreSQL pode ser adicionado após a primeira implementação funcionar com SQLite e MySQL/MariaDB. Filas, cache externo, busca dedicada, WebSocket e serviços de terceiros serão opcionais e só entram quando um caso de uso justificar sua complexidade.

## O que continua válido do protótipo Laravel

Esta decisão não descarta o aprendizado nem as regras já demonstradas. O novo núcleo deve preservar estes contratos:

1. revisão publicada é imutável;
2. toda alteração cria uma nova revisão;
3. rascunhos não aparecem publicamente;
4. cada idioma pode ter slug e publicação próprios;
5. conteúdo é formado por blocos ordenados;
6. acesso administrativo é protegido no servidor;
7. o pacote de conteúdo é independente do banco.

Os documentos [ARCHITECTURE.md](ARCHITECTURE.md) e [CONTENT-PACKAGE.md](CONTENT-PACKAGE.md) continuam sendo referência de domínio. O código Laravel permanece no histórico Git como **protótipo de referência**, mas não receberá novos recursos funcionais.

## Alternativas consideradas

| Alternativa | Decisão | Motivo |
| --- | --- | --- |
| Manter Laravel 12 | Não adotar como núcleo ativo | É produtivo e seguro, mas adiciona convenções, componentes e dependências além do necessário para o primeiro produto instalável. |
| PHP sem qualquer biblioteca | Não adotar | Reduz dependências, mas transfere para o projeto responsabilidades sensíveis de roteamento, middleware, templates e segurança básica. |
| Slim + Twig + PDO | Adotada | Mantém uma base pequena e explícita sem reimplementar componentes web consolidados. |
| JavaScript SPA | Não adotar no alfa | Aumenta build, hospedagem e complexidade sem ser necessária para a experiência editorial inicial. |
| SQLite como único banco | Não adotar | É o padrão simples, mas instalações maiores precisam de uma opção servidor. |

## Consequências

### Positivas

- instalação inicial mais próxima de uma hospedagem PHP convencional;
- menor consumo de memória e menos serviços para operar;
- arquivos e fluxo de requisição mais fáceis de estudar;
- tema renderizado no servidor, favorecendo SEO, acessibilidade e desempenho;
- conteúdo e banco continuam sob controle da própria instalação.

### Custos assumidos

- alguns recursos que Laravel oferece prontos precisarão de integração explícita;
- o time deverá manter disciplina em validação, CSRF, autorização, migrações e testes;
- há trabalho de reimplementação controlada do protótipo antes de novos módulos;
- integração OAuth, e-mail e filas será adicionada apenas quando necessária e com documentação própria.

## Ambiente suportado no alfa

| Item | Padrão | Alternativa |
| --- | --- | --- |
| Sistema | Linux | Windows para desenvolvimento local |
| PHP | 8.2 ou superior | — |
| Servidor web | Apache ou Nginx com PHP-FPM | servidor embutido do PHP para desenvolvimento |
| Banco | SQLite | MySQL/MariaDB |
| Dependências | Composer | — |
| Front-end | HTML, CSS e JavaScript progressivo | sem build obrigatório |

## Plano de transição

1. Não adicionar recursos ao protótipo Laravel além de correções críticas de segurança ou documentação.
2. Criar uma tag de referência do protótipo antes de iniciar a remoção ou reorganização de código.
3. Criar uma base limpa em diretório próprio com o bootstrap Slim, Twig, configuração e testes.
4. Reimplementar uma única fatia vertical: página pública publicada em SQLite, com blocos de texto e código.
5. Acrescentar login local e administração mínima.
6. Reimplementar revisões e localização, confirmando os contratos listados acima.
7. Só então iniciar exportação/importação, mídia, editor avançado e Usina Learn.

Cada passo deve ter uma PR independente, testes automatizados e instruções de instalação atualizadas. A aplicação Laravel só deve ser removida do repositório após a nova base reproduzir o núcleo editorial e houver uma tag de referência acessível.

## Critério para rever esta decisão

Revisar esta ADR se a camada leve não suportar com clareza segurança, migrações, temas, conteúdo estruturado, uploads e o primeiro fluxo de aprendizado; ou se a manutenção de integrações explícitas superar o benefício operacional da simplicidade. A revisão exige uma nova ADR, não uma troca silenciosa de stack.
