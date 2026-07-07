import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { SelectableCard } from '@/components/discovery/SelectableCard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useAutosaveField } from '@/hooks/useAutosaveField';
import type { Locale } from '@/lib/i18n';
import type { DcpState } from '@/Pages/Discovery/Show';

type NicheOption = { id: number; name: { en: string; bg: string } };
type CategoryOption = { id: number; name: { en: string; bg: string }; niches: NicheOption[] };

type Props = {
    locale: Locale;
    t: (key: string, vars?: Record<string, string>) => string;
    businessOwner: {
        name: string;
        company: string;
        pre_selected_niche_id: number | null;
        pre_selected_category_id: number | null;
    };
    answers: Record<string, unknown>;
    taxonomyCategories: CategoryOption[];
    dcp: DcpState;
    onValidityChange: (valid: boolean) => void;
};

export function Phase1BusinessProfile({
    locale,
    t,
    businessOwner,
    answers,
    taxonomyCategories,
    dcp,
    onValidityChange,
}: Props) {
    const initialName = typeof answers.profile_name === 'string' ? answers.profile_name : businessOwner.name;
    const initialCompany = typeof answers.profile_company === 'string' ? answers.profile_company : businessOwner.company;
    const initialWebsite = typeof answers.profile_website === 'string' ? answers.profile_website : '';
    // DCP-detected niche (dcp.generate, §3.2): pre-highlighted with a badge,
    // never auto-selected — one tap to confirm. Only suggested while the BO
    // hasn't picked a niche yet.
    const suggestedNicheId = dcp?.detected_niche?.niche_id ?? null;
    const suggestedCategoryId = dcp?.detected_niche?.category_id ?? null;

    const initialCategoryId =
        'category_id' in answers
            ? (answers.category_id as number | null)
            : (businessOwner.pre_selected_category_id ?? suggestedCategoryId);
    const initialNicheId =
        'niche_id' in answers ? (answers.niche_id as number | null) : (businessOwner.pre_selected_niche_id ?? null);
    const initialCustomNiche = typeof answers.custom_niche_text === 'string' ? answers.custom_niche_text : '';

    const name = useAutosaveField('phase_1', 'profile_name', initialName);
    const company = useAutosaveField('phase_1', 'profile_company', initialCompany);
    const website = useAutosaveField('phase_1', 'profile_website', initialWebsite);
    const categoryId = useAutosaveField<number | null>('phase_1', 'category_id', initialCategoryId);
    const nicheId = useAutosaveField<number | null>('phase_1', 'niche_id', initialNicheId);
    const customNiche = useAutosaveField('phase_1', 'custom_niche_text', initialCustomNiche);

    const [search, setSearch] = useState('');
    const [otherOpen, setOtherOpen] = useState(initialNicheId === null && initialCustomNiche.trim() !== '');
    const [retrying, setRetrying] = useState(false);

    const retryDcp = () => {
        setRetrying(true);
        router.post(route('discovery.intake.store'), {}, { onFinish: () => setRetrying(false) });
    };

    const nicheBadge = (id: number) => (id === suggestedNicheId ? t('phase1.suggestedBadge') : undefined);

    const isValid = nicheId.value !== null || (otherOpen && customNiche.value.trim() !== '');

    useEffect(() => {
        onValidityChange(isValid);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isValid]);

    const selectCategory = (id: number) => {
        categoryId.setValue(id);
        nicheId.setValue(null);
        setOtherOpen(false);
        customNiche.setValue('');
    };

    const changeCategory = () => {
        categoryId.setValue(null);
        nicheId.setValue(null);
    };

    const selectNiche = (niche: NicheOption, catId: number) => {
        categoryId.setValue(catId);
        nicheId.setValue(niche.id);
        setOtherOpen(false);
        customNiche.setValue('');
    };

    const selectOther = () => {
        nicheId.setValue(null);
        setOtherOpen(true);
    };

    const searchLower = search.trim().toLowerCase();
    const searchMatches = searchLower
        ? taxonomyCategories.flatMap((category) =>
              category.niches
                  .filter(
                      (niche) =>
                          niche.name.en.toLowerCase().includes(searchLower) ||
                          niche.name.bg.toLowerCase().includes(searchLower) ||
                          category.name.en.toLowerCase().includes(searchLower) ||
                          category.name.bg.toLowerCase().includes(searchLower),
                  )
                  .map((niche) => ({ niche, category })),
          )
        : [];

    const activeCategory = taxonomyCategories.find((category) => category.id === categoryId.value) ?? null;

    return (
        <div className="flex flex-col gap-6">
            {dcp?.status === 'empty' && (
                <div className="flex flex-col gap-2 rounded-md border border-line bg-surface-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="font-body text-sm text-text-muted">{t('phase1.dcpFailedBody')}</p>
                    <Button variant="ghost" size="sm" onClick={retryDcp} disabled={retrying}>
                        {retrying ? t('phase1.dcpRetrying') : t('phase1.dcpRetry')}
                    </Button>
                </div>
            )}

            <div className="flex flex-col gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">
                    {t('phase1.profileHeading')}
                </p>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="profile_name">{t('phase1.nameLabel')}</Label>
                    <Input id="profile_name" value={name.value} onChange={(e) => name.setValue(e.target.value)} />
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="profile_company">{t('phase1.companyLabel')}</Label>
                    <Input id="profile_company" value={company.value} onChange={(e) => company.setValue(e.target.value)} />
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="profile_website">{t('phase1.websiteLabel')}</Label>
                    <Input
                        id="profile_website"
                        placeholder={t('phase1.websitePlaceholder')}
                        value={website.value}
                        onChange={(e) => website.setValue(e.target.value)}
                    />
                </div>
            </div>

            <div className="flex flex-col gap-3">
                <p className="font-ui text-xs font-semibold uppercase tracking-wide text-text-muted">
                    {t('phase1.categoryHeading')}
                </p>

                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder={t('phase1.searchPlaceholder')}
                />

                {searchLower ? (
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {searchMatches.map(({ niche, category }) => (
                            <SelectableCard
                                key={niche.id}
                                selected={nicheId.value === niche.id}
                                onSelect={() => selectNiche(niche, category.id)}
                                title={niche.name[locale]}
                                subtitle={category.name[locale]}
                                badge={nicheBadge(niche.id)}
                            />
                        ))}
                    </div>
                ) : activeCategory === null ? (
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        {taxonomyCategories.map((category) => (
                            <SelectableCard
                                key={category.id}
                                selected={false}
                                onSelect={() => selectCategory(category.id)}
                                title={category.name[locale]}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        <button
                            type="button"
                            onClick={changeCategory}
                            className="self-start font-ui text-xs text-text-muted hover:text-text"
                        >
                            {t('phase1.changeCategory')}
                        </button>
                        <p className="font-ui text-sm font-medium text-text">{activeCategory.name[locale]}</p>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            {activeCategory.niches.map((niche) => (
                                <SelectableCard
                                    key={niche.id}
                                    selected={nicheId.value === niche.id}
                                    onSelect={() => selectNiche(niche, activeCategory.id)}
                                    title={niche.name[locale]}
                                    badge={nicheBadge(niche.id)}
                                />
                            ))}
                        </div>
                    </div>
                )}

                <SelectableCard
                    selected={otherOpen}
                    onSelect={selectOther}
                    title={t('phase1.otherTitle')}
                    subtitle={t('phase1.otherDescription')}
                />

                {otherOpen && (
                    <div className="flex flex-col gap-1.5">
                        <Textarea
                            value={customNiche.value}
                            onChange={(e) => customNiche.setValue(e.target.value)}
                            placeholder={t('phase1.otherPlaceholder')}
                            rows={3}
                        />
                        <p className="font-body text-xs text-text-faint">{t('phase1.otherHint')}</p>
                    </div>
                )}

                {!isValid && <p className="font-body text-xs text-red">{t('phase1.requiredHint')}</p>}
            </div>
        </div>
    );
}
