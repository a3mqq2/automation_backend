# منصة أتمتة التعليقات وبوتات الماسنجر (Meta Automation Platform)

## نظرة عامة

منصة SaaS تتيح لأصحاب صفحات فيسبوك تسجيل الدخول بحساب فيسبوك (Social Login)،
اختيار الصفحات التي يريدون ربطها، ثم تفعيل أتمتة:

- ردود آلية على تعليقات المنشورات (بالكلمات المفتاحية).
- بوتات على ماسنجر (تدفقات رد آلي + ردود خاصة على التعليقات Private Reply).

المنصّة نفسها منتج تجاري: تُدار بواسطة **أدمن واحد** (صاحب المنصة)، والعملاء
(أصحاب الصفحات) يشتركون عبر **نظام مفاتيح تفعيل (License Keys)** يصدرها الأدمن يدوياً.

## الريبوهات (مشروعان منفصلان تماماً)

| الريبو | التقنية | الدور |
| --- | --- | --- |
| `meta-automation-api` | Laravel 13 + PHP 8.4 (API فقط، بدون Blade) | كل المنطق، قاعدة البيانات، التكامل مع Meta Graph API، Webhooks |
| `meta-automation-web` | Vue 3 + Vite + TypeScript + Pinia + Vue Router + Tailwind | واجهتان: واجهة العميل وواجهة الأدمن، تتواصل مع الـ API فقط عبر Axios |

الاتصال بين الاثنين: Laravel Sanctum (SPA token-based auth، ليس عبر جلسات كوكيز مشتركة النطاق
لتفادي تعقيد CORS/cookies بين نطاقين مختلفين لاحقاً في الإنتاج).

## بيئة العمل الحالية

- مشروع الـ API موجود محلياً في مجلد `automation_backend` (هو نفسه `meta-automation-api`).
- PHP 8.4 وComposer متاحان عبر Laravel Herd، والوصول إلى `packagist.org` يعمل،
  لذلك تُثبَّت حزم Composer مباشرة بـ `composer require` وتُشغَّل أوامر `php artisan` فعلياً.
- `npm`/`registry.npmjs.org` يعمل أيضاً، لذلك مشروع Vue يُبنى ويُثبَّت فعلياً.
- قاعدة البيانات MySQL (`automation_backend`)، والـ Queue والـ Cache والـ Session على driver `database`.

## نوعا المستخدمين في النظام

### 1. الأدمن (مستخدم واحد فقط، بلا نظام أدوار/صلاحيات متعدد)

- مصادقة منفصلة تماماً عن Socialite: تسجيل دخول باسم مستخدم/بريد + كلمة مرور عادية
  (جدول `admins`، أو حتى صف واحد ثابت — لا حاجة لتسجيل أدمن جدد من الواجهة).
- شاشات لوحة الأدمن:
  - **قائمة العملاء**: لكل عميل يظهر بريده/اسمه، حالة اشتراكه (فعّال/منتهي/بلا اشتراك)،
    تاريخ انتهاء الاشتراك، وعدد الصفحات المربوطة فعلياً.
  - **إدارة مفاتيح الاشتراك (License Keys)**: توليد مفتاح جديد بتاريخ انتهاء صلاحية
    يحدده الأدمن، وعرض جدول بكل المفاتيح مع: المفتاح نفسه، تاريخ الإصدار، تاريخ
    الانتهاء، هل استُخدم أم لا، وإن استُخدم فمن استخدمه (أي عميل) ومتى.
  - **إحصائيات عامة**: عدد العملاء الكلي، عدد الاشتراكات الفعّالة، عدد الصفحات
    المربوطة إجمالاً، عدد الردود الآلية المنفذة (من `activity_logs`)، عدد المفاتيح
    المصدرة/المستخدمة/المتبقية.

### 2. العميل (صاحب الصفحة)

