import SmsListener from 'react-native-android-sms-listener';
import { parseSms } from '../parsers/smsParsers';
import { hasBeenSent, markAsSent } from '../storage/dedupStore';
import { getSettings } from '../storage/settingsStore';
import { postTransaction } from '../api/ispOsClient';

export type SubscriptionHandle = { remove: () => void };

export type ListenerEvent =
    | { type: 'forwarded'; transactionId: string; provider: string }
    | { type: 'skipped-duplicate'; transactionId: string }
    | { type: 'ignored' }
    | { type: 'error'; message: string };

export function startSmsListener(
    onEvent?: (event: ListenerEvent) => void
): SubscriptionHandle {
    const subscription = SmsListener.addListener(
        async (message: { originatingAddress: string; body: string }) => {
            try {
                const parsed = parseSms(
                    message.originatingAddress,
                    message.body
                );
                if (!parsed) {
                    onEvent?.({ type: 'ignored' });
                    return;
                }

                if (await hasBeenSent(parsed.transactionId)) {
                    onEvent?.({
                        type: 'skipped-duplicate',
                        transactionId: parsed.transactionId,
                    });
                    return;
                }

                const settings = await getSettings();
                await postTransaction(settings, parsed);
                await markAsSent(parsed.transactionId);
                onEvent?.({
                    type: 'forwarded',
                    transactionId: parsed.transactionId,
                    provider: parsed.provider,
                });
            } catch (err) {
                onEvent?.({
                    type: 'error',
                    message: err instanceof Error ? err.message : String(err),
                });
            }
        }
    );

    return { remove: () => subscription.remove() };
}
