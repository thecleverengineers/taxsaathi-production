export const HOME_SECTIONS = [
  ['hero', 'Hero carousel'], ['trust', 'Trust highlights'], ['video', 'Homepage film'],
  ['services', 'Featured services'], ['categories', 'Service categories'],
  ['editorial', 'Editorial carousel'], ['workflow', 'How it works'],
  ['testimonials', 'Testimonials'], ['faq', 'FAQs'], ['cta', 'Final call to action']
];
const slide = (eyebrow, title, accent, description, imageDescription = '') => ({
  id: cryptoId(), enabled: true, eyebrow, title, accent, description, mediaType: 'image',
  mediaUrl: '', mediaPosition: 'center', imageDescription,
  primaryLabel: 'Explore Services', primaryLink: '/services',
  secondaryLabel: 'My Workspace', secondaryLink: '/auth'
});
function cryptoId() { return 'entry-' + Math.random().toString(36).slice(2, 11); }
export const DEFAULT_HOME = {
  order: HOME_SECTIONS.map(([id]) => id),
  sections: {
    hero: {
      enabled: true, autoplayMs: 7200, brandBadge: true,
      assuranceOne: 'Secure records', assuranceTwo: 'Guided process', assuranceThree: 'Human support',
      slides: [
        slide('TAX · GST · COMPLIANCE', 'Your Taxes, Our Priority.', 'Your Peace of Mind.', 'Simplify tax filing and compliance with expert guidance you can trust.', 'Relaxed Tax Saathi customer working from home'),
        slide('PERSONALISED TAX SUPPORT', 'Expert Guidance.', 'A Personal Touch.', 'From everyday tax needs to business compliance, we are here to make things easier.', 'Friendly advisor consultation'),
        slide('SIMPLE · CLEAR · COMFORTABLE', 'Less Paperwork.', 'More Peace of Mind.', 'Experience a simpler way to manage your taxes, filings and financial responsibilities.', 'Customer reviewing tax documents'),
        slide('TRUSTED FINANCIAL GUIDANCE', 'The Right Support.', 'For Every Financial Step.', 'Reliable tax and compliance services designed around your personal and business needs.', 'Tax advisors collaborating'),
        slide('YOUR COMPLIANCE PARTNER', 'Together, We Make', 'Compliance Simple.', 'Your trusted partner for tax filing, business registrations and seamless compliance management.', 'Tax Saathi consultation')
      ]
    },
    trust: { enabled: true, title: 'One connected workspace', description: 'For your tax and compliance journey.', items: [
      { id: 'trust-1', icon: 'file', title: 'Organised paperwork', description: 'Keep information together' },
      { id: 'trust-2', icon: 'activity', title: 'Visible progress', description: 'Follow each stage' },
      { id: 'trust-3', icon: 'shield', title: 'Protected access', description: 'Role-aware workspaces' }
    ] },
    video: { enabled: true, eyebrow: 'SEE TAX SAATHI IN ACTION', title: 'Filing made simple.', accent: 'Life made easier.',
      description: 'From tax-filing stress to expert support and a clearer path forward. Discover Tax Saathi in this short film.',
      mediaUrl: '/media/taxsaathi-intro.mp4', posterUrl: '', ctaLabel: 'Find your service', ctaLink: '/services',
      supportingText: 'Professional tax & compliance support', caption: 'WATCH THE TAX SAATHI FILM', metadata: 'FULL HD · UNMUTE TO HEAR AUDIO',
      autoplay: true, muted: true, loop: true },
    services: { enabled: true, eyebrow: 'OUR SERVICES', title: 'Expert help, without the complexity.',
      description: 'Explore available tax, filing, and business compliance services. Select a service to review details and requirements.',
      actionLabel: 'All services', actionLink: '/services', limit: 6 },
    categories: { enabled: true, eyebrow: 'FIND YOUR SERVICE', title: 'A starting point for every need.',
      description: 'Choose a category to see the services available to you.', limit: 8 },
    editorial: { enabled: true, ctaLabel: 'Get Started', ctaLink: '/register', autoplayMs: 6500,
      slides: [
        { id: 'editorial-1', enabled: true, eyebrow: 'Income Tax · GST · Compliance', title: 'Professional guidance at every stage.', description: 'Explore a service, share your documents, track the work, and receive approved deliverables in one connected Tax Saathi workspace.', mediaUrl: '/legacy-assets/uploads/service-banners/banner_20260519070346_7c66d9c8b29e.jpg' },
        { id: 'editorial-2', enabled: true, eyebrow: 'Your client workspace', title: 'A clearer view of what comes next.', description: 'Stay informed about submissions, document requests, payments, and progress without losing track of the details.', mediaUrl: '/legacy-assets/uploads/service-banners/banner_20260519100545_3a39b21b4f20.jpg' },
        { id: 'editorial-3', enabled: true, eyebrow: 'Connected service delivery', title: 'From the first enquiry to final delivery.', description: 'Keep each service request organised with updates, secure files, and a straightforward review process.', mediaUrl: '/legacy-assets/uploads/service-banners/banner_20260519134532_535851726f9f.png' }
      ] },
    workflow: { enabled: true, eyebrow: 'HOW IT WORKS', title: 'From request to result,', accent: 'clearly.',
      description: 'Find the right service and manage the stages of your application in one place.',
      ctaLabel: 'Explore our services', ctaLink: '/services',
      illustrationTitle: 'Service application', illustrationDescription: 'All stages, one clear view',
      illustrationStageOne: 'Submitted', illustrationStageTwo: 'In review', illustrationStageThree: 'Deliverable',
      illustrationFooter: 'Secure document handling', illustrationFloatOne: 'Track each update', illustrationFloatTwo: 'Files in one place',
      steps: [
        { id: 'step-1', title: 'Choose the right service', description: 'Review the scope, requirements, and available pricing.' },
        { id: 'step-2', title: 'Share details securely', description: 'Submit your application and required documents.' },
        { id: 'step-3', title: 'Follow progress and delivery', description: 'Stay informed through review, approval, and completion.' }
      ] },
    testimonials: { enabled: true, eyebrow: 'CLIENT EXPERIENCES', title: 'People behind the paperwork.', limit: 3 },
    faq: { enabled: true, eyebrow: 'GOOD TO KNOW', title: 'Questions, answered.', limit: 6 },
    cta: { enabled: true, eyebrow: 'TAKE THE NEXT STEP', title: 'Less stress starts', accent: 'here.',
      description: 'Discover services or speak to the Tax Saathi team about your requirements.',
      primaryLabel: 'Explore Services', primaryLink: '/services', secondaryLabel: 'Contact Us', secondaryLink: '/contact' }
  }
};
export function normalizeHome(value) {
  const input = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
  const sections = {};
  for (const [key] of HOME_SECTIONS) {
    const original = DEFAULT_HOME.sections[key];
    const patch = input.sections?.[key];
    sections[key] = { ...original, ...(patch && typeof patch === 'object' && !Array.isArray(patch) ? patch : {}) };
    if (Array.isArray(original.slides) && !Array.isArray(sections[key].slides)) sections[key].slides = original.slides;
    if (Array.isArray(original.items) && !Array.isArray(sections[key].items)) sections[key].items = original.items;
    if (Array.isArray(original.steps) && !Array.isArray(sections[key].steps)) sections[key].steps = original.steps;
  }
  const ordered = Array.isArray(input.order) ? input.order.filter((key) => HOME_SECTIONS.some(([id]) => id === key)) : [];
  return { order: [...new Set([...ordered, ...DEFAULT_HOME.order])], sections };
}
export function homeId() { return cryptoId(); }
