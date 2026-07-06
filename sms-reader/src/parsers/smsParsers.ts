export type Provider = 'bKash' | 'Nagad' | 'Rocket';

export interface ParsedTransaction {
    provider: Provider;
    amount: number;
    senderPhone: string | null;
    transactionId: string;
    timestamp: string | null;
    rawSender: string;
    rawBody: string;
}

interface ProviderPattern {
    provider: Provider;
    senderMatch: RegExp;
    bodyMatch: RegExp;
    amountGroup: number;
    phoneGroup: number;
    trxIdGroup: number;
}

const DATE_PATTERN =
    /(\d{1,2}[/-]\d{1,2}[/-]\d{2,4}\s+\d{1,2}:\d{2}(?::\d{2})?)/;

/**
 * Patterns follow each provider's publicly documented "cash in / received"
 * SMS template as of writing. Operators change wording without notice and
 * templates vary by app version, so these are unverified against live SMS
 * (no bKash/Nagad/Rocket merchant account available in this environment) —
 * confirm against real messages before relying on this in production, same
 * caveat class as the OLT drivers in app/Services/Olt/*.
 */
const PATTERNS: ProviderPattern[] = [
    {
        provider: 'bKash',
        senderMatch: /bkash/i,
        bodyMatch:
            /received\s+Tk\.?\s*([\d,]+\.?\d*)\s+from\s+(\d{11}).*?TrxID\s*:?\s*([A-Za-z0-9]+)/is,
        amountGroup: 1,
        phoneGroup: 2,
        trxIdGroup: 3,
    },
    {
        provider: 'Nagad',
        senderMatch: /nagad/i,
        bodyMatch:
            /received\s+Tk\.?\s*([\d,]+\.?\d*)\s+from\s+(\d{11}).*?TxnID\s*:?\s*([A-Za-z0-9]+)/is,
        amountGroup: 1,
        phoneGroup: 2,
        trxIdGroup: 3,
    },
    {
        provider: 'Rocket',
        senderMatch: /rocket|dbbl/i,
        bodyMatch:
            /received\s+Tk\.?\s*([\d,]+\.?\d*)\s+from\s+(\d{11}).*?(?:TxnID|TrxID)\s*:?\s*([A-Za-z0-9]+)/is,
        amountGroup: 1,
        phoneGroup: 2,
        trxIdGroup: 3,
    },
];

export function detectProvider(sender: string, body: string): Provider | null {
    for (const pattern of PATTERNS) {
        if (
            pattern.senderMatch.test(sender) ||
            pattern.senderMatch.test(body)
        ) {
            return pattern.provider;
        }
    }
    return null;
}

export function parseSms(
    sender: string,
    body: string
): ParsedTransaction | null {
    const provider = detectProvider(sender, body);
    if (!provider) {
        return null;
    }

    const pattern = PATTERNS.find((p) => p.provider === provider)!;
    const match = pattern.bodyMatch.exec(body);
    if (!match) {
        return null;
    }

    const amount = parseFloat(match[pattern.amountGroup].replace(/,/g, ''));
    if (Number.isNaN(amount)) {
        return null;
    }

    const dateMatch = DATE_PATTERN.exec(body);

    return {
        provider,
        amount,
        senderPhone: match[pattern.phoneGroup] ?? null,
        transactionId: match[pattern.trxIdGroup],
        timestamp: dateMatch ? dateMatch[1] : null,
        rawSender: sender,
        rawBody: body,
    };
}
