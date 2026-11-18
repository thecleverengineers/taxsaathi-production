import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { api } from '../lib/api';

const AuthContext = createContext(null);

const TRANSIENT_GATEWAY_STATUSES = new Set([502, 503, 504]);
const LOGIN_RETRY_DELAYS_MS = [2000, 4000, 7000, 10000, 15000, 20000];

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
    async login(payload, onRetry) {
      let result;
      for (let attempt = 0; ; attempt += 1) {
        try {
          result = await api('/auth/login', { method: 'POST', body: payload });
          break;
        } catch (error) {
          if (!TRANSIENT_GATEWAY_STATUSES.has(Number(error?.status))) throw error;
          if (attempt >= LOGIN_RETRY_DELAYS_MS.length) {
            throw new Error('The sign-in server is still unavailable after automatic retries. Please wait a moment and try again.');
          }
          onRetry?.(attempt + 1, LOGIN_RETRY_DELAYS_MS.length);
          await new Promise((resolve) => window.setTimeout(resolve, LOGIN_RETRY_DELAYS_MS[attempt]));
        }
      }
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
