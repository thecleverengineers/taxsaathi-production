import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
import './styles.css';

class AppErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { failed: false };
  }

  static getDerivedStateFromError() {
    return { failed: true };
  }

  componentDidCatch(error) {
    console.error('TaxSaathi application failed to render.', error);
  }

  render() {
    if (this.state.failed) {
      return <main className="app-error-screen"><div><strong>TaxSaathi</strong><h1>We could not load this workspace.</h1><p>Please refresh the page. If the problem continues, contact the TaxSaathi team.</p><button type="button" onClick={() => window.location.reload()}>Refresh</button></div></main>;
    }
    return this.props.children;
  }
}

createRoot(document.getElementById('root')).render(<React.StrictMode><AppErrorBoundary><App /></AppErrorBoundary></React.StrictMode>);
