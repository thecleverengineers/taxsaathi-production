import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { api } from '../lib/api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!localStorage.getItem('taxsaathi_token')) {
      setLoading(false);
      return;
    }
    api('/auth/me').then((payload) => setUser(payload.user)).catch(() => localStorage.removeItem('taxsaathi_token')).finally(() => setLoading(false));
  }, []);

  const value = useMemo(() => ({
    user,
    loading,
    isAuthenticated: Boolean(user),
    async refresh() {
      const result = await api('/auth/me');
      setUser(result.user);
      return result.user;
    },
    async login(payload) {
      const result = await api('/auth/login', { method: 'POST', body: payload });
      localStorage.setItem('taxsaathi_token', result.token);
      setUser(result.user);
      return result;
    },
    async requestRegisterOtp(payload) {
      return api('/auth/register/otp/request', { method: 'POST', body: payload });
    },
    async register(payload) {
      const result = await api('/auth/register', { method: 'POST', body: payload });
      localStorage.setItem('taxsaathi_token', result.token);
      setUser(result.user);
      return result;
    },
    async requestPasswordOtp(payload) {
      return api('/auth/forgot-password/otp/request', { method: 'POST', body: payload });
    },
    async resetPassword(payload) {
      return api('/auth/forgot-password/reset', { method: 'POST', body: payload });
    },
    async logout() {
      await api('/auth/logout', { method: 'POST' }).catch(() => {});
      localStorage.removeItem('taxsaathi_token');
      setUser(null);
    }
  }), [user, loading]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  return useContext(AuthContext);
}
