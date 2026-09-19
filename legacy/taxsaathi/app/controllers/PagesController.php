<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class PagesController extends Controller
{
    public function refundPolicy(): void
    {
        $data = [
            'siteName'      => 'Tax Saathi',
            'supportEmail'  => 'support@taxsaathi.in',
            'supportPhone'  => '+91 XXXXX XXXXX',
            'effectiveDate' => date('F d, Y'),
        ];

        $this->view('public/refund_policy', $data);
    }
    public function contact(): void
{
    $data = [
        'siteName'       => 'Tax Saathi',
        'supportEmail'   => 'support@taxsaathi.in',
        'supportPhone'   => '+91 XXXXX XXXXX',
        'businessAddress'=> 'Your Business Address, City, State, India',
        'businessHours'  => 'Monday to Saturday, 9:00 AM to 6:00 PM',
        'whatsappNumber' => '+91XXXXXXXXXX',
        'mapEmbedUrl'    => 'https://www.google.com/maps',
    ];

    $this->view('public/contact', $data);
}
    
    public function privacyPolicy(): void
{
    $data = [
        'siteName'      => 'Tax Saathi',
        'supportEmail'  => 'support@taxsaathi.in',
        'supportPhone'  => '+91 XXXXX XXXXX',
        'effectiveDate' => date('F d, Y'),
    ];

    $this->view('public/privacy_policy', $data);
}
    
public function about(): void
{
    $data = [
        'siteName' => 'Tax Saathi',
        'tagline'  => 'Simplifying Taxes for Everyone',
    ];

    $this->view('public/about', $data);
}
public function terms(): void
{
    $data = [
        'siteName'      => 'Tax Saathi',
        'supportEmail'  => 'support@taxsaathi.in',
        'supportPhone'  => '+91 XXXXX XXXXX',
        'effectiveDate' => date('F d, Y'),
    ];

    $this->view('public/terms_conditions', $data);
}


    
}