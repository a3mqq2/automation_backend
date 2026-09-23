<?php

namespace App\Services\Demo;

use App\Models\FacebookPage;
use App\Models\Product;

final class ElectronicsFlowBlueprints
{
    public const MAIN_MENU = 'المتجر: القائمة الرئيسية';

    public const ORDER_TRACKING = 'تتبع الطلبات';

    public const MAINTENANCE = 'الصيانة والضمان';

    public const HUMAN_HANDOFF = 'التحويل لموظف';

    public const MAIN_MENU_TRIGGERS = ['القائمة', 'قائمة', 'مرحبا', 'السلام عليكم', 'هلا', 'ابدأ', 'menu', 'start', 'hi', 'hello'];

    public const ORDER_TRACKING_TRIGGERS = ['تتبع', 'تتبع طلبي', 'طلبي', 'وين طلبي'];

    public const MAINTENANCE_TRIGGERS = ['صيانة', 'ضمان', 'تصليح', 'عطل'];

    public const HUMAN_HANDOFF_TRIGGERS = ['موظف', 'خدمة العملاء', 'agent'];

    private const HANDOFF_MINUTES = 30;

    public static function mainMenu(FacebookPage $page, array $offerProducts, int $trackingFlowId, int $maintenanceFlowId): array
    {
        $flow = (new FlowBlueprint(self::MAIN_MENU_TRIGGERS, 'welcome'))
            ->node('welcome', 'quick_replies', 3, 0, [
                'text' => "أهلاً {{first_name}} 👋\nمرحباً بك في {$page->name} 📱💻\n\nأحدث الهواتف واللابتوبات والسماعات بضمان سنة وتوصيل لكل المدن.\nكيف نقدر نساعدك اليوم؟",
                'image_url' => null,
                'options' => [
                    ['id' => 'o_browse', 'label' => '🛍️ تصفح المنتجات'],
                    ['id' => 'o_offers', 'label' => '🔥 عروض الأسبوع'],
                    ['id' => 'o_track', 'label' => '📦 تتبع طلبي'],
                    ['id' => 'o_maint', 'label' => '🛠️ الصيانة والضمان'],
                    ['id' => 'o_delivery', 'label' => '🚚 التوصيل والدفع'],
                    ['id' => 'o_agent', 'label' => '👤 موظف خدمة العملاء'],
                ],
            ])
            ->node('menu_hint', 'message', 6, 0, [
                'text' => 'اختر من الأزرار بالأسفل 👇 أو اكتب «موظف» للتحدث مع خدمة العملاء.',
            ])
            ->node('cat_list', 'catalog', 0, 1, [
                'text' => 'اختر القسم الذي يهمك 👇',
                'source' => 'categories',
                'limit' => 10,
                'select_label' => 'عرض القسم',
                'link_label' => '',
                'more_label' => 'أقسام أخرى',
            ])
            ->node('brand_list', 'catalog', 0, 2, [
                'text' => 'اختر الماركة 👇',
                'source' => 'brands',
                'limit' => 10,
                'select_label' => 'عرض المنتجات',
                'link_label' => '',
                'more_label' => 'ماركات أخرى',
            ])
            ->node('product_list', 'catalog', 0, 3, [
                'text' => 'هذه المنتجات المتوفرة حالياً 👇',
                'source' => 'products',
                'limit' => 10,
                'select_label' => 'التفاصيل والطلب',
                'link_label' => 'صفحة المنتج',
                'more_label' => 'منتجات أخرى',
            ])
            ->node('no_products', 'message', 1, 2, [
                'text' => 'لا توجد منتجات متاحة هنا حالياً، جرّب قسماً آخر 🙏',
            ])
            ->node('product_detail', 'quick_replies', 0, 4, [
                'text' => "📱 {{product.name}}\n💰 السعر: {{product.price}} {{product.currency}}\n\n{{product.description}}\n\n✅ ضمان سنة كاملة\n🚚 توصيل لكل المدن",
                'image_url' => null,
                'options' => [
                    ['id' => 'od_order', 'label' => '🛒 اطلبه الآن'],
                    ['id' => 'od_more', 'label' => '↩️ منتجات أخرى'],
                    ['id' => 'od_menu', 'label' => '🏠 القائمة الرئيسية'],
                ],
            ])
            ->node('offers', 'cards', 2, 1, [
                'elements' => array_map(fn (Product $product, int $index) => [
                    'title' => $product->name,
                    'subtitle' => '🔥 عرض الأسبوع: '.$product->formattedPrice().' د.ل فقط',
                    'image_url' => $product->image_url,
                    'buttons' => [
                        ['id' => 'offer_'.($index + 1).'_order', 'label' => 'اطلبها الآن', 'type' => 'next'],
                        ['id' => 'offer_'.($index + 1).'_link', 'label' => 'التفاصيل', 'type' => 'url', 'url' => $product->product_url],
                    ],
                ], $offerProducts, array_keys($offerProducts)),
                'options' => [
                    ['id' => 'offers_menu', 'label' => '🏠 القائمة الرئيسية'],
                ],
            ])
            ->node('order_check', 'condition', 1.5, 5, [
                'variable' => 'phone',
                'operator' => 'is_set',
                'value' => '',
            ])
            ->node('order_returning', 'buttons', 2.5, 6, [
                'text' => "أهلاً مجدداً {{customer_name}} 😊\nهل نرسل طلب {{product.name}} على نفس البيانات؟\n\n📞 {{phone}}\n📍 {{city}}",
                'buttons' => [
                    ['id' => 'b_same', 'label' => 'نعم، نفس البيانات', 'type' => 'next'],
                    ['id' => 'b_new', 'label' => 'بيانات جديدة', 'type' => 'next'],
                ],
            ])
            ->node('ask_name', 'ask', 0.5, 6, [
                'text' => "ممتاز! 🎉 لإتمام طلب {{product.name}} نحتاج بعض البيانات.\n\nما اسمك الكامل؟",
                'variable' => 'customer_name',
                'expects' => 'text',
                'retries' => 1,
            ])
            ->node('ask_phone', 'ask', 0.5, 7, [
                'text' => 'شكراً {{customer_name}} 🙏 ما رقم هاتفك للتواصل؟',
                'variable' => 'phone',
                'expects' => 'phone',
                'retries' => 2,
                'retry_text' => 'الرقم غير صحيح ❌ اكتب رقم هاتف صحيح مثل 0912345678',
            ])
            ->node('phone_failed', 'message', -0.5, 8, [
                'text' => 'لم نتمكن من تسجيل الرقم. سيتواصل معك أحد موظفينا لإكمال الطلب 🙏',
            ])
            ->node('ask_city', 'ask', 0.5, 8, [
                'text' => 'في أي مدينة تريد التوصيل؟ 📍',
                'variable' => 'city',
                'expects' => 'text',
                'retries' => 1,
            ])
            ->node('order_summary', 'buttons', 1.5, 9, [
                'text' => "📋 ملخص طلبك:\n\n🛒 المنتج: {{product.name}}\n💰 السعر: {{product.price}} {{product.currency}}\n👤 الاسم: {{customer_name}}\n📞 الهاتف: {{phone}}\n📍 المدينة: {{city}}\n\nهل تؤكد الطلب؟",
                'buttons' => [
                    ['id' => 'b_confirm', 'label' => '✅ تأكيد الطلب', 'type' => 'next'],
                    ['id' => 'b_cancel', 'label' => '❌ إلغاء', 'type' => 'next'],
                ],
            ])
            ->node('save_order', 'set_variable', 1, 10, [
                'variable' => 'last_order',
                'value' => '{{product.name}}',
            ])
            ->node('order_done', 'message', 1, 11, [
                'text' => "تم استلام طلبك بنجاح ✅\n\nسيتصل بك فريقنا خلال ساعة لتأكيد موعد التوصيل 🚚\nشكراً لثقتك بنا {{customer_name}} 🌟",
            ])
            ->node('order_cancelled', 'message', 2, 10, [
                'text' => 'تم إلغاء الطلب. نحن هنا متى احتجتنا 😊',
            ])
            ->node('upsell_pause', 'delay', 1, 12, [
                'seconds' => 4,
            ])
            ->node('anything_else', 'quick_replies', 2, 13, [
                'text' => 'هل تحتاج شيئاً آخر؟',
                'image_url' => null,
                'options' => [
                    ['id' => 'ae_browse', 'label' => '🛍️ تصفح المنتجات'],
                    ['id' => 'ae_menu', 'label' => '🏠 القائمة الرئيسية'],
                    ['id' => 'ae_bye', 'label' => '👋 لا، شكراً'],
                ],
            ])
            ->node('goodbye', 'message', 2, 14, [
                'text' => 'شكراً لتواصلك معنا {{first_name}} 🌟 نسعد بخدمتك دائماً.',
            ])
            ->node('done', 'end', 2, 15)
            ->node('delivery_info', 'message', 4, 1, [
                'text' => "🚚 التوصيل والدفع\n\n• داخل المدينة: خلال 24 ساعة (10 د.ل)\n• باقي المدن: خلال 2 إلى 4 أيام (20 د.ل)\n• توصيل مجاني للطلبات فوق 1000 د.ل\n\n💳 الدفع: نقداً عند الاستلام، أو تحويل مصرفي، أو بطاقة.",
            ])
            ->node('go_tracking', 'jump', 5, 1, [
                'bot_flow_id' => $trackingFlowId,
            ])
            ->node('go_maintenance', 'jump', 6, 1, [
                'bot_flow_id' => $maintenanceFlowId,
            ])
            ->node('agent', 'handoff', 7, 1, [
                'text' => 'تم تحويلك إلى أحد موظفي خدمة العملاء 👤 سيرد عليك خلال دقائق.',
                'pause_minutes' => self::HANDOFF_MINUTES,
            ])
            ->edge('welcome', 'o_browse', 'cat_list')
            ->edge('welcome', 'o_offers', 'offers')
            ->edge('welcome', 'o_track', 'go_tracking')
            ->edge('welcome', 'o_maint', 'go_maintenance')
            ->edge('welcome', 'o_delivery', 'delivery_info')
            ->edge('welcome', 'o_agent', 'agent')
            ->edge('welcome', 'fallback', 'menu_hint')
            ->edge('menu_hint', 'next', 'welcome')
            ->edge('cat_list', 'selected', 'brand_list')
            ->edge('cat_list', 'empty', 'no_products')
            ->edge('brand_list', 'selected', 'product_list')
            ->edge('brand_list', 'empty', 'product_list')
            ->edge('product_list', 'selected', 'product_detail')
            ->edge('product_list', 'empty', 'no_products')
            ->edge('no_products', 'next', 'welcome')
            ->edge('product_detail', 'od_order', 'order_check')
            ->edge('product_detail', 'od_more', 'product_list')
            ->edge('product_detail', 'od_menu', 'welcome')
            ->edge('offers', 'offers_menu', 'welcome');

        foreach ($offerProducts as $index => $product) {
            $number = $index + 1;
            $flow->node("set_offer_{$number}", 'set_variable', 2 + ($number - 2) * 0.8, 3.5, [
                'variable' => 'product_id',
                'value' => (string) $product->id,
            ])
                ->edge('offers', "offer_{$number}_order", "set_offer_{$number}")
                ->edge("set_offer_{$number}", 'next', 'order_check');
        }

        return $flow
            ->edge('order_check', 'true', 'order_returning')
            ->edge('order_check', 'false', 'ask_name')
            ->edge('order_returning', 'b_same', 'order_summary')
            ->edge('order_returning', 'b_new', 'ask_name')
            ->edge('ask_name', 'success', 'ask_phone')
            ->edge('ask_phone', 'success', 'ask_city')
            ->edge('ask_phone', 'failure', 'phone_failed')
            ->edge('phone_failed', 'next', 'agent')
            ->edge('ask_city', 'success', 'order_summary')
            ->edge('order_summary', 'b_confirm', 'save_order')
            ->edge('order_summary', 'b_cancel', 'order_cancelled')
            ->edge('save_order', 'next', 'order_done')
            ->edge('order_done', 'next', 'upsell_pause')
            ->edge('upsell_pause', 'next', 'anything_else')
            ->edge('order_cancelled', 'next', 'anything_else')
            ->edge('delivery_info', 'next', 'anything_else')
            ->edge('anything_else', 'ae_browse', 'cat_list')
            ->edge('anything_else', 'ae_menu', 'welcome')
            ->edge('anything_else', 'ae_bye', 'goodbye')
            ->edge('goodbye', 'next', 'done')
            ->toArray();
    }

