import { PermissionsAndroid, Platform } from 'react-native';

export async function requestSmsPermissions(): Promise<boolean> {
    if (Platform.OS !== 'android') {
        return false;
    }

    const granted = await PermissionsAndroid.requestMultiple([
        PermissionsAndroid.PERMISSIONS.RECEIVE_SMS,
        PermissionsAndroid.PERMISSIONS.READ_SMS,
    ]);

    return (
        granted[PermissionsAndroid.PERMISSIONS.RECEIVE_SMS] ===
            PermissionsAndroid.RESULTS.GRANTED &&
        granted[PermissionsAndroid.PERMISSIONS.READ_SMS] ===
            PermissionsAndroid.RESULTS.GRANTED
    );
}
