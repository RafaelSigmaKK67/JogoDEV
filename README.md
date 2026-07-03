# 🧑‍💻 DEV SURVIVOR

Jogo **battle royale 2D top-down** (inspirado em Surviv.io) com tema de programação.
Você é um(a) **dev** preso(a) num ambiente digital infestado de vírus, malwares,
trojans, ransomwares, bugs críticos, bots corrompidos e o temível boss
**Kernel Corrompido**. Sobreviva, colete armas, escolha habilidades e dispute o ranking.

Feito **apenas** com: PHP, MySQL (phpMyAdmin), HTML, CSS, JavaScript e Canvas HTML5.
Sem frameworks. Roda 100% no XAMPP.

---

## 📁 Estrutura do projeto

```
/dev-survivor
├── index.php              → Tela inicial (landing page)
├── logout.php             → Encerra a sessão
├── create_admin.php       → (opcional) recria o usuário admin — apague depois de usar
├── install.sql            → Banco de dados completo + dados iniciais
├── README.md
├── /config
│   └── database.php       → Conexão PDO + detecção da BASE_URL
├── /includes
│   ├── auth.php           → Sessão, login, CSRF, helpers de segurança
│   ├── helpers.php        → Economia/jogo: loja, personagens, roleta, medalhas, recompensas
│   ├── header.php         → Topo padrão das páginas (menu completo)
│   └── footer.php         → Rodapé padrão
├── /pages
│   ├── login.php          → Login (password_verify)
│   ├── register.php       → Cadastro (password_hash) + setup do jogador
│   ├── dashboard.php      → Painel do jogador (stats, mapas, inventário)
│   ├── maps.php           → Seleção dos 10 mapas + dificuldade
│   ├── characters.php     → Sobreviventes: ver, desbloquear, selecionar
│   ├── store.php          → Loja (skins, efeitos, molduras, equipamentos…)
│   ├── locker.php         → Armário (equipar loadout)
│   ├── roulette.php       → Roletas com animação
│   ├── medals.php         → Mural de medalhas
│   ├── rewards.php        → Recompensas por nível de conta
│   ├── sandbox.php        → Modo Sandbox (jogo livre)
│   ├── ranking.php        → Top 50 global
│   └── admin.php          → Painel admin (CRUD de TUDO + relatórios)
├── /game
│   ├── index.php          → Tela do jogo (injeta os dados do banco no JS)
│   ├── save_match.php     → API: salva partida/conclusão (medalhas + recompensas)
│   ├── spin.php           → API: gira a roleta e aplica o prêmio
│   ├── maps.js            → Utilitários + geração/desenho de mapas
│   ├── items.js           → Drops (moedas, vida, armas)
│   ├── weapons.js         → Armas e projéteis (efeitos de skin)
│   ├── skills.js          → Habilidades de level up
│   ├── characters.js      → Personagens jogáveis + agregação de bônus
│   ├── enemies.js         → Inimigos, bosses por mapa e comportamentos
│   ├── player.js          → O dev: classe, ultimate (R), especial (Q)
│   ├── engine.js          → Game loop, dificuldade, colisões, HUD, sons
│   └── game.js            → Bootstrap: liga banco ↔ jogo ↔ overlays
└── /assets
    ├── /css  (style.css, game.css)
    ├── /js   (main.js)
    ├── /img  (vazio — pronto para sprites futuros)
    └── /sounds (vazio — sons atuais são sintetizados via WebAudio)
```

---

## 🚀 Passo a passo para rodar no XAMPP

