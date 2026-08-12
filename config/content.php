<?php

return [
    'demo' => [
        'title' => 'Uma página, muitos usos',
        'summary' => 'Esta demonstração confirma o primeiro princípio do Usina Docs: o conteúdo nasce estruturado para ser lido, traduzido, revisado e posteriormente reutilizado em aulas.',
        'status' => 'Demonstração',
        'updated_at' => '2026-08-11',
        'blocks' => [
            [
                'type' => 'Texto',
                'title' => 'Conhecimento reutilizável',
                'body' => 'Uma explicação pode atender à consulta rápida de quem pesquisa um assunto e, mais tarde, compor uma lição, uma revisão ou uma atividade. O conteúdo continua sendo uma única fonte editorial.',
            ],
            [
                'type' => 'Exemplo',
                'title' => 'Um bloco com intenção clara',
                'code' => "# Modelo conceitual\nPágina → Revisão → Blocos → Traduções\n                 ↘ Aulas e atividades",
            ],
            [
                'type' => 'Referência',
                'title' => 'Rastreabilidade desde o início',
                'body' => 'As futuras páginas registrarão autoria, data de criação e revisão, fontes, licenças e o estado de tradução. O objetivo é manter o conhecimento útil e verificável.',
            ],
        ],
    ],
];
