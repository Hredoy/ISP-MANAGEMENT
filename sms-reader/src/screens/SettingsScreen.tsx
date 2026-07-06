import React, { useEffect, useState } from 'react';
import { View, Text, TextInput, Button, StyleSheet, Alert } from 'react-native';
import { getSettings, saveSettings } from '../storage/settingsStore';
import { requestSmsPermissions } from '../permissions/smsPermissions';

interface Props {
    onSaved?: () => void;
}

export default function SettingsScreen({ onSaved }: Props) {
    const [apiUrl, setApiUrl] = useState('');
    const [deviceToken, setDeviceToken] = useState('');

    useEffect(() => {
        getSettings().then((settings) => {
            setApiUrl(settings.apiUrl);
            setDeviceToken(settings.deviceToken);
        });
    }, []);

    const handleSave = async () => {
        const granted = await requestSmsPermissions();
        if (!granted) {
            Alert.alert(
                'SMS permission required',
                'This app cannot detect payment messages without SMS permission.'
            );
            return;
        }

        await saveSettings({
            apiUrl: apiUrl.trim(),
            deviceToken: deviceToken.trim(),
        });
        Alert.alert(
            'Saved',
            'Settings saved. The SMS listener will use these on the next message.'
        );
        onSaved?.();
    };

    return (
        <View style={styles.container}>
            <Text style={styles.label}>ISP OS API URL</Text>
            <TextInput
                style={styles.input}
                value={apiUrl}
                onChangeText={setApiUrl}
                placeholder="https://your-isp.example.com"
                autoCapitalize="none"
                autoCorrect={false}
            />

            <Text style={styles.label}>Device Token</Text>
            <TextInput
                style={styles.input}
                value={deviceToken}
                onChangeText={setDeviceToken}
                placeholder="Device token issued by ISP OS"
                autoCapitalize="none"
                autoCorrect={false}
                secureTextEntry
            />

            <Button title="Save" onPress={handleSave} />
        </View>
    );
}

const styles = StyleSheet.create({
    container: { padding: 16 },
    label: { fontWeight: '600', marginTop: 12, marginBottom: 4 },
    input: {
        borderWidth: 1,
        borderColor: '#ccc',
        borderRadius: 6,
        paddingHorizontal: 10,
        paddingVertical: 8,
    },
});
