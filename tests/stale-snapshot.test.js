function remoteSnapshotStale(data, local) {
    if (!data || typeof data !== 'object') return true;
    const inEpoch = typeof data.epoch === 'number' ? data.epoch : 0;
    const inRev = typeof data.rev === 'number' ? data.rev : 0;
    const localEpoch = Number(local.epoch) || 0;
    const localRev = Number(local.rev) || 0;
    if (inEpoch < localEpoch) return true;
    if (inEpoch === localEpoch && inRev < localRev) return true;
    return false;
}

function applyOrder(local, snapshots) {
    snapshots.forEach(function (data) {
        if (remoteSnapshotStale(data, local)) return;
        local.rev = data.rev;
        local.epoch = data.epoch;
        local.ownerId = data.ownerId;
        local.balance = data.balance;
    });
    return local;
}

let fails = 0;
function assert(cond, msg) {
    if (!cond) {
        fails++;
        console.error('FAIL', msg);
    } else {
        console.log('ok', msg);
    }
}

assert(remoteSnapshotStale({ epoch: 0, rev: 4 }, { epoch: 0, rev: 5 }), 'rev lebih kecil stale');
assert(!remoteSnapshotStale({ epoch: 0, rev: 5 }, { epoch: 0, rev: 5 }), 'rev sama tidak stale');
assert(!remoteSnapshotStale({ epoch: 0, rev: 6 }, { epoch: 0, rev: 5 }), 'rev lebih besar tidak stale');
assert(remoteSnapshotStale({ epoch: 0, rev: 9 }, { epoch: 1, rev: 0 }), 'epoch lebih kecil stale');

const newer = { epoch: 0, rev: 5, ownerId: 'p1', balance: 1300 };
const older = { epoch: 0, rev: 4, ownerId: null, balance: 1500 };
let a = applyOrder({ epoch: 0, rev: 0, ownerId: null, balance: 1500 }, [older, newer]);
assert(a.ownerId === 'p1' && a.balance === 1300, 'lama lalu baru = snapshot baru');
a = applyOrder({ epoch: 0, rev: 0, ownerId: null, balance: 1500 }, [newer, older]);
assert(a.ownerId === 'p1' && a.balance === 1300, 'baru lalu lama = tetap snapshot baru');

if (fails) {
    process.exit(1);
}
console.log('all passed');
