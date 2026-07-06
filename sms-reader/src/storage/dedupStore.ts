import AsyncStorage from '@react-native-async-storage/async-storage';

const DEDUP_KEY = '@isp_os_sms_reader/sent_transaction_ids';
const MAX_TRACKED = 500;

export async function hasBeenSent(transactionId: string): Promise<boolean> {
    const raw = await AsyncStorage.getItem(DEDUP_KEY);
    const ids: string[] = raw ? JSON.parse(raw) : [];
    return ids.includes(transactionId);
}

export async function markAsSent(transactionId: string): Promise<void> {
    const raw = await AsyncStorage.getItem(DEDUP_KEY);
    const ids: string[] = raw ? JSON.parse(raw) : [];
    if (ids.includes(transactionId)) {
        return;
    }

    ids.push(transactionId);
    const trimmed =
        ids.length > MAX_TRACKED ? ids.slice(ids.length - MAX_TRACKED) : ids;
    await AsyncStorage.setItem(DEDUP_KEY, JSON.stringify(trimmed));
}
