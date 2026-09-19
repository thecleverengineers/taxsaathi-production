<?php
declare(strict_types=1);

$siteName      = $siteName ?? 'Tax Saathi';
$supportEmail  = $supportEmail ?? 'support@taxsaathi.in';
$supportPhone  = $supportPhone ?? '+91 XXXXX XXXXX';
$effectiveDate = $effectiveDate ?? date('F d, Y');
?>

<section class="px-4 py-12 sm:px-6 lg:px-10">
    <div class="mx-auto max-w-5xl rounded-[36px] border border-white/70 bg-white/70 p-6 shadow-[0_20px_50px_rgba(15,23,42,0.06)] backdrop-blur-xl sm:p-8 lg:p-10">

        <div class="text-center">
            <span class="inline-flex rounded-full border border-sky-200 bg-sky-100/60 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-sky-700 shadow-sm">
                Privacy Policy
            </span>

            <h1 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900 sm:text-4xl">
                Privacy Policy
            </h1>

            <p class="mt-4 text-base leading-8 text-slate-600">
                This Privacy Policy explains how <?= e($siteName) ?> collects, uses, stores, and protects your personal information when you use our website and services.
            </p>

            <p class="mt-2 text-xs font-semibold text-slate-400">
                Effective Date: <?= e($effectiveDate) ?>
            </p>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Data Security</p>
                <p class="mt-1 text-sm font-black text-slate-900">Protected Systems</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Use of Data</p>
                <p class="mt-1 text-sm font-black text-slate-900">Service Delivery</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 text-center shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Contact</p>
                <p class="mt-1 text-sm font-black text-slate-900">Support Team</p>
            </div>
        </div>

        <div class="mt-10 space-y-8 text-sm leading-7 text-slate-600">

            <section>
                <h2 class="text-lg font-black text-slate-900">1. Information We Collect</h2>
                <p class="mt-2">
                    We may collect personal, financial, and service-related information that you provide while using our platform. This may include your name, phone number, email address, billing details, tax-related documents, government identification details, and any information submitted for compliance or filing purposes.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">2. How We Use Your Information</h2>
                <p class="mt-2">
                    We use your information to provide requested services, process payments, communicate updates, verify documents, maintain records, improve service quality, and comply with legal or regulatory requirements.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">3. Payment Information</h2>
                <p class="mt-2">
                    Payments made on <?= e($siteName) ?> may be processed through secure third-party payment gateways. We do not store full card details on our servers. Payment-related data is handled in accordance with the security standards of the payment provider.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">4. Sharing of Information</h2>
                <p class="mt-2">
                    We do not sell your personal information. We may share data only where required for service delivery, statutory filing, legal compliance, payment processing, technology operations, or with trusted service partners working under confidentiality obligations.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">5. Data Retention</h2>
                <p class="mt-2">
                    We retain your information only for as long as necessary for service fulfillment, record keeping, legal obligations, dispute resolution, and compliance purposes.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">6. Cookies and Analytics</h2>
                <p class="mt-2">
                    Our website may use cookies, session tools, and analytics technologies to improve performance, remember preferences, measure usage, and enhance user experience.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">7. Data Security</h2>
                <p class="mt-2">
                    We take reasonable technical and organizational measures to protect your data from unauthorized access, misuse, loss, or disclosure. However, no online system can guarantee absolute security.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">8. Your Rights</h2>
                <p class="mt-2">
                    You may request access to your personal data, correction of inaccurate information, or assistance with account-related concerns, subject to applicable legal and operational limitations.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">9. Third-Party Links and Services</h2>
                <p class="mt-2">
                    Our website may contain links to third-party websites or tools. We are not responsible for the privacy practices, content, or policies of external websites or providers.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">10. Policy Updates</h2>
                <p class="mt-2">
                    We may update this Privacy Policy from time to time. The latest version available on this page will apply from the effective date shown above.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">11. Contact Us</h2>
                <div class="mt-4 rounded-[24px] border border-slate-200 bg-slate-50 p-5">
                    <p><strong class="text-slate-900">Business Name:</strong> <?= e($siteName) ?></p>
                    <p class="mt-1"><strong class="text-slate-900">Email:</strong> <?= e($supportEmail) ?></p>
                    <p class="mt-1"><strong class="text-slate-900">Phone / WhatsApp:</strong> <?= e($supportPhone) ?></p>
                </div>
            </section>
        </div>

        <div class="mt-10 rounded-2xl border border-slate-200 bg-white p-4 text-center text-xs leading-6 text-slate-500">
            By using <?= e($siteName) ?>, you agree to the collection and use of information in accordance with this Privacy Policy.
        </div>
    </div>
</section>