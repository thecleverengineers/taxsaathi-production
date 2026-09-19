<?php
declare(strict_types=1);
$upiId=(string)($paymentSettings['upi_id']??'');
$upiName=(string)($paymentSettings['upi_name']??'Tax Saathi');
$qrImage=(string)($paymentSettings['qr_image']??'');
$amount=(float)($order['payable_amount']??0);
$upiLink='upi://pay?pa='.rawurlencode($upiId).'&pn='.rawurlencode($upiName).'&am='.rawurlencode(number_format($amount,2,'.','')).'&cu=INR&tn='.rawurlencode((string)($order['order_no']??'Partner Order'));
?>
<section class="grid gap-5">
    <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Waiting for Payment</p>
        <h1 class="mt-1 text-2xl font-black text-slate-900"><?= e((string)($order['order_no']??'')) ?></h1>
        <p class="mt-2 text-sm text-slate-500">Complete manual UPI payment and submit transaction reference for admin approval.</p>
    </div>
    <div class="grid gap-5 lg:grid-cols-[0.9fr_1.1fr]">
        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-black text-slate-900">Payment Summary</h2>
            <div class="mt-4 grid gap-3 text-sm">
                <div class="flex justify-between"><span>Service</span><strong><?= e((string)($order['service_title']??'-')) ?></strong></div>
                <div class="flex justify-between"><span>Filing Fee</span><strong>₹<?= e(number_format((float)($order['filing_fee']??0),2)) ?></strong></div>
                <div class="flex justify-between text-emerald-700"><span>Coupon Discount</span><strong>- ₹<?= e(number_format((float)($order['coupon_discount_amount']??0),2)) ?></strong></div>
                <?php if (!empty($order['coupon_code'])): ?><div class="flex justify-between text-emerald-700"><span>Coupon</span><strong><?= e((string)$order['coupon_code']) ?></strong></div><?php endif; ?>
                <div class="flex justify-between border-t border-slate-200 pt-3 text-lg"><span>Payable</span><strong>₹<?= e(number_format($amount,2)) ?></strong></div>
            </div>
            <a href="<?= e($upiLink) ?>" class="mt-5 inline-flex h-11 w-full items-center justify-center rounded-2xl bg-emerald-600 text-sm font-bold text-white">Open UPI App</a>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-black text-slate-900">Manual UPI Payment</h2>
            <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">UPI ID</div>
                <div class="mt-2 flex items-center justify-between gap-3">
                    <strong id="upiIdText" class="text-lg text-slate-900"><?= e($upiId) ?></strong>
                    <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('upiIdText').innerText)" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold">Copy</button>
                </div>
            </div>
            <?php if ($qrImage !== ''): ?><div class="mt-4 flex justify-center rounded-2xl border border-slate-200 p-4"><img src="<?= e(base_url($qrImage)) ?>" alt="UPI QR" class="max-h-56 rounded-xl"></div><?php endif; ?>
            <form method="post" action="<?= e(base_url('partner/orders/payment/submit')) ?>" enctype="multipart/form-data" class="mt-5 grid gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string)($order['id']??0)) ?>">
                <label class="grid gap-1"><span class="text-xs font-bold text-slate-500">UPI Transaction ID / UTR</span><input name="payment_reference" class="h-11 rounded-2xl border border-slate-200 px-3 text-sm" placeholder="Enter UTR / Transaction ID" required></label>
                <label class="grid gap-1"><span class="text-xs font-bold text-slate-500">Payment Screenshot optional</span><input type="file" name="payment_proof" class="rounded-2xl border border-slate-200 px-3 py-2 text-sm"></label>
                <button class="h-12 rounded-2xl bg-slate-900 text-sm font-bold text-white" type="submit">Submit Payment for Approval</button>
            </form>
        </div>
    </div>
</section>
