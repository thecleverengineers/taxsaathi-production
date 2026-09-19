<?php
declare(strict_types=1);

$siteName      = $siteName ?? 'Tax Saathi';
$supportEmail  = $supportEmail ?? 'support@taxsaathi.in';
$supportPhone  = $supportPhone ?? '+91 XXXXX XXXXX';
$effectiveDate = $effectiveDate ?? date('F d, Y');
?>

<section class="px-4 py-12 sm:px-6 lg:px-10">
    <div class="mx-auto max-w-5xl rounded-[36px] border border-white/70 bg-white/70 p-6 shadow-[0_20px_50px_rgba(15,23,42,0.06)] backdrop-blur-xl sm:p-8 lg:p-10">

        <!-- Header -->
        <div class="text-center">
            <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-slate-700">
                Terms & Conditions
            </span>

            <h1 class="mt-4 text-3xl font-black text-slate-900 sm:text-4xl">
                Terms & Conditions
            </h1>

            <p class="mt-4 text-base text-slate-600">
                These Terms govern your use of <?= e($siteName) ?> and the services provided through our platform.
            </p>

            <p class="mt-2 text-xs text-slate-400">
                Effective Date: <?= e($effectiveDate) ?>
            </p>
        </div>

        <!-- Content -->
        <div class="mt-10 space-y-8 text-sm text-slate-600 leading-7">

            <section>
                <h2 class="text-lg font-black text-slate-900">1. Acceptance of Terms</h2>
                <p class="mt-2">
                    By accessing or using <?= e($siteName) ?>, you agree to be bound by these Terms & Conditions.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">2. Services</h2>
                <p class="mt-2">
                    <?= e($siteName) ?> provides tax filing, compliance, registration, and related services. We reserve the right to modify or discontinue services at any time.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">3. User Responsibilities</h2>
                <ul class="mt-2 list-disc pl-5 space-y-2">
                    <li>Provide accurate and complete information</li>
                    <li>Submit required documents on time</li>
                    <li>Comply with applicable laws and regulations</li>
                    <li>Maintain confidentiality of account credentials</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">4. Payments</h2>
                <p class="mt-2">
                    Payments must be made in full before service processing begins unless otherwise agreed. Payments are processed through secure third-party gateways such as Razorpay.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">5. Refunds</h2>
                <p class="mt-2">
                    Refunds are governed by our Refund Policy. Please review the Refund Policy page for detailed terms.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">6. Service Timelines</h2>
                <p class="mt-2">
                    Timelines may vary depending on the service type, document submission, and regulatory requirements. Delays caused by users or third parties are not the responsibility of <?= e($siteName) ?>.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">7. Limitation of Liability</h2>
                <p class="mt-2">
                    <?= e($siteName) ?> shall not be liable for any indirect, incidental, or consequential damages arising from the use of our services.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">8. Intellectual Property</h2>
                <p class="mt-2">
                    All content, branding, and materials on this website are the property of <?= e($siteName) ?> and may not be used without permission.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">9. Third-Party Services</h2>
                <p class="mt-2">
                    We may use third-party providers for payments, analytics, and communication. We are not responsible for their independent policies or actions.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">10. Termination</h2>
                <p class="mt-2">
                    We reserve the right to suspend or terminate access to our services in case of misuse, fraud, or violation of these Terms.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">11. Governing Law</h2>
                <p class="mt-2">
                    These Terms are governed by the laws of India. Any disputes shall be subject to the jurisdiction of the appropriate courts.
                </p>
            </section>

            <section>
                <h2 class="text-lg font-black text-slate-900">12. Contact Information</h2>
                <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <p><strong>Email:</strong> <?= e($supportEmail) ?></p>
                    <p><strong>Phone:</strong> <?= e($supportPhone) ?></p>
                </div>
            </section>

        </div>

      

    </div>
</section>