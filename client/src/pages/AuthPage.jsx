import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { ArrowLeft, ArrowRight, BarChart3, Eye, EyeOff, LockKeyhole, Mail, Phone, ShieldCheck, UserRound } from 'lucide-react';
import { useAuth } from '../components/AuthContext';
import './auth-premium.css';

function AuthInput({ label, icon = 'mail', type = 'text', ...props }) {
  const [visible, setVisible] = useState(false);
  const isPassword = type === 'password';
  const Glyph = icon === 'user' ? UserRound : icon === 'phone' ? Phone : icon === 'lock' ? LockKeyhole : Mail;
  return (
    <label className="ts-auth-field">
      <span className="ts-auth-field-label">{label}</span>
      <span className="ts-auth-input-wrap">
        <Glyph size={21} strokeWidth={1.9} aria-hidden="true" />
        <input {...props} aria-label={label} type={isPassword && visible ? 'text' : type} />
        {isPassword && <button className="ts-auth-visibility" type="button"
          onClick={() => setVisible((value) => !value)}
          aria-label={(visible ? 'Hide ' : 'Show ') + label.toLowerCase()}
          aria-pressed={visible}>
          {visible ? <EyeOff size={20} /> : <Eye size={20} />}
        </button>}
      </span>
    </label>
  );
}

function GoldButton({ children, busy = false }) {
  return <button type="submit" className="ts-auth-submit" disabled={busy}>
    <span>{busy ? 'Please wait…' : children}</span><ArrowRight size={21} aria-hidden="true" />
  </button>;
}

function BrandIllustration() {
  return (
    <div className="ts-auth-office" aria-hidden="true">
      <div className="ts-auth-office-glow" />
      <div className="ts-auth-plant"><i /><i /><i /><i /><i /><i /><b /></div>
      <div className="ts-auth-books">
        <span>TAX</span><span>COMPLIANCE</span><span>ACCOUNTING</span><span>FINANCE</span>
      </div>
      <div className="ts-auth-paper"><span className="ts-auth-chart-bars"><i /><i /><i /><i /></span><b /><b /><b /></div>
      <div className="ts-auth-laptop">
        <div className="ts-auth-screen"><BarChart3 size={70} strokeWidth={1.4} /></div>
        <div className="ts-auth-keyboard" />
      </div>
      <div className="ts-auth-calculator"><i /><i /><i /><i /><i /><i /><i /><i /><i /><i /><i /><i /></div>
      <div className="ts-auth-pen-holder"><i /><i /><b /></div>
    </div>
  );
}

