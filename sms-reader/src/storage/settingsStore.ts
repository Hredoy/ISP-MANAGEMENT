import AsyncStorage from '@react-native-async-storage/async-storage';

const SETTINGS_KEY = '@isp_os_sms_reader/settings';

export interface Settings {
    apiUrl: string;
    deviceToken: string;
}

const EMPTY_SETTINGS: Settings = { apiUrl: '', deviceToken: '' };

export async function getSettings(): Promise<Settings> {
    const raw = await AsyncStorage.getItem(SETTINGS_KEY);
    return raw ? { ...EMPTY_SETTINGS, ...JSON.parse(raw) } : EMPTY_SETTINGS;
}

export async function saveSettings(settings: Settings): Promise<void> {
    await AsyncStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
}
