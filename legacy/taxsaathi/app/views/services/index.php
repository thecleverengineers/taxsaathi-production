<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$services         = is_array($services ?? null) ? $services : [];
$featuredServices = is_array($featuredServices ?? null) ? $featuredServices : [];

$totalServices = count($services);

$minFee = null;
foreach ($services as $service) {
    $fee = isset($service['filing_fee']) ? (float)$service['filing_fee'] : 0.0;
    if ($fee > 0 && ($minFee === null || $fee < $minFee)) {
        $minFee = $fee;
    }
}
?>

<style>
    :root{
        --ts-dark:#091413;
        --ts-green:#285A48;
        --ts-mint:#B0E4CC;
        --ts-white:#ffffff;
        --ts-text:#11211d;
        --ts-muted:#62756f;
        --ts-bg:#f7fcf9;
        --ts-border:rgba(40,90,72,.10);
        --ts-border-strong:rgba(40,90,72,.18);
        --ts-shadow:0 20px 60px rgba(9,20,19,.08);
        --ts-shadow-lg:0 28px 90px rgba(9,20,19,.12);
    }

    *{box-sizing:border-box}

    .services-page{
        width:100%;
        color:var(--ts-text);
        background:
            radial-gradient(circle at top left, rgba(176,228,204,.28) 0%, transparent 26%),
            radial-gradient(circle at bottom right, rgba(40,90,72,.10) 0%, transparent 22%),
            linear-gradient(180deg, #f7fcf9 0%, #ffffff 42%, #f5fbf7 100%);
    }

    .services-wrap{
        width:min(100%, 1440px);
        margin:0 auto;
        padding-left:clamp(18px, 3vw, 42px);
        padding-right:clamp(18px, 3vw, 42px);
    }

    .services-hero{
        width:100%;
        min-height:calc(100vh - 76px);
        display:flex;
        align-items:center;
        padding:42px 0 54px;
    }

    .services-hero-grid{
        display:grid;
        grid-template-columns:minmax(0,1.06fr) minmax(0,.94fr);
        gap:clamp(24px, 4vw, 54px);
        align-items:center;
        width:100%;
    }

    .services-badge{
        display:inline-flex;
        align-items:center;
        gap:10px;
        padding:10px 16px;
        border-radius:999px;
        background:rgba(40,90,72,.08);
        border:1px solid rgba(40,90,72,.12);
        color:var(--ts-green);
        font-size:.82rem;
        font-weight:800;
        letter-spacing:.12em;
        text-transform:uppercase;
    }

    .services-title{
        margin:16px 0 14px;
        font-size:clamp(2.2rem, 5.8vw, 4.9rem);
        line-height:.95;
        letter-spacing:-.05em;
        color:var(--ts-dark);
        font-weight:900;
        max-width:900px;
    }

    .services-title .highlight{
        color:var(--ts-green);
        position:relative;
        display:inline-block;
    }

    .services-title .highlight::after{
        content:"";
        position:absolute;
        left:0;
        bottom:.08em;
        width:100%;
        height:.18em;
        border-radius:999px;
        background:rgba(176,228,204,.62);
        z-index:-1;
    }

    .services-subtitle{
        max-width:760px;
        margin:0 0 28px;
        color:var(--ts-muted);
        font-size:1.04rem;
        line-height:1.85;
    }

    .services-actions{
        display:flex;
        flex-wrap:wrap;
        gap:14px;
        margin-bottom:26px;
    }

    .services-btn{
        min-height:54px;
        padding:0 24px;
        border-radius:999px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:10px;
        text-decoration:none;
        font-weight:800;
        transition:.25s ease;
        border:1px solid transparent;
    }

    .services-btn-primary{
        color:#fff;
        background:linear-gradient(135deg, #285A48 0%, #091413 100%);
        box-shadow:0 18px 40px rgba(40,90,72,.22);
    }

    .services-btn-primary:hover{transform:translateY(-2px)}

    .services-btn-secondary{
        color:var(--ts-green);
        background:#fff;
        border-color:var(--ts-border-strong);
        box-shadow:var(--ts-shadow);
    }

    .services-btn-secondary:hover{transform:translateY(-2px)}

    .services-metrics{
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:14px;
        max-width:780px;
    }

    .metric-card{
        background:rgba(255,255,255,.92);
        border:1px solid var(--ts-border);
        border-radius:24px;
        padding:20px 18px;
        box-shadow:var(--ts-shadow);
    }

    .metric-value{
        font-size:1.55rem;
        font-weight:900;
        letter-spacing:-.03em;
        color:var(--ts-dark);
        margin-bottom:6px;
    }

    .metric-label{
        color:var(--ts-muted);
        font-size:.92rem;
        line-height:1.55;
    }

    .hero-panel{
        position:relative;
        border-radius:32px;
        padding:26px;
        background:linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(176,228,204,.82) 100%);
        border:1px solid rgba(40,90,72,.12);
        box-shadow:var(--ts-shadow-lg);
        overflow:hidden;
    }

    .hero-panel::before{
        content:"";
        position:absolute;
        width:240px;
        height:240px;
        border-radius:999px;
        top:-90px;
        right:-70px;
        background:rgba(176,228,204,.42);
        filter:blur(10px);
    }

    .hero-panel-top{
        position:relative;
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:16px;
        margin-bottom:20px;
    }

    .hero-chip{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:9px 14px;
        border-radius:999px;
        background:rgba(9,20,19,.92);
        color:#fff;
        font-size:.8rem;
        font-weight:800;
    }

    .hero-mini{
        text-align:right;
    }

    .hero-mini strong{
        display:block;
        font-size:2rem;
        line-height:1;
        letter-spacing:-.05em;
        color:var(--ts-dark);
    }

    .hero-mini span{
        color:var(--ts-muted);
        font-size:.88rem;
        font-weight:700;
    }

    .hero-stack{
        position:relative;
        display:grid;
        gap:14px;
    }

    .hero-item{
        display:flex;
        gap:14px;
        align-items:flex-start;
        padding:16px 18px;
        background:rgba(255,255,255,.88);
        border:1px solid rgba(40,90,72,.10);
        border-radius:20px;
    }

    .hero-icon{
        width:46px;
        height:46px;
        flex:0 0 46px;
        border-radius:14px;
        display:flex;
        align-items:center;
        justify-content:center;
        background:linear-gradient(135deg, #285A48, #091413);
        color:#fff;
        font-size:.82rem;
        font-weight:900;
        letter-spacing:.06em;
    }

    .hero-item h4{
        margin:0 0 6px;
        font-size:1rem;
        color:var(--ts-dark);
    }

    .hero-item p{
        margin:0;
        color:var(--ts-muted);
        font-size:.92rem;
        line-height:1.6;
    }

    .section{
        padding:clamp(50px, 7vw, 88px) 0;
    }

    .section-head{
        max-width:820px;
        margin:0 auto 38px;
        text-align:center;
    }

    .section-head .mini{
        display:inline-block;
        margin-bottom:12px;
        color:var(--ts-green);
        font-size:.84rem;
        font-weight:800;
        text-transform:uppercase;
        letter-spacing:.14em;
    }

    .section-head h2{
        margin:0 0 14px;
        font-size:clamp(1.9rem, 4vw, 3.2rem);
        line-height:1.02;
        letter-spacing:-.04em;
        color:var(--ts-dark);
    }

    .section-head p{
        margin:0;
        color:var(--ts-muted);
        font-size:1rem;
        line-height:1.8;
    }

    .featured-grid{
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:20px;
    }

    .service-grid{
        display:grid;
        grid-template-columns:repeat(4, minmax(0, 1fr));
        gap:20px;
    }

    .service-card{
        position:relative;
        height:100%;
        padding:24px;
        border-radius:28px;
        background:#fff;
        border:1px solid var(--ts-border);
        box-shadow:var(--ts-shadow);
        transition:.28s ease;
        overflow:hidden;
    }

    .service-card::before{
        content:"";
        position:absolute;
        left:0;
        top:0;
        width:100%;
        height:5px;
        background:linear-gradient(90deg, #285A48 0%, #B0E4CC 100%);
    }

    .service-card:hover{
        transform:translateY(-5px);
        border-color:var(--ts-border-strong);
        box-shadow:0 24px 70px rgba(9,20,19,.12);
    }

    .service-card.featured{
        background:linear-gradient(180deg, rgba(255,255,255,1) 0%, rgba(176,228,204,.18) 100%);
    }

    .service-top{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:12px;
        margin-bottom:18px;
    }

    .service-icon{
        min-width:58px;
        height:58px;
        padding:0 16px;
        border-radius:18px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background:linear-gradient(135deg, #285A48 0%, #091413 100%);
        color:#fff;
        font-size:.88rem;
        font-weight:900;
        letter-spacing:.08em;
        box-shadow:0 12px 28px rgba(40,90,72,.18);
    }

    .featured-tag{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:8px 12px;
        border-radius:999px;
        background:rgba(176,228,204,.34);
        color:var(--ts-green);
        border:1px solid rgba(40,90,72,.10);
        font-size:.74rem;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.1em;
        white-space:nowrap;
    }

    .service-card h3{
        margin:0 0 10px;
        font-size:1.14rem;
        line-height:1.3;
        letter-spacing:-.02em;
        color:var(--ts-dark);
    }

    .service-excerpt{
        margin:0 0 12px;
        color:var(--ts-muted);
        line-height:1.72;
        font-size:.94rem;
    }

    .service-description{
        margin:0 0 20px;
        color:#36534a;
        line-height:1.72;
        font-size:.92rem;
    }

    .service-meta{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:12px;
        margin-bottom:20px;
        padding-top:18px;
        border-top:1px dashed rgba(40,90,72,.14);
    }

    .service-meta-box{
        padding:14px 14px 12px;
        border-radius:18px;
        background:#f7fcf9;
        border:1px solid rgba(40,90,72,.08);
    }

    .service-meta-box span{
        display:block;
        color:var(--ts-muted);
        font-size:.78rem;
        text-transform:uppercase;
        letter-spacing:.08em;
        font-weight:800;
        margin-bottom:6px;
    }

    .service-meta-box strong{
        display:block;
        color:var(--ts-dark);
        font-size:1.02rem;
        letter-spacing:-.02em;
    }

    .service-actions{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
    }

    .service-link,
    .service-link-light{
        min-height:46px;
        padding:0 18px;
        border-radius:999px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        text-decoration:none;
        font-size:.92rem;
        font-weight:800;
        transition:.25s ease;
    }

    .service-link{
        color:#fff;
        background:linear-gradient(135deg, #285A48 0%, #091413 100%);
        box-shadow:0 14px 30px rgba(40,90,72,.18);
    }

    .service-link-light{
        color:var(--ts-green);
        background:#fff;
        border:1px solid rgba(40,90,72,.12);
    }

    .service-link:hover,
    .service-link-light:hover{
        transform:translateY(-2px);
    }

    .process-strip{
        background:#091413;
        color:#fff;
    }

    .process-grid{
        display:grid;
        grid-template-columns:repeat(4, minmax(0, 1fr));
        gap:18px;
        padding:30px 0;
    }

    .process-card{
        min-height:100%;
        padding:22px;
        border-radius:24px;
        background:rgba(255,255,255,.04);
        border:1px solid rgba(176,228,204,.12);
    }

    .process-no{
        width:44px;
        height:44px;
        border-radius:14px;
        background:rgba(176,228,204,.18);
        color:#B0E4CC;
        display:flex;
        align-items:center;
        justify-content:center;
        font-weight:900;
        margin-bottom:14px;
    }

    .process-card h4{
        margin:0 0 8px;
        color:#fff;
        font-size:1.02rem;
    }

    .process-card p{
        margin:0;
        color:rgba(255,255,255,.74);
        line-height:1.72;
        font-size:.92rem;
    }

    .cta-band{
        padding:0 0 80px;
    }

    .cta-box{
        display:grid;
        grid-template-columns:minmax(0, 1fr) auto;
        gap:20px;
        align-items:center;
        border-radius:34px;
        padding:clamp(26px, 4vw, 44px);
        background:
            radial-gradient(circle at top right, rgba(176,228,204,.16) 0%, transparent 24%),
            linear-gradient(135deg, #091413 0%, #16382d 56%, #285A48 100%);
        color:#fff;
        box-shadow:0 28px 90px rgba(9,20,19,.22);
    }

    .cta-box h3{
        margin:0 0 10px;
        font-size:clamp(1.7rem, 4vw, 2.7rem);
        line-height:1.06;
        letter-spacing:-.04em;
    }

    .cta-box p{
        margin:0;
        color:rgba(255,255,255,.82);
        line-height:1.8;
        max-width:780px;
    }

    .cta-actions{
        display:flex;
        flex-wrap:wrap;
        justify-content:flex-end;
        gap:12px;
    }

    .cta-primary,
    .cta-outline{
        min-height:52px;
        padding:0 22px;
        border-radius:999px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        text-decoration:none;
        font-weight:800;
        transition:.25s ease;
    }

    .cta-primary{
        background:#fff;
        color:var(--ts-dark);
    }

    .cta-outline{
        background:rgba(255,255,255,.06);
        color:#fff;
        border:1px solid rgba(255,255,255,.16);
    }

    .cta-primary:hover,
    .cta-outline:hover{
        transform:translateY(-2px);
    }

    .empty-state{
        max-width:760px;
        margin:0 auto;
        padding:40px 28px;
        text-align:center;
        background:#fff;
        border:1px solid var(--ts-border);
        border-radius:28px;
        box-shadow:var(--ts-shadow);
    }

    .empty-state h3{
        margin:0 0 10px;
        color:var(--ts-dark);
        font-size:1.5rem;
    }

    .empty-state p{
        margin:0;
        color:var(--ts-muted);
        line-height:1.8;
    }

    @media (max-width: 1180px){
        .service-grid{grid-template-columns:repeat(2, minmax(0, 1fr))}
        .featured-grid{grid-template-columns:repeat(2, minmax(0, 1fr))}
        .process-grid{grid-template-columns:repeat(2, minmax(0, 1fr))}
    }

    @media (max-width: 920px){
        .services-hero-grid,
        .cta-box{
            grid-template-columns:1fr;
        }

        .services-hero{
            min-height:auto;
        }

        .services-metrics{
            grid-template-columns:1fr;
        }

        .cta-actions{
            justify-content:flex-start;
        }
    }

    @media (max-width: 700px){
        .service-grid,
        .featured-grid,
        .process-grid{
            grid-template-columns:1fr;
        }

        .services-title{
            font-size:2.4rem;
        }

        .service-card,
        .hero-panel,
        .cta-box{
            border-radius:24px;
        }
    }
</style>

<div class="services-page">

    <section class="services-hero">
        <div class="services-wrap">
            <div class="services-hero-grid">
                <div>
                    <span class="services-badge">Tax Saathi Services</span>

                    <h1 class="services-title">
                        Premium tax and compliance services
                        <span class="highlight">for every stage.</span>
                    </h1>

                    <p class="services-subtitle">
                        Explore structured filing, GST, notice reply, and professional compliance support.
                        Every service below is loaded directly from your database table, so the page stays dynamic
                        when you add, edit, feature, or reorder services.
                    </p>

                    <div class="services-actions">
                        <a href="/register" class="services-btn services-btn-primary">Get Started</a>
                        <a href="/login" class="services-btn services-btn-secondary">Client Login</a>
                    </div>

                    <div class="services-metrics">
                        <div class="metric-card">
                            <div class="metric-value"><?= e((string)$totalServices) ?></div>
                            <div class="metric-label">Active services displayed directly from the services table.</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value"><?= $minFee !== null ? '₹' . e(number_format((float)$minFee, 0)) : 'Custom' ?></div>
                            <div class="metric-label">Starting price from your active service records.</div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-value"><?= e((string)count($featuredServices)) ?></div>
                            <div class="metric-label">Featured services highlighted first for stronger conversion.</div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="hero-panel">
                        <div class="hero-panel-top">
                            <span class="hero-chip">Dynamic Service Catalog</span>
                            <div class="hero-mini">
                                <strong><?= e((string)$totalServices) ?></strong>
                                <span>Live services</span>
                            </div>
                        </div>

                        <div class="hero-stack">
                            <?php foreach (array_slice($services, 0, 4) as $service): ?>
                                <?php
                                    $heroIcon = trim((string)($service['icon'] ?? 'SRV'));
                                    $heroTitle = trim((string)($service['title'] ?? 'Service'));
                                    $heroExcerpt = trim((string)($service['excerpt'] ?? 'Professional tax and compliance support.'));
                                ?>
                                <div class="hero-item">
                                    <div class="hero-icon"><?= e(strtoupper($heroIcon)) ?></div>
                                    <div>
                                        <h4><?= e($heroTitle) ?></h4>
                                        <p><?= e($heroExcerpt) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($services === []): ?>
                                <div class="hero-item">
                                    <div class="hero-icon">00</div>
                                    <div>
                                        <h4>No services yet</h4>
                                        <p>Add active services in the services table to display them here.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($featuredServices !== []): ?>
        <section class="section">
            <div class="services-wrap">
                <div class="section-head">
                    <span class="mini">Featured Services</span>
                    <h2>Highlighted solutions for the most common tax needs.</h2>
                    <p>
                        Services marked as featured in your table are shown first here using `is_featured = 1`.
                    </p>
                </div>

                <div class="featured-grid">
                    <?php foreach ($featuredServices as $service): ?>
                        <?php
                            $title = trim((string)($service['title'] ?? 'Service'));
                            $excerpt = trim((string)($service['excerpt'] ?? 'Professional filing and compliance support.'));
                            $description = trim((string)($service['description'] ?? ''));
                            $fee = (float)($service['filing_fee'] ?? 0);
                            $days = (int)($service['turnaround_days'] ?? 0);
                            $icon = trim((string)($service['icon'] ?? 'SRV'));
                            $slug = trim((string)($service['slug'] ?? ''));
                        ?>
                        <article class="service-card featured">
                            <div class="service-top">
                                <span class="service-icon"><?= e(strtoupper($icon)) ?></span>
                                <span class="featured-tag">Featured</span>
                            </div>

                            <h3><?= e($title) ?></h3>
                            <p class="service-excerpt"><?= e($excerpt) ?></p>

                            <?php if ($description !== ''): ?>
                                <p class="service-description"><?= e($description) ?></p>
                            <?php endif; ?>

                            <div class="service-meta">
                                <div class="service-meta-box">
                                    <span>Filing Fee</span>
                                    <strong><?= $fee > 0 ? '₹' . e(number_format($fee, 2)) : 'Custom Quote' ?></strong>
                                </div>
                                <div class="service-meta-box">
                                    <span>Turnaround</span>
                                    <strong><?= $days > 0 ? e((string)$days) . ' Days' : 'As per scope' ?></strong>
                                </div>
                            </div>

                            <div class="service-actions">
                                <a href="/service-details?slug=<?= urlencode($slug) ?>" class="service-link">View Details</a>
                                <a href="/register" class="service-link-light">Get Started</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="section" style="padding-top:0;">
        <div class="services-wrap">
            <div class="section-head">
                <span class="mini">All Services</span>
                <h2>Transparent pricing, structured delivery, and clear scope.</h2>
                <p>
                    Each service card uses title, excerpt, description, filing fee, turnaround time, icon,
                    featured flag, and ordering directly from your services table.
                </p>
            </div>

            <?php if ($services !== []): ?>
                <div class="service-grid">
                    <?php foreach ($services as $service): ?>
                        <?php
                            $title = trim((string)($service['title'] ?? 'Service'));
                            $excerpt = trim((string)($service['excerpt'] ?? 'Professional tax and compliance support.'));
                            $description = trim((string)($service['description'] ?? ''));
                            $fee = (float)($service['filing_fee'] ?? 0);
                            $days = (int)($service['turnaround_days'] ?? 0);
                            $icon = trim((string)($service['icon'] ?? 'SRV'));
                            $slug = trim((string)($service['slug'] ?? ''));
                            $isFeatured = (int)($service['is_featured'] ?? 0) === 1;
                        ?>
                        <article class="service-card <?= $isFeatured ? 'featured' : '' ?>">
                            <div class="service-top">
                                <span class="service-icon"><?= e(strtoupper($icon)) ?></span>
                                <?php if ($isFeatured): ?>
                                    <span class="featured-tag">Featured</span>
                                <?php endif; ?>
                            </div>

                            <h3><?= e($title) ?></h3>
                            <p class="service-excerpt"><?= e($excerpt) ?></p>

                            <?php if ($description !== ''): ?>
                                <p class="service-description"><?= e($description) ?></p>
                            <?php endif; ?>

                            <div class="service-meta">
                                <div class="service-meta-box">
                                    <span>Fee</span>
                                    <strong><?= $fee > 0 ? '₹' . e(number_format($fee, 2)) : 'Custom Quote' ?></strong>
                                </div>
                                <div class="service-meta-box">
                                    <span>Timeline</span>
                                    <strong><?= $days > 0 ? e((string)$days) . ' Days' : 'As per scope' ?></strong>
                                </div>
                            </div>

                            <div class="service-actions">
                                <a href="/service-details?slug=<?= urlencode($slug) ?>" class="service-link">View Details</a>
                                <a href="/register" class="service-link-light">Get Started</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No active services found</h3>
                    <p>
                        Add records into the <strong>services</strong> table with <strong>is_active = 1</strong>
                        to display them on this page.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="process-strip">
        <div class="services-wrap">
            <div class="process-grid">
                <div class="process-card">
                    <div class="process-no">01</div>
                    <h4>Select Service</h4>
                    <p>Pick the right filing or compliance service based on your requirement.</p>
                </div>
                <div class="process-card">
                    <div class="process-no">02</div>
                    <h4>Submit Details</h4>
                    <p>Share your documents and information for expert review and processing.</p>
                </div>
                <div class="process-card">
                    <div class="process-no">03</div>
                    <h4>Expert Handling</h4>
                    <p>Your case is managed professionally with guidance, drafting, and compliance support.</p>
                </div>
                <div class="process-card">
                    <div class="process-no">04</div>
                    <h4>Completion</h4>
                    <p>Receive timely completion, filing support, and assistance for the next steps.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cta-band section">
        <div class="services-wrap">
            <div class="cta-box">
                <div>
                    <h3>Ready to move ahead with the right service?</h3>
                    <p>
                        Start with a structured process for income tax filing, GST returns, business or professional
                        tax support, and notice reply assistance.
                    </p>
                </div>

                <div class="cta-actions">
                    <a href="/register" class="cta-primary">Get Started</a>
                    <a href="/login" class="cta-outline">Login</a>
                </div>
            </div>
        </div>
    </section>

</div>