    public static function orderTracking(int $mainMenuFlowId): array
    {
        return (new FlowBlueprint(self::ORDER_TRACKING_TRIGGERS, 'track_ask'))
            ->node('track_ask', 'ask', 0, 0, [
                'text' => 'اكتب رقم طلبك 🔎 (مثال: 1045)',
                'variable' => 'order_number',
                'expects' => 'number',
                'retries' => 1,
                'retry_text' => 'رقم الطلب يتكون من أرقام فقط، حاول مجدداً.',
            ])
            ->node('track_status', 'quick_replies', 0, 1, [
                'text' => "📦 الطلب رقم {{order_number}}\n\nالحالة: قيد التجهيز ✅\nالتوصيل المتوقع: خلال 24 إلى 48 ساعة 🚚\n\nسيتصل بك المندوب قبل الوصول.",
                'image_url' => null,
                'options' => [
                    ['id' => 't_menu', 'label' => '🏠 القائمة الرئيسية'],
                    ['id' => 't_agent', 'label' => '👤 موظف'],
                ],
            ])
            ->node('track_help', 'message', 1, 1, [
                'text' => 'لم نتعرف على رقم الطلب. سنحوّلك لموظف لمساعدتك 🙏',
            ])
            ->node('track_to_menu', 'jump', -0.5, 2, [
                'bot_flow_id' => $mainMenuFlowId,
            ])
            ->node('track_agent', 'handoff', 0.5, 2, [
                'text' => 'تم تحويلك إلى أحد موظفي خدمة العملاء 👤 سيرد عليك خلال دقائق.',
                'pause_minutes' => self::HANDOFF_MINUTES,
            ])
            ->edge('track_ask', 'success', 'track_status')
            ->edge('track_ask', 'failure', 'track_help')
            ->edge('track_status', 't_menu', 'track_to_menu')
            ->edge('track_status', 't_agent', 'track_agent')
            ->edge('track_help', 'next', 'track_agent')
            ->toArray();
    }

