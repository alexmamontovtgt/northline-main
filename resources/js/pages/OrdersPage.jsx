import { useEffect, useState } from 'react';

const statusOptions = [
    { value: '', label: 'All statuses' },
    { value: 'new', label: 'New' },
    { value: 'processing', label: 'Processing' },
    { value: 'completed', label: 'Completed' },
];

function blankItem() {
    return { product_name: '', qty: '1', unit_price: '' };
}

function money(value) {
    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return '0.00';
    }

    return amount.toFixed(2);
}

export default function OrdersPage({ user, onLogout }) {
    const [orders, setOrders] = useState([]);
    const [customers, setCustomers] = useState([]);
    const [status, setStatus] = useState('');
    const [email, setEmail] = useState('');
    const [loaded, setLoaded] = useState(false);
    const [listError, setListError] = useState('');
    const [reloadKey, setReloadKey] = useState(0);
    const [customerId, setCustomerId] = useState('');
    const [items, setItems] = useState([blankItem()]);
    const [formError, setFormError] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        window.axios.get('/api/customers')
            .then((response) => setCustomers(response.data.data))
            .catch(() => setListError('Could not load customers.'));
    }, []);

    useEffect(() => {
        const load = () => {
            const params = {};

            if (status) {
                params.status = status;
            }

            window.axios.get('/api/orders', { params })
                .then((response) => {
                    setOrders(response.data.data);
                    setListError('');
                    setLoaded(true);
                })
                .catch(() => setListError('Could not load orders.'));
        };

        load();
        const timer = window.setInterval(load, 4000);

        return () => window.clearInterval(timer);
    }, [status, reloadKey]);

    const updateItem = (index, field, value) => {
        setItems((current) => current.map((item, itemIndex) => {
            if (itemIndex !== index) {
                return item;
            }

            return { ...item, [field]: value };
        }));
    };

    const submit = (event) => {
        event.preventDefault();
        setFormError('');
        setSaving(true);

        window.axios.post('/api/orders', {
            customer_id: customerId,
            items: items.map((item) => ({
                product_name: item.product_name,
                qty: item.qty,
                unit_price: item.unit_price,
            })),
        })
            .then(() => {
                setCustomerId('');
                setItems([blankItem()]);
                setReloadKey((value) => value + 1);
            })
            .catch((error) => {
                setFormError(error.response?.data?.message || 'Could not save the order.');
            })
            .finally(() => setSaving(false));
    };

    const removeOrder = (order) => {
        if (!window.confirm(`Delete order ${order.id}?`)) {
            return;
        }

        window.axios.delete(`/api/orders/${order.id}`)
            .then(() => setReloadKey((value) => value + 1))
            .catch(() => setListError('Could not delete the order.'));
    };

    const signOut = () => {
        window.axios.post('/logout').finally(() => onLogout());
    };

    const draftTotal = items.reduce((sum, item) => {
        return sum + (Number(item.qty) || 0) * (Number(item.unit_price) || 0);
    }, 0);

    return (
        <div className="min-h-screen">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
                    <div>
                        <p className="text-xs uppercase tracking-wide text-slate-500">Northline</p>
                        <h1 className="text-lg font-semibold">Order desk</h1>
                    </div>
                    <div className="flex items-center gap-3 text-sm">
                        <span className="text-slate-600">{user.name}</span>
                        <button type="button" onClick={signOut} className="rounded-md border border-slate-300 px-3 py-1.5">
                            Sign out
                        </button>
                    </div>
                </div>
            </header>

            <main className="mx-auto grid max-w-6xl gap-6 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <section className="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-end gap-3 border-b border-slate-200 px-4 py-3">
                        <label className="text-sm">
                            <span className="mb-1 block font-medium text-slate-700">Status</span>
                            <select
                                value={status}
                                onChange={(event) => setStatus(event.target.value)}
                                className="rounded-md border border-slate-300 px-3 py-2"
                            >
                                {statusOptions.map((option) => (
                                    <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                            </select>
                        </label>
                        <label className="text-sm">
                            <span className="mb-1 block font-medium text-slate-700">Customer email</span>
                            <input
                                type="email"
                                value={email}
                                onChange={(event) => setEmail(event.target.value)}
                                placeholder="buyer@company.test"
                                className="rounded-md border border-slate-300 px-3 py-2"
                            />
                        </label>
                    </div>

                    {listError && <p className="px-4 pt-3 text-sm text-red-700">{listError}</p>}

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Order</th>
                                    <th className="px-4 py-3 font-medium">Customer</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 font-medium">Items</th>
                                    <th className="px-4 py-3 font-medium">Total</th>
                                    <th className="px-4 py-3 font-medium">Email</th>
                                    <th className="px-4 py-3 font-medium">Owner</th>
                                    <th className="px-4 py-3 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {loaded && orders.length === 0 && (
                                    <tr>
                                        <td colSpan="8" className="px-4 py-6 text-slate-500">No orders match this filter.</td>
                                    </tr>
                                )}
                                {orders.map((order) => (
                                    <tr key={order.id} className="border-t border-slate-100">
                                        <td className="px-4 py-3 font-medium">#{order.id}</td>
                                        <td className="px-4 py-3">
                                            <div>{order.customer.name}</div>
                                            <div className="text-slate-500">{order.customer.email}</div>
                                        </td>
                                        <td className="px-4 py-3 capitalize">{order.status}</td>
                                        <td className="px-4 py-3">{order.items_count}</td>
                                        <td className="px-4 py-3">{money(order.total_amount)}</td>
                                        <td className="px-4 py-3">{order.email_sent ? 'Sent' : 'Pending'}</td>
                                        <td className="px-4 py-3">{order.created_by === user.id ? 'You' : 'Teammate'}</td>
                                        <td className="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() => removeOrder(order)}
                                                className="text-red-700"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="h-fit rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 className="font-semibold">New order</h2>
                    <form onSubmit={submit} className="mt-4 space-y-3">
                        {formError && <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{formError}</p>}

                        <label className="block text-sm">
                            <span className="font-medium text-slate-700">Customer</span>
                            <select
                                value={customerId}
                                onChange={(event) => setCustomerId(event.target.value)}
                                className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                                required
                            >
                                <option value="">Select a customer</option>
                                {customers.map((customer) => (
                                    <option key={customer.id} value={customer.id}>
                                        {customer.name}
                                    </option>
                                ))}
                            </select>
                        </label>

                        {items.map((item, index) => (
                            <div key={index} className="space-y-2 rounded-md border border-slate-200 p-3">
                                <label className="block text-sm">
                                    <span className="text-slate-600">Product</span>
                                    <input
                                        value={item.product_name}
                                        onChange={(event) => updateItem(index, 'product_name', event.target.value)}
                                        className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                                    />
                                </label>
                                <div className="grid grid-cols-2 gap-2">
                                    <label className="block text-sm">
                                        <span className="text-slate-600">Qty</span>
                                        <input
                                            type="number"
                                            min="1"
                                            value={item.qty}
                                            onChange={(event) => updateItem(index, 'qty', event.target.value)}
                                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                                        />
                                    </label>
                                    <label className="block text-sm">
                                        <span className="text-slate-600">Unit price</span>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={item.unit_price}
                                            onChange={(event) => updateItem(index, 'unit_price', event.target.value)}
                                            className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
                                        />
                                    </label>
                                </div>
                                {items.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={() => setItems((current) => current.filter((_, itemIndex) => itemIndex !== index))}
                                        className="text-sm text-slate-500"
                                    >
                                        Remove line
                                    </button>
                                )}
                            </div>
                        ))}

                        <button
                            type="button"
                            onClick={() => setItems((current) => [...current, blankItem()])}
                            className="text-sm font-medium text-slate-700"
                        >
                            Add line
                        </button>

                        <p className="text-sm text-slate-600">Draft total {money(draftTotal)}</p>

                        <button
                            type="submit"
                            disabled={saving}
                            className="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white disabled:opacity-60"
                        >
                            {saving ? 'Saving…' : 'Create order'}
                        </button>
                    </form>
                </section>
            </main>
        </div>
    );
}
