<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$service = is_array($service ?? null) ? $service : [];
$relatedServices = is_array($relatedServices ?? null) ? $relatedServices : [];

$title = trim((string)($service['title'] ?? 'Service'));
$slug = trim((string)($service['slug'] ?? ''));
$excerpt = trim((string)($service['excerpt'] ?? 'Professional tax and compliance support.'));
$description = trim((string)($service['description'] ?? ''));
$icon = trim((string)($service['icon'] ?? 'SRV'));
$fee = (float)($service['filing_fee'] ?? 0);
$days = (int)($service['turnaround_days'] ?? 0);
$isFeatured = (int)($service['is_featured'] ?? 0) === 1;
?>

<style>
    :root{
        --ts-dark:#091413;
        --ts-green:#285A48;
        --ts-mint:#B0E4CC;
        --ts-text:#11211d;
        --ts-muted:#61746d;
        --ts-border:rgba(40,90,72,.10);
        --ts-border-strong:rgba(40,90,72,.18);
        --ts-shadow:0 20px 60px rgba(9,20,19,.08);
        --ts-shadow-lg:0 28px 90px rgba(9,20,19,.12);
    }

    *{box-sizing:border-box}

    .service-show-page{
        width:100%;
        color:var(--ts-text);
        background:
            radial-gradient(circle at top left, rgba(176,228,204,.28) 0%, transparent 24%),
            radial-gradient(circle at bottom right, rgba(40,90,72,.10) 0%, transparent 22%),
            linear-gradient(180deg, #f7fcf9 0%, #ffffff 45%, #f6fbf8 100%);
    }

    .service-show-wrap{
        width:min(100%, 1440px);
        margin:0 auto;
        padding-left:clamp(18px, 3vw, 42px);
        padding-right:clamp(18px, 3vw, 42px);
    }

    .service-show-hero{
        min-height:calc(100vh - 76px);
        display:flex;
        align-items:center;
        padding:42px 0 56px;
    }

    .service-show-grid{
        width:100%;
        display:grid;
        grid-template-columns:minmax(0, 1.08fr) minmax(0, .92fr);
        gap:clamp(24px, 4vw, 54px);
        align-items:center;
    }

    .crumbs{
        display:flex;
        flex-wrap:wrap;
        gap:10px;
        align-items:center;
        margin-bottom:14px;
        color:var(--ts-muted);
        font-size:.92rem;
        font-weight:700;
    }

    .crumbs a{
        color:var(--ts-green);
        text-decoration:none;
    }

    .service-badge{
        display:inline-flex;
        align-items:center;
        gap:10px;
        padding:10px 16px;
        border-radius:999px;
        background:rgba(40,90,72,.08);
        border:1px solid rgba(40,90,72,.10);
        color:var(--ts-green);
        font-size:.82rem;
        font-weight:900;
        letter-spacing:.11em;
        text-transform:uppercase;
        margin-bottom:16px;
    }

    .service-title{
        margin:0 0 14px;
        font-size:clamp(2.2rem, 5.6vw, 4.8rem);
        line-height:.95;
        letter-spacing:-.05em;
        font-weight:900;
        color:var(--ts-dark);
    }

    .service-title .highlight{
        color:var(--ts-green);
        position:relative;
        display:inline-block;
    }

    .service-title .highlight::after{
        content:"";
        position:absolute;
        left:0;
        bottom:.08em;
        width:100%;
        height:.18em;
        background:rgba(176,228,204,.62);
        border-radius:999px;
        z-index:-1;
    }

    .service-excerpt{
        margin:0 0 20px;
        color:var(--ts-muted);
        font-size:1.04rem;
        line-height:1.82;
        max-width:780px;
    }

    .service-actions{
        display:flex;
        flex-wrap:wrap;
        gap:14px;
        margin-bottom:26px;
    }

    .btn-primary,
    .btn-secondary{
        min-height:54px;
        padding:0 24px;
        border-radius:999px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        text-decoration:none;
        font-weight:800;
        transition:.25s ease;
    }

    .btn-primary{
        color:#fff;
        background:linear-gradient(135deg, #285A48 0%, #091413 100%);
        box-shadow:0 18px 40px rgba(40,90,72,.22);
    }

    .btn-secondary{
        color:var(--ts-green);
        background:#fff;
        border:1px solid var(--ts-border-strong);
        box-shadow:var(--ts-shadow);
    }

    .btn-primary:hover,
    .btn-secondary:hover{
        transform:translateY(-2px);
    }

    .meta-grid{
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:14px;
        max-width:860px;
    }

    .meta-card{
        background:rgba(255,255,255,.94);
        border:1px solid var(--ts-border);
        border-radius:24px;
        padding:20px 18px;
        box-shadow:var(--ts-shadow);
    }

    .meta-card span{
        display:block;
        font-size:.78rem;
        color:var(--ts-muted);
        text-transform:uppercase;
        letter-spacing:.09em;
        font-weight:900;
        margin-bottom:7px;
    }

    .meta-card strong{
        display:block;
        font-size:1.1rem;
        color:var(--ts-dark);
        letter-spacing:-.02em;
    }

    .hero-panel{
        position:relative;
        border-radius:32px;
        padding:26px;
        background:linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(176,228,204,.84) 100%);
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

    .hero-top{
        position:relative;
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:16px;
        margin-bottom:20px;
    }

    .icon-box{
        min-width:72px;
        height:72px;
        padding:0 18px;
        border-radius:22px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background:linear-gradient(135deg, #285A48 0%, #091413 100%);
        color:#fff;
        font-size:1rem;
        font-weight:900;
        letter-spacing:.08em;
        box-shadow:0 14px 34px rgba(40,90,72,.18);
    }

    .featured-tag{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:8px 12px;
        border-radius:999px;
        background:rgba(176,228,204,.36);
        color:var(--ts-green);
        border:1px solid rgba(40,90,72,.10);
        font-size:.74rem;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.1em;
    }

    .panel-stack{
        display:grid;
        gap:14px;
        position:relative;
    }

    .panel-item{
        padding:16px 18px;
        border-radius:20px;
        background:rgba(255,255,255,.88);
        border:1px solid rgba(40,90,72,.10);
    }

    .panel-item h4{
        margin:0 0 6px;
        font-size:1rem;
        color:var(--ts-dark);
    }

    .panel-item p{
        margin:0;
        color:var(--ts-muted);
        font-size:.92rem;
        line-height:1.65;
    }

    .section{
        padding:clamp(48px, 7vw, 86px) 0;
    }

    .detail-layout{
        display:grid;
        grid-template-columns:minmax(0, 1.05fr) minmax(320px, .95fr);
        gap:24px;
        align-items:start;
    }

    .detail-card,
    .sidebar-card{
        background:#fff;
        border:1px solid var(--ts-border);
        border-radius:30px;
        box-shadow:var(--ts-shadow);
        overflow:hidden;
    }

    .detail-card{
        padding:28px;
    }

    .detail-card h2{
        margin:0 0 14px;
        font-size:1.7rem;
        line-height:1.1;
        letter-spacing:-.03em;
        color:var(--ts-dark);
    }

    .detail-card p{
        margin:0;
        color:var(--ts-muted);
        line-height:1.9;
        font-size:1rem;
    }

    .sidebar-card{
        padding:24px;
    }

    .sidebar-title{
        margin:0 0 18px;
        font-size:1.2rem;
        line-height:1.2;
        letter-spacing:-.02em;
        color:var(--ts-dark);
    }

    .quick-list{
        display:grid;
        gap:14px;
        margin:0;
        padding:0;
        list-style:none;
    }

    .quick-list li{
        display:flex;
        gap:12px;
        line-height:1.75;
        color:var(--ts-muted);
    }

    .quick-list i{
        width:24px;
        height:24px;
        flex:0 0 24px;
        border-radius:999px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background:rgba(176,228,204,.22);
        color:var(--ts-green);
        font-style:normal;
        font-weight:900;
        margin-top:2px;
    }

    .price-box{
        margin-top:20px;
        padding:18px;
        border-radius:22px;
        background:#f7fcf9;
        border:1px solid rgba(40,90,72,.08);
    }

    .price-box span{
        display:block;
        font-size:.8rem;
        color:var(--ts-muted);
        text-transform:uppercase;
        letter-spacing:.08em;
        font-weight:900;
        margin-bottom:6px;
    }

    .price-box strong{
        display:block;
        color:var(--ts-dark);
        font-size:1.4rem;
        letter-spacing:-.03em;
    }

    .related-head{
        margin:0 0 28px;
        text-align:center;
    }

    .related-head h2{
        margin:0 0 10px;
        font-size:clamp(1.8rem, 4vw, 3rem);
        line-height:1.04;
        letter-spacing:-.04em;
        color:var(--ts-dark);
    }

    .related-head p{
        margin:0;
        color:var(--ts-muted);
        line-height:1.75;
    }

    .related-grid{
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:20px;
    }

    .related-card{
        height:100%;
        padding:24px;
        border-radius:28px;
        background:#fff;
        border:1px solid var(--ts-border);
        box-shadow:var(--ts-shadow);
        transition:.25s ease;
    }

    .related-card:hover{
        transform:translateY(-4px);
        border-color:var(--ts-border-strong);
    }

    .related-card-top{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:12px;
        margin-bottom:16px;
    }

    .related-icon{
        min-width:56px;
        height:56px;
        padding:0 14px;
        border-radius:18px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background:linear-gradient(135deg, #285A48 0%, #091413 100%);
        color:#fff;
        font-size:.88rem;
        font-weight:900;
        letter-spacing:.08em;
    }

    .related-card h3{
        margin:0 0 10px;
        font-size:1.08rem;
        line-height:1.3;
        color:var(--ts-dark);
    }

    .related-card p{
        margin:0 0 16px;
        color:var(--ts-muted);
        line-height:1.7;
        font-size:.94rem;
    }

    .related-meta{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:12px;
        margin-bottom:16px;
    }

    .related-meta div{
        padding:12px 14px;
        border-radius:16px;
        background:#f7fcf9;
        border:1px solid rgba(40,90,72,.08);
    }

    .related-meta span{
        display:block;
        font-size:.75rem;
        color:var(--ts-muted);
        text-transform:uppercase;
        letter-spacing:.08em;
        font-weight:900;
        margin-bottom:5px;
    }

    .related-meta strong{
        display:block;
        color:var(--ts-dark);
        font-size:.94rem;
    }

    .related-link{
        min-height:46px;
        padding:0 18px;
        border-radius:999px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        text-decoration:none;
        font-size:.92rem;
        font-weight:800;
        color:#fff;
        background:linear-gradient(135deg, #285A48 0%, #091413 100%);
        box-shadow:0 14px 30px rgba(40,90,72,.16);
        transition:.25s ease;
    }

    .related-link:hover{
        transform:translateY(-2px);
    }

    @media (max-width: 1180px){
        .related-grid{grid-template-columns:repeat(2, minmax(0, 1fr))}
    }

    @media (max-width: 920px){
        .service-show-grid,
        .detail-layout{
            grid-template-columns:1fr;
        }

        .service-show-hero{
            min-height:auto;
        }

        .meta-grid{
            grid-template-columns:1fr;
        }
    }

    @media (max-width: 700px){
        .related-grid{grid-template-columns:1fr}
        .service-title{font-size:2.35rem}
        .detail-card,
        .sidebar-card,
        .hero-panel{border-radius:24px}
    }
</style>

<div class="service-show-page">

    <section class="service-show-hero">
        <div class="service-show-wrap">
            <div class="service-show-grid">
                <div>
                    <div class="crumbs">
                        <a href="/">Home</a>
                        <span>•</span>
                        <a href="/services">Services</a>
                        <span>•</span>
                        <span><?= e($title) ?></span>
                    </div>

                    <span class="service-badge">
                        Service Details
                        <?php if ($isFeatured): ?>
                            • Featured
                        <?php endif; ?>
                    </span>

                    <p class="service-excerpt"><?= e($excerpt) ?></p>

                    <div class="service-actions">
                        <a href="/register" class="btn-primary">Get Started</a>
                        <a href="/services" class="btn-secondary">Back to Services</a>
                    </div>

                    <div class="meta-grid">
                        <div class="meta-card">
                            <span>Service Fee</span>
                            <strong><?= $fee > 0 ? '₹' . e(number_format($fee, 2)) : 'Custom Quote' ?></strong>
                        </div>
                        <div class="meta-card">
                            <span>Turnaround Time</span>
                            <strong><?= $days > 0 ? e((string)$days) . ' Days' : 'As per scope' ?></strong>
                        </div>
                        <div class="meta-card">
                            <span>Service Code</span>
                            <strong><?= e($slug !== '' ? $slug : 'N/A') ?></strong>
                        </div>
                    </div>
                </div>

                <div>
                    
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="service-show-wrap">
            <div class="detail-layout">
                <div class="detail-card">
                    <h2>About this service</h2>
                    <p><?= nl2br(e($description !== '' ? $description : $excerpt)) ?></p>
                </div>

                <aside class="sidebar-card">
                    <h3 class="sidebar-title">Quick overview</h3>

                    <ul class="quick-list">
                        <li><i>✓</i><span>Dynamic title, slug, excerpt, description, pricing, icon, and featured flag from the services table.</span></li>
                        <li><i>✓</i><span>Only active services are accessible on the public details page.</span></li>
                        <li><i>✓</i><span>Stable detail route using query slug for custom router compatibility.</span></li>
                        <li><i>✓</i><span>Related services are loaded automatically below.</span></li>
                    </ul>

                    <div class="price-box">
                        <span>Starting From</span>
                        <strong><?= $fee > 0 ? '₹' . e(number_format($fee, 2)) : 'Custom Quote' ?></strong>
                    </div>

                    <div class="service-actions" style="margin-top:18px;margin-bottom:0;">
                        <a href="/register" class="btn-primary">Proceed</a>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <?php if ($relatedServices !== []): ?>
        <section class="section" style="padding-top:0;">
            <div class="service-show-wrap">
                <div class="related-head">
                    <h2>Related services</h2>
                    <p>Explore other active solutions from the same service catalog.</p>
                </div>

                <div class="related-grid">
                    <?php foreach ($relatedServices as $item): ?>
                        <?php
                            $rTitle = trim((string)($item['title'] ?? 'Service'));
                            $rSlug = trim((string)($item['slug'] ?? ''));
                            $rExcerpt = trim((string)($item['excerpt'] ?? 'Professional support.'));
                            $rIcon = trim((string)($item['icon'] ?? 'SRV'));
                            $rFee = (float)($item['filing_fee'] ?? 0);
                            $rDays = (int)($item['turnaround_days'] ?? 0);
                        ?>
                        <article class="related-card">
                            <div class="related-card-top">
                                <span class="related-icon"><?= e(strtoupper($rIcon)) ?></span>
                            </div>

                            <h3><?= e($rTitle) ?></h3>
                            <p><?= e($rExcerpt) ?></p>

                            <div class="related-meta">
                                <div>
                                    <span>Fee</span>
                                    <strong><?= $rFee > 0 ? '₹' . e(number_format($rFee, 2)) : 'Custom Quote' ?></strong>
                                </div>
                                <div>
                                    <span>Timeline</span>
                                    <strong><?= $rDays > 0 ? e((string)$rDays) . ' Days' : 'As per scope' ?></strong>
                                </div>
                            </div>

                            <a href="/service-details?slug=<?= urlencode($rSlug) ?>" class="related-link">View Details</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

</div>