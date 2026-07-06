declare module 'react-native-android-sms-listener' {
    interface SmsMessage {
        originatingAddress: string;
        body: string;
    }

    interface Subscription {
        remove(): void;
    }

    const SmsListener: {
        addListener(callback: (message: SmsMessage) => void): Subscription;
    };

    export default SmsListener;
}
