<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Contact Us';
$flashSuccess = $flashSuccess ?? null;
$flashError = $flashError ?? null;
$validationErrors = is_array($validationErrors ?? null) ? $validationErrors : [];
$old = is_array($old ?? null) ? $old : [];

$companyEmail = $companyEmail ?? 'support@taxsaathi.in';
$companyPhone = $companyPhone ?? '+91 00000 00000';
$companyHours = $companyHours ?? 'Mon - Sat, 9 AM - 6 PM';
$companyAddress = $companyAddress ?? 'Tax Saathi Office Address Here, City, State, PIN Code';
?>

<section id="contact-section" class="relative overflow-hidden bg-white">
    <div aria-hidden="true" class="pointer-events-none absolute inset-0 select-none overflow-hidden">
        <div class="absolute -top-8 left-[-4%] rotate-[-18deg] text-[110px] font-black uppercase tracking-[0.35em] text-slate-100/60">
            CONTACT
        </div>
        <div class="absolute right-[-2%] top-[18%] rotate-[12deg] text-[88px] font-black uppercase tracking-[0.3em] text-slate-100/50">
            SUPPORT
        </div>
        <div class="absolute left-[8%] top-[34%] rotate-[-14deg] text-[72px] font-extrabold uppercase tracking-[0.28em] text-slate-100/50">
            TAX SAATHI
        </div>
        <div class="absolute bottom-[18%] right-[8%] rotate-[10deg] text-[74px] font-extrabold uppercase tracking-[0.28em] text-slate-100/50">
            HELP DESK
        </div>
    </div>

    <div class="relative z-10 mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
        <div class="mb-8 flex flex-wrap items-center gap-2 text-sm text-slate-500">
            <a href="<?= e(base_url('')) ?>" class="transition hover:text-slate-900">Home</a>
            <span>/</span>
            <span class="font-medium text-slate-900"><?= e($pageTitle) ?></span>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-6 sm:px-8">
                    <div class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                        Contact Us
                    </div>

                    <h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
                        Let’s talk about your tax and compliance needs
                    </h1>

                    <p class="mt-4 max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                        Reach out to our team for filing support, registrations, returns, compliance assistance, or service guidance.
                    </p>
                </div>

             
                <div class="border-t border-slate-200 px-6 py-6 sm:px-8">
                    <?php if ($flashSuccess): ?>
                        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            <?= e((string) $flashSuccess) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($flashError): ?>
                        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            <?= e((string) $flashError) ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?= e(base_url('contact-submit')) ?>" method="POST" class="grid gap-5">
                        <?php if (function_exists('csrf_field')): ?>
                            <?= csrf_field() ?>
                        <?php endif; ?>

                        <input type="hidden" name="source_page" value="<?= e(rtrim(base_url('contact'), '/') . '#contact-section') ?>">

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="full_name" class="mb-2 block text-sm font-semibold text-slate-700">Full Name</label>
                                <input
                                    id="full_name"
                                    name="full_name"
                                    type="text"
                                    value="<?= e((string) ($old['full_name'] ?? '')) ?>"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-900"
                                    placeholder="Enter your full name"
                                    required
                                >
                                <?php if (!empty($validationErrors['full_name'])): ?>
                                    <p class="mt-2 text-xs text-red-600"><?= e((string) $validationErrors['full_name']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label for="phone" class="mb-2 block text-sm font-semibold text-slate-700">Phone Number</label>
                                <input
                                    id="phone"
                                    name="phone"
                                    type="text"
                                    value="<?= e((string) ($old['phone'] ?? '')) ?>"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-900"
                                    placeholder="Enter your phone number"
                                    required
                                >
                                <?php if (!empty($validationErrors['phone'])): ?>
                                    <p class="mt-2 text-xs text-red-600"><?= e((string) $validationErrors['phone']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email Address</label>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="<?= e((string) ($old['email'] ?? '')) ?>"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-900"
                                    placeholder="Enter your email address"
                                    required
                                >
                                <?php if (!empty($validationErrors['email'])): ?>
                                    <p class="mt-2 text-xs text-red-600"><?= e((string) $validationErrors['email']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label for="service" class="mb-2 block text-sm font-semibold text-slate-700">Service Interested In</label>
                                <input
                                    id="service"
                                    name="service"
                                    type="text"
                                    value="<?= e((string) ($old['service'] ?? '')) ?>"
                                    class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-900"
                                    placeholder="Example: GST Filing"
                                >
                                <?php if (!empty($validationErrors['service'])): ?>
                                    <p class="mt-2 text-xs text-red-600"><?= e((string) $validationErrors['service']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <label for="subject" class="mb-2 block text-sm font-semibold text-slate-700">Subject</label>
                            <input
                                id="subject"
                                name="subject"
                                type="text"
                                value="<?= e((string) ($old['subject'] ?? '')) ?>"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-900"
                                placeholder="Enter subject"
                                required
                            >
                            <?php if (!empty($validationErrors['subject'])): ?>
                                <p class="mt-2 text-xs text-red-600"><?= e((string) $validationErrors['subject']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label for="message" class="mb-2 block text-sm font-semibold text-slate-700">Message</label>
                            <textarea
                                id="message"
                                name="message"
                                rows="6"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-900"
                                placeholder="Tell us how we can help"
                                required
                            ><?= e((string) ($old['message'] ?? '')) ?></textarea>
                            <?php if (!empty($validationErrors['message'])): ?>
                                <p class="mt-2 text-xs text-red-600"><?= e((string) $validationErrors['message']) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row">
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800"
                            >
                                Send Enquiry
                            </button>

                            <a
                                href="<?= e(base_url('services')) ?>"
                                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                View Services
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="grid gap-6">
                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <div class="pointer-events-none absolute right-3 top-2 text-[44px] font-black uppercase tracking-[0.2em] text-slate-100/70">
                        OFFICE
                    </div>

                    <div class="relative z-10">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Office Details</div>
                        <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">Get in touch directly</h2>

                        <div class="mt-6 space-y-5">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                                <div class="text-sm font-semibold text-slate-900">Office Address</div>
                                <p class="mt-2 text-sm leading-7 text-slate-600"><?= nl2br(e($companyAddress)) ?></p>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                                <div class="text-sm font-semibold text-slate-900">Email Support</div>
                                <p class="mt-2 text-sm leading-7 text-slate-600"><?= e($companyEmail) ?></p>
                            </div>

                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                                <div class="text-sm font-semibold text-slate-900">Call Support</div>
                                <p class="mt-2 text-sm leading-7 text-slate-600"><?= e($companyPhone) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 p-6 sm:p-8">
                    <div class="pointer-events-none absolute right-4 top-3 rotate-[-8deg] text-[52px] font-black uppercase tracking-[0.22em] text-slate-200/70">
                        HELP
                    </div>

                    <div class="relative z-10">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Why Contact Us</div>
                        <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                            Clear guidance. Professional support.
                        </h2>

                        <div class="mt-5 space-y-4 text-sm leading-7 text-slate-600">
                            <p>Get assistance for GST, income tax, registrations, returns, compliance, and documentation.</p>
                            <p>Our team can help you understand the right service, required documents, timelines, and next steps.</p>
                            <p>Use this page for enquiries, callback requests, and service-related support.</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Map</div>
                        <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-900">Location</h2>
                    </div>

                    <div class="flex min-h-[260px] items-center justify-center bg-slate-100 px-6 py-10 text-center text-sm text-slate-500">
                        Embed your Google Map iframe here
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>