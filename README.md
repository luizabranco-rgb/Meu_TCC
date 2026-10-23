# MathRabbit

Portal gamificado de Matemática para o Ensino Fundamental II. A aplicação usa PHP, SQLite, HTML, CSS e JavaScript sem dependências externas de backend.

## Executar no Laragon

1. Coloque a pasta do projeto em `C:\laragon\www\mathplay-solutions` (ou na pasta `www` da sua instalação).
2. Inicie o Apache no Laragon.
3. Acesse `http://localhost/mathplay-solutions/`.
4. Crie uma conta de estudante ou professor e comece a jogar.

Também é possível iniciar o servidor embutido do PHP a partir desta pasta:

```powershell
php -S 127.0.0.1:8000
```

Depois, acesse `http://127.0.0.1:8000/`.

O PHP precisa das extensões `PDO` e `pdo_sqlite` habilitadas. Na primeira requisição, a aplicação cria automaticamente o banco SQLite e suas tabelas em `mathplay-solutions-data`, fora da pasta pública do site. Você pode definir a variável de ambiente `MATHPLAY_DATA_DIR` para escolher outro diretório persistente, também fora da pasta pública. Não é necessário instalar pacotes nem configurar um servidor MySQL.

## O que já está implementado

- Cadastro, login e sessão persistente, com senha armazenada usando `password_hash`.
- Perfis de estudante e professor; as contas de professor usam o mesmo portal de jogos.
- Oito jogos com perguntas geradas novamente a cada desafio: números inteiros, frações, equações, geometria, corrida das tabuadas, teclado de código secreto, sequências para completar uma ponte e o Modo PRO.
- Fases com dez perguntas, três dificuldades, dicas, alternativas e explicação imediata após cada resposta; os três jogos novos usam pistas de corrida, teclado numérico e peças interativas de sequência. Em todos os jogos regulares, os níveis Médio e Difícil aumentam gradualmente os valores e a complexidade em relação ao Fácil. A Corrida das Tabuadas no Difícil usa multiplicações de dois fatores entre 11 e 25 e explica a decomposição em dezenas e unidades. Poções de Frações usa denominadores diferentes no Médio e amplia a faixa no Difícil; o Médio alterna soma e subtração, encontra o MMC e concede 10 moedas extras ao concluir a fase. A Ponte dos Padrões no nível Difícil / Avançado inclui padrões intercalados, progressões de diferenças crescentes e sequências multiplicativas; cada acerto vale 10 moedas e concluir a fase concede bônus de 20 moedas.
- Modo PRO desbloqueado após três fases concluídas: dez questões avançadas sobre sistemas, equações quadráticas, Pitágoras, trigonometria básica, áreas compostas, porcentagens sucessivas, diferença de quadrados e propriedades de potências. O desafio tem cronômetro visual, tema dourado/neon e recompensa única ao concluir: 100 moedas com 6 a 9 acertos e 200 moedas com 10 acertos; com 5 acertos ou menos não há bônus. Respostas corretas no PRO não concedem moedas adicionais por questão.
- Área individual "Meu Desempenho", com indicadores de acertos e erros por assunto, destaque de ponto forte e área de melhoria, recomendações pedagógicas baseadas nos erros recentes, barras de maestria, comparação de evolução entre os últimos blocos de respostas e explicações das questões erradas.
- Registro de respostas, partidas concluídas, pontuação, moedas, precisão e histórico no SQLite.
- Ranking completo dos estudantes, da maior para a menor pontuação acumulada, atualizado depois de cada resposta.
- Medalhas por primeira partida, fase perfeita e 10 respostas corretas.
- Transformações do personagem principal por pontuação: Coelho (0), Tartaruga (100), Raposa (300), Pandinha (600) e Unicórnio (1.000 pontos). A forma ativa aparece no painel principal e no palco de transformações; os novos animais usam o mesmo tratamento visual kawaii dos antigos companheiros. Cada desbloqueio mostra uma mensagem própria, com animação e som suave de conquista.
- Loja independente de 36 companions em `companions.json`, organizados em cinco raridades com preços progressivos, descrição visual e mensagem única por bichinho. Os lendários têm emojis/insígnias próprios, equipamentos exclusivos e efeitos de brilho; a Água-viva-lua é diferenciada da Água-viva comum por seus quatro anéis internos e tentáculos curtos. As compras descontam moedas e ficam salvas no SQLite; o estudante pode equipar um companion para exibi-lo ao lado da forma ativa sem alterar as transformações por pontos.
- Desbloqueios de novas formas e compras de companions exibem uma celebração em destaque, com personagem animado, brilho, partículas, mensagem personalizada e som suave; desbloqueios em sequência são apresentados um por vez.
- Progressão persistida por usuário no SQLite em `unlocked_characters` (JSON) e `active_character`; a migração remove os antigos campos e tabelas de roupas e companheiros.
- Pet System persistido por usuário no SQLite: nome e energia do personagem (menos 10 a cada 10 minutos), contador de carinhos com animação e recuperação da energia para 100% ao iniciar uma partida.
- Interface responsiva, fundo animado e suporte a `prefers-reduced-motion`.
- Recursos de acessibilidade opcionais no cadastro e ajustáveis a qualquer momento na conta: modo de alto contraste (texto branco sobre fundo preto) e tamanho de texto menor, padrão ou maior, com preferências salvas no perfil.
- Coelhinho decorativo bebê sem roupas, que atravessa a parte inferior da tela, muda de direção, dá pulinhos e solta corações ao receber atenção; este mascote decorativo é separado do personagem principal transformável.
- Trilha musical original sintetizada no navegador, com sons diferentes para acertos e erros; pode ser ativada ou desativada pelo botão de música.

Cada resposta correta nos jogos regulares vale 10 pontos e 5 moedas; completar a fase concede mais 10 moedas (na Ponte avançada, o acerto vale 10 e a conclusão mais 20). O nível médio é liberado após uma fase concluída e o difícil e o Modo PRO após três. No PRO, a recompensa única é de 100 moedas com mais da metade das questões corretas (6 a 9) ou 200 moedas com aproveitamento perfeito; com 5 acertos ou menos não há bônus e não há moedas por resposta. A música só começa após a primeira interação com o site, pois os navegadores exigem interação para reproduzir áudio, e sua preferência é salva neste dispositivo.

## Observação

Esta é uma base funcional para demonstração e evolução do projeto. Em uma implantação pública, configure HTTPS, backups do arquivo SQLite, políticas de privacidade e os controles administrativos específicos para professores. Se o diretório de dados for alterado, mantenha-o fora da pasta pública do servidor.
