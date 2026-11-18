export const DEFAULT_SERVICES_PAGE = {
  sections: {
    hero: {
      enabled: true,
      eyebrow: 'Tax & compliance services',
      title: 'Expert help with tax and compliance',
      description: 'Find the right service and manage your documents in one place.',
      buttonLabel: 'Explore services',
      buttonHref: '#browse-services',
      imageUrl: '/media/taxsaathi-services-consultation.webp',
      imageAlt: '',
      imagePosition: '53% center'
    },
    browser: {
      enabled: true,
      title: 'Browse services',
      categoryFallbackTitle: 'Browse services',
      searchPlaceholder: 'Search services',
      filterAllLabel: 'All services',
      loadingLabel: 'Loading services…',
      unavailableTitle: 'Services are temporarily unavailable.',
      retryLabel: 'Try again',
      categoryEmptyTitle: 'No service categories are available yet.',
      resultsSuffix: 'services found',
      resultEmptyTitle: 'No services matched your search.',
      resultEmptyDescription: 'Try another keyword or clear the category filter.',
      previousLabel: 'Previous',
      nextLabel: 'Next',
      pageSize: 24,
      showSearch: true,
      showCategoryFilter: true,
      showResultsCount: true
    },
    categories: {
      enabled: true,
      showCount: true,
      showImages: true,
      sortMode: 'current',
      customOrder: [],
      displayMode: 'grid'
    },
    cards: {
      enabled: true,
      showImages: true,
      showCategory: true,
      showPrice: true,
      showFeatured: true,
      showContactButtons: true,
      showApplyButton: true,
      viewLabel: 'View service',
      callLabel: 'Call us',
      whatsappLabel: 'WhatsApp',
      applyLabel: 'Apply for this Service',
      featuredLabel: 'Featured',
      emptyFeeLabel: 'Ask for pricing'
    },
    footer: {
      enabled: false,
      eyebrow: 'YOUR COMPLIANCE PARTNER',
      title: 'Make compliance easier with Tax Saathi.',
      description: 'Browse our services and get the right support for your needs.',
      buttonLabel: 'Explore services',
      buttonHref: '#browse-services'
    }
  },
  categoryOverrides: {}
};

export function normalizeServicesPage(value) {
  const input = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
  const sections = {};
  for (const [key, fallback] of Object.entries(DEFAULT_SERVICES_PAGE.sections)) {
    const patch = input.sections?.[key];
    sections[key] = { ...fallback, ...(patch && typeof patch === 'object' && !Array.isArray(patch) ? patch : {}) };
    if (key === 'categories' && !Array.isArray(sections[key].customOrder)) sections[key].customOrder = [];
  }
  const overrides = input.categoryOverrides && typeof input.categoryOverrides === 'object' && !Array.isArray(input.categoryOverrides)
    ? input.categoryOverrides : {};
  return { sections, categoryOverrides: overrides };
}
export function resolveServicesHero(content, category) {
  const config = normalizeServicesPage(content);
  const base = config.sections.hero;
  const override = category ? config.categoryOverrides[String(category.slug || category)] : null;
  if (!override || override.enabled !== true) return base;
  return {
    ...base,
    eyebrow: override.eyebrow || base.eyebrow,
    title: override.title || base.title,
    description: override.description || base.description,
    imageUrl: override.imageUrl || base.imageUrl,
    imagePosition: override.imagePosition || base.imagePosition
  };
}
