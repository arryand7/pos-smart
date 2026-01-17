const STORAGE_KEY = 'smart.pos.offline.queue';

function loadRawQueue() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return [];
        }

        return JSON.parse(raw);
    } catch (error) {
        console.warn('SMART POS: gagal memuat antrean offline', error);

        return [];
    }
}

function persistQueue(queue) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
    } catch (error) {
        console.warn('SMART POS: gagal menyimpan antrean offline', error);
    }
}

export function getQueue() {
    return loadRawQueue();
}

export function enqueueTransaction(payload) {
    const queue = loadRawQueue();
    const reference = payload.reference || `OFF-${Date.now()}`;

    queue.push({
        ...payload,
        reference,
        enqueued_at: new Date().toISOString(),
    });

    persistQueue(queue);

    return queue;
}

export function removeTransaction(reference) {
    const queue = loadRawQueue().filter((item) => item.reference !== reference);

    persistQueue(queue);

    return queue;
}

export async function flushQueue(sendFn) {
    const queue = loadRawQueue();
    const results = [];

    for (const transaction of queue) {
        try {
            // eslint-disable-next-line no-await-in-loop
            await sendFn(transaction);
            results.push({ reference: transaction.reference, status: 'success' });
            removeTransaction(transaction.reference);
        } catch (error) {
            results.push({ reference: transaction.reference, status: 'failed', error });
        }
    }

    return results;
}
