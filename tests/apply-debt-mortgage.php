<?php
require __DIR__ . '/../game_apply.php';
$s = mb_norm_state(array(
    'players' => array(
        array('id' => 'a', 'name' => 'A', 'color' => '#000', 'balance' => 20, 'bankrupt' => false),
        array('id' => 'b', 'name' => 'B', 'color' => '#111', 'balance' => 1500, 'bankrupt' => false)
    ),
    'properties' => array(
        array('id' => 'med', 'name' => 'Med', 'group' => 'brown', 'price' => 60, 'mortgage' => 30, 'ownerId' => 'a', 'houses' => 1, 'hotel' => false, 'mortgaged' => false, 'houseCost' => 50, 'rentLevels' => array(2, 10, 30, 90, 160, 250)),
        array('id' => 'bal', 'name' => 'Bal', 'group' => 'brown', 'price' => 60, 'mortgage' => 30, 'ownerId' => 'a', 'houses' => 0, 'hotel' => false, 'mortgaged' => false, 'houseCost' => 50, 'rentLevels' => array(4, 20, 60, 180, 320, 450)),
        array('id' => 'ori', 'name' => 'Ori', 'group' => 'lightblue', 'price' => 100, 'mortgage' => 50, 'ownerId' => 'b', 'houses' => 0, 'hotel' => false, 'mortgaged' => false, 'houseCost' => 50, 'rentLevels' => array(6, 30, 90, 270, 400, 550))
    ),
    'settings' => array('goAmount' => 200)
));
$due = mb_apply_rent_due($s, 'a', 'b', 40, 'ori');
if (empty($due['ok']) || empty($s['debtSettlement'])) {
    fwrite(STDERR, "debt start failed\n");
    exit(1);
}
$m = mb_apply_mortgage($s, 'med');
if (empty($m['ok']) || !empty($s['debtSettlement'])) {
    fwrite(STDERR, "debt mortgage settle failed\n");
    exit(1);
}
if ($s['players'][0]['balance'] != 35 || $s['players'][1]['balance'] != 1540) {
    fwrite(STDERR, "balances after debt settle mismatch\n");
    exit(1);
}
echo "debt mortgage settle ok\n";