- يسجّل دخول بحساب فيسبوك (Socialite) → يمنح صلاحيات الصفحات.
- **لا يستطيع استخدام أي ميزة أتمتة قبل تفعيل مفتاح اشتراك صالح** (Middleware
  `EnsureSubscriptionActive` أو ما شابه: يتحقق من وجود مفتاح مفعّل وغير منتهي
  الصلاحية مرتبط بحساب العميل قبل السماح بربط الصفحات أو إنشاء القواعد).
- بعد تسجيل الدخول لأول مرة (أو إن لم يكن مشتركاً): شاشة "أدخل مفتاح التفعيل".
- بعد التفعيل: يرى صفحاته على فيسبوك، يختار ما يريد ربطه، يدير قواعد الأتمتة
  وتدفقات البوت كما في خارطة الطريق الأصلية.

## الكيانات الأساسية في قاعدة البيانات

- `admins` — name, email (فريد), password, last_login_at. يُنشأ عبر `php artisan admin:create`.
- `users` (العملاء) — fb_user_id (فريد), name, email (nullable), avatar_url, fb_access_token (مشفّر),
  token_expires_at, last_login_at.
- `license_keys` — key (فريد، صيغة `XXXXX-XXXXX-XXXXX-XXXXX` بدون 0/O/1/I)، expires_at،
  is_used، used_by (→ users.id)، used_at، created_by (→ admins.id)، note.
- **الاشتراك (تم القرار)**: أعمدة على `users` وليس جدولاً منفصلاً: `subscription_expires_at`
  و`active_license_key_id`. تاريخ الاشتراكات محفوظ ضمنياً في `license_keys` (used_by/used_at).
  تفعيل مفتاح يجعل `subscription_expires_at = key.expires_at`، ويُرفض المفتاح إن كان لا يمدّد
  اشتراكاً فعّالاً قائماً.
- `facebook_pages` — user_id, page_id (معرّف فيسبوك), name, category, picture_url,
  page_access_token (مشفّر), is_connected, connected_at. فريد على (user_id, page_id)، ويُسمح
  بصف واحد فقط `is_connected = true` لكل page_id (يُفرض في `PageConnectionService`).
- في كل الجداول التابعة للصفحة، المفتاح الأجنبي اسمه **`facebook_page_id`** (→ facebook_pages.id)
  وليس `page_id`، لتفادي الخلط مع معرّف فيسبوك.
- `automation_rules` — facebook_page_id, name, trigger_type (comment/message), match_type
  (exact/contains/starts_with/any), keywords (json), response_text, private_reply_text
  (للتعليقات فقط)، is_active.
- `bot_flows` — facebook_page_id, name, flow_json, is_active.
- `conversations` — facebook_page_id, psid, bot_flow_id, bot_flow_version_id, current_node_id, awaiting,
  variables (json)، automation_paused_until، last_message_at.
- **الكتالوج**: `product_categories` و`product_brands` (name, image_url, sort_order, is_active لكل صفحة)،
  و`products` (category, brand, name, sku, price, currency, description, image_url, product_url, in_stock)،
  و`product_posts` (product_id ↔ post_id فريد) لربط المنتج بمنشور الإعلان.
- `activity_logs` — facebook_page_id, event_type (comment_reply/private_reply/message_reply/flow_step),
  status (success/failed), payload (json).

### صيغة `flow_json` (النسخة 2: عُقد وروابط)

```json
{
  "version": 2,
  "entry": { "triggers": ["القائمة"], "match_type": "exact", "start_node": "welcome", "locale": "ar" },
  "nodes": [
    { "id": "welcome", "type": "quick_replies", "position": { "x": 0, "y": 0 },
      "data": { "text": "كيف نساعدك؟", "image_url": null, "options": [{ "id": "o1", "label": "الأسعار" }] } },
    { "id": "prices", "type": "message", "position": { "x": 0, "y": 200 }, "data": { "text": "..." } },
    { "id": "done", "type": "end", "position": { "x": 0, "y": 400 }, "data": {} }
  ],
  "edges": [
    { "id": "e1", "source": "welcome", "source_handle": "o1", "target": "prices" },
    { "id": "e2", "source": "prices", "source_handle": "next", "target": "done" }
  ]
}
```

