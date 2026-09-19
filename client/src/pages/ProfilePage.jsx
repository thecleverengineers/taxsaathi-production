import { useState } from 'react';
import { api } from '../lib/api';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';

export default function ProfilePage() {
  const { user, refresh } = useAuth();
  const [form, setForm] = useState({ name: user?.name || '', email: user?.email || '', phone: user?.phone || '' });
  const [password, setPassword] = useState({ current_password: '', new_password: '', confirm: '' });
  const [notice, setNotice] = useState('');
  const [saving, setSaving] = useState(false);
  async function saveProfile(event) {
    event.preventDefault(); setSaving(true); setNotice('');
    try { await api('/auth/profile', { method: 'PATCH', body: form }); await refresh(); setNotice('Profile updated.'); } catch (error) { setNotice(error.message); } finally { setSaving(false); }
  }
  async function changePassword(event) {
    event.preventDefault(); setNotice('');
    if (password.new_password !== password.confirm) return setNotice('New passwords do not match.');
    try { await api('/auth/change-password', { method: 'POST', body: password }); setPassword({ current_password: '', new_password: '', confirm: '' }); setNotice('Password changed successfully.'); } catch (error) { setNotice(error.message); }
  }
  return <div className="workspace-page"><div className="profile-hero"><span className="profile-large-avatar">{user?.name?.slice(0, 1) || 'U'}</span><div><span className="eyebrow">Account profile</span><h2>{user?.name}</h2><p>{user?.role?.name || 'Client'} · Member since the migrated workspace</p></div></div>{notice && <div className="form-message success">{notice}</div>}<div className="profile-grid"><form className="panel" onSubmit={saveProfile}><div className="panel-heading"><div><span className="eyebrow">Identity</span><h3>Contact details</h3></div><Icon name="shield" size={20} /></div><label>Name<input required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></label><label>Email<input required type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label><label>Phone<input required value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></label><label>Role<input disabled value={user?.roles?.map((role) => role.name).join(', ') || user?.role?.name || '—'} /></label><button className="button dark" disabled={saving}>{saving ? 'Saving…' : 'Save profile'} <Icon name="check" size={16} /></button></form><form className="panel" onSubmit={changePassword}><div className="panel-heading"><div><span className="eyebrow">Security</span><h3>Change password</h3></div><Icon name="shield" size={20} /></div><label>Current password<input required type="password" value={password.current_password} onChange={(event) => setPassword({ ...password, current_password: event.target.value })} /></label><label>New password<input required minLength="8" type="password" value={password.new_password} onChange={(event) => setPassword({ ...password, new_password: event.target.value })} /></label><label>Confirm new password<input required minLength="8" type="password" value={password.confirm} onChange={(event) => setPassword({ ...password, confirm: event.target.value })} /></label><button className="button primary">Update password <Icon name="shield" size={16} /></button></form></div></div>;
}
