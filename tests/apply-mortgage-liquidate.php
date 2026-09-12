<?php
require __DIR__ . '/../game_apply.php';
$s = mb_norm_state(array(
    'players' => array(
        array('id' => 'a', 'name' => 'A', 'color' => '#000', 'balance' => 50, 'bankrupt' => false),
        array('id' => 'b', 'name' => 'B', 'color' => '#111', 'balance' => 1500, 'bankrupt' => false)
    ),
    'properties' => array(
        array('id' => 'med', 'name' => 'Med', 'group' => 'brown', 'price' => 60, 'mortgage' => 30, 'ownerId' => 'a', 'houses' => 1, 'hotel' => false, 'mortgaged' => false, 'houseCost' => 50, 'rentLevels' => array(2, 10, 30, 90, 160, 250)),
        array('id' => 'bal', 'name' => 'Bal', 'group' => 'brown', 'price' => 60, 'mortgage' => 30, 'ownerId' => 'a', 'houses' => 1, 'hotel' => false, 'mortgaged' => false, 'houseCost' => 50, 'rentLevels' => array(4, 20, 60, 180, 320, 450))
    ),
    'settings' => array('goAmount' => 200)
));
$r = mb_apply_mortgage($s, 'med');
$ok = !empty($r['ok'])
    && !empty($s['properties'][0]['mortgaged'])
    && intval($s['properties'][0]['houses']) === 0
    && intval($s['properties'][1]['houses']) === 0
    && empty($s['properties'][0]['hotel'])
    && empty($s['properties'][1]['hotel']);
if (!$ok) {
    fwrite(STDERR, "mortgage liquidate failed\n");
    exit(1);
}
echo "mortgage liquidate ok\n";
