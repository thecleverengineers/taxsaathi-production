<?php
declare(strict_types=1);

$siteName = $siteName ?? 'Tax Saathi';
$tagline  = $tagline ?? 'Simplifying Taxes for Everyone';
?>

<section class="px-4 py-12 sm:px-6 lg:px-10">
    <div class="mx-auto max-w-6xl space-y-10">

        <!-- HERO -->
        <div class="rounded-[36px] border border-white/70 bg-white/70 p-8 text-center shadow-[0_20px_50px_rgba(15,23,42,0.06)] backdrop-blur-xl">
            
            <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-slate-700">
                About <?= e($siteName) ?>
            </span>

            <h1 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900 sm:text-4xl">
                <?= e($tagline) ?>
            </h1>

            <p class="mt-4 max-w-2xl mx-auto text-base leading-8 text-slate-600">
                <?= e($siteName) ?> is a modern tax and compliance platform designed to simplify financial services for individuals, professionals, and businesses across India.
            </p>
        </div>

        <!-- STATS -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm">
                <p class="text-[11px] font-extrabold uppercase text-slate-400">Trusted By</p>
                <p class="mt-1 text-xl font-black text-slate-900">1000+</p>
                <p class="text-xs text-slate-500">Users</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm">
                <p class="text-[11px] font-extrabold uppercase text-slate-400">Services</p>
                <p class="mt-1 text-xl font-black text-slate-900">50+</p>
                <p class="text-xs text-slate-500">Offerings</p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm">
                <p class="text-[11px] font-extrabold uppercase text-slate-400">Support</p>
                <p class="mt-1 text-xl font-black text-slate-900">24/7</p>
                <p class="text-xs text-slate-500">Assistance</p>
            </div>
        </div>

        <!-- ABOUT CONTENT -->
        <div class="rounded-[30px] border border-slate-200 bg-white p-8 shadow-sm space-y-6">
            <h2 class="text-xl font-black text-slate-900">Who We Are</h2>
            <p class="text-sm leading-7 text-slate-600">
                <?= e($siteName) ?> is built to eliminate the complexity of taxation and compliance. We provide structured, transparent, and efficient services for tax filing, registrations, and regulatory requirements.
            </p>

            <h2 class="text-xl font-black text-slate-900">What We Do</h2>
            <ul class="list-disc pl-5 space-y-2 text-sm text-slate-600">
                <li>Income Tax Filing & Advisory</li>
                <li>GST Registration & Compliance</li>
                <li>Business Registrations</li>
                <li>Accounting & Financial Services</li>
                <li>Legal & Compliance Solutions</li>
            </ul>

            <h2 class="text-xl font-black text-slate-900">Our Mission</h2>
            <p class="text-sm leading-7 text-slate-600">
                To make tax and compliance services accessible, transparent, and hassle-free for everyone through technology-driven solutions.
            </p>

            <h2 class="text-xl font-black text-slate-900">Why Choose Us</h2>
            <ul class="list-disc pl-5 space-y-2 text-sm text-slate-600">
                <li>Simple & structured process</li>
                <li>Transparent pricing</li>
                <li>Secure data handling</li>
                <li>Fast turnaround time</li>
                <li>Professional support team</li>
            </ul>
        </div>

        <!-- CTA -->
        <div class="rounded-[30px] border border-slate-200 bg-slate-900 p-8 text-center text-white">
            <h3 class="text-xl font-black">Get Started with <?= e($siteName) ?></h3>
            <p class="mt-2 text-sm text-slate-300">
                Experience a smarter way to manage taxes and compliance.
            </p>

            <a href="<?= e(base_url('services')) ?>"
               class="mt-4 inline-flex items-center justify-center rounded-full bg-white px-6 py-2.5 text-xs font-extrabold text-slate-900 hover:bg-slate-100">
                Explore Services
            </a>
        </div>

        <!-- FOOTER -->
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-center text-xs text-slate-500">
            <p>© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</p>
            <p class="mt-1">
                Developed by 
                <a href="https://ahibi.in" target="_blank" class="font-semibold text-slate-700 hover:text-slate-900">
                    AHIBI
                </a>
            </p>
        </div>

    </div>
</section>