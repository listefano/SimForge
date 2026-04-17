import { Head } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useMemo, useState } from 'react';

type AuthUser = {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
};

type PageProps = {
    auth: {
        user: AuthUser | null;
    };
};

type Batch = {
    id: number;
    name: string;
    status: string;
    type: string;
    total_simulations: number;
    completed_simulations: number;
    remaining_simulations: number;
    progress_percent: number;
};

type SimulationResult = {
    id: number;
    status: string;
    type: string;
    item_name: string | null;
    dps: number | null;
    dps_gain: number | null;
};

export default function Dashboard({ auth }: PageProps) {
    const [batches, setBatches] = useState<Batch[]>([]);
    const [selectedBatchId, setSelectedBatchId] = useState<number | null>(null);
    const [results, setResults] = useState<SimulationResult[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const selectedBatch = useMemo(
        () => batches.find((batch) => batch.id === selectedBatchId) ?? null,
        [batches, selectedBatchId],
    );

    useEffect(() => {
        void loadBatches();
    }, []);

    useEffect(() => {
        if (selectedBatchId) {
            void loadResults(selectedBatchId);
        }
    }, [selectedBatchId]);

    useEffect(() => {
        const echo = window.Echo;

        if (!echo || !selectedBatchId) {
            return;
        }

        const batchChannelName = `private-batch.${selectedBatchId}`;
        const userChannelName = auth.user ? `private-user.${auth.user.id}` : null;

        echo.private(`batch.${selectedBatchId}`)
            .listen('.simulation.batch.updated', (event: Partial<Batch> & { batch_id: number }) => {
                setBatches((current) =>
                    current.map((batch) =>
                        batch.id === event.batch_id
                            ? {
                                  ...batch,
                                  ...event,
                                  id: batch.id,
                                  name: batch.name,
                                  type: batch.type,
                              }
                            : batch,
                    ),
                );
            })
            .listen('.simulation.completed', () => {
                void loadResults(selectedBatchId);
            });

        if (auth.user) {
            echo.private(`user.${auth.user.id}`)
                .listen('.simulation.batch.updated', () => {
                    void loadBatches();
                });
        }

        return () => {
            echo.leaveChannel(batchChannelName);

            if (userChannelName) {
                echo.leaveChannel(userChannelName);
            }
        };
    }, [auth.user, selectedBatchId]);

    async function loadBatches(): Promise<void> {
        try {
            setLoading(true);
            const response = await axios.get('/api/v1/simulations/batches', {
                params: { per_page: 20 },
            });

            const nextBatches = response.data?.data ?? [];
            setBatches(nextBatches);

            if (!selectedBatchId && nextBatches.length > 0) {
                setSelectedBatchId(nextBatches[0].id);
            }

            setError(null);
        } catch {
            setError('Batches konnten nicht geladen werden. Bitte einloggen und erneut versuchen.');
        } finally {
            setLoading(false);
        }
    }

    async function loadResults(batchId: number): Promise<void> {
        try {
            const response = await axios.get(`/api/v1/simulations/batches/${batchId}/simulations`, {
                params: { per_page: 100 },
            });

            setResults(response.data?.data ?? []);
        } catch {
            setResults([]);
        }
    }

    return (
        <>
            <Head title="Live Progress" />

            <main className="mx-auto grid min-h-screen max-w-7xl gap-8 p-6 lg:grid-cols-[minmax(280px,360px)_1fr] lg:p-10">
                <aside className="rounded-3xl border border-orange-200/20 bg-slate-900/70 p-5 shadow-[0_20px_60px_-35px_rgba(251,146,60,0.45)] backdrop-blur">
                    <h1 className="font-serif text-2xl tracking-wide text-orange-300">SimForge Live</h1>
                    <p className="mt-2 text-sm text-slate-300">
                        Live-Fortschritt ueber Inertia + React + TypeScript.
                    </p>

                    <div className="mt-6">
                        <button
                            type="button"
                            className="rounded-full bg-orange-400 px-4 py-2 text-sm font-semibold text-slate-900 transition hover:bg-orange-300"
                            onClick={() => void loadBatches()}
                        >
                            Aktualisieren
                        </button>
                    </div>

                    <div className="mt-6 space-y-3">
                        {loading && <p className="text-sm text-slate-400">Lade Batches...</p>}

                        {!loading && batches.length === 0 && !error && (
                            <p className="text-sm text-slate-400">Noch keine Simulation-Batches gefunden.</p>
                        )}

                        {error && <p className="text-sm text-rose-300">{error}</p>}

                        {batches.map((batch) => (
                            <button
                                key={batch.id}
                                type="button"
                                onClick={() => setSelectedBatchId(batch.id)}
                                className={`w-full rounded-2xl border p-4 text-left transition ${
                                    batch.id === selectedBatchId
                                        ? 'border-orange-300 bg-orange-300/10'
                                        : 'border-slate-700 hover:border-orange-200/50'
                                }`}
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm font-semibold text-slate-100">#{batch.id}</span>
                                    <span className="rounded-full bg-slate-800 px-2 py-1 text-xs uppercase tracking-wide text-orange-200">
                                        {batch.status}
                                    </span>
                                </div>
                                <p className="mt-2 truncate text-sm text-slate-300">{batch.name}</p>
                                <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-800">
                                    <div
                                        className="h-full rounded-full bg-gradient-to-r from-orange-500 to-amber-300 transition-all"
                                        style={{ width: `${Math.min(batch.progress_percent, 100)}%` }}
                                    />
                                </div>
                                <p className="mt-2 text-xs text-slate-400">
                                    {batch.completed_simulations} / {batch.total_simulations} ({batch.progress_percent}%)
                                </p>
                            </button>
                        ))}
                    </div>
                </aside>

                <section className="rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-[0_20px_60px_-35px_rgba(15,23,42,0.7)]">
                    {!selectedBatch && (
                        <div className="grid h-full place-content-center">
                            <p className="text-slate-400">Waehle links einen Batch aus.</p>
                        </div>
                    )}

                    {selectedBatch && (
                        <>
                            <header className="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-4">
                                <div>
                                    <h2 className="font-serif text-3xl text-slate-100">Batch #{selectedBatch.id}</h2>
                                    <p className="text-sm text-slate-400">{selectedBatch.name}</p>
                                </div>
                                <div className="text-right">
                                    <p className="text-sm text-slate-300">Rest: {selectedBatch.remaining_simulations}</p>
                                    <p className="text-lg font-semibold text-orange-300">{selectedBatch.progress_percent}%</p>
                                </div>
                            </header>

                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[580px] text-sm">
                                    <thead>
                                        <tr className="text-left text-slate-400">
                                            <th className="pb-3">Simulation</th>
                                            <th className="pb-3">Status</th>
                                            <th className="pb-3">Item</th>
                                            <th className="pb-3">DPS</th>
                                            <th className="pb-3">Gain</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {results.map((result) => (
                                            <tr key={result.id} className="border-t border-slate-800 text-slate-200">
                                                <td className="py-3">#{result.id}</td>
                                                <td className="py-3 uppercase text-xs tracking-wide">{result.status}</td>
                                                <td className="py-3">{result.item_name ?? '-'}</td>
                                                <td className="py-3">
                                                    {typeof result.dps === 'number'
                                                        ? result.dps.toLocaleString('de-DE', {
                                                              maximumFractionDigits: 0,
                                                          })
                                                        : '-'}
                                                </td>
                                                <td className="py-3 text-emerald-300">
                                                    {typeof result.dps_gain === 'number'
                                                        ? `+${result.dps_gain.toLocaleString('de-DE', {
                                                              maximumFractionDigits: 0,
                                                          })}`
                                                        : '-'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}
                </section>
            </main>
        </>
    );
}
