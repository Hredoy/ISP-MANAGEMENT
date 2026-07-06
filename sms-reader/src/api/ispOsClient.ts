import type { ParsedTransaction } from '../parsers/smsParsers';
import type { Settings } from '../storage/settingsStore';

export const SMS_TRANSACTIONS_ENDPOINT = '/api/mobile/sms-transactions';

export class ApiError extends Error {
    constructor(message: string, public status?: number) {
        super(message);
        this.name = 'ApiError';
    }
}

/**
 * Posts a parsed transaction to the tenant's ISP OS instance. The base URL
 * and device token are configured on-device via the Settings screen (per
 * task spec) rather than hardcoded, since each installed device belongs to
 * a specific tenant/ISP.
 */
export async function postTransaction(
    settings: Settings,
    transaction: ParsedTransaction
): Promise<void> {
    if (!settings.apiUrl || !settings.deviceToken) {
        throw new ApiError(
            'ISP API URL and device token must be configured in Settings first.'
        );
    }

    const url = `${settings.apiUrl.replace(
        /\/+$/,
        ''
    )}${SMS_TRANSACTIONS_ENDPOINT}`;

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${settings.deviceToken}`,
        },
        body: JSON.stringify({
            provider: transaction.provider,
            amount: transaction.amount,
            sender_phone: transaction.senderPhone,
            transaction_id: transaction.transactionId,
            sms_timestamp: transaction.timestamp,
            raw_sender: transaction.rawSender,
            raw_body: transaction.rawBody,
        }),
    });

    if (!response.ok) {
        throw new ApiError(
            `ISP OS API responded with status ${response.status}`,
            response.status
        );
    }
}
