import { useState } from 'react';

export default function LoginPage({ onSuccess }) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [emailError, setEmailError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        setEmailError('');
        setSubmitting(true);

        window.axios.post('/login', { email, password })
            .then((response) => onSuccess(response.data))
            .catch((error) => {
                const message = error.response?.data?.errors?.email?.[0];
                setEmailError(message || 'Could not sign in.');
            })
            .finally(() => setSubmitting(false));
    };

    return (
        <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4">
            <p className="text-xs uppercase tracking-wide text-slate-500">Northline</p>
            <h1 className="mt-1 text-2xl font-semibold">Order desk</h1>
            <p className="mt-2 text-sm text-slate-600">Sign in to review and create orders.</p>

            <form onSubmit={submit} className="mt-6 space-y-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <label className="block text-sm">
                    <span className="font-medium text-slate-700">Email</span>
                    <input
                        type="email"
                        autoComplete="username"
                        value={email}
                        onChange={(event) => setEmail(event.target.value)}
                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                        required
                    />
                    {emailError && <span className="mt-1 block text-sm text-red-700">{emailError}</span>}
                </label>

                <label className="block text-sm">
                    <span className="font-medium text-slate-700">Password</span>
                    <input
                        type="password"
                        autoComplete="current-password"
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                        required
                    />
                </label>

                <button
                    type="submit"
                    disabled={submitting}
                    className="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
                >
                    {submitting ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
        </main>
    );
}
