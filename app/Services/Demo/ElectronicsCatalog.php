<?php

namespace App\Services\Demo;

final class ElectronicsCatalog
{
    public const CURRENCY = 'LYD';

    public const CATEGORIES = [
        'phones' => ['name' => 'هواتف ذكية', 'label' => 'Smartphones', 'color' => '1d4ed8'],
        'laptops' => ['name' => 'لابتوبات', 'label' => 'Laptops', 'color' => '0f766e'],
        'audio' => ['name' => 'سماعات وصوتيات', 'label' => 'Audio', 'color' => '7c3aed'],
        'watches' => ['name' => 'ساعات ذكية', 'label' => 'Smart Watches', 'color' => 'b45309'],
        'accessories' => ['name' => 'شواحن وإكسسوارات', 'label' => 'Accessories', 'color' => '334155'],
    ];

    public const BRANDS = [
        'apple' => 'Apple',
        'samsung' => 'Samsung',
        'xiaomi' => 'Xiaomi',
        'lenovo' => 'Lenovo',
        'hp' => 'HP',
        'sony' => 'Sony',
        'anker' => 'Anker',
    ];

    public const OFFER_SKUS = ['SM-S25U-256', 'AP-APP2', 'XI-RN14P-256'];

    public const PRODUCTS = [
        [
            'sku' => 'AP-IP16PM-256',
            'category' => 'phones',
            'brand' => 'apple',
            'name' => 'iPhone 16 Pro Max 256GB',
            'price' => 7200,
            'description' => 'شاشة 6.9 بوصة، معالج A18 Pro، كاميرا 48 ميجابكسل مع زوم بصري 5x، بطارية تدوم طوال اليوم.',
        ],
        [
            'sku' => 'AP-IP16-128',
            'category' => 'phones',
            'brand' => 'apple',
            'name' => 'iPhone 16 128GB',
            'price' => 4900,
            'description' => 'شاشة 6.1 بوصة، معالج A18، زر التحكم بالكاميرا، متوفر بعدة ألوان.',
        ],
        [
            'sku' => 'SM-S25U-256',
            'category' => 'phones',
            'brand' => 'samsung',
            'name' => 'Galaxy S25 Ultra 256GB',
            'price' => 6400,
            'description' => 'شاشة 6.9 بوصة، قلم S Pen مدمج، كاميرا 200 ميجابكسل، ميزات Galaxy AI.',
        ],
        [
            'sku' => 'SM-A56-128',
            'category' => 'phones',
            'brand' => 'samsung',
            'name' => 'Galaxy A56 128GB',
            'price' => 1850,
            'description' => 'شاشة Super AMOLED بتردد 120Hz، بطارية 5000mAh، مقاوم للماء IP67.',
        ],
        [
            'sku' => 'XI-RN14P-256',
            'category' => 'phones',
            'brand' => 'xiaomi',
            'name' => 'Redmi Note 14 Pro 256GB',
            'price' => 1350,
            'description' => 'كاميرا 200 ميجابكسل، شحن سريع 45W، شاشة AMOLED منحنية.',
        ],
        [
            'sku' => 'XI-15-256',
            'category' => 'phones',
            'brand' => 'xiaomi',
            'name' => 'Xiaomi 15 256GB',
            'price' => 3600,
            'description' => 'عدسات Leica، معالج Snapdragon 8 Elite، شحن 90W.',
        ],
        [
            'sku' => 'AP-MBA-M4',
            'category' => 'laptops',
            'brand' => 'apple',
            'name' => 'MacBook Air M4 13" 256GB',
            'price' => 5600,
            'description' => 'معالج Apple M4، ذاكرة 16GB، بطارية حتى 18 ساعة، وزن خفيف جداً.',
        ],
        [
            'sku' => 'LN-SLIM5-I7',
            'category' => 'laptops',
            'brand' => 'lenovo',
            'name' => 'Lenovo IdeaPad Slim 5 Core i7',
            'price' => 4100,
            'description' => 'معالج Intel Core i7، ذاكرة 16GB، SSD بسعة 512GB، شاشة OLED 14 بوصة.',
        ],
        [
            'sku' => 'HP-VICTUS15',
            'category' => 'laptops',
            'brand' => 'hp',
            'name' => 'HP Victus 15 RTX 4050',
            'price' => 4800,
            'description' => 'لابتوب ألعاب بكرت شاشة RTX 4050، شاشة 144Hz، ذاكرة 16GB.',
        ],
        [
            'sku' => 'AP-APP2',
            'category' => 'audio',
            'brand' => 'apple',
            'name' => 'AirPods Pro 2',
            'price' => 1250,
            'description' => 'عزل ضوضاء نشط، صوت محيطي، علبة شحن USB-C مقاومة للماء.',
        ],
        [
            'sku' => 'SM-BUDS3P',
            'category' => 'audio',
            'brand' => 'samsung',
            'name' => 'Galaxy Buds3 Pro',
            'price' => 1050,
            'description' => 'عزل ضوضاء ذكي، صوت Hi-Fi بدقة 24bit، ترجمة فورية بـ Galaxy AI.',
        ],
        [
            'sku' => 'SN-WH1000XM5',
            'category' => 'audio',
            'brand' => 'sony',
            'name' => 'Sony WH-1000XM5',
            'price' => 1750,
            'description' => 'أفضل عزل ضوضاء في فئتها، بطارية 30 ساعة، مريحة للاستخدام الطويل.',
        ],
        [
            'sku' => 'XI-BUDS6P',
            'category' => 'audio',
            'brand' => 'xiaomi',
            'name' => 'Redmi Buds 6 Pro',
            'price' => 320,
            'description' => 'عزل ضوضاء حتى 55dB، بطارية 36 ساعة مع العلبة.',
        ],
        [
            'sku' => 'AP-AWS10-45',
            'category' => 'watches',
            'brand' => 'apple',
            'name' => 'Apple Watch Series 10 45mm',
            'price' => 2300,
            'description' => 'أنحف Apple Watch، شاشة أكبر، تتبع النوم وصحة القلب.',
        ],
        [
            'sku' => 'SM-GW7-44',
            'category' => 'watches',
            'brand' => 'samsung',
            'name' => 'Galaxy Watch7 44mm',
            'price' => 1400,
            'description' => 'تتبع صحي متقدم، GPS مدمج، بطارية تدوم يومين.',
        ],
        [
            'sku' => 'XI-BAND9',
            'category' => 'watches',
            'brand' => 'xiaomi',
            'name' => 'Xiaomi Smart Band 9',
            'price' => 220,
            'description' => 'سوار رياضي بشاشة AMOLED، بطارية حتى 21 يوماً.',
        ],
        [
            'sku' => 'AN-737-24K',
            'category' => 'accessories',
            'brand' => 'anker',
            'name' => 'Anker 737 Power Bank 24000mAh',
            'price' => 420,
            'description' => 'باور بانك بقدرة 140W يشحن اللابتوب والهاتف معاً، شاشة ذكية.',
        ],
        [
            'sku' => 'AN-GAN-65W',
            'category' => 'accessories',
            'brand' => 'anker',
            'name' => 'Anker 65W GaN Charger',
            'price' => 180,
            'description' => 'شاحن صغير بثلاثة منافذ يشحن اللابتوب والهاتف والسماعة.',
        ],
        [
            'sku' => 'AP-20W-USBC',
            'category' => 'accessories',
            'brand' => 'apple',
            'name' => 'Apple 20W USB-C Adapter',
            'price' => 110,
            'description' => 'شاحن Apple الأصلي للشحن السريع لأجهزة iPhone وiPad.',
        ],
        [
            'sku' => 'SM-45W-TA',
            'category' => 'accessories',
            'brand' => 'samsung',
            'name' => 'Samsung 45W Super Fast Charger',
            'price' => 150,
            'description' => 'شاحن سامسونج الأصلي 45W بمنفذ USB-C.',
            'in_stock' => false,
        ],
    ];

    public static function imageUrl(string $label, string $color): string
    {
        return 'https://placehold.co/955x500/'.$color.'/ffffff/png?text='.rawurlencode($label);
    }
}
