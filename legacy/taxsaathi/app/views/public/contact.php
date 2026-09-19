<?php
declare(strict_types=1);

$siteName        = $siteName ?? 'Tax Saathi';
$supportEmail    = $supportEmail ?? 'support@taxsaathi.in';
$supportPhone    = $supportPhone ?? '+91 XXXXX XXXXX';
$businessAddress = $businessAddress ?? 'Your Business Address, City, State, India';
$businessHours   = $businessHours ?? 'Monday to Saturday, 9:00 AM to 6:00 PM';
$whatsappNumber  = $whatsappNumber ?? '+91XXXXXXXXXX';
$mapEmbedUrl     = $mapEmbedUrl ?? 'https://www.google.com/maps';

$cleanWhatsapp = preg_replace('/[^0-9]/', '', $whatsappNumber);
?>

<section class="px-4 py-12 sm:px-6 lg:px-10">
    <div class="mx-auto max-w-6xl space-y-8">

        <!-- Hero -->
        <div class="rounded-[36px] border border-white/70 bg-white/70 p-6 shadow-[0_20px_50px_rgba(15,23,42,0.06)] backdrop-blur-xl sm:p-8 lg:p-10">
            <div class="mx-auto max-w-3xl text-center">
                <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-100/60 px-4 py-2 text-[11px] font-extrabold uppercase tracking-[0.24em] text-emerald-700 shadow-sm">
                    Contact Us
                </span>

                <h1 class="mt-4 text-3xl font-black tracking-[-0.04em] text-slate-900 sm:text-4xl">
                    Get in touch with <?= e($siteName) ?>
                </h1>

                <p class="mt-4 text-base leading-8 text-slate-600">
                    Have questions about tax filing, registrations, payments, or compliance services? Our team is here to help you with clear guidance and reliable support.
                </p>
            </div>
        </div>

        <!-- Contact info cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Email</p>
                <p class="mt-2 text-sm font-black text-slate-900 break-all"><?= e($supportEmail) ?></p>
                <a href="mailto:<?= e($supportEmail) ?>" class="mt-3 inline-flex text-xs font-bold text-brand-700 hover:text-slate-900">
                    Send Email
                </a>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Phone</p>
                <p class="mt-2 text-sm font-black text-slate-900"><?= e($supportPhone) ?></p>
                <a href="tel:<?= e($supportPhone) ?>" class="mt-3 inline-flex text-xs font-bold text-brand-700 hover:text-slate-900">
                    Call Now
                </a>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">WhatsApp</p>
                <p class="mt-2 text-sm font-black text-slate-900"><?= e($whatsappNumber) ?></p>
                <a
                    href="https://wa.me/<?= e($cleanWhatsapp) ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-flex text-xs font-bold text-brand-700 hover:text-slate-900"
                >
                    Chat on WhatsApp
                </a>
            </div>

            <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Business Hours</p>
                <p class="mt-2 text-sm font-black text-slate-900"><?= e($businessHours) ?></p>
                <span class="mt-3 inline-flex text-xs font-bold text-slate-500">
                    Support Window
                </span>
            </div>
        </div>

        <!-- Main content -->
        <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
            
            <!-- Contact form -->
            <div class="rounded-[30px] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-6">
                    <h2 class="text-xl font-black tracking-[-0.03em] text-slate-900">
                        Send us a message
                    </h2>
                    <p class="mt-2 text-sm leading-7 text-slate-600">
                        Fill out the form below and our team will get back to you as soon as possible.
                    </p>
                </div>

                <form action="<?= e(base_url('contact')) ?>" method="post" class="space-y-4">
                    <?php if (function_exists('csrf_field')): ?>
                        <?= csrf_field() ?>
                    <?php endif; ?>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
                                Full Name
                            </label>
                            <input
                                type="text"
                                name="name"
                                required
                                class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-900 outline-none transition focus:border-slate-400 focus:bg-white"
                                placeholder="Enter your full name"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
                                Phone Number
                            </label>
                            <input
                                type="text"
                                name="phone"
                                required
                                class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-900 outline-none transition focus:border-slate-400 focus:bg-white"
                                placeholder="Enter your phone number"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
                            Email Address
                        </label>
                        <input
                            type="email"
                            name="email"
                            required
                            class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-900 outline-none transition focus:border-slate-400 focus:bg-white"
                            placeholder="Enter your email address"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
                            Subject
                        </label>
                        <input
                            type="text"
                            name="subject"
                            required
                            class="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-sm font-medium text-slate-900 outline-none transition focus:border-slate-400 focus:bg-white"
                            placeholder="Enter subject"
                        >
                    </div>

                    <div>
                        <label class="mb-2 block text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-500">
                            Message
                        </label>
                        <textarea
                            name="message"
                            rows="6"
                            required
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-900 outline-none transition focus:border-slate-400 focus:bg-white"
                            placeholder="Write your message here"
                        ></textarea>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-full bg-gradient-to-r from-[#3852B4] to-[#5E7AC4] px-6 py-3 text-xs font-extrabold uppercase tracking-[0.14em] text-white shadow-[0_18px_36px_rgba(56,82,180,0.18)] transition hover:-translate-y-0.5"
                        >
                            Submit Message
                        </button>
                    </div>
                </form>
            </div>

            <!-- Business info + map -->
            <div class="space-y-6">
                <div class="rounded-[30px] border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <h2 class="text-xl font-black tracking-[-0.03em] text-slate-900">
                        Business Information
                    </h2>

                    <div class="mt-5 space-y-5 text-sm leading-7 text-slate-600">
                        <div>
                            <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Business Name</p>
                            <p class="mt-1 font-semibold text-slate-900"><?= e($siteName) ?></p>
                        </div>

                        <div>
                            <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Office Address</p>
                            <p class="mt-1 font-semibold text-slate-900"><?= e($businessAddress) ?></p>
                        </div>

                        <div>
                            <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Support Email</p>
                            <p class="mt-1 font-semibold text-slate-900 break-all"><?= e($supportEmail) ?></p>
                        </div>

                        <div>
                            <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Phone / WhatsApp</p>
                            <p class="mt-1 font-semibold text-slate-900"><?= e($supportPhone) ?></p>
                        </div>

                        <div>
                            <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-400">Working Hours</p>
                            <p class="mt-1 font-semibold text-slate-900"><?= e($businessHours) ?></p>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-[30px] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h3 class="text-base font-black text-slate-900">Location Map</h3>
                    </div>

                    <div class="h-[320px] w-full bg-slate-100">
                        <iframe
                            src="<?= e($mapEmbedUrl) ?>"
                            width="100%"
                            height="100%"
                            style="border:0;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="Business Location"
                        ></iframe>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>