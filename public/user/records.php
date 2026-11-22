<?php
require __DIR__ . '/../../app/bootstrap.php';
$u = require_role('user');
$uid = $u['_id'];

$pets = all('pets', ['owner_id' => $uid], ['sort' => ['name' => 1]]);
$petId = oid($_GET['pet'] ?? null);
$filter = ['owner_id' => $uid];
if ($petId) $filter['pet_id'] = $petId;
$records = all('records', $filter, ['sort' => ['visit_date' => -1, 'created_at' => -1], 'limit' => 200]);
$petMap = lookup('pets', array_column($records, 'pet_id'));
$vetMap = lookup('vets', array_column($records, 'vet_id'));

page_start('Medical Records', 'user');
page_header('Veterinary medical records', 'Diagnoses, treatments and vaccinations recorded by the clinic.');
?>
<div class="tabs">
    <a class="<?= !$petId ? 'active' : '' ?>" href="?">All pets</a>
    <?php foreach ($pets as $p): ?><a class="<?= $petId && (string)$petId === (string)$p['_id'] ? 'active' : '' ?>" href="?pet=<?= e($p['_id']) ?>"><?= e(pet_emoji($p['species']) . ' ' . $p['name']) ?></a><?php endforeach; ?>
</div>
<?php if (!$records): empty_state('🩺', 'No medical records yet', 'After a visit, the veterinarian\'s notes will appear here.'); else: ?>
    <div class="timeline">
        <?php foreach ($records as $r): $p = $petMap[(string)$r['pet_id']] ?? null; $v = $vetMap[(string)($r['vet_id'] ?? '')] ?? null; ?>
            <div class="card tl-item">
                <div class="tl-head">
                    <div><h3><?= e($p ? pet_emoji($p['species']) . ' ' . $p['name'] : 'Pet') ?> — <?= e($r['diagnosis'] ?: 'Check-up') ?></h3>
                        <span class="muted small"><?= e(fdate($r['visit_date'])) ?><?= $v ? ' · ' . e($v['name']) : '' ?></span></div>
                    <?php if (!empty($r['follow_up'])): ?><span class="pill warn">Follow-up: <?= e(fdate($r['follow_up'])) ?></span><?php endif; ?>
                </div>
                <div class="vitals">
                    <?php if ($r['weight']): ?><span>⚖️ <?= e($r['weight']) ?> kg</span><?php endif; ?>
                    <?php if ($r['temperature']): ?><span>🌡️ <?= e($r['temperature']) ?> °C</span><?php endif; ?>
                </div>
                <dl class="rec">
                    <?php if ($r['treatment']): ?><div><dt>Treatment</dt><dd><?= nl2br(e($r['treatment'])) ?></dd></div><?php endif; ?>
                    <?php if ($r['medications']): ?><div><dt>Medications</dt><dd><?= nl2br(e($r['medications'])) ?></dd></div><?php endif; ?>
                    <?php if ($r['vaccinations']): ?><div><dt>Vaccinations</dt><dd><?= nl2br(e($r['vaccinations'])) ?></dd></div><?php endif; ?>
                    <?php if ($r['notes']): ?><div><dt>Notes</dt><dd><?= nl2br(e($r['notes'])) ?></dd></div><?php endif; ?>
                </dl>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif;
page_end('user'); ?>
