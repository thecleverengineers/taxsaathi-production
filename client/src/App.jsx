import { BrowserRouter, Navigate, Route, Routes, useParams } from 'react-router-dom';
import { AuthProvider } from './components/AuthContext';
import RequireAuth from './components/RequireAuth';
import PublicLayout from './components/PublicLayout';
import DashboardLayout from './components/DashboardLayout';
import HomePage from './pages/HomePage';
import ServicesPage from './pages/ServicesPage';
import ServiceDetailPage from './pages/ServiceDetailPage';
import ServiceApplicationPage from './pages/ServiceApplicationPage';
import AuthPage from './pages/AuthPage';
import DashboardHomePage from './pages/DashboardHomePage';
import PartnerDashboardPage from './pages/PartnerDashboardPage';
import OrdersPage from './pages/OrdersPage';
import OrderDetailPage from './pages/OrderDetailPage';
import PaymentPage from './pages/PaymentPage';
import SupportPage from './pages/SupportPage';
import ProfilePage from './pages/ProfilePage';
import { AboutPage, CalculatorPage, ContactPage, LegalPage } from './pages/PublicPages';
import { AdminAuditPage, AdminContentPage, AdminNotificationsPage, AdminPartnersPage, AdminPaymentSettingsPage, AdminReportsPage, AdminServicesPage, AdminUsersPage, ExecutivePayoutsPage } from './pages/AdminPages';
import { AdminExecutiveDetailPage, AdminExecutivesPage, AdminIntelligencePage, AdminLeadDetailPage, AdminManagementPage, AdminMarketingPage, AdminNotificationWorkflowsPage, AdminPartnerProfilePage, AdminUserDetailPage } from './pages/AdminAdvancedPages';
import AdvancedAdminPage from './pages/AdvancedAdminPage';

function Protected({ children, roles }) {
  return <RequireAuth roles={roles}>{children}</RequireAuth>;
}

