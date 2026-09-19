import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from './AuthContext';

export default function RequireAuth({ children, roles }) {
  const { loading, user } = useAuth();
  const location = useLocation();
  if (loading) return <div className="loading-screen"><span className="spinner" />Loading your workspace…</div>;
  if (!user) return <Navigate to="/auth" replace state={{ from: location.pathname }} />;
  const userRoles = [user.role?.slug, ...(user.roles || []).map((role) => role.slug)].filter(Boolean);
  if (roles?.length && !roles.some((role) => userRoles.includes(role))) return <Navigate to="/dashboard" replace />;
  return children;
}
