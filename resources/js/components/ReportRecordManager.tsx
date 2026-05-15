import React from 'react';

type IndexType = 'formation' | 'parish_involvement' | 'social_apostolate';

type ReportRecord = {
    id: number;
    indexType: IndexType;
    servedOn: string;
    timeStart: string;
    timeEnd: string;
    cycleCode?: string | null;
    moduleCode?: string | null;
    title?: string | null;
    about?: string | null;
    durationLabel: string;
    obsidianNotePath?: string | null;
};

type ReportSection = {
    type: IndexType;
    label: string;
    cardTitle: string;
    records: ReportRecord[];
};

type Props = {
    csrfToken: string;
    reportTag: string;
    syncAction: string;
    sections: ReportSection[];
};

const sectionDescription: Record<IndexType, string> = {
    formation: 'Manage the formation-only records attached to this saved report.',
    parish_involvement: 'Manage the parish involvement rows attached to this saved report.',
    social_apostolate: 'Manage the social apostolate rows attached to this saved report.',
};

function RecordFields({ type, record }: { type: IndexType; record?: ReportRecord }) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            <div>
                <label className="form-label" htmlFor={`${type}-${record?.id ?? 'new'}-served_on`}>
                    Date
                </label>
                <input
                    className="form-input mt-2"
                    defaultValue={record?.servedOn ?? ''}
                    id={`${type}-${record?.id ?? 'new'}-served_on`}
                    name="served_on"
                    type="date"
                />
            </div>

            {type === 'formation' && (
                <>
                    <div>
                        <label className="form-label" htmlFor={`${type}-${record?.id ?? 'new'}-cycle_code`}>
                            Cycle No.
                        </label>
                        <input
                            className="form-input mt-2"
                            defaultValue={record?.cycleCode ?? ''}
                            id={`${type}-${record?.id ?? 'new'}-cycle_code`}
                            name="cycle_code"
                            type="text"
                        />
                    </div>
                    <div>
                        <label className="form-label" htmlFor={`${type}-${record?.id ?? 'new'}-module_code`}>
                            Module No.
                        </label>
                        <input
                            className="form-input mt-2"
                            defaultValue={record?.moduleCode ?? ''}
                            id={`${type}-${record?.id ?? 'new'}-module_code`}
                            name="module_code"
                            type="text"
                        />
                    </div>
                    <div className="md:col-span-2">
                        <label className="form-label" htmlFor={`${type}-${record?.id ?? 'new'}-title`}>
                            Title
                        </label>
                        <input
                            className="form-input mt-2"
                            defaultValue={record?.title ?? ''}
                            id={`${type}-${record?.id ?? 'new'}-title`}
                            name="title"
                            type="text"
                        />
                    </div>
                </>
            )}

            {type === 'social_apostolate' && (
                <div className="md:col-span-2">
                    <label className="form-label" htmlFor={`${type}-${record?.id ?? 'new'}-about`}>
                        Activity / about
                    </label>
                    <input
                        className="form-input mt-2"
                        defaultValue={record?.about ?? ''}
                        id={`${type}-${record?.id ?? 'new'}-about`}
                        name="about"
                        type="text"
                    />
                </div>
            )}

            <div>
                <label className="form-label" htmlFor={`${type}-${record?.id ?? 'new'}-time_start`}>
                    Time in
                </label>
                <input
                    className="form-input mt-2"
                    defaultValue={record?.timeStart ?? ''}
                    id={`${type}-${record?.id ?? 'new'}-time_start`}
                    name="time_start"
                    type="time"
                />
            </div>
            <div>
                <label className="form-label" htmlFor={`${type}-${record?.id ?? 'new'}-time_end`}>
                    Time out
                </label>
                <input
                    className="form-input mt-2"
                    defaultValue={record?.timeEnd ?? ''}
                    id={`${type}-${record?.id ?? 'new'}-time_end`}
                    name="time_end"
                    type="time"
                />
            </div>
        </div>
    );
}