أنواع العقد: `message`, `quick_replies`, `buttons`, `cards`, `catalog`, `ask`, `condition`, `delay`,
`set_variable`, `jump`, `handoff`, `end`. الحدود: حتى 100 عقدة، 13 رد سريع، 3 أزرار، 10 بطاقات،
نص 2000 حرف، عنوان زر 20 حرفاً.

التدفقات القديمة (steps/start_step) تُحوَّل تلقائياً إلى هذه الصيغة عند القراءة (`FlowDefinitionConverter`).

**المسودة والنشر**: `bot_flows.flow_json` هي المسودة، والمحرك يشغّل `published_version` فقط. النشر ينشئ نسخة
مرقّمة في `bot_flow_versions`، والمحادثة الجارية تبقى على نسختها (`conversations.bot_flow_version_id`).
`FlowDefinitionValidator` يمنع النشر عند وجود خطأ (مَخرج غير موصول، عقدة غير قابلة للوصول، **حلقة بلا انتظار**،
تجاوز حدود ماسنجر)، والمحاكي `POST /api/bot-flows/{id}/simulate` يجرّب المسودة بلا إرسال لفيسبوك.

`image_url` اختياري لكل عقدة رسالة (رابط `https` فقط)، والعقدة يجب أن تحتوي نصاً أو صورة على الأقل. ماسنجر لا يقبل
نصاً وصورة في رسالة واحدة، لذلك يرسل المحرك الصورة ثم النص، والأزرار تُرفق دائماً بآخر رسالة.
الصور تُرفع عبر `POST /api/media` (قرص `public`، مجلد لكل عميلة) ويُحذف الملف بـ `DELETE /api/media`.

### عقدة `cards` (كاروسيل المنتجات)

تُرسل كـ Generic Template: حتى 10 بطاقات، لكل بطاقة `title` (≤80) و`subtitle` (≤80، مناسب للسعر)
و`image_url` و`buttons` (حتى 3، نوع `url` أو `next`). ويمكن إرفاق `options` (أزرار رد سريع) بنفس الرسالة.
كل زر `next` وكل خيار يصنع مَخرجاً في اللوحة، بينما أزرار الروابط لا مخارج لها. تكرار عنوان الزر بين البطاقات
مسموح (مثل «اطلبها الان» في كل بطاقة)، وعند كتابة العميل للعنوان نصاً يُؤخذ أول تطابق.

**متغيرات العميل**: عند تشغيل أول تدفق لمحادثة جديدة يجلب المحرك اسم العميل من ماسنجر مرة واحدة ويحفظه في
`conversations.variables`، فتعمل `{{first_name}}` و`{{last_name}}` داخل النصوص وعناوين البطاقات.

### عقدة `catalog` (كتالوج ديناميكي)

عقدة واحدة تعرض الأقسام أو الشركات أو المنتجات من قاعدة البيانات بدل كتابتها يدوياً:
`data.source` = `categories|brands|products`، و`limit` (حتى 10)، و`select_label` و`link_label` و`more_label`.
المخارج: `selected` (إلزامي) و`empty` و`fallback`. اختيار العميل يُحفظ تلقائياً في `category_id` أو `brand_id`
أو `product_id`، والمستوى الأدنى يُرشَّح بما اختاره في المستوى الأعلى، واختيار قسم جديد يمسح الاختيارات الأعمق.
الترقيم عبر زر «المزيد» بحمولة `more:{offset}`.