    public static function maintenance(int $mainMenuFlowId): array
    {
        return (new FlowBlueprint(self::MAINTENANCE_TRIGGERS, 'maint_intro'))
            ->node('maint_intro', 'buttons', 1, 0, [
                'text' => "🛠️ الصيانة والضمان\n\nكل أجهزتنا عليها ضمان سنة ضد عيوب التصنيع، ومركز الصيانة يستقبل الأجهزة من السبت إلى الخميس.",
                'buttons' => [
                    ['id' => 'm_book', 'label' => 'حجز موعد صيانة', 'type' => 'next'],
                    ['id' => 'm_policy', 'label' => 'سياسة الضمان', 'type' => 'next'],
                    ['id' => 'm_menu', 'label' => 'القائمة الرئيسية', 'type' => 'next'],
                ],
            ])
            ->node('maint_policy', 'message', 2, 1, [
                'text' => "📄 سياسة الضمان\n\n• سنة كاملة على كل الأجهزة\n• استبدال خلال 7 أيام عند وجود عيب مصنعي\n• الضمان لا يشمل الكسر أو دخول السوائل",
            ])
            ->node('maint_after', 'quick_replies', 2, 2, [
                'text' => 'هل تريد حجز موعد صيانة؟',
                'image_url' => null,
                'options' => [
                    ['id' => 'ma_book', 'label' => '🛠️ حجز موعد'],
                    ['id' => 'ma_menu', 'label' => '🏠 القائمة الرئيسية'],
                ],
            ])
            ->node('maint_device', 'ask', 0, 2, [
                'text' => 'ما نوع الجهاز وموديله؟ 📱 (مثال: iPhone 15 Pro)',
                'variable' => 'device',
                'expects' => 'text',
                'retries' => 1,
            ])
            ->node('maint_issue', 'ask', 0, 3, [
                'text' => 'صف المشكلة باختصار 📝',
                'variable' => 'issue',
                'expects' => 'text',
                'retries' => 1,
            ])
            ->node('maint_phone_check', 'condition', 0, 4, [
                'variable' => 'phone',
                'operator' => 'is_set',
                'value' => '',
            ])
            ->node('maint_phone', 'ask', 1, 5, [
                'text' => 'ما رقم هاتفك ليتواصل معك الفني؟ 📞',
                'variable' => 'phone',
                'expects' => 'phone',
                'retries' => 2,
                'retry_text' => 'الرقم غير صحيح ❌ اكتب رقم هاتف صحيح مثل 0912345678',
            ])
            ->node('maint_confirm', 'message', 0, 6, [
                'text' => "تم حجز طلب الصيانة ✅\n\n📱 الجهاز: {{device}}\n📝 المشكلة: {{issue}}\n📞 الهاتف: {{phone}}\n\nسيتواصل معك فني الصيانة خلال يوم عمل.",
            ])
            ->node('maint_end', 'end', 0, 7)
            ->node('maint_agent', 'handoff', 2, 6, [
                'text' => 'سيتواصل معك أحد موظفينا لإكمال حجز الصيانة 🙏',
                'pause_minutes' => self::HANDOFF_MINUTES,
            ])
            ->node('maint_to_menu', 'jump', 3, 3, [
                'bot_flow_id' => $mainMenuFlowId,
            ])
            ->edge('maint_intro', 'm_book', 'maint_device')
            ->edge('maint_intro', 'm_policy', 'maint_policy')
            ->edge('maint_intro', 'm_menu', 'maint_to_menu')
            ->edge('maint_policy', 'next', 'maint_after')
            ->edge('maint_after', 'ma_book', 'maint_device')
            ->edge('maint_after', 'ma_menu', 'maint_to_menu')
            ->edge('maint_device', 'success', 'maint_issue')
            ->edge('maint_issue', 'success', 'maint_phone_check')
            ->edge('maint_phone_check', 'true', 'maint_confirm')
            ->edge('maint_phone_check', 'false', 'maint_phone')
            ->edge('maint_phone', 'success', 'maint_confirm')
            ->edge('maint_phone', 'failure', 'maint_agent')
            ->edge('maint_confirm', 'next', 'maint_end')
            ->toArray();
    }

    public static function humanHandoff(): array
    {
        return (new FlowBlueprint(self::HUMAN_HANDOFF_TRIGGERS, 'handoff'))
            ->node('handoff', 'handoff', 0, 0, [
                'text' => 'تم تحويلك إلى أحد موظفي خدمة العملاء 👤 سيرد عليك خلال دقائق.',
                'pause_minutes' => self::HANDOFF_MINUTES,
            ])
            ->toArray();
    }
}
