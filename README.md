# MathRabbit

Portal gamificado de Matemática para o Ensino Fundamental II. A aplicação usa PHP, MySQL/MariaDB, HTML, CSS e JavaScript sem dependências externas de backend.

## Banco de dados MySQL/MariaDB

1. Inicie o MySQL/MariaDB e abra o phpMyAdmin.
2. Crie um banco de dados chamado `mathrabbit_db` com collation `utf8mb4_unicode_ci` (na hospedagem, use o nome definido em `config.php`).
3. Selecione esse banco, abra **Importar** e importe `banco.sql`.
4. Se necessário, edite `config.php` com as credenciais do banco. Variáveis de ambiente `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` e `DB_PASSWORD` podem substituir esses valores.
5. Ative as extensões PHP `PDO` e `pdo_mysql`, inicie o Apache e acesse o site. Se a pasta estiver em `C:\laragon\www\Meu_TCC`, use `http://localhost/Meu_TCC/`.

O projeto requer MySQL 8.0+ ou MariaDB 10.2+ (a API usa funções de janela para os indicadores de desempenho). Os valores padrão de `config.php` são host `127.0.0.1`, porta `3306`, banco `mathrabbit_db`, usuário `root` e senha vazia. Em hospedagem, configure `config.php` ou as variáveis de ambiente com os dados fornecidos pelo provedor. O usuário MySQL precisa ter acesso ao banco e permissões para ler, inserir, atualizar e excluir dados.

Para testar localmente, também é possível iniciar o servidor embutido do PHP a partir desta pasta:

```powershell
php -S 127.0.0.1:8000
```

Depois, acesse `http://127.0.0.1:8000/`. O servidor embutido não substitui o MySQL/MariaDB: o banco precisa estar ativo.

O schema SQL cria as tabelas e os dados iniciais das medalhas. As tabelas não são criadas automaticamente pela API: importe o arquivo SQL uma vez antes de abrir o site.

Eventos de sessão (bootstrap, login, logout, falhas de CSRF e erros de banco sem credenciais ou tokens) são registrados em `data/session.log`. O diretório `data` é bloqueado para acesso HTTP pelo `.htaccess`; em produção, mantenha essa proteção e permita que o PHP grave nesse arquivo.

## O que já está implementado

- Cadastro, login e sessão persistente, com senha armazenada usando `password_hash`.
- Perfis de estudante e professor; as contas de professor usam o mesmo portal de jogos.
- Oito jogos com perguntas geradas novamente a cada desafio: números inteiros, frações, equações, geometria, corrida das tabuadas, teclado de código secreto, sequências para completar uma ponte e o Modo PRO.
- Fases com dez perguntas, três dificuldades, dicas, alternativas e explicação imediata após cada resposta; os três jogos novos usam pistas de corrida, teclado numérico e peças interativas de sequência. Em todos os jogos regulares, os níveis Médio e Difícil aumentam gradualmente os valores e a complexidade em relação ao Fácil. A Corrida das Tabuadas no Difícil usa multiplicações de dois fatores entre 11 e 25 e explica a decomposição em dezenas e unidades. Poções de Frações usa denominadores diferentes no Médio e amplia a faixa no Difícil; o Médio alterna soma e subtração, encontra o MMC e concede 10 moedas extras ao concluir a fase. A Ponte dos Padrões no nível Difícil / Avançado inclui padrões intercalados, progressões de diferenças crescentes e sequências multiplicativas; cada acerto vale 10 moedas e concluir a fase concede bônus de 20 moedas.
- Modo PRO desbloqueado após três fases concluídas: dez questões avançadas sobre sistemas, equações quadráticas, Pitágoras, trigonometria básica, áreas compostas, porcentagens sucessivas, diferença de quadrados e propriedades de potências. O desafio tem cronômetro visual, tema dourado/neon e recompensa única ao concluir: 100 moedas com 6 a 9 acertos e 200 moedas com 10 acertos; com 5 acertos ou menos não há bônus. Respostas corretas no PRO não concedem moedas adicionais por questão.
- Área individual "Meu Desempenho", com indicadores de acertos e erros por assunto, destaque de ponto forte e área de melhoria, recomendações pedagógicas baseadas nos erros recentes, barras de maestria, comparação de evolução entre os últimos blocos de respostas e explicações das questões erradas.
- Registro de respostas, partidas concluídas, pontuação, moedas, precisão e histórico no MySQL/MariaDB.
- Ranking completo dos estudantes, da maior para a menor pontuação acumulada, atualizado depois de cada resposta.
- Medalhas por primeira partida, fase perfeita e 10 respostas corretas.
- Transformações do personagem principal por pontuação: Coelho (0), Tartaruga (100), Raposa (300), Pandinha (600) e Unicórnio (1.000 pontos). A forma ativa aparece no painel principal e no palco de transformações; os novos animais usam o mesmo tratamento visual kawaii dos antigos companheiros. Cada desbloqueio mostra uma mensagem própria, com animação e som suave de conquista.
- Loja independente de 36 companions em `companions.json`, organizados em cinco raridades com preços progressivos, descrição visual e mensagem única por bichinho. Os lendários têm emojis/insígnias próprios, equipamentos exclusivos e efeitos de brilho; a Água-viva-lua é diferenciada da Água-viva comum por seus quatro anéis internos e tentáculos curtos. As compras descontam moedas e ficam salvas no MySQL/MariaDB; o estudante pode equipar um companion para exibi-lo ao lado da forma ativa sem alterar as transformações por pontos.
- Desbloqueios de novas formas e compras de companions exibem uma celebração em destaque, com personagem animado, brilho, partículas, mensagem personalizada e som suave; desbloqueios em sequência são apresentados um por vez.
- Progressão persistida por usuário no MySQL/MariaDB em `unlocked_characters` e `active_character`.
- Pet System persistido por usuário no MySQL/MariaDB: nome e energia do personagem (menos 10 a cada 10 minutos), contador de carinhos com animação e recuperação da energia para 100% ao iniciar uma partida.
- Interface responsiva, fundo animado e suporte a `prefers-reduced-motion`.
- Recursos de acessibilidade opcionais no cadastro e ajustáveis a qualquer momento na conta: modo de alto contraste (texto branco sobre fundo preto) e tamanho de texto menor, padrão ou maior, com preferências salvas no perfil.
- Coelhinho decorativo bebê sem roupas, que atravessa a parte inferior da tela, muda de direção, dá pulinhos e solta corações ao receber atenção; este mascote decorativo é separado do personagem principal transformável.
- Trilha musical original sintetizada no navegador, com sons diferentes para acertos e erros; pode ser ativada ou desativada pelo botão de música.

Cada resposta correta nos jogos regulares vale 10 pontos e 5 moedas; completar a fase concede mais 10 moedas (na Ponte avançada, o acerto vale 10 e a conclusão mais 20). O nível médio é liberado após uma fase concluída e o difícil e o Modo PRO após três. No PRO, a recompensa única é de 100 moedas com mais da metade das questões corretas (6 a 9) ou 200 moedas com aproveitamento perfeito; com 5 acertos ou menos não há bônus e não há moedas por resposta. A música só começa após a primeira interação com o site, pois os navegadores exigem interação para reproduzir áudio, e sua preferência é salva neste dispositivo.

## Observação

Esta é uma base funcional para demonstração e evolução do projeto. Em uma implantação pública, configure HTTPS, backups periódicos do banco MySQL/MariaDB, políticas de privacidade e os controles administrativos específicos para professores. Não publique nem compartilhe as credenciais do banco. As contas e o histórico que existiam no arquivo SQLite não são copiados automaticamente para o MySQL.
