import { useState } from 'react';

import { ChipInput } from '@/components/discovery/ChipInput';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export function AddCustomServiceForm({
    t,
    onSubmit,
}: {
    t: (key: string, vars?: Record<string, string>) => string;
    onSubmit: (payload: { name: string; description: string; features: string[]; reference_links: string[] }) => void;
}) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [features, setFeatures] = useState<string[]>([]);
    const [referenceLinks, setReferenceLinks] = useState<string[]>([]);

    const reset = () => {
        setName('');
        setDescription('');
        setFeatures([]);
        setReferenceLinks([]);
        setOpen(false);
    };

    const submit = () => {
        if (name.trim() === '') return;
        onSubmit({ name: name.trim(), description: description.trim(), features, reference_links: referenceLinks });
        reset();
    };

    if (!open) {
        return (
            <Button type="button" variant="secondary" size="sm" onClick={() => setOpen(true)} className="self-start">
                {t('phase2.addOwnCta')}
            </Button>
        );
    }

    return (
        <div className="flex flex-col gap-3 rounded-md border border-dashed border-line-strong bg-surface-2 p-4">
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="custom_service_name">{t('phase2.nameLabel')}</Label>
                <Input id="custom_service_name" value={name} onChange={(e) => setName(e.target.value)} />
            </div>

            <div className="flex flex-col gap-1.5">
                <Label htmlFor="custom_service_description">{t('phase2.descriptionLabel')}</Label>
                <Textarea
                    id="custom_service_description"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    rows={2}
                />
            </div>

            <div className="flex flex-col gap-1.5">
                <Label>{t('phase2.featureListLabel')}</Label>
                <ChipInput values={features} onChange={setFeatures} placeholder={t('phase2.featurePlaceholder')} />
            </div>

            <div className="flex flex-col gap-1.5">
                <Label>{t('phase2.referenceLinksLabel')}</Label>
                <ChipInput values={referenceLinks} onChange={setReferenceLinks} placeholder={t('phase2.referenceLinkPlaceholder')} />
                <p className="font-body text-xs text-text-faint">{t('phase2.referenceLinksHint')}</p>
            </div>

            <div className="flex gap-2">
                <Button type="button" size="sm" onClick={submit} disabled={name.trim() === ''}>
                    {t('phase2.addOwnSubmit')}
                </Button>
                <Button type="button" variant="ghost" size="sm" onClick={reset}>
                    {t('common.cancel')}
                </Button>
            </div>
        </div>
    );
}
