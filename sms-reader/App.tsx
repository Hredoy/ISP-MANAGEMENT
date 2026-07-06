import React, { useState } from 'react';
import { SafeAreaView, View, Button, StyleSheet } from 'react-native';
import SettingsScreen from './src/screens/SettingsScreen';
import StatusScreen from './src/screens/StatusScreen';

type Screen = 'status' | 'settings';

export default function App() {
    const [screen, setScreen] = useState<Screen>('status');

    return (
        <SafeAreaView style={styles.container}>
            <View style={styles.nav}>
                <Button title="Status" onPress={() => setScreen('status')} />
                <Button
                    title="Settings"
                    onPress={() => setScreen('settings')}
                />
            </View>
            {screen === 'status' ? (
                <StatusScreen />
            ) : (
                <SettingsScreen onSaved={() => setScreen('status')} />
            )}
        </SafeAreaView>
    );
}

const styles = StyleSheet.create({
    container: { flex: 1 },
    nav: { flexDirection: 'row', justifyContent: 'space-around', padding: 8 },
});
