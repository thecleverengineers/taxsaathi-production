import { useState } from 'react';
import { api } from '../lib/api';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';

export default function ProfilePage() {
  const { user, refresh } = useAuth();
  const [form, setForm] = useState({ name: user?.name || '', email: user?.email || '', phone: user?.phone || '' });
  const [password, setPassword] = useState({ current_password: '', new_password: '', confirm: '' });
  const [notice, setNotice] = useState('');
  const [noticeType, setNoticeType] = useState('success');
  const [saving, setSaving] = useState(false);
  const [avatarBusy, setAvatarBusy] = useState(false);
  const [avatarVersion, setAvatarVersion] = useState(0);
  const showMessage = (value, type = 'success') => { setNotice(value); setNoticeType(type); };
  async function saveProfile(event) {
    event.preventDefault(); setSaving(true); setNotice('');
    try {
      const result = await api('/auth/profile', { method: 'PATCH', body: form });
      await refresh();
      setForm({ name: result.user?.name || form.name, email: result.user?.email || form.email, phone: result.user?.phone || form.phone });
      showMessage('Profile updated.');
    } catch (error) { showMessage(error.message, 'danger'); }
    finally { setSaving(false); }
  }
  async function changePassword(event) {
    event.preventDefault(); setNotice('');
    if (password.new_password !== password.confirm) return showMessage('New passwords do not match.', 'danger');
    try {
      await api('/auth/change-password', { method: 'POST', body: password });
      setPassword({ current_password: '', new_password: '', confirm: '' });
      showMessage('Password changed successfully.');
    } catch (error) { showMessage(error.message, 'danger'); }
  }
  async function uploadAvatar(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    event.target.value = '';
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return showMessage('Choose a JPG, PNG or WebP image.', 'danger');
    if (file.size > 3 * 1024 * 1024) return showMessage('Profile photos must be smaller than 3 MB.', 'danger');
    setAvatarBusy(true); setNotice('');
    try {
      const body = new FormData();
      body.append('file', file);
      await api('/auth/avatar', { method: 'POST', body, timeoutMs: 60000 });
      await refresh();
      setAvatarVersion(Date.now());
      showMessage('Profile photo updated.');
    } catch (error) { showMessage(error.message, 'danger'); }
    finally { setAvatarBusy(false); }
  }
  async function removeAvatar() {
    setAvatarBusy(true); setNotice('');
    try {
      await api('/auth/avatar', { method: 'DELETE' });
      await refresh();
      setAvatarVersion(Date.now());
      showMessage('Profile photo removed.');
    } catch (error) { showMessage(error.message, 'danger'); }
    finally { setAvatarBusy(false); }
  }
  const avatarImage = user?.avatar_url ? user.avatar_url + (avatarVersion ? '?updated=' + avatarVersion : '') : null;
  return <div className="workspace-page">
    <div className="profile-hero">
      <span className="profile-large-avatar">{avatarImage ? <img src={avatarImage} alt="" className="profile-photo-image" /> : user?.name?.slice(0, 1) || 'U'}</span>
      <div><span className="eyebrow">Account profile</span><h2>{user?.name}</h2><p>{user?.role?.name || 'Client'} · Secure Tax Saathi workspace</p></div>
    </div>
    {notice && <div className={'form-message ' + noticeType} role={noticeType === 'danger' ? 'alert' : 'status'}>{notice}</div>}
    <div className="profile-grid">
      <section className="panel profile-avatar-panel">
        <div className="panel-heading"><div><span className="eyebrow">Personalisation</span><h3>Profile picture</h3></div><Icon name="userCircle" size={20} /></div>
        <div className="profile-avatar-row">
          <span className="profile-avatar-preview">{avatarImage ? <img src={avatarImage} alt="Current profile photo" /> : (user?.name || 'U').slice(0,1).toUpperCase()}</span>
          <div>
            <p>Upload a JPG, PNG or WebP photo, up to 3 MB. Only your authenticated account can access the original image.</p>
            <div className="inline-actions">
              <label className="button dark small upload-button">
                <Icon name="upload" size={15}/>{avatarBusy ? 'Uploading…' : 'Choose photo'}
                <input type="file" accept="image/jpeg,image/png,image/webp" disabled={avatarBusy} onChange={uploadAvatar} hidden/>
              </label>
              {avatarImage && <button type="button" className="button light small" disabled={avatarBusy} onClick={removeAvatar}>Remove photo</button>}
            </div>
          </div>
        </div>
      </section>
      <form className="panel profile-form profile-form--contact" onSubmit={saveProfile}>
        <div className="panel-heading"><div><span className="eyebrow">Identity</span><h3>Contact details</h3></div><Icon name="shield" size={20} /></div>
        <div className="profile-form-fields">
          <label className="profile-form-field">Name<input required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></label>
          <label className="profile-form-field">Email<input required type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label>
          <label className="profile-form-field">Phone<input required value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></label>
          <label className="profile-form-field">Role<input disabled value={user?.roles?.map((role) => role.name).join(', ') || user?.role?.name || '—'} /></label>
        </div>
        <button className="button dark" disabled={saving}>{saving ? 'Saving…' : 'Save profile'} <Icon name="check" size={16} /></button>
      </form>
      <form className="panel profile-form profile-form--password" onSubmit={changePassword}>
        <div className="panel-heading"><div><span className="eyebrow">Security</span><h3>Change password</h3></div><Icon name="shield" size={20} /></div>
        <div className="profile-form-fields">
          <label className="profile-form-field">Current password<input required type="password" autoComplete="current-password" value={password.current_password} onChange={(event) => setPassword({ ...password, current_password: event.target.value })} /></label>
          <label className="profile-form-field">New password<input required minLength="8" type="password" autoComplete="new-password" value={password.new_password} onChange={(event) => setPassword({ ...password, new_password: event.target.value })} /></label>
          <label className="profile-form-field">Confirm new password<input required minLength="8" type="password" autoComplete="new-password" value={password.confirm} onChange={(event) => setPassword({ ...password, confirm: event.target.value })} /></label>
        </div>
        <button className="button primary">Update password <Icon name="shield" size={16} /></button>
      </form>
    </div>
  </div>;
}
