import assert from 'node:assert/strict';
import test from 'node:test';

import {
    createOperationalPoller,
    operationalPollingDelay,
} from '../../resources/js/shared/operational-poller.js';

test('distribuye clientes alrededor del intervalo configurado', () => {
    assert.equal(operationalPollingDelay({
        intervalMs: 30_000,
        random: () => 0,
    }), 25_500);
    assert.equal(operationalPollingDelay({
        intervalMs: 30_000,
        random: () => 0.5,
    }), 30_000);
    assert.equal(operationalPollingDelay({
        intervalMs: 30_000,
        random: () => 1,
    }), 34_500);
});

test('aplica retroceso progresivo y respeta el máximo', () => {
    assert.equal(operationalPollingDelay({
        intervalMs: 30_000,
        consecutiveFailures: 2,
        jitterRatio: 0,
        maxBackoffMs: 90_000,
    }), 90_000);
});

test('no programa dos ejecuciones concurrentes', async () => {
    const scheduled = [];
    let releases;
    let calls = 0;
    const poller = createOperationalPoller(async () => {
        calls += 1;
        await new Promise((resolve) => { releases = resolve; });
    }, {
        intervalMs: 30_000,
        setTimer: (callback) => {
            scheduled.push(callback);
            return scheduled.length;
        },
        clearTimer: () => {},
        documentTarget: null,
        windowTarget: null,
        navigatorTarget: null,
        random: () => 0.5,
    });

    poller.start({ immediate: true });
    const overlapping = poller.runNow();
    assert.equal(await overlapping, false);
    assert.equal(calls, 1);

    releases();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(scheduled.length, 1);
    poller.stop();
});
