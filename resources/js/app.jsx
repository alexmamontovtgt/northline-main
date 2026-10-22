import './bootstrap';
import { createRoot } from 'react-dom/client';
import { useEffect, useState } from 'react';
import LoginPage from './pages/LoginPage';
import OrdersPage from './pages/OrdersPage';

function App() {
    const [user, setUser] = useState(undefined);

    useEffect(() => {
        window.axios.get('/api/me')
            .then((response) => setUser(response.data))
            .catch(() => setUser(null));
    }, []);

    if (user === undefined) {
        return <p className="p-8 text-sm text-slate-500">Loading…</p>;
    }

    if (!user) {
        return <LoginPage onSuccess={setUser} />;
    }

    return <OrdersPage user={user} onLogout={() => setUser(null)} />;
}

createRoot(document.getElementById('app')).render(<App />);
