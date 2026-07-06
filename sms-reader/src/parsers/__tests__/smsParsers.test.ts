import { detectProvider, parseSms } from '../smsParsers';

describe('detectProvider', () => {
    it('detects bKash by sender id', () => {
        expect(detectProvider('bKash', 'anything')).toBe('bKash');
    });

    it('detects Nagad by sender id', () => {
        expect(detectProvider('Nagad', 'anything')).toBe('Nagad');
    });

    it('detects Rocket by sender id', () => {
        expect(detectProvider('Rocket', 'anything')).toBe('Rocket');
    });

    it('returns null for unrelated senders', () => {
        expect(
            detectProvider('Grameenphone', 'You have used 50MB data')
        ).toBeNull();
    });
});

describe('parseSms', () => {
    it('parses a bKash "received" message', () => {
        const body =
            'You have received Tk 500.00 from 01712345678. Ref 01712345678. ' +
            'Fee Tk 0.00. Balance Tk 1500.00. TrxID 8N7A6S5D4F at 01/07/2026 14:30';

        const result = parseSms('bKash', body);

        expect(result).toEqual({
            provider: 'bKash',
            amount: 500,
            senderPhone: '01712345678',
            transactionId: '8N7A6S5D4F',
            timestamp: '01/07/2026 14:30',
            rawSender: 'bKash',
            rawBody: body,
        });
    });

    it('parses a Nagad "received" message', () => {
        const body =
            'You have received Tk. 500 from 01798765432. Ref: Bill Pay. ' +
            'TxnID: 1234567890ABCDEF. Balance: TK 2000.00 at 01-07-2026 14:30:00';

        const result = parseSms('Nagad', body);

        expect(result?.provider).toBe('Nagad');
        expect(result?.amount).toBe(500);
        expect(result?.senderPhone).toBe('01798765432');
        expect(result?.transactionId).toBe('1234567890ABCDEF');
        expect(result?.timestamp).toBe('01-07-2026 14:30:00');
    });

    it('parses a Rocket "received" message', () => {
        const body =
            'You have received Tk 1,250.50 from 01611112222. TrxID RKT998877 at 01/07/2026 09:15';

        const result = parseSms('Rocket', body);

        expect(result?.provider).toBe('Rocket');
        expect(result?.amount).toBe(1250.5);
        expect(result?.transactionId).toBe('RKT998877');
    });

    it('returns null when the body does not match the expected template', () => {
        const result = parseSms('bKash', 'Your bKash PIN reset code is 123456');
        expect(result).toBeNull();
    });

    it('returns null for an unrecognized sender', () => {
        const result = parseSms(
            'SomeBank',
            'You have received Tk 500.00 from 01712345678'
        );
        expect(result).toBeNull();
    });
});
