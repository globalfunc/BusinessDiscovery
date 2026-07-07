import { Sparkles } from 'lucide-react';
import { useState } from 'react';

import { AddCustomServiceForm } from '@/components/discovery/AddCustomServiceForm';
import { CatalogServiceCard, type CatalogService } from '@/components/discovery/CatalogServiceCard';
import { SelectedServiceCard, type SelectedServiceRecord } from '@/components/discovery/SelectedServiceCard';
import { Button } from '@/components/ui/button';
import type { Locale } from '@/lib/i18n';

type Props = {
    locale: Locale;
    t: (key: string, vars?: Record<string, string>) => string;
    serviceCatalog: CatalogService[];
    initialSelectedServices: SelectedServiceRecord[];
    showPricesToBo: boolean;
};

export function Phase2ServicesSelection({ locale, t, serviceCatalog, initialSelectedServices, showPricesToBo }: Props) {
    const [selectedServices, setSelectedServices] = useState<SelectedServiceRecord[]>(initialSelectedServices);

    const byServiceId = new Map(selectedServices.filter((s) => s.service_id !== null).map((s) => [s.service_id, s]));
    const catalogByKey = new Map(serviceCatalog.map((s) => [s.id, s.key]));

    const addFromCatalog = (serviceId: number) => {
        window.axios.post(route('discovery.services.store'), { service_id: serviceId }).then(({ data }) => {
            setSelectedServices((prev) => [...prev, data.selectedService]);
        });
    };

    const addCustom = (payload: { name: string; description: string; features: string[]; reference_links: string[] }) => {
        window.axios.post(route('discovery.services.store'), payload).then(({ data }) => {
            setSelectedServices((prev) => [...prev, data.selectedService]);
        });
    };

    const removeSelection = (id: number) => {
        setSelectedServices((prev) => prev.filter((s) => s.id !== id));
        window.axios.delete(route('discovery.services.destroy', { selectedService: id }));
    };

    const patchSelection = (id: number, payload: Partial<SelectedServiceRecord>) => {
        setSelectedServices((prev) => prev.map((s) => (s.id === id ? { ...s, ...payload } : s)));
        window.axios.patch(route('discovery.services.update', { selectedService: id }), payload);
    };

    return (
        <div className="flex flex-col gap-6">
            <div className="flex items-start justify-between gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase2.catalogHeading')}</p>
                <Button type="button" variant="secondary" size="sm" disabled className="shrink-0 gap-1.5 border-accent/40 text-accent">
                    <Sparkles className="h-3.5 w-3.5" />
                    {t('phase2.aiSuggestionsCta')}
                </Button>
            </div>
            <p className="-mt-4 font-body text-xs text-text-faint">{t('phase2.aiSuggestionsComingSoon')}</p>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {serviceCatalog.map((service) => {
                    const selected = byServiceId.get(service.id) ?? null;
                    return (
                        <CatalogServiceCard
                            key={service.id}
                            t={t}
                            locale={locale}
                            service={service}
                            added={selected !== null}
                            showPrice={showPricesToBo}
                            onAdd={() => addFromCatalog(service.id)}
                            onRemove={() => selected && removeSelection(selected.id)}
                        />
                    );
                })}
            </div>

            {selectedServices.length > 0 && (
                <div className="flex flex-col gap-3">
                    <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">
                        {t('phase2.selectedHeading')}
                    </p>
                    <div className="flex flex-col gap-3">
                        {selectedServices.map((record) => {
                            const catalogEntry = record.service_id
                                ? serviceCatalog.find((s) => s.id === record.service_id)
                                : null;
                            const displayName = record.custom ? (record.name ?? '') : (catalogEntry?.name[locale] ?? '');
                            const displaySubtitle = record.custom ? record.description : (catalogEntry?.one_liner[locale] ?? null);

                            return (
                                <SelectedServiceCard
                                    key={record.id}
                                    t={t}
                                    record={record}
                                    serviceKey={record.service_id ? catalogByKey.get(record.service_id) : null}
                                    displayName={displayName}
                                    displaySubtitle={displaySubtitle}
                                    showPrice={showPricesToBo}
                                    onFeaturesChange={(features) => patchSelection(record.id, { features })}
                                    onPriorityToggle={() => patchSelection(record.id, { priority: !record.priority })}
                                    onNoteChange={(note) => patchSelection(record.id, { note })}
                                    onRemove={() => removeSelection(record.id)}
                                />
                            );
                        })}
                    </div>
                </div>
            )}

            <div className="flex flex-col gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">{t('phase2.addOwnHeading')}</p>
                <AddCustomServiceForm t={t} onSubmit={addCustom} />
            </div>
        </div>
    );
}
