import React, { useEffect, useRef, useState } from 'react';
import { View, Text, FlatList, StyleSheet } from 'react-native';
import {
    startSmsListener,
    ListenerEvent,
} from '../services/smsListenerService';

interface LogEntry {
    id: string;
    message: string;
}

export default function StatusScreen() {
    const [logs, setLogs] = useState<LogEntry[]>([]);
    const counter = useRef(0);

    useEffect(() => {
        const handle = startSmsListener((event: ListenerEvent) => {
            const message = describeEvent(event);
            counter.current += 1;
            setLogs((prev) =>
                [{ id: String(counter.current), message }, ...prev].slice(
                    0,
                    100
                )
            );
        });

        return () => handle.remove();
    }, []);

    return (
        <View style={styles.container}>
            <Text style={styles.title}>Listening for payment SMS…</Text>
            <FlatList
                data={logs}
                keyExtractor={(item) => item.id}
                renderItem={({ item }) => (
                    <Text style={styles.logLine}>{item.message}</Text>
                )}
                ListEmptyComponent={
                    <Text style={styles.empty}>No messages processed yet.</Text>
                }
            />
        </View>
    );
}

function describeEvent(event: ListenerEvent): string {
    switch (event.type) {
        case 'forwarded':
            return `Forwarded ${event.provider} transaction ${event.transactionId}`;
        case 'skipped-duplicate':
            return `Skipped duplicate transaction ${event.transactionId}`;
        case 'ignored':
            return 'Ignored non-payment SMS';
        case 'error':
            return `Error: ${event.message}`;
        default:
            return 'Unknown event';
    }
}

const styles = StyleSheet.create({
    container: { flex: 1, padding: 16 },
    title: { fontWeight: '600', marginBottom: 8 },
    logLine: {
        paddingVertical: 4,
        borderBottomWidth: 1,
        borderBottomColor: '#eee',
    },
    empty: { color: '#888', marginTop: 20 },
});
