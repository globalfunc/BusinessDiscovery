import { useEffect, useRef, useState } from 'react';

import type { SaveState } from '@/components/discovery/BottomActionBar';

/**
 * Debounced per-field autosave: instant local state update, background PATCH
 * to the discovery_answers upsert endpoint after a pause in typing.
 */
export function useAutosaveField(phase: string, fieldKey: string, initialValue: string) {
    const [value, setValue] = useState(initialValue);
    const [saveState, setSaveState] = useState<SaveState>('idle');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const savedResetRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const skipNextSave = useRef(true);

    useEffect(() => {
        setValue(initialValue);
        skipNextSave.current = true;
    }, [phase, initialValue]);

    useEffect(() => {
        if (skipNextSave.current) {
            skipNextSave.current = false;
            return;
        }

        setSaveState('saving');
        if (debounceRef.current) clearTimeout(debounceRef.current);

        debounceRef.current = setTimeout(() => {
            window.axios
                .patch(route('discovery.answers.update'), { phase, field_key: fieldKey, value })
                .then(() => {
                    setSaveState('saved');
                    if (savedResetRef.current) clearTimeout(savedResetRef.current);
                    savedResetRef.current = setTimeout(() => setSaveState('idle'), 2000);
                });
        }, 600);

        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    return { value, setValue, saveState };
}