**متغيرات المنتج**: `{{product.name}}` و`{{product.price}}` و`{{product.currency}}` و`{{product.url}}` وغيرها
تعمل في نصوص التدفق (من `product_id` في المحادثة) **وفي قواعد التعليقات** (يُستنتج المنتج من `post_id` للمنشور).
قاعدة تعليقات نصّها يستخدم متغيرات منتج تُتجاهَل على المنشورات غير المرتبطة بمنتج.

**لغة البوت**: `entry.locale` في تعريف التدفق (الافتراضي `ar`) تحدد لغة النصوص الافتراضية مثل «اختيار» و«المزيد»
و«السعر»، لأن طلبات Meta لا تحمل هيدر لغة.

## صيغة الأخطاء واللغة

- كل خطأ من الـ API يعود بالشكل `{ "code": "subscription.inactive", "message": "..." }`،
  وأخطاء التحقق تضيف `errors` بالشكل المعتاد في Laravel. الرموز معرّفة في `App\Enums\ErrorCode`.
- الرسالة تُترجم حسب هيدر `Accept-Language` (ar/en، الافتراضي en)، والواجهة يمكنها الاعتماد
  على `code` للترجمة الخاصة بها. ملفات الترجمة في `lang/ar` و`lang/en` ويجب أن تبقى مفاتيحهما متطابقة
  (يتحقق من ذلك `LocalizationTest`).
- القوائم تدعم `page`, `per_page` (حتى 100), `sort`, `direction`, `search` + فلاتر خاصة بكل مورد،
  وتعود بصيغة Laravel المعتادة `{ data, links, meta }`.

## نقاط الـ API

### مصادقة العميل
- `GET /api/auth/facebook/redirect` → `{ data: { url } }` (رابط فيسبوك مع state محفوظ في الكاش)
- `GET /api/auth/facebook/callback?code&state` → `{ token, user }` (الـ redirect_uri صفحة في الواجهة
  تمرّر code/state لهذا الـ endpoint)
- `POST /api/auth/license-key/activate` — `POST /api/auth/logout` — `GET /api/me`
  (هذه الثلاثة لا تتطلب اشتراكاً فعّالاً)

### الأدمن
- `POST /api/admin/login` — `POST /api/admin/logout` — `GET /api/admin/me`
- `GET /api/admin/clients` — `GET /api/admin/clients/{id}`
- `GET|POST /api/admin/license-keys` — `GET|DELETE /api/admin/license-keys/{id}` (الحذف للمفاتيح غير المستخدمة فقط)
- `GET /api/admin/stats`
- `GET /api/admin/activity-logs` — `GET /api/admin/activity-logs/{id}`

### العميل (Sanctum + اشتراك فعّال)
- `GET /api/pages` (صفحات فيسبوك من Graph API مع حالة الربط)
- `GET /api/pages/connected` — `GET /api/pages/{pageId}`
- `POST /api/pages/{pageId}/connect` (يشترك التطبيق في webhooks الصفحة) — `DELETE /api/pages/{pageId}`
- `GET|POST /api/rules` — `GET|PUT|PATCH|DELETE /api/rules/{id}`
- `GET|POST /api/bot-flows` — `GET|PUT|PATCH|DELETE /api/bot-flows/{id}`
- `POST /api/media` (رفع صورة لخطوات البوت) — `DELETE /api/media` (بالمسار الراجع من الرفع)
- `GET|POST /api/product-categories` — `GET|PUT|DELETE /api/product-categories/{id}` (ومثلها `product-brands`)
- `GET|POST /api/products` — `GET|PUT|DELETE /api/products/{id}`
- `POST|DELETE /api/products/{id}/posts` (ربط المنتج بمنشور أو فك الربط)
- `GET /api/pages/{pageId}/posts` (آخر منشورات الصفحة من Graph مع المنتج المرتبط بكل منشور)
- `POST /api/bot-flows/{id}/publish` — `POST /api/bot-flows/{id}/simulate`
- `GET /api/bot-flows/{id}/versions` — `POST /api/bot-flows/{id}/versions/{version}/restore`
- `GET /api/activity-logs` — `GET /api/activity-logs/{id}`

