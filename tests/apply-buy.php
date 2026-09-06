<?php
require __DIR__ . '/../game_apply.php';
$s = mb_norm_state(array(
    'players' => array(
        array('id' => 'a', 'name' => 'A', 'color' => '#000', 'balance' => 1500, 'bankrupt' => false),
        array('id' => 'b', 'name' => 'B', 'color' => '#111', 'balance' => 1500, 'bankrupt' => false)
    ),
    'properties' => array(
        array('id' => 'med', 'name' => 'Med', 'group' => 'brown', 'price' => 60, 'mortgage' => 30, 'ownerId' => null, 'houses' => 0, 'hotel' => false, 'mortgaged' => false, 'houseCost' => 50, 'rentLevels' => array(2, 10, 30, 90, 160, 250)),
        array('id' => 'bal', 'name' => 'Bal', 'group' => 'brown', 'price' => 60, 'mortgage' => 30, 'ownerId' => null, 'houses' => 0, 'hotel' => false, 'mortgaged' => false, 'houseCost' => 50, 'rentLevels' => array(4, 20, 60, 180, 320, 450))
    ),
    'settings' => array('goAmount' => 200)
));
$r1 = mb_apply_buy($s, 'a', 'med', 60);
$r2 = mb_apply_buy($s, 'b', 'bal', 60);
$r3 = mb_apply_buy($s, 'b', 'med', 60);
$ok = !empty($r1['ok']) && !empty($r2['ok']) && empty($r3['ok'])
    && $s['players'][0]['balance'] == 1440 && $s['players'][1]['balance'] == 1440
    && $s['properties'][0]['ownerId'] === 'a' && $s['properties'][1]['ownerId'] === 'b';
if (!$ok) {
    fwrite(STDERR, "buy sequential failed\n");
    exit(1);
}
echo "buy sequential ok\n";