1. **Instale o XAMPP** (https://www.apachefriends.org) se ainda não tiver.
2. Copie a pasta **`dev-survivor`** inteira para dentro de:
   ```
   C:\xampp\htdocs\
   ```
   Ficando: `C:\xampp\htdocs\dev-survivor\`
3. Abra o **XAMPP Control Panel** e clique em **Start** no **Apache** e no **MySQL**.

### 🗄️ Importar o banco no phpMyAdmin

4. Acesse **http://localhost/phpmyadmin** no navegador.
5. Clique na aba **Importar** (no topo).
6. Clique em **Escolher arquivo** e selecione o `install.sql` (está na raiz do projeto).
7. Clique em **Executar** (ou **Importar**). O banco `dev_survivor` será criado com
   todas as tabelas e os dados iniciais (mapas, armas, inimigos, habilidades e admin).

> 💡 Alternativa: aba **SQL** → cole todo o conteúdo do `install.sql` → Executar.

### 🎮 Acessar o jogo

8. Abra no navegador:
   ```
   http://localhost/dev-survivor/
   ```
9. Crie sua conta em **Criar Conta** ou entre com o admin (abaixo) e clique em **▶ Jogar**.

---

## 🔑 Usuário admin padrão

| Campo  | Valor                   |
|--------|-------------------------|
| E-mail | `admin@devsurvivor.com` |
| Senha  | `admin123`              |

O painel admin fica em **http://localhost/dev-survivor/pages/admin.php**
(o link "Admin" aparece no menu quando você loga como admin).

> Se o hash da senha não funcionar no seu ambiente, acesse uma única vez
> `http://localhost/dev-survivor/create_admin.php` — ele recria o admin com a
> senha `admin123` usando o `password_hash()` do **seu** PHP. **Apague o arquivo depois.**

---

## 🕹️ Controles

| Tecla / Mouse        | Ação                          |
|----------------------|-------------------------------|
| `W` `A` `S` `D` / setas | Mover                       |
| Mouse                | Mirar                         |
| Botão esquerdo (segurar) | Atirar                    |
| `Q`                  | Habilidade especial do personagem |
| `R`                  | Ultimate (quando a barra encher)  |
| `1`–`9` ou scroll    | Trocar de arma                |
| `P` ou `Esc`         | Pausar                        |
| `M`                  | Ligar/desligar som            |

## ⚙️ Mecânicas

- **XP e level up na partida**: ao subir de nível, escolha 1 entre 3 habilidades.
- **Garbage Collector**: a zona segura encolhe — fique dentro do círculo!
- **Boss Kernel Corrompido**: aparece aos 3 minutos (rajadas radiais + invoca lacaios),
  e volta cada vez mais forte.
- **Drops**: inimigos largam moedas, kits de vida e armas novas.
- **Mapas com hazards** (Nuvem Instável, Deep Web): zonas de dano temporárias.
- **Progressão de conta**: XP das partidas sobe o nível da conta e libera novos mapas.
- Ao morrer, a partida é **salva no banco** (matches, players_stats, rankings, inventory).

## 🛡️ Segurança implementada

- PDO + **prepared statements** em todas as queries (anti SQL injection)
- `password_hash()` / `password_verify()` no cadastro/login
- `session_start` com cookie `httponly` + `session_regenerate_id` no login
- **Token CSRF** em todos os formulários POST e na API do jogo
- `htmlspecialchars()` em toda saída dinâmica (anti XSS)
- Páginas privadas protegidas (`requireLogin` / `requireAdmin`)
- Validação de formulário no servidor + limites de sanidade no salvamento de partidas

---

## 🚀 Novidades da Expansão (v2)

- **10 mapas** com tema/dificuldade/obstáculos/recompensas e **boss exclusivo** cada
  (Bug Mestre, SQL Injection Vivo, Botnet Suprema, Cloud Phantom, Kernel Corrompido,
  Guardião Firewall, Servidor Fantasma, Root Admin Corrompido, Algoritmo Rebelde, Mainframe Supremo).
- **6 dificuldades** (Fácil → Pesadelo) que alteram vida/dano/velocidade/quantidade dos inimigos,
  tempo até o boss, moedas, XP e chance de itens raros.
- **Conclusão de mapa**: sobreviva ao tempo da dificuldade, o boss aparece, derrote-o = mapa concluído
  → ganha **medalha** + recompensas. Medalhas por mapa×dificuldade (60), "Mestre" por mapa (10) e a
  **Lenda Suprema do Sistema** (todos os mapas em todas as dificuldades).
- **10 personagens** jogáveis, cada um com atributos, passiva, **habilidade especial (Q)** e
  **ultimate (R)** com barra de carga e efeito visual.
- **Loja** (skins de personagem/arma, efeitos de personagem/tiro/eliminação, entradas, molduras,
  pacotes, roletas, equipamentos) com **6 raridades** (Comum → Mítico). Skins dão pequenos efeitos.
- **Armário**: equipa personagem, skins, efeitos, moldura e **equipamentos** (6 slots com bônus).
- **4 roletas** (grátis diária, comum, rara, lendária) com animação e prêmios salvos no banco.
- **Recompensas por nível de conta** (níveis 5 a 100), resgatáveis na página Recompensas.
- **Modo Sandbox**: qualquer mapa/dificuldade/personagem, quantidade de inimigos, liga/desliga boss,
  vida e moedas infinitas, arma de teste. Salvo separado — **não afeta o ranking oficial**.
- **Painel admin** ampliado: CRUD de mapas, bosses, dificuldades, personagens, armas, habilidades,
  itens da loja, equipamentos, medalhas, recompensas e roletas + relatórios de conclusões e roleta.

### Como reimportar o banco da expansão
O `install.sql` foi atualizado (recria tudo do zero). Reimporte-o no phpMyAdmin
(aba **Importar**) — ele apaga as tabelas antigas e recria com os novos dados.

## 🖼️ Sprites futuros

O jogo usa formas geométricas no Canvas, mas já está preparado para imagens:
coloque `player.png` em `/assets/img/` e o personagem passa a usar o sprite
automaticamente (veja `GameAssets` em `game/game.js` para adicionar mais).

## 🔮 Melhorias futuras possíveis

- Multiplayer em tempo real (WebSockets)
- Loja para gastar moedas (upgrades permanentes, skins)
- Sons em arquivos (.mp3/.wav em `/assets/sounds`) substituindo o sintetizador
- Conquistas/missões diárias e novos bosses por mapa
- Mobile: controles touch (joystick virtual)
- Sistema de clãs/squads e ranking semanal com temporadas