### Webhook واستدعاءات الحساب (بدون مصادقة Sanctum، توقيع Meta فقط)
- `GET /api/webhook` (تحقق hub.challenge مقابل `META_WEBHOOK_VERIFY_TOKEN`)
- `POST /api/webhook` (يتحقق من `X-Hub-Signature-256`، يرد فوراً `EVENT_RECEIVED`، والمعالجة في
  `ProcessMetaWebhook` ثم `HandleCommentEvent` / `HandleMessagingEvent`)
- `POST /api/facebook/deauthorize` (يفصل صفحات العميل ويمسح توكناته عند حذفه للتطبيق)
- `POST /api/facebook/data-deletion` — `GET /api/facebook/data-deletion/status?code=` (حذف بيانات العميل
  ورمز تأكيد كما تشترط Meta؛ كلاهما يتحقق من `signed_request`)

**مسار احتياطي للتعليقات**: إن لم تُرسل Meta أحداث `feed` (شائع قبل نشر التطبيق ومراجعته)، يُفعَّل
`META_COMMENT_POLLING=true` فيسحب `comments:poll` (مجدول كل دقيقة) التعليقات الجديدة من Graph API
ويمرّرها على نفس `HandleCommentEvent`. لكل صفحة علامة زمنية `comments_polled_at`، وأول تشغيل لا يرد
على التعليقات القديمة، ومنع التكرار مشترك مع مسار الـ webhook فلا يتكرر الرد.

**تسجيل الـ webhook في تطبيق Meta**: `php artisan webhook:subscribe` (و`webhook:status` للعرض).
ربط الصفحة من الـ API يشترك في أحداثها تلقائياً، لكن رابط الاستدعاء على مستوى التطبيق يجب تسجيله مرة واحدة،
وإعادة تسجيله كلما تغيّر `APP_URL` (مثل روابط الأنفاق المؤقتة) وإلا لن تصل أي أحداث.

## معايير الكود والتصميم (إلزامية لكل الريبوهين)

### الكود
- بنية Clean Architecture: كل مسؤولية في كلاس منفصل (Controllers نحيفة، المنطق في
  Services، لا منطق أعمال داخل الـ Controller أو الـ Component مباشرة).
- بدون أي تعليقات في الكود إطلاقاً (لا inline ولا block ولا doc comments) — الكود
  نفسه يجب أن يكون واضحاً بأسماء دالة ومعبّرة بدل الشرح.
- PSR-12 في كل كود PHP.
- بدون `alert()` أو `confirm()` من جافاسكربت أبداً — التأكيدات والتنبيهات في الواجهة
  تكون عبر Modals مخصصة (أو SweetAlert2/Toasts)، أبداً حوارات المتصفح الافتراضية.

### تعدد اللغات (إلزامي)
- الواجهة (`meta-automation-web`) تدعم **العربية والإنجليزية** بالكامل، مع تبديل لغة
  واضح للمستخدم (وحفظ تفضيله، كما في Dark Mode).
- كل النصوص عبر i18n (مثل `vue-i18n`) في ملفات ترجمة منفصلة (`ar.json` / `en.json`)،
  بدون أي نص مكتوب مباشرة (hardcoded) داخل الكومبوننتات.
- التخطيط يدعم **RTL** تلقائياً عند اختيار العربية و**LTR** عند اختيار الإنجليزية
  (اتجاه الصفحة، محاذاة النصوص، ترتيب الأيقونات وHeader bar، كل ذلك ينعكس تلقائياً
  عبر `dir="rtl"`/`dir="ltr"` وليس بتصميم مضاعف لكل لغة).
