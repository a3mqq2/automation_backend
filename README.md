# meta-automation-api

واجهة برمجية (Laravel 13، API فقط) لمنصة أتمتة ردود تعليقات فيسبوك وبوتات ماسنجر.
التفاصيل المعمارية والقواعد في [CLAUDE.md](CLAUDE.md).

- مرجع الـ API للواجهة: [docs/API.md](docs/API.md)
- مجموعة Postman: [docs/postman/Meta-Automation-API.postman_collection.json](docs/postman/Meta-Automation-API.postman_collection.json)

## المتطلبات

- PHP 8.3+ (مُختبر على 8.4)، Composer
- MySQL 8 (أو SQLite للاختبارات)
- تطبيق Meta (Facebook App) مع منتجَي Facebook Login وMessenger/Webhooks

## الإعداد

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan admin:create owner@example.com --name="Owner"
```

`storage:link` ضروري لأن صور خطوات البوت تُخدَم من `public/storage`، وفيسبوك يجب أن يصل إليها عبر رابط عام
مبني على `APP_URL`.

متغيرات `.env` الخاصة بالمنصة:

| المتغير | الوصف |
| --- | --- |
| `FRONTEND_URL` | رابط واجهة Vue (يُستخدم في CORS)، يمكن فصل أكثر من رابط بفاصلة |
| `FACEBOOK_CLIENT_ID` / `FACEBOOK_CLIENT_SECRET` | App ID وApp Secret (الـ secret يُستخدم أيضاً للتحقق من توقيع الـ webhook) |
| `FACEBOOK_REDIRECT_URI` | صفحة الـ callback في الواجهة، مثل `https://app.example.com/auth/facebook/callback` |
| `META_GRAPH_VERSION` | إصدار Graph API (الافتراضي `v23.0`) |
| `META_WEBHOOK_VERIFY_TOKEN` | النص السري الذي يُدخل في إعدادات Webhooks في لوحة Meta |
| `META_LOG_WEBHOOK_PAYLOADS` | `true` لتسجيل كل حمولة تصل من Meta في `storage/logs/laravel.log` (للتشخيص فقط) |
| `META_COMMENT_POLLING` | `true` لتفعيل سحب التعليقات دورياً من Graph API كبديل عن webhook حقل `feed` |
| `META_MEDIA_MAX_KILOBYTES` | الحجم الأقصى لصور خطوات البوت (الافتراضي 8192 = 8 ميجابايت) |
| `META_MEDIA_DISK` | قرص تخزين الصور (الافتراضي `public`) |
| `SANCTUM_TOKEN_EXPIRATION_MINUTES` | مدة صلاحية توكن الدخول (الافتراضي 30 يوماً) |

## التشغيل

```bash
php artisan serve
php artisan queue:work --tries=1
php artisan schedule:work
```

- **الـ Queue إلزامي**: الـ webhook يرد فوراً ويضع كل المعالجة في الطابور، فبدون `queue:work`
  لن تُرسل أي ردود آلية. في الإنتاج شغّله عبر Supervisor.
- الـ Scheduler يحذف التوكنات المنتهية وسجلات الـ jobs الفاشلة القديمة يومياً، ويشغّل سحب التعليقات كل دقيقة
  عند تفعيل `META_COMMENT_POLLING`.

### سحب التعليقات (بديل عن webhook حقل `feed`)

Meta قد لا ترسل أحداث `feed` قبل نشر التطبيق ومراجعة صلاحياته، بينما تصل أحداث الرسائل بشكل طبيعي.
لهذا يوجد مسار احتياطي يقرأ التعليقات الجديدة من Graph API ويمرّرها على **نفس** محرك الأتمتة
(نفس القواعد، ونفس منع التكرار، ونفس سجل النشاط):

```bash
php artisan comments:poll          # سحب فوري
php artisan comments:poll --force  # حتى لو كان الخيار معطّلاً
```

- يعمل فقط للصفحات المربوطة التي لديها قاعدة تعليقات فعّالة.
- أول تشغيل لكل صفحة يضبط علامة زمنية فقط ولا يرد على التعليقات القديمة.
- عند توفر webhook `feed` يمكن إطفاء الخيار؛ المساران آمنان معاً لأن منع التكرار مشترك.

## ربط Meta

1. سجّل رابط الـ webhook في تطبيق Meta بأمر واحد (يقرأ `APP_URL` و`META_WEBHOOK_VERIFY_TOKEN` من `.env`):

   ```bash
   php artisan webhook:subscribe
   php artisan webhook:status
   ```

   `webhook:subscribe --url=https://...` لتجاوز `APP_URL`. الرابط يجب أن يكون متاحاً عبر HTTPS لأن Meta
   تستدعيه للتحقق قبل قبوله. **أعد تشغيل الأمر كلما تغيّر رابط النفق أو النطاق.**
   البديل يدوياً: لوحة التطبيق → Webhooks → Page.
2. أضف رابط `FACEBOOK_REDIRECT_URI` إلى Valid OAuth Redirect URIs.
3. أضف رابطَي `/api/facebook/deauthorize` و`/api/facebook/data-deletion` في إعدادات تسجيل دخول فيسبوك.
4. الصلاحيات المطلوبة معرّفة في `config/meta.php` (`login_scopes`) وتحتاج App Review للاستخدام العام.
5. ربط صفحة من الـ API يشترك تلقائياً في أحداثها (`subscribed_apps`)، ولا يحتاج خطوة في اللوحة.

## الاختبارات

```bash
php artisan test
vendor/bin/pint --test
```

الاختبارات تعمل على SQLite في الذاكرة افتراضياً، ويمكن تشغيلها على MySQL:

```bash
DB_CONNECTION=mysql DB_DATABASE=automation_backend_testing php artisan test
```
