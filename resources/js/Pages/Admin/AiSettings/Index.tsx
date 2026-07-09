import { Head, router, useForm } from '@inertiajs/react';
import { type FormEventHandler, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';

type Defaults = {
    model: string;
    effort: string | null;
    max_tokens: number;
    temperature: number | null;
};

type ToolConfig = {
    tool: string;
    model: string | null;
    effort: string | null;
    max_tokens: number | null;
    temperature: number | null;
};

type Budgets = {
    global_monthly_token_cap: number | null;
    per_bo_token_cap: number | null;
    alert_threshold_pct: number;
    rate_limit_per_minute: number;
    budget_mode: 'hard' | 'soft';
};

type PromptVersionRow = {
    id: number;
    version: number;
    active: boolean;
    created_at: string;
};

type PromptTemplateRow = {
    tool: string;
    current_version: number;
    current_prompt: string;
    is_override: boolean;
    default_prompt: string;
    default_version: number;
    history: PromptVersionRow[];
};

const TOOL_LABELS: Record<string, string> = {
    'dcp.generate': 'DCP generate (Phase 0)',
    'suggest.services': 'Suggest — services (Phase 2)',
    'suggest.branding': 'Suggest — branding (Phase 3)',
    'suggest.content_social': 'Suggest — content & social (Phase 4)',
    'suggest.growth': 'Suggest — growth (Phase 5)',
    'spec.compile': 'Spec compile',
    'spec.amend': 'Spec amend',
    'assessment.generate': 'Assessment generate (admin)',
    'proposal.generate': 'Proposal generate (admin)',
    'email.generate': 'Email generate (admin)',
};

function DefaultsCard({ defaults, effortLevels, models }: { defaults: Defaults; effortLevels: string[]; models: string[] }) {
    const form = useForm({
        model: defaults.model,
        effort: defaults.effort ?? '',
        max_tokens: defaults.max_tokens,
        temperature: defaults.temperature ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('admin.ai-settings.defaults.update'), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Global defaults</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div className="flex flex-col gap-1.5">
                        <Label>Model</Label>
                        <Select value={form.data.model} onChange={(e) => form.setData('model', e.target.value)}>
                            {models.map((model) => (
                                <option key={model} value={model}>
                                    {model}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Effort</Label>
                        <Select value={form.data.effort} onChange={(e) => form.setData('effort', e.target.value)}>
                            {effortLevels.map((level) => (
                                <option key={level} value={level}>
                                    {level}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Max output tokens</Label>
                        <Input
                            type="number"
                            min={1}
                            value={form.data.max_tokens}
                            onChange={(e) => form.setData('max_tokens', Number(e.target.value))}
                        />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Temperature</Label>
                        <Input
                            type="number"
                            step={0.1}
                            min={0}
                            max={1}
                            placeholder="omit (adaptive)"
                            value={form.data.temperature}
                            onChange={(e) => form.setData('temperature', e.target.value)}
                        />
                    </div>
                    <div className="col-span-full">
                        <Button type="submit" disabled={form.processing}>
                            Save defaults
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function EditToolDialog({ row, effortLevels, models }: { row: ToolConfig; effortLevels: string[]; models: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        tool: row.tool,
        model: row.model ?? '',
        effort: row.effort ?? '',
        max_tokens: row.max_tokens ?? '',
        temperature: row.temperature ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, _method: 'patch' }));
        form.post(route('admin.ai-settings.tools.update'), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="secondary">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{TOOL_LABELS[row.tool] ?? row.tool}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <p className="font-body text-xs text-text-faint">Leave a field blank to inherit the global default.</p>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-1.5">
                            <Label>Model override</Label>
                            <Select value={form.data.model} onChange={(e) => form.setData('model', e.target.value)}>
                                <option value="">— inherit default —</option>
                                {models.map((model) => (
                                    <option key={model} value={model}>
                                        {model}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label>Effort override</Label>
                            <Select value={form.data.effort} onChange={(e) => form.setData('effort', e.target.value)}>
                                <option value="">— inherit default —</option>
                                {effortLevels.map((level) => (
                                    <option key={level} value={level}>
                                        {level}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label>Max output tokens</Label>
                            <Input
                                type="number"
                                min={1}
                                placeholder="inherit"
                                value={form.data.max_tokens}
                                onChange={(e) => form.setData('max_tokens', e.target.value)}
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label>Temperature</Label>
                            <Input
                                type="number"
                                step={0.1}
                                min={0}
                                max={1}
                                placeholder="inherit"
                                value={form.data.temperature}
                                onChange={(e) => form.setData('temperature', e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ToolConfigSection({ tools, effortLevels, models }: { tools: ToolConfig[]; effortLevels: string[]; models: string[] }) {
    return (
        <div className="mt-8">
            <h2 className="font-display text-xl font-semibold text-text">Per-tool config</h2>
            <p className="mt-1 max-w-2xl font-body text-sm text-text-muted">
                Overrides model/effort/max tokens/temperature for one §7.2 call type. Blank fields inherit the global
                defaults above.
            </p>
            <div className="mt-4 overflow-hidden rounded-admin border border-line bg-surface">
                <table className="w-full border-collapse font-body text-sm">
                    <thead>
                        <tr className="border-b border-line text-left font-ui text-xs uppercase tracking-wide text-text-faint">
                            <th className="px-4 py-3 font-medium">Tool</th>
                            <th className="px-4 py-3 font-medium">Model</th>
                            <th className="px-4 py-3 font-medium">Effort</th>
                            <th className="px-4 py-3 font-medium">Max tokens</th>
                            <th className="px-4 py-3 font-medium">Temp</th>
                            <th className="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {tools.map((row) => (
                            <tr key={row.tool} className="border-b border-line last:border-0 hover:bg-surface-2">
                                <td className="px-4 py-3 font-ui font-medium text-text">
                                    {TOOL_LABELS[row.tool] ?? row.tool}
                                </td>
                                <td className="px-4 py-3 text-text-muted">
                                    {row.model ?? <span className="text-text-faint italic">default</span>}
                                </td>
                                <td className="px-4 py-3 text-text-muted">
                                    {row.effort ?? <span className="text-text-faint italic">default</span>}
                                </td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">
                                    {row.max_tokens ?? <span className="text-text-faint italic">default</span>}
                                </td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">
                                    {row.temperature ?? <span className="text-text-faint italic">default</span>}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <EditToolDialog row={row} effortLevels={effortLevels} models={models} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function BudgetsCard({ budgets }: { budgets: Budgets }) {
    const form = useForm({
        global_monthly_token_cap: budgets.global_monthly_token_cap ?? '',
        per_bo_token_cap: budgets.per_bo_token_cap ?? '',
        alert_threshold_pct: budgets.alert_threshold_pct,
        rate_limit_per_minute: budgets.rate_limit_per_minute,
        budget_mode: budgets.budget_mode,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.patch(route('admin.ai-settings.budgets.update'), { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Token budgets & safeguards (§7.7)</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid grid-cols-2 gap-4 md:grid-cols-3">
                    <div className="flex flex-col gap-1.5">
                        <Label>Global monthly cap (tokens)</Label>
                        <Input
                            type="number"
                            min={0}
                            placeholder="unlimited"
                            value={form.data.global_monthly_token_cap}
                            onChange={(e) => form.setData('global_monthly_token_cap', e.target.value)}
                        />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Per-BO cap (tokens)</Label>
                        <Input
                            type="number"
                            min={0}
                            placeholder="unlimited"
                            value={form.data.per_bo_token_cap}
                            onChange={(e) => form.setData('per_bo_token_cap', e.target.value)}
                        />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Alert threshold (%)</Label>
                        <Input
                            type="number"
                            min={1}
                            max={100}
                            value={form.data.alert_threshold_pct}
                            onChange={(e) => form.setData('alert_threshold_pct', Number(e.target.value))}
                        />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Rate limit (calls/min per BO)</Label>
                        <Input
                            type="number"
                            min={1}
                            value={form.data.rate_limit_per_minute}
                            onChange={(e) => form.setData('rate_limit_per_minute', Number(e.target.value))}
                        />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label>Hard-stop mode</Label>
                        <Select
                            value={form.data.budget_mode}
                            onChange={(e) => form.setData('budget_mode', e.target.value as 'hard' | 'soft')}
                        >
                            <option value="hard">Hard — block calls once exhausted</option>
                            <option value="soft">Soft — warn only, let calls through</option>
                        </Select>
                    </div>
                    <div className="col-span-full">
                        <Button type="submit" disabled={form.processing}>
                            Save budgets
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function PricingCard({ pricing }: { pricing: Record<string, { input: number; output: number }> }) {
    const [rows, setRows] = useState(pricing);
    const [saving, setSaving] = useState(false);

    const setValue = (model: string, key: 'input' | 'output', value: number) => {
        setRows({ ...rows, [model]: { ...rows[model], [key]: value } });
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setSaving(true);
        router.patch(
            route('admin.ai-settings.pricing.update'),
            { pricing: rows },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Pricing table ($ / million tokens)</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="flex flex-col gap-3">
                    {Object.entries(rows).map(([model, price]) => (
                        <div key={model} className="grid grid-cols-3 items-center gap-4">
                            <span className="font-ui text-sm text-text">{model}</span>
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-xs">Input</Label>
                                <Input
                                    type="number"
                                    step={0.01}
                                    min={0}
                                    value={price.input}
                                    onChange={(e) => setValue(model, 'input', Number(e.target.value))}
                                />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label className="text-xs">Output</Label>
                                <Input
                                    type="number"
                                    step={0.01}
                                    min={0}
                                    value={price.output}
                                    onChange={(e) => setValue(model, 'output', Number(e.target.value))}
                                />
                            </div>
                        </div>
                    ))}
                    <div>
                        <Button type="submit" disabled={saving}>
                            Save pricing
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function PromptTemplateDialog({ template }: { template: PromptTemplateRow }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ tool: template.tool, system_prompt: template.current_prompt });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.post(route('admin.ai-settings.prompt-templates.store'), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    const reset = () => {
        if (!confirm(`Revert ${TOOL_LABELS[template.tool] ?? template.tool} to its built-in default prompt?`)) return;
        router.post(
            route('admin.ai-settings.prompt-templates.reset'),
            { tool: template.tool },
            { preserveScroll: true, onSuccess: () => setOpen(false) },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="secondary">
                    View / edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {TOOL_LABELS[template.tool] ?? template.tool} — v{template.current_version}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <Textarea
                        value={form.data.system_prompt}
                        onChange={(e) => form.setData('system_prompt', e.target.value)}
                        rows={16}
                        className="font-mono text-xs"
                    />
                    {template.history.length > 0 && (
                        <div className="flex flex-col gap-1.5">
                            <Label className="text-xs">Version history</Label>
                            <div className="flex max-h-28 flex-col gap-1 overflow-y-auto rounded-md border border-line p-2">
                                {template.history.map((row) => (
                                    <div key={row.id} className="flex items-center justify-between font-body text-xs text-text-muted">
                                        <span>
                                            v{row.version} · {new Date(row.created_at).toLocaleString()}
                                        </span>
                                        {row.active && <Badge variant="accent">Active</Badge>}
                                    </div>
                                ))}
                                <div className="flex items-center justify-between font-body text-xs text-text-faint">
                                    <span>v{template.default_version} · built-in default</span>
                                </div>
                            </div>
                        </div>
                    )}
                    <DialogFooter className="justify-between sm:justify-between">
                        <Button type="button" variant="ghost" onClick={reset} disabled={!template.is_override}>
                            Reset to default
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Save new version
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function PromptTemplatesSection({ templates }: { templates: PromptTemplateRow[] }) {
    return (
        <div className="mt-8">
            <h2 className="font-display text-xl font-semibold text-text">Prompt templates</h2>
            <p className="mt-1 max-w-2xl font-body text-sm text-text-muted">
                The system-policy prompt behind each tool (§7.3 block 1). Every save creates a new version; "reset to
                default" reverts to the built-in prompt without losing history.
            </p>
            <div className="mt-4 overflow-hidden rounded-admin border border-line bg-surface">
                <table className="w-full border-collapse font-body text-sm">
                    <thead>
                        <tr className="border-b border-line text-left font-ui text-xs uppercase tracking-wide text-text-faint">
                            <th className="px-4 py-3 font-medium">Tool</th>
                            <th className="px-4 py-3 font-medium">Version</th>
                            <th className="px-4 py-3 font-medium">Status</th>
                            <th className="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {templates.map((template) => (
                            <tr key={template.tool} className="border-b border-line last:border-0 hover:bg-surface-2">
                                <td className="px-4 py-3 font-ui font-medium text-text">
                                    {TOOL_LABELS[template.tool] ?? template.tool}
                                </td>
                                <td className="px-4 py-3 tabular-nums text-text-muted">v{template.current_version}</td>
                                <td className="px-4 py-3">
                                    {template.is_override ? (
                                        <Badge variant="accent">Customized</Badge>
                                    ) : (
                                        <Badge variant="muted">Default</Badge>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <PromptTemplateDialog template={template} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function AiSettingsIndex({
    defaults,
    tools,
    budgets,
    pricing,
    promptTemplates,
    availableModels,
    effortLevels,
}: {
    defaults: Defaults;
    tools: ToolConfig[];
    budgets: Budgets;
    pricing: Record<string, { input: number; output: number }>;
    promptTemplates: PromptTemplateRow[];
    availableModels: string[];
    effortLevels: string[];
}) {
    return (
        <AdminLayout>
            <Head title="AI & system settings" />

            <section className="animate-rise rounded-section border border-line-strong bg-surface-glass p-8 backdrop-blur-2xl">
                <p className="eyebrow text-accent">Admin</p>
                <h1 className="mt-2 font-display text-3xl font-semibold text-text">AI & system settings</h1>
                <p className="mt-2 max-w-2xl font-body text-sm text-text-muted">
                    Model/token/effort config per tool, global &amp; per-BO budgets, pricing, and the prompt template
                    library (§6.7). See the <span className="font-ui text-text">Usage</span> tab for the cost
                    explorer.
                </p>
            </section>

            <div className="mt-6 flex flex-col gap-6">
                <DefaultsCard defaults={defaults} effortLevels={effortLevels} models={availableModels} />
                <BudgetsCard budgets={budgets} />
                <PricingCard pricing={pricing} />
            </div>

            <ToolConfigSection tools={tools} effortLevels={effortLevels} models={availableModels} />
            <PromptTemplatesSection templates={promptTemplates} />
        </AdminLayout>
    );
}
