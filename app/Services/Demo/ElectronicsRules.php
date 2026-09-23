<?php

namespace App\Services\Demo;

final class ElectronicsRules
{
    public const PRICE_KEYWORDS = ['بكم', 'بكام', 'السعر', 'سعر', 'سعره', 'الثمن', 'price'];

    public static function comments(bool $productPostsLinked): array
    {
        return [
            [
                'name' => 'تعليقات: السعر',
                'match_type' => 'contains',
                'keywords' => self::PRICE_KEYWORDS,
                'response_text' => 'أهلاً 👋 أرسلنا لك السعر وكل التفاصيل على الخاص 📩',
                'private_reply_text' => $productPostsLinked
                    ? "أهلاً! 😊\n\n📱 {{product.name}}\n💰 السعر: {{product.price}} {{product.currency}}\n✅ ضمان سنة وتوصيل لكل المدن\n\nللطلب اكتب «القائمة» واختر «تصفح المنتجات» 🛍️"
                    : "أهلاً! 😊 أسعارنا تبدأ من 110 د.ل للإكسسوارات، والهواتف من 1350 د.ل.\n\nاكتب «القائمة» واختر «تصفح المنتجات» لتشوف كل الأسعار 🛍️",
                'is_active' => true,
            ],
            [
                'name' => 'تعليقات: التوفر',
                'match_type' => 'contains',
                'keywords' => ['متوفر', 'متوفرة', 'موجود', 'موجودة', 'available'],
                'response_text' => 'متوفر ✅ راسلناك على الخاص بالتفاصيل',
                'private_reply_text' => "أهلاً! نعم المنتج متوفر حالياً ✅\n\nاكتب «القائمة» لتشوف كل المنتجات والعروض، أو «موظف» للتحدث مع فريقنا.",
                'is_active' => true,
            ],
            [
                'name' => 'تعليقات: الرغبة في الطلب',
                'match_type' => 'contains',
                'keywords' => ['اطلب', 'نبي', 'نبيه', 'نبيها', 'اريد', 'أريد', 'مهتم', 'حجز'],
                'response_text' => 'تم 👍 تواصلنا معك على الخاص لإكمال الطلب',
                'private_reply_text' => "أهلاً! 😊 يسعدنا طلبك.\n\nاكتب «القائمة» ثم اختر المنتج واضغط «اطلبه الآن»، ونوصّل لكل المدن 🚚",
                'is_active' => true,
            ],
            [
                'name' => 'تعليقات: التوصيل',
                'match_type' => 'contains',
                'keywords' => ['توصيل', 'شحن', 'يوصل', 'توصلون', 'delivery'],
                'response_text' => 'نعم نوصّل لكل المدن 🚚 التفاصيل على الخاص',
                'private_reply_text' => "🚚 التوصيل داخل المدينة خلال 24 ساعة، وباقي المدن خلال 2 إلى 4 أيام.\nتوصيل مجاني للطلبات فوق 1000 د.ل 🎁\n\nاكتب «القائمة» لبدء طلبك.",
                'is_active' => true,
            ],
            [
                'name' => 'تعليقات: الضمان',
                'match_type' => 'contains',
                'keywords' => ['ضمان', 'كفالة', 'warranty'],
                'response_text' => 'كل أجهزتنا عليها ضمان سنة كاملة ✅',
                'private_reply_text' => "أهلاً! كل أجهزتنا عليها ضمان سنة ضد عيوب التصنيع ✅\n\nاكتب «صيانة» لمعرفة سياسة الضمان أو حجز موعد صيانة 🛠️",
                'is_active' => true,
            ],
            [
                'name' => 'تعليقات: شكر على أي تعليق',
                'match_type' => 'any',
                'keywords' => [],
                'response_text' => 'شكراً لتعليقك ❤️ لأي استفسار راسلنا على الخاص',
                'private_reply_text' => null,
                'is_active' => false,
            ],
        ];
    }

    public static function messages(): array
    {
        return [
            [
                'name' => 'رسائل: أوقات العمل',
                'match_type' => 'contains',
                'keywords' => ['الدوام', 'ساعات العمل', 'اوقات العمل', 'متى تفتحون', 'مفتوحين'],
                'response_text' => "🕘 أوقات العمل:\nمن السبت إلى الخميس، 9 صباحاً حتى 10 مساءً.\nالجمعة من 4 عصراً حتى 10 مساءً.",
            ],
            [
                'name' => 'رسائل: موقع المعرض',
                'match_type' => 'contains',
                'keywords' => ['الموقع', 'العنوان', 'وين مكانكم', 'وين المحل', 'location'],
                'response_text' => "📍 المعرض الرئيسي: شارع عمر المختار، طرابلس.\nونوصّل لكل المدن 🚚",
            ],
            [
                'name' => 'رسائل: أرقام التواصل',
                'match_type' => 'contains',
                'keywords' => ['رقمكم', 'واتساب', 'هاتف', 'اتصال', 'نتصل'],
                'response_text' => '📞 للتواصل: 0910000000 (هاتف وواتساب)',
            ],
            [
                'name' => 'رسائل: السعر',
                'match_type' => 'contains',
                'keywords' => self::PRICE_KEYWORDS,
                'response_text' => "💰 أسعارنا تبدأ من 110 د.ل للإكسسوارات، والهواتف من 1350 د.ل.\n\nاكتب «القائمة» واختر «تصفح المنتجات» لتشوف كل الأسعار 🛍️",
            ],
            [
                'name' => 'رسائل: الشكر',
                'match_type' => 'contains',
                'keywords' => ['شكرا', 'مشكور', 'يعطيك العافية', 'thanks'],
                'response_text' => 'العفو 🌟 نسعد بخدمتك دائماً!',
            ],
            [
                'name' => 'رسائل: أي رسالة أخرى',
                'match_type' => 'any',
                'keywords' => [],
                'response_text' => "أهلاً بك 👋\n\nاكتب «القائمة» لتصفح المنتجات والعروض، أو «موظف» للتحدث مع خدمة العملاء.",
            ],
        ];
    }
}