export function ReportRecordManager({ csrfToken, reportTag, sections, syncAction }: Props) {
    return (
        <div className="space-y-6">
            <form action={syncAction} method="POST" className="paper-panel rounded-[2rem] p-5 sm:p-6">
                <input name="_token" type="hidden" value={csrfToken} />
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="section-kicker">Obsidian Sync</div>
                        <h2 className="mt-3 font-serif text-2xl text-stone-950">Pull saved report note updates</h2>
                        <p className="mt-2 text-sm leading-7 text-stone-600">
                            Use this when record notes were edited directly in Obsidian and you want the Laravel report view to refresh from the vault.
                        </p>
                    </div>
                    <button className="secondary-button !w-auto" type="submit">
                        Sync From Obsidian
                    </button>
                </div>
            </form>

            {sections.map((section) => (
                <section key={section.type} className="paper-panel rounded-[2rem] p-5 sm:p-6">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div className="section-kicker">{section.cardTitle}</div>
                            <h2 className="mt-3 font-serif text-2xl text-stone-950">{section.label}</h2>
                            <p className="mt-2 max-w-3xl text-sm leading-7 text-stone-600">
                                {sectionDescription[section.type]}
                            </p>
                        </div>
                        <div className="rounded-full border border-stone-900/10 bg-white/70 px-3 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
                            {section.records.length} record(s)
                        </div>
                    </div>

                    <form
                        action={`/reports/${reportTag}/records`}
                        className="record-form-shell mt-6"
                        method="POST"
                    >
                        <input name="_token" type="hidden" value={csrfToken} />
                        <input name="index_type" type="hidden" value={section.type} />

                        <RecordFields type={section.type} />

                        <div className="mt-4 flex justify-end">
                            <button className="primary-button" type="submit">
                                Add {section.label} Record
                            </button>
                        </div>
                    </form>

                    <div className="mt-6 space-y-4">
                        {section.records.length === 0 && (
                            <div className="rounded-[1.5rem] border border-dashed border-stone-900/12 bg-stone-50/60 px-5 py-8 text-center text-sm leading-7 text-stone-600">
                                No {section.label.toLowerCase()} records are attached to this report yet.
                            </div>
                        )}

                        {section.records.map((record) => (
                            <article key={record.id} className="report-record-card">
                                <div className="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                    <div className="space-y-2">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="assignment-chip assignment-chip--saved">
                                                <span className="assignment-chip__dot" aria-hidden="true" />
                                                <span>{section.label}</span>
                                            </span>
                                            <span className="compact-pill">{record.durationLabel}</span>
                                        </div>

                                        <div className="text-lg font-semibold text-stone-900">
                                            {section.type === 'formation'
                                                ? `${record.cycleCode} / ${record.moduleCode} - ${record.title}`
                                                : section.type === 'social_apostolate'
                                                    ? record.about
                                                    : 'Parish Involvement'}
                                        </div>

                                        {record.obsidianNotePath && (
                                            <div className="break-all font-mono text-[0.72rem] text-stone-500">
                                                {record.obsidianNotePath}
                                            </div>
                                        )}
                                    </div>

                                    <form
                                        action={`/reports/${reportTag}/records/${record.id}`}
                                        className="xl:pt-1"
                                        method="POST"
                                    >
                                        <input name="_token" type="hidden" value={csrfToken} />
                                        <input name="_method" type="hidden" value="DELETE" />
                                        <button className="danger-button" type="submit">
                                            Delete Record
                                        </button>
                                    </form>
                                </div>

                                <form
                                    action={`/reports/${reportTag}/records/${record.id}`}
                                    className="record-form-shell mt-5"
                                    method="POST"
                                >
                                    <input name="_token" type="hidden" value={csrfToken} />
                                    <input name="_method" type="hidden" value="PATCH" />
                                    <input name="index_type" type="hidden" value={section.type} />

                                    <RecordFields type={section.type} record={record} />

                                    <div className="mt-4 flex justify-end">
                                        <button className="secondary-button !w-auto" type="submit">
                                            Update {section.label} Record
                                        </button>
                                    </div>
                                </form>
                            </article>
                        ))}
                    </div>
                </section>
            ))}
        </div>
    );
}

export default ReportRecordManager;
