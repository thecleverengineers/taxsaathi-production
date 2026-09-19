import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../components/AuthContext';
import Icon from '../components/Icons';

export default function AuthPage() {
  const {
    user,
    login,
    register,
    requestRegisterOtp,
    requestPasswordOtp,
    resetPassword
  } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const isRegister = location.pathname === '/register';
  const isForgot = location.pathname === '/forgot-password';

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [form, setForm] = useState({ name: '', email: '', phone: '', password: '' });
  const [registerStep, setRegisterStep] = useState('details');
  const [registerRequestId, setRegisterRequestId] = useState('');
  const [registerOtp, setRegisterOtp] = useState('');
  const [forgotPhone, setForgotPhone] = useState('');
  const [forgotOtp, setForgotOtp] = useState('');
  const [forgotRequestId, setForgotRequestId] = useState('');
  const [forgotPassword, setForgotPassword] = useState('');
  const [forgotConfirmPassword, setForgotConfirmPassword] = useState('');
  const [forgotStep, setForgotStep] = useState('phone');
  const [message, setMessage] = useState(location.state?.message || '');
  const [messageType, setMessageType] = useState('danger');

  useEffect(() => {
    if (location.state?.message) {
      setMessage(location.state.message);
      setMessageType('success');
    }
  }, [location.state]);

  if (user) {
    navigate('/dashboard', { replace: true });
    return null;
  }

  const destination = location.state?.from || '/dashboard';

  function feedback(text, type = 'danger') {
    setMessage(text);
    setMessageType(type);
  }

  async function signIn(event) {
    event.preventDefault();
    feedback('');
    try {
      await login({ email, password });
      navigate(destination, { replace: true });
    } catch (error) {
      feedback(error.message);
    }
  }

  async function sendRegisterOtp(event) {
    event.preventDefault();
    feedback('');
    try {
      const result = await requestRegisterOtp(form);
      setRegisterRequestId(result.request_id);
      setRegisterStep('otp');
      feedback(result.message || 'OTP sent to your mobile number.', 'success');
    } catch (error) {
      feedback(error.message);
    }
  }

  async function createAccount(event) {
    event.preventDefault();
    feedback('');
    try {
      const result = await register({
        ...form,
        otp_request_id: registerRequestId,
        otp: registerOtp
      });
      if (result.user) navigate(destination, { replace: true });
    } catch (error) {
      feedback(error.message);
    }
  }

  async function sendForgotOtp(event) {
    event.preventDefault();
    feedback('');
    try {
      const result = await requestPasswordOtp({ phone: forgotPhone });
      setForgotRequestId(result.request_id);
      setForgotStep('reset');
      feedback(result.message || 'OTP sent to your registered mobile number.', 'success');
    } catch (error) {
      feedback(error.message);
    }
  }

  async function changePassword(event) {
    event.preventDefault();
    feedback('');
    if (forgotPassword !== forgotConfirmPassword) {
      feedback('The passwords do not match.');
      return;
    }
    try {
      const result = await resetPassword({
        phone: forgotPhone,
        otp_request_id: forgotRequestId,
        otp: forgotOtp,
        password: forgotPassword
      });
      navigate('/auth', {
        replace: true,
        state: { message: result.message || 'Password reset successfully.' }
      });
    } catch (error) {
      feedback(error.message);
    }
  }

  function renderLogin() {
    return (
      <>
        <form onSubmit={signIn} className="auth-form">
          <label>
            Email
            <input autoFocus type="email" required value={email} onChange={(event) => setEmail(event.target.value)} placeholder="you@example.com" />
          </label>
          <label>
            Password
            <input type="password" required value={password} onChange={(event) => setPassword(event.target.value)} placeholder="Your password" />
          </label>
          <button className="button primary full" type="submit">Sign in <Icon name="arrow" size={16} /></button>
        </form>
        <Link className="auth-inline-link" to="/forgot-password">Forgot password?</Link>
        <div className="auth-switch">Don't have an account? <Link to="/register">Create an account</Link></div>
      </>
    );
  }

  function renderRegister() {
    if (registerStep === 'otp') {
      return (
        <>
          <p className="auth-otp-note">Enter the OTP sent to {form.phone}. Your account will be created only after the mobile number is verified.</p>
          <form onSubmit={createAccount} className="auth-form">
            <label>
              Mobile OTP
              <input autoFocus required inputMode="numeric" maxLength="6" value={registerOtp} onChange={(event) => setRegisterOtp(event.target.value.replace(/\D/g, ''))} placeholder="6-digit OTP" />
            </label>
            <button className="button primary full" type="submit">Verify and create account <Icon name="arrow" size={16} /></button>
            <button className="text-button" type="button" onClick={() => { setRegisterStep('details'); setRegisterRequestId(''); setRegisterOtp(''); }}>Change details</button>
          </form>
        </>
      );
    }

    return (
      <form onSubmit={sendRegisterOtp} className="auth-form">
        <label>
          Full name
          <input autoFocus required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Your full name" />
        </label>
        <label>
          Email
          <input type="email" required value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} placeholder="you@example.com" />
        </label>
        <label>
          Mobile number
          <input required inputMode="tel" value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} placeholder="10-digit mobile number" />
        </label>
        <label>
          Password
          <input type="password" minLength="6" required value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} placeholder="At least 6 characters" />
        </label>
        <button className="button primary full" type="submit">Send mobile OTP <Icon name="arrow" size={16} /></button>
        <div className="auth-switch">Already have an account? <Link to="/auth">Sign in</Link></div>
      </form>
    );
  }

  function renderForgotPassword() {
    if (forgotStep === 'reset') {
      return (
        <>
          <p className="auth-otp-note">Enter the OTP sent to {forgotPhone}, then choose a new password.</p>
          <form onSubmit={changePassword} className="auth-form">
            <label>
              Mobile OTP
              <input autoFocus required inputMode="numeric" maxLength="6" value={forgotOtp} onChange={(event) => setForgotOtp(event.target.value.replace(/\D/g, ''))} placeholder="6-digit OTP" />
            </label>
            <label>
              New password
              <input type="password" minLength="6" required value={forgotPassword} onChange={(event) => setForgotPassword(event.target.value)} placeholder="At least 6 characters" />
            </label>
            <label>
              Confirm new password
              <input type="password" minLength="6" required value={forgotConfirmPassword} onChange={(event) => setForgotConfirmPassword(event.target.value)} placeholder="Repeat your password" />
            </label>
            <button className="button primary full" type="submit">Reset password <Icon name="arrow" size={16} /></button>
            <button className="text-button" type="button" onClick={() => { setForgotStep('phone'); setForgotRequestId(''); setForgotOtp(''); }}>Use another mobile number</button>
          </form>
        </>
      );
    }

    return (
      <form onSubmit={sendForgotOtp} className="auth-form">
        <label>
          Registered mobile number
          <input autoFocus required inputMode="tel" value={forgotPhone} onChange={(event) => setForgotPhone(event.target.value)} placeholder="10-digit mobile number" />
        </label>
        <button className="button primary full" type="submit">Send mobile OTP <Icon name="arrow" size={16} /></button>
        <div className="auth-switch"><Link to="/auth">Back to sign in</Link></div>
      </form>
    );
  }

  const title = isRegister ? 'Create your account' : isForgot ? 'Reset your password' : 'Enter your workspace';
  const eyebrow = isRegister ? 'Get started' : isForgot ? 'Password recovery' : 'Welcome back';
  const subtitle = isRegister
    ? 'Create your account with mobile verification to access your TaxSaathi workspace.'
    : isForgot
      ? 'Verify your registered mobile number to create a new password.'
      : 'Sign in with the email address and password associated with your account.';

  return (
    <div className="auth-shell-page">
      <div className="auth-presentation">
        <Link to="/" className="auth-logo"><span className="auth-logo-mark">TS</span><strong>TaxSaathi</strong></Link>
        <div className="auth-presentation-copy">
          <span className="eyebrow">A clearer way to manage compliance</span>
          <h1>From filing to final delivery, keep every step visible.</h1>
          <p>Sign in to follow your applications, upload documents, review payment status, and communicate with your service team.</p>
          <div className="auth-feature-list">
            <span><Icon name="shield" size={17} /> Structured client portal</span>
            <span><Icon name="activity" size={17} /> Real-time workflow updates</span>
            <span><Icon name="file" size={17} /> Order-wise document delivery</span>
          </div>
        </div>
        <div className="auth-orbit orbit-one" /><div className="auth-orbit orbit-two" />
      </div>

      <div className="auth-form-panel">
        <Link to="/" className="mobile-auth-brand"><span className="auth-logo-mark">TS</span><strong>TaxSaathi</strong></Link>
        <div className="auth-card">
          <span className="eyebrow">{eyebrow}</span>
          <h2>{title}</h2>
          <p className="auth-subtitle">{subtitle}</p>
          {message && <div className={`form-message ${messageType}`}>{message}</div>}
          {isRegister ? renderRegister() : isForgot ? renderForgotPassword() : renderLogin()}
          <div className="auth-foot"><Icon name="shield" size={15} /> Your account and documents stay protected by role-based access.</div>
        </div>
      </div>
    </div>
  );
}