export default function App() {
  return <AuthProvider><BrowserRouter><Routes>
    <Route element={<PublicLayout />}>
      <Route path="/" element={<HomePage />} />
      <Route path="/services" element={<ServicesPage />} />
      <Route path="/services/:slug/apply" element={<ServiceApplicationPage />} />
      <Route path="/services/:slug" element={<ServiceDetailPage />} />
      <Route path="/service-details" element={<ServiceDetailPage />} />
      <Route path="/about" element={<AboutPage />} />
      <Route path="/contact" element={<ContactPage />} />
      <Route path="/tax-calculators" element={<CalculatorPage />} />
      <Route path="/terms-conditions" element={<LegalPage title="Terms and conditions" />} />
      <Route path="/privacy-policy" element={<LegalPage title="Privacy policy" />} />
      <Route path="/refund-policy" element={<LegalPage title="Refund policy" />} />
      <Route path="/payment-method" element={<LegalPage title="Payment methods" text="TaxSaathi supports the payment methods configured by the service team. Payment records are attached to orders and reviewed before work begins." />} />
      <Route path="/auth" element={<AuthPage />} />
      <Route path="/login" element={<Navigate to="/auth" replace />} />
      <Route path="/register" element={<AuthPage />} />
      <Route path="/forgot-password" element={<AuthPage />} />
    </Route>

    <Route element={<Protected><DashboardLayout /></Protected>}>
      <Route path="/dashboard" element={<DashboardHomePage />} />
      <Route path="/client/orders" element={<Protected roles={['client']}><OrdersPage /></Protected>} />
      <Route path="/client/orders/:id" element={<Protected roles={['client']}><OrderDetailPage /></Protected>} />
      <Route path="/client/orders/:id/pay" element={<Protected roles={['client']}><PaymentPage /></Protected>} />
      <Route path="/client/profile" element={<Protected roles={['client']}><ProfilePage /></Protected>} />
      <Route path="/partner/dashboard" element={<Protected roles={['admin', 'manager', 'partner', 'partners']}><PartnerDashboardPage /></Protected>} />
      <Route path="/partner/orders" element={<Protected roles={['admin', 'manager', 'partner', 'partners']}><OrdersPage partner /></Protected>} />
      <Route path="/partner/orders/:id" element={<Protected roles={['admin', 'manager', 'partner', 'partners']}><OrderDetailPage partner /></Protected>} />
      <Route path="/partner/orders/:id/pay" element={<Protected roles={['admin', 'manager', 'partner', 'partners']}><PaymentPage partner /></Protected>} />
      <Route path="/partner/profile" element={<Protected roles={['partner', 'partners']}><ProfilePage /></Protected>} />
      <Route path="/support" element={<SupportPage />} />
      <Route path="/notifications" element={<AdminNotificationsPage />} />
      <Route path="/admin/orders" element={<Protected roles={['admin', 'manager', 'executive']}><OrdersPage /></Protected>} />
      <Route path="/admin/orders/:id" element={<Protected roles={['admin', 'manager', 'executive']}><OrderDetailPage /></Protected>} />
      <Route path="/admin/users" element={<Protected roles={['admin', 'manager']}><AdminUsersPage /></Protected>} />
      <Route path="/admin/users/:id" element={<Protected roles={['admin', 'manager']}><AdminUserDetailPage /></Protected>} />
      <Route path="/admin/services" element={<Protected roles={['admin', 'manager']}><AdminServicesPage /></Protected>} />
      <Route path="/admin/partners" element={<Protected roles={['admin', 'manager']}><AdminPartnersPage /></Protected>} />
      <Route path="/admin/partners/:id" element={<Protected roles={['admin', 'manager']}><AdminPartnerProfilePage /></Protected>} />
      <Route path="/admin/partners/:id/workspace" element={<Protected roles={['admin', 'manager']}><AdminPartnerProfilePage /></Protected>} />
      <Route path="/admin/executive" element={<Protected roles={['admin', 'manager']}><AdminExecutivesPage /></Protected>} />
      <Route path="/admin/executive/:id" element={<Protected roles={['admin', 'manager']}><AdminExecutiveDetailPage /></Protected>} />
      <Route path="/admin/reports" element={<Protected roles={['admin', 'manager', 'executive']}><AdminReportsPage /></Protected>} />
      <Route path="/admin/notifications" element={<Protected roles={['admin', 'manager', 'executive']}><AdminNotificationsPage /></Protected>} />
      <Route path="/admin/audit" element={<Protected roles={['admin', 'manager']}><AdminAuditPage /></Protected>} />
      <Route path="/admin/content" element={<Protected roles={['admin']}><AdminContentPage /></Protected>} />
      <Route path="/admin/management" element={<Protected roles={['admin']}><AdminManagementPage /></Protected>} />
      <Route path="/admin/marketing" element={<Protected roles={['admin', 'manager']}><AdminMarketingPage /></Protected>} />
      <Route path="/admin/intelligence" element={<Protected roles={['admin']}><AdminIntelligencePage /></Protected>} />
      <Route path="/admin/notification-workflows" element={<Protected roles={['admin']}><AdminNotificationWorkflowsPage /></Protected>} />
      <Route path="/admin/operations/leads/:id" element={<Protected roles={['admin', 'manager']}><AdminLeadDetailPage /></Protected>} />
      <Route path="/admin/operations/:section" element={<Protected roles={['admin', 'manager']}><AdvancedAdminPageWrapper /></Protected>} />
      <Route path="/admin/operations" element={<Protected roles={['admin', 'manager']}><AdvancedAdminPage /></Protected>} />
      <Route path="/admin/payment-settings" element={<Protected roles={['admin']}><AdminPaymentSettingsPage /></Protected>} />
      <Route path="/executive/payouts" element={<Protected roles={['admin', 'manager', 'executive']}><ExecutivePayoutsPage /></Protected>} />
      <Route path="/admin/dashboard" element={<Navigate to="/dashboard" replace />} />
      <Route path="/admin/*" element={<Navigate to="/dashboard" replace />} />
    </Route>
    <Route path="*" element={<Navigate to="/" replace />} />
  </Routes></BrowserRouter></AuthProvider>;
}

function AdvancedAdminPageWrapper() {
  const { section = 'leads' } = useParams();
  return <AdvancedAdminPage section={section} />;
}