- رسائل الـ API (من `meta-automation-api`) القابلة للعرض للمستخدم (كرسائل الأخطاء
  والتحقق من الاشتراك) يجب أن تُبنى بشكل يسمح بترجمتها من الواجهة، وليس كنص عربي
  ثابت مرسل من الباك-إند فقط — الأفضل: الباك-إند يرسل رمز/مفتاح رسالة (مثل
  `error.subscription_inactive`) والواجهة تترجمه، أو كحل انتقالي أبسط يرسل نصاً
  بلغتين حسب هيدر `Accept-Language` القادم من الطلب.

### تصميم الواجهة (Vue)
- بدون تدرّجات لونية (gradients)، بدون ألوان نيون، بدون glassmorphism، بدون أنيميشن ثقيل.
- ألوان صلبة محايدة، تباين عالٍ، خط واضح. لون أساسي واحد + لون خطر واحد + لون نجاح واحد فقط.
- Dark Mode إلزامي في كل صفحة: لا ألوان مكتوبة مباشرة في HTML/CSS، بل عبر CSS variables:
  `--bg-primary` `--bg-secondary` `--text-primary` `--text-secondary` `--border-color`
  `--card-bg` `--table-bg` `--input-bg`. يدعم تفضيل النظام (`prefers-color-scheme`)
  + تبديل يدوي + حفظ تفضيل المستخدم. يمنع النص الرمادي على رمادي أو أي تباين ضعيف.
- Layout قائم على Grid، تباعد متسق، بدون عناصر عائمة.
- الأداء أولوية على التأثيرات البصرية؛ Lazy load حين يكون ممكناً.
- توافق إمكانية الوصول (WCAG contrast)، ودعم كامل للتنقل بلوحة المفاتيح.
- أيقونات بسيطة، خطوط النظام (system fonts) مفضّلة.
- نظام تصميم واحد فقط عبر كل الصفحات، بدون تخصيص شكل مختلف لكل صفحة.
- القاعدة الحاسمة: أي عنصر واجهة يبدو "زخرفياً محضاً" فهو خطأ — هذا نظام تشغيلي
  (Operational Software) يُصمَّم لساعات عمل طويلة، وليس معرض تصميم.

### الجداول والنماذج
- الجداول: فرز (Sorting)، ترقيم صفحات (Pagination)، رأس ثابت (Sticky header).
- **ممنوع عمود "Actions" داخل الجداول تماماً**، وممنوع أي أزرار إجراءات داخل الصف.
- **النقر في أي مكان من الصف يفتح صفحة التفاصيل الخاصة بذلك السجل** مباشرة.
- كل الإجراءات الأساسية (تعديل، حذف، رجوع، المزيد) توضع في **شريط علوي (header bar)
  داخل صفحة التفاصيل** فقط، وليس في الجدول.
- الصف يظهر عليه hover وfocus state واضحان، والمؤشر (cursor) يتحول لـ pointer عند
  المرور فوقه. بدون عناصر قابلة للنقر متداخلة داخل الصف (nested clickable elements).
- النماذج: تسميات (labels) واضحة دائماً، ممنوع الاعتماد على placeholder فقط كتسمية للحقل.

## قواعد عمل عامة يجب اتباعها

- كل توكن (fb_access_token, page_access_token) يُخزَّن مشفّراً (`encrypted` cast في Laravel).
- أي endpoint خاص بالعميل يتحقق أولاً أن الاشتراك فعّال (middleware مخصص)، ما عدا
  تفعيل المفتاح نفسه وتسجيل الدخول.
- معالجة أحداث Webhook تتم دائماً داخل Queue Job، والرد على طلب الـ Webhook نفسه
  يكون فوري (200) قبل أي معالجة فعلية.
- توليد مفاتيح الاشتراك: مفتاح عشوائي آمن (مثل `Str::random(20)` أو UUID مقسّم
  بشرطات لسهولة القراءة/الكتابة اليدوية)، فريد، غير قابل للتخمين.
- لا يوجد نظام أدوار/صلاحيات متعدد المستويات للأدمن — تصميم بسيط لمستخدم واحد فقط.