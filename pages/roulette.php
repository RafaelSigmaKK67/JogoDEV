<?php
/**
 * DEV SURVIVOR - Roletas
 * 4 roletas (gratis diaria, comum, rara, lendaria) com animacao JS.
 * O giro chama game/spin.php (JSON) que aplica e salva a recompensa.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = db();
$userId = (int)$_SESSION['user_id'];
$user = currentUser();

$freeAvailable = canSpinFreeRoulette($userId);

$roulettes = [
    ['type' => 'free',      'name' => 'Roleta Gratis Diaria', 'cost' => 0,    'rarity' => 'incomum',  'desc' => '1 giro gratis por dia.'],
    ['type' => 'common',    'name' => 'Roleta Comum',         'cost' => 300,  'rarity' => 'raro',     'desc' => 'Premios variados e algumas skins.'],
    ['type' => 'rare',      'name' => 'Roleta Rara',          'cost' => 800,  'rarity' => 'epico',    'desc' => 'Mais chance de itens epicos e personagens.'],
    ['type' => 'legendary', 'name' => 'Roleta Lendaria',      'cost' => 2000, 'rarity' => 'lendario', 'desc' => 'Skins lendarias, miticas e personagens raros.'],
];

// Historico recente
$stmt = $pdo->prepare('SELECT * FROM roulette_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 15');
$stmt->execute([$userId]);
$history = $stmt->fetchAll();

$pageTitle = 'Roleta';
require_once __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">&gt; Roletas<span class="cursor">_</span></h1>
<p class="page-sub">// gire para ganhar moedas, XP, skins, efeitos, equipamentos e personagens. Saldo:
   <span class="tx-yellow" id="coinBalance"><?= e(number_format((int)$user['coins'],0,',','.')) ?></span> &cent;</p>

<!-- Rolo de animacao -->
<div id="reelWrap" class="reel-wrap hidden">
    <div class="reel-pointer">&#9660;</div>
    <div class="reel-track" id="reelTrack"></div>
    <div class="reel-result" id="reelResult"></div>
</div>

<section class="roulette-grid">
    <?php foreach ($roulettes as $r):
        $rc = rarityColor($r['rarity']);
        $disabled = ($r['type'] === 'free' && !$freeAvailable);
    ?>
        <div class="roulette-card" style="--rarity: <?= e($rc) ?>">
            <div class="roulette-wheel" style="border-color: <?= e($rc) ?>">&#127920;</div>
            <h3><?= e($r['name']) ?></h3>
            <p class="shop-desc"><?= e($r['desc']) ?></p>
            <p class="roulette-cost">
                <?php if ($r['type'] === 'free'): ?>
                    <?= $freeAvailable ? '<span class="tx-green">GRATIS hoje</span>' : '<span class="tx-dim">volte amanha</span>' ?>
                <?php else: ?>
                    <span class="tx-yellow"><?= e($r['cost']) ?> &cent;</span>
                <?php endif; ?>
            </p>
            <button class="btn btn-primary btn-block spin-btn" data-type="<?= e($r['type']) ?>" <?= $disabled ? 'disabled' : '' ?>>
                GIRAR
            </button>
        </div>
    <?php endforeach; ?>
</section>

<h2 class="section-title">// Historico recente</h2>
<?php if (!$history): ?>
    <p class="empty-msg">Nenhum giro ainda. Gire a roleta gratis para comecar!</p>
<?php else: ?>
    <table class="table">
        <thead><tr><th>Roleta</th><th>Premio</th><th>Raridade</th><th>Quando</th></tr></thead>
        <tbody>
            <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= e(ucfirst($h['roulette_type'])) ?></td>
                    <td><?= e($h['label']) ?></td>
                    <td style="color: <?= e(rarityColor($h['rarity'])) ?>"><?= e(rarityLabel($h['rarity'])) ?></td>
                    <td class="tx-dim"><?= e(date('d/m H:i', strtotime($h['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
(function () {
    'use strict';
    var BASE = <?= json_encode(BASE_URL) ?>;
    var CSRF = <?= json_encode(csrfToken()) ?>;
    var spinning = false;

    var reelWrap   = document.getElementById('reelWrap');
    var reelTrack  = document.getElementById('reelTrack');
    var reelResult = document.getElementById('reelResult');

    document.querySelectorAll('.spin-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (spinning) return;
            spin(btn.dataset.type, btn);
        });
    });

    function spin(type, btn) {
        spinning = true;
        btn.disabled = true;
        reelResult.textContent = '';
        reelResult.className = 'reel-result';

        fetch(BASE + '/game/spin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ type: type }),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) {
                spinning = false;
                btn.disabled = false;
                alert(data.error || 'Erro ao girar.');
                return;
            }
            animateReel(data);
            document.getElementById('coinBalance').textContent = Number(data.coins).toLocaleString('pt-BR');
        })
        .catch(function () {
            spinning = false;
            btn.disabled = false;
            alert('Falha de conexao.');
        });
    }

    function animateReel(data) {
        reelWrap.classList.remove('hidden');
        reelTrack.innerHTML = '';
        var cellW = 150;

        data.reel.forEach(function (cell) {
            var div = document.createElement('div');
            div.className = 'reel-cell';
            div.style.borderColor = cell.color;
            div.style.color = cell.color;
            div.textContent = cell.label;
            reelTrack.appendChild(div);
        });

        // Posiciona para que a celula vencedora pare sob o ponteiro central
        var center = reelWrap.clientWidth / 2 - cellW / 2;
        var target = -(data.result_index * cellW) + center;
        reelTrack.style.transition = 'none';
        reelTrack.style.transform = 'translateX(' + center + 'px)';
        // Força reflow e dispara a animacao
        void reelTrack.offsetWidth;
        reelTrack.style.transition = 'transform 3.4s cubic-bezier(0.12, 0.7, 0.1, 1)';
        reelTrack.style.transform = 'translateX(' + target + 'px)';

        setTimeout(function () {
            reelResult.textContent = '🎉 Voce ganhou: ' + data.label;
            reelResult.className = 'reel-result reel-win';
            reelResult.style.color = data.color;
            // Recarrega para atualizar saldo, historico e disponibilidade
            setTimeout(function () { window.location.reload(); }, 2200);
        }, 3500);
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