export default function AuthPage() {
  const { user, login, register, requestRegisterOtp, requestPasswordOtp, resetPassword } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const isRegister = location.pathname === '/register';
  const isForgot = location.pathname === '/forgot-password';
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [form, setForm] = useState({ name: '', email: '', phone: '', password: '', client_type: 'individual', company_name: '' });
  const [confirmPassword, setConfirmPassword] = useState('');
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
  const [messageType, setMessageType] = useState(location.state?.message ? 'success' : 'danger');
  const [busy, setBusy] = useState(false);
  const destination = location.state?.from || '/dashboard';

  useEffect(() => {
    setMessage(location.state?.message || '');
    setMessageType(location.state?.message ? 'success' : 'danger');
  }, [location.pathname, location.state?.message]);

  useEffect(() => {
    if (user) navigate('/dashboard', { replace: true });
  }, [navigate, user]);

  if (user) return null;

  function feedback(text, type = 'danger') {
    setMessage(text);
    setMessageType(type);
  }

  async function signIn(event) {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    feedback('');
    try {
      await login({ email, password }, (attempt, total) => feedback(`Reconnecting to the sign-in service (${attempt}/${total})…`, 'success'));
      navigate(destination, { replace: true });
    } catch (error) {
      feedback(error.message || 'Unable to sign in right now.');
    } finally {
      setBusy(false);
    }
  }

  async function sendRegisterOtp(event) {
    event.preventDefault();
    if (busy) return;
    if (form.password !== confirmPassword) {
      feedback('The passwords do not match.');
      return;
    }
    setBusy(true);
    feedback('');
    try {
      const result = await requestRegisterOtp(form);
      setRegisterRequestId(result.request_id);
      setRegisterStep('otp');
      feedback(result.message || 'OTP sent to your mobile number.', 'success');
    } catch (error) {
      feedback(error.message || 'Could not send the verification code.');
    } finally {
      setBusy(false);
    }
  }

  async function createAccount(event) {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    feedback('');
    try {
      const result = await register({ ...form, otp_request_id: registerRequestId, otp: registerOtp });
      if (result.user) navigate(destination === '/dashboard' && form.client_type === 'corporate' ? '/client/company' : destination, { replace: true });
    } catch (error) {
      feedback(error.message || 'Could not verify your account.');
    } finally {
      setBusy(false);
    }
  }

  async function sendForgotOtp(event) {
    event.preventDefault();
    if (busy) return;
    setBusy(true);
    feedback('');
    try {
      const result = await requestPasswordOtp({ phone: forgotPhone });
      setForgotRequestId(result.request_id);
      setForgotStep('reset');
      feedback(result.message || 'OTP sent to your registered mobile number.', 'success');
    } catch (error) {
      feedback(error.message || 'Could not send the reset code.');
    } finally {
      setBusy(false);
    }
  }

  async function changePassword(event) {
    event.preventDefault();
    if (busy) return;
    if (forgotPassword !== forgotConfirmPassword) {
      feedback('The passwords do not match.');
      return;
    }
    setBusy(true);
    feedback('');
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
      feedback(error.message || 'Unable to reset your password.');
    } finally {
      setBusy(false);
    }
  }

  function renderLogin() {
    return (
      <>
        <form onSubmit={signIn} className="ts-auth-form">
          <AuthInput label="Email address" icon="mail" type="email" required
            autoComplete="username" placeholder="Email address" value={email}
            onChange={(event) => setEmail(event.target.value)} />
          <AuthInput label="Password" icon="lock" type="password" required
            autoComplete="current-password" placeholder="Password" value={password}
            onChange={(event) => setPassword(event.target.value)} />
          <div className="ts-auth-form-tools"><Link to="/forgot-password">Forgot Password?</Link></div>
          <GoldButton busy={busy}>Sign In</GoldButton>
        </form>
        <p className="ts-auth-switch">New to Tax Saathi? <Link to="/register">Create an account</Link></p>
      </>
    );
  }

  function renderRegister() {
    if (registerStep === 'otp') {
      return (
        <>
          <p className="ts-auth-step-note">We sent a verification code to <strong>{form.phone}</strong>. Confirm the code to create your {form.client_type === 'corporate' ? 'Corporate' : 'Individual'} account.</p>
          <form onSubmit={createAccount} className="ts-auth-form">
            <AuthInput label="Mobile OTP" icon="lock" inputMode="numeric"
              autoComplete="one-time-code" maxLength={6} required placeholder="6-digit OTP"
              value={registerOtp} onChange={(event) => setRegisterOtp(event.target.value.replace(/\D/g, ''))} />
            <GoldButton busy={busy}>Verify &amp; Create Account</GoldButton>
          </form>
          <button className="ts-auth-back-button" type="button" onClick={() => {
            setRegisterStep('details'); setRegisterRequestId(''); setRegisterOtp(''); feedback('');
          }}><ArrowLeft size={17} /> Change details</button>
        </>
      );
    }
    return (
      <>
        <form onSubmit={sendRegisterOtp} className="ts-auth-form">
          <div className="ts-auth-account-type" role="group" aria-label="Account type">
            <label className={form.client_type === 'individual' ? 'selected' : ''}>
              <input type="radio" name="client_type" value="individual" checked={form.client_type === 'individual'}
                onChange={() => setForm({...form, client_type:'individual',company_name:''})}/>
              <span>Individual</span>
            </label>
            <label className={form.client_type === 'corporate' ? 'selected' : ''}>
              <input type="radio" name="client_type" value="corporate" checked={form.client_type === 'corporate'}
                onChange={() => setForm({...form, client_type:'corporate'})}/>
              <span>Corporate</span>
            </label>
          </div>
          {form.client_type === 'corporate' && <AuthInput label="Registered company name" icon="briefcase" required
            placeholder="Company or organisation name" value={form.company_name}
            onChange={(event) => setForm({ ...form, company_name: event.target.value })}/>}
          <AuthInput label="Full Name" icon="user" required autoComplete="name" placeholder="Full Name"
            value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />
          <AuthInput label="Email address" icon="mail" type="email" required autoComplete="email"
            placeholder="Email address" value={form.email}
            onChange={(event) => setForm({ ...form, email: event.target.value })} />
          <AuthInput label="Mobile number" icon="phone" type="tel" inputMode="tel" required
            autoComplete="tel" placeholder="Mobile number" value={form.phone}
            onChange={(event) => setForm({ ...form, phone: event.target.value })} />
          <AuthInput label="Password" icon="lock" type="password" required minLength={6}
            autoComplete="new-password" placeholder="Password (6+ characters)" value={form.password}
            onChange={(event) => setForm({ ...form, password: event.target.value })} />
          <AuthInput label="Confirm Password" icon="lock" type="password" required minLength={6}
            autoComplete="new-password" placeholder="Confirm Password" value={confirmPassword}
            onChange={(event) => setConfirmPassword(event.target.value)} />
          <GoldButton busy={busy}>Create Account</GoldButton>
        </form>
        <p className="ts-auth-microcopy">We'll verify your mobile number with a one-time code.</p>
        <p className="ts-auth-switch">Already have an account? <Link to="/auth">Sign In</Link></p>
      </>
    );
  }

  function renderForgotPassword() {
    if (forgotStep === 'reset') {
      return (
        <>
          <p className="ts-auth-step-note">Enter the verification code sent to <strong>{forgotPhone}</strong> and choose a new password.</p>
          <form onSubmit={changePassword} className="ts-auth-form">
            <AuthInput label="Mobile OTP" icon="lock" inputMode="numeric" autoComplete="one-time-code"
              maxLength={6} required placeholder="6-digit OTP" value={forgotOtp}
              onChange={(event) => setForgotOtp(event.target.value.replace(/\D/g, ''))} />
            <AuthInput label="New password" icon="lock" type="password" autoComplete="new-password"
              minLength={6} required placeholder="New password" value={forgotPassword}
              onChange={(event) => setForgotPassword(event.target.value)} />
            <AuthInput label="Confirm new password" icon="lock" type="password"
              autoComplete="new-password" minLength={6} required placeholder="Confirm new password"
              value={forgotConfirmPassword}
              onChange={(event) => setForgotConfirmPassword(event.target.value)} />
            <GoldButton busy={busy}>Reset Password</GoldButton>
          </form>
          <button className="ts-auth-back-button" type="button" onClick={() => {
            setForgotStep('phone'); setForgotRequestId(''); setForgotOtp(''); feedback('');
          }}><ArrowLeft size={17} /> Use another mobile number</button>
        </>
      );
    }
    return (
      <>
        <form onSubmit={sendForgotOtp} className="ts-auth-form">
          <AuthInput label="Registered mobile number" icon="phone" type="tel" inputMode="tel"
            autoComplete="tel" placeholder="Registered mobile number" required value={forgotPhone}
            onChange={(event) => setForgotPhone(event.target.value)} />
          <GoldButton busy={busy}>Send Reset OTP</GoldButton>
        </form>
        <p className="ts-auth-microcopy">A secure OTP will be sent to your registered mobile number.</p>
        <p className="ts-auth-switch ts-auth-return"><Link to="/auth"><ArrowLeft size={18} /> Back to Login</Link></p>
      </>
    );
  }

  const title = isRegister ? 'Create Your Account' : isForgot ? 'Reset Your Password' : 'Welcome Back';
  const section = isRegister ? (registerStep === 'otp' ? 'Verify Mobile Number' : 'Register')
    : isForgot ? (forgotStep === 'reset' ? 'Set New Password' : 'Forgot Password?') : 'Login';
  const subtitle = isRegister
    ? 'Start your journey with Tax Saathi and manage your taxes, compliance, and finances with ease.'
    : isForgot
      ? 'Securely reset your password using your registered mobile number.'
      : 'Login to your Tax Saathi account to manage your taxes, compliance, and finances with ease.';

  return (
    <div className={'ts-auth-root ' + (isRegister ? 'ts-auth-register' : isForgot ? 'ts-auth-forgot' : 'ts-auth-login')}>
      <section className="ts-auth-showcase" aria-label="Tax Saathi brand">
        <div className="ts-auth-ribbon ts-auth-ribbon-one" aria-hidden="true" />
        <div className="ts-auth-ribbon ts-auth-ribbon-two" aria-hidden="true" />
        <div className="ts-auth-city" aria-hidden="true" />
        <div className="ts-auth-showcase-top">
          <Link to="/" className="ts-auth-logo" aria-label="Tax Saathi home">
            <img src="/taxsaathi-logo.png" alt="Tax Saathi — Less Stress, More Success" />
          </Link>
          <div className="ts-auth-tagline"><i /><span>Less Stress, More Success.</span><i /></div>
        </div>
        <BrandIllustration />
      </section>
      <main className="ts-auth-panel">
        <div className="ts-auth-decor ts-auth-decor-top" aria-hidden="true" />
        <div className="ts-auth-decor ts-auth-decor-bottom" aria-hidden="true" />
        <div className="ts-auth-panel-inner">
          <header className="ts-auth-heading">
            <h1>{title}</h1>
            <p>{subtitle}</p>
            <span className="ts-auth-heading-accent" aria-hidden="true" />
          </header>
          <div className="ts-auth-section-title">{section}</div>
          {message && <div className={'ts-auth-alert ' + messageType} role={messageType === 'danger' ? 'alert' : 'status'}>{message}</div>}
          {isRegister ? renderRegister() : isForgot ? renderForgotPassword() : renderLogin()}
          <div className="ts-auth-security"><ShieldCheck size={17} aria-hidden="true" /> Your account and documents stay protected.</div>
        </div>
      </main>
    </div>
  );
}
