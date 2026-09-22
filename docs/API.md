# دليل الـ API لواجهة Meta Automation

مرجع لمطوّر الواجهة (Vue) لكل ما يلزم للتكامل مع `meta-automation-api`.
أمثلة الردود مأخوذة من ردود حقيقية للـ API، مع اختصار بعض القوائم و`meta` الترقيم للإيجاز.

مجموعة Postman جاهزة في [`docs/postman/Meta-Automation-API.postman_collection.json`](postman/Meta-Automation-API.postman_collection.json)
(انظر [القسم الأخير](#10-مجموعة-postman)).

## المحتويات

1. [الأساسيات](#1-الأساسيات)
2. [شكل الردود والقوائم](#2-شكل-الردود-والقوائم)
3. [الأخطاء](#3-الأخطاء)
4. [دخول العميل بفيسبوك وحراسة المسارات](#4-دخول-العميل-بفيسبوك-وحراسة-المسارات)
5. [endpoints العميل](#5-endpoints-العميل)
6. [endpoints الأدمن](#6-endpoints-الأدمن)
7. [القيم الثابتة (Enums)](#7-القيم-الثابتة-enums)
8. [صيغة تدفق البوت `flow_json`](#8-صيغة-تدفق-البوت-flow_json)
9. [خريطة الجداول وصفحات التفاصيل](#9-خريطة-الجداول-وصفحات-التفاصيل)
10. [مجموعة Postman](#10-مجموعة-postman)

---

## 1. الأساسيات

| البند | القيمة |
| --- | --- |
| الرابط الأساسي | `{API_URL}/api`، محلياً: `http://automation_backend.test:8000/api` |
| المصادقة | `Authorization: Bearer {token}` (Laravel Sanctum، بدون كوكيز) |
| هيدر إلزامي | `Accept: application/json` |
| اللغة | `Accept-Language: ar` أو `en` (الافتراضي `en`)، ويعود في هيدر `Content-Language` |
| صيغة الجسم | `Content-Type: application/json` |
| التواريخ | ISO 8601 بتوقيت UTC، مثل `2026-12-31T23:59:59+00:00` |
| صلاحية التوكن | 30 يوماً، ثم يعود `401 auth.unauthenticated` |

### نوعان من التوكن

- **توكن العميل**: يُحصل عليه من `GET /api/auth/facebook/callback`.
- **توكن الأدمن**: يُحصل عليه من `POST /api/admin/login`.

التوكنان غير متبادلين: توكن العميل على مسار أدمن (أو العكس) يعيد `403 auth.forbidden`.
احفظهما في مفتاحين منفصلين في الواجهة.

### ثلاثة معرّفات للصفحة، انتبه للفرق

| الحقل | النوع | المعنى | أين يُستخدم |
| --- | --- | --- | --- |
| `id` / `facebook_page_id` | رقم | معرّف الصفحة في قاعدة بياناتنا | في أجسام طلبات القواعد والتدفقات وفلاتر القوائم |
| `page_id` | نص | معرّف فيسبوك للصفحة | في مسارات `/api/pages/{pageId}` فقط |

---

## 2. شكل الردود والقوائم

**عنصر واحد** يعود داخل `data`:

```json
{ "data": { "id": 1, "name": "..." } }
```

**قائمة مقسّمة لصفحات** (كل جداول الواجهة):

```json
{
  "data": [ { "id": 1 }, { "id": 2 } ],
  "links": { "first": "...?page=1", "last": "...?page=3", "prev": null, "next": "...?page=2" },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 3,
    "links": [ { "url": null, "label": "&laquo; Previous", "page": null, "active": false } ],
    "path": "http://automation_backend.test:8000/api/rules",
    "per_page": 15,
    "to": 15,
    "total": 42
  }
}
```

> `meta.links` مخصص لواجهات Blade، ولا تحتاجه. ابنِ مكوّن الترقيم من `current_page` و`last_page` و`total`.

**استثناءات من الشكل العام:**
- ردّا تسجيل الدخول (`/admin/login` و`/auth/facebook/callback`) يعيدان `{ token, admin | user }` بدون `data`.
- الحذف وتسجيل الخروج يعيدان `204` بدون جسم.
- `POST /api/admin/license-keys` يعيد دائماً مصفوفة في `data`.

### معاملات القوائم المشتركة

| المعامل | القيم | الافتراضي |
| --- | --- | --- |
| `page` | رقم ≥ 1 | `1` |
| `per_page` | من 1 إلى 100 | `15` |
| `sort` | حقل من الحقول المسموحة لكل قائمة ([القسم 9](#9-خريطة-الجداول-وصفحات-التفاصيل)) | `created_at` |
| `direction` | `asc` / `desc` | `desc` |
| `search` | نص حتى 100 حرف | — |

فلاتر `true/false` تقبل `true` و`false` و`1` و`0`. أي قيمة `sort` أو فلتر غير مسموحة تعيد `422 validation.failed`.

---

## 3. الأخطاء

كل خطأ يعود بنفس الشكل:

```json
{ "code": "subscription.inactive", "message": "Your subscription is not active. Please activate a license key to continue." }
```

وأخطاء التحقق تضيف `errors` (المفاتيح بصيغة النقاط، مثل `flow_json.steps.0.text`):

```json
{
  "code": "validation.failed",
  "message": "البيانات المرسلة غير صالحة.",
  "errors": {
    "name": ["حقل الاسم مطلوب."],
    "keywords": ["حقل الكلمات المفتاحية مطلوب."],
    "response_text": ["حقل نص الرد مطلوب."]
  }
}
```

`message` مترجمة مسبقاً حسب `Accept-Language`. إن أردت ترجمتها من ملفات الواجهة فاعتمد على `code`:
الرموز مصممة لتكون مفاتيح i18n مباشرة، مثلاً `t('errors.' + code)`، مع `message` كقيمة احتياطية.

### جدول الرموز

| `code` | HTTP | متى | التصرف المقترح في الواجهة |
| --- | --- | --- | --- |
| `auth.unauthenticated` | 401 | لا يوجد توكن، أو التوكن منتهٍ أو ملغى | امسح التوكن ووجّه لشاشة الدخول |
| `auth.forbidden` | 403 | توكن من النوع الخطأ (عميل على مسار أدمن أو العكس) | امسح التوكن ووجّه لشاشة الدخول المناسبة |
| `auth.invalid_credentials` | 422 | بريد أو كلمة مرور الأدمن خاطئة | رسالة في نموذج الدخول |
| `auth.facebook_failed` | 422 | رفض المستخدم الصلاحيات أو فشل فيسبوك | رسالة + زر "حاول مجدداً" |
| `auth.invalid_state` | 422 | رابط الدخول منتهٍ (أكثر من 10 دقائق) أو مستخدم سابقاً | ابدأ الدخول من جديد |
| `subscription.inactive` | 403 | العميل بلا اشتراك فعّال | وجّه لشاشة "أدخل مفتاح التفعيل" |
| `license_key.invalid` | 422 | المفتاح غير موجود | رسالة تحت الحقل |
| `license_key.already_used` | 422 | المفتاح مستخدم | رسالة تحت الحقل |
| `license_key.expired` | 422 | المفتاح منتهي الصلاحية | رسالة تحت الحقل |
| `license_key.does_not_extend` | 422 | المفتاح ينتهي قبل الاشتراك الحالي | رسالة تحت الحقل |
| `license_key.used_cannot_be_deleted` | 422 | الأدمن يحاول حذف مفتاح مستخدم | Toast |
| `facebook.token_expired` | 401 | انتهت صلاحية ربط فيسبوك للعميل | وجّه لإعادة الدخول بفيسبوك |
| `facebook.request_failed` | 502 | فيسبوك لم يستجب كما يجب | Toast + إعادة المحاولة لاحقاً |
| `page.not_available` | 404 | الصفحة ليست في حساب العميل أو لا يملك صلاحيات إدارتها | Toast |
| `page.connected_by_another_account` | 409 | الصفحة مربوطة بحساب عميل آخر | Toast |
| `page.not_connected` | 422 | محاولة إلغاء ربط صفحة غير مربوطة | Toast |
| `resource.not_found` | 404 | السجل غير موجود أو لا يخص العميل | صفحة "غير موجود" |
| `request.method_not_allowed` | 405 | خطأ برمجي في الواجهة | — |
| `request.too_many_attempts` | 429 | تجاوز حد المحاولات (راجع هيدر `Retry-After`) | رسالة انتظار |
| `validation.failed` | 422 | بيانات غير صالحة | اعرض `errors` تحت الحقول |
| `server.error` | 500 | خطأ غير متوقع | Toast عام |

> رمزا `401` مختلفان: `auth.unauthenticated` يعني انتهاء جلسة المنصة، و`facebook.token_expired` يعني
> انتهاء ربط فيسبوك. كلاهما يُحل بتسجيل الدخول بفيسبوك مجدداً، لكن اعرض رسالة مختلفة لكل منهما.

### حدود المحاولات

| المسار | الحد |
| --- | --- |
| `POST /api/admin/login` | 5 في الدقيقة لكل بريد + IP |
| `POST /api/auth/license-key/activate` | 5 في الدقيقة لكل عميل |
| `/api/auth/facebook/*` | 20 في الدقيقة لكل IP |

### مثال Axios interceptor

```ts
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const code = error.response?.data?.code

    if (code === 'auth.unauthenticated' || code === 'auth.forbidden' || code === 'facebook.token_expired') {
      session.clear()
      router.push({ name: 'login', query: { reason: code } })
    } else if (code === 'subscription.inactive') {
      router.push({ name: 'activate-license' })
    }

    return Promise.reject(error)
  },
)
```

---

## 4. دخول العميل بفيسبوك وحراسة المسارات

`FACEBOOK_REDIRECT_URI` في الـ API يشير إلى **صفحة في الواجهة**، مثل `https://app.example.com/auth/facebook/callback`.

```
الواجهة                         الـ API                         فيسبوك
   │ GET /auth/facebook/redirect ─►│
   │◄──── { data: { url } } ────────│
   │ window.location = url ───────────────────────────────────────►│
   │◄──────────── إعادة توجيه إلى /auth/facebook/callback?code&state │
   │ GET /auth/facebook/callback?code&state ─►│
   │◄──── { token, user } ──────────│
```

1. زر "الدخول بفيسبوك" يستدعي `GET /api/auth/facebook/redirect` ثم يوجّه المتصفح إلى `data.url`.
2. صفحة الواجهة `/auth/facebook/callback` تقرأ `code` و`state` (أو `error`) من الرابط
   وترسلها **كما هي** إلى `GET /api/auth/facebook/callback`.
3. احفظ `token` واستخدم `user` مباشرة، أو اطلب `GET /api/me`.
4. `state` صالح لمرة واحدة ولمدة 10 دقائق فقط. إن أعدت تحميل صفحة الـ callback فسيعود `auth.invalid_state`،
   وعندها ابدأ الدخول من جديد.

### حارس المسارات المقترح

| الحالة | التوجيه |
| --- | --- |
| لا يوجد توكن عميل | شاشة الدخول |
| `me.subscription.status !== 'active'` | شاشة "أدخل مفتاح التفعيل" (`none` = لم يشترك، `expired` = انتهى اشتراكه) |
| `me.facebook_token_valid === false` | شريط تنبيه "أعد ربط حساب فيسبوك" (قائمة الصفحات ستفشل بـ `facebook.token_expired`) |
| غير ذلك | لوحة التحكم |

المسارات التي **لا تتطلب** اشتراكاً فعّالاً: `/api/me` و`/api/auth/license-key/activate` و`/api/auth/logout`.
كل مسارات العميل الأخرى تعيد `403 subscription.inactive` بدونه.

---

## 5. endpoints العميل

كل الطلبات هنا تتطلب `Authorization: Bearer {client_token}`، ما عدا طلبي فيسبوك.

### 5.1 الدخول والحساب

#### `GET /api/auth/facebook/redirect`

بدون مصادقة.

```json
{ "data": { "url": "https://www.facebook.com/v23.0/dialog/oauth?client_id=...&redirect_uri=...&scope=email%2Cpublic_profile%2Cpages_show_list%2C...&response_type=code&state=TJgAIgZPRZqMOFWP7VOu5aip82em3zbm3Hb155hi" } }
```

#### `GET /api/auth/facebook/callback`

بدون مصادقة. معاملات الرابط: `code` و`state`، أو `error` إذا رفض المستخدم.

```json
{
  "token": "2|pW1qIO0IFRS9eIqFVGjpkXclQpbQqcoQkPQFPyxrc867f922",
  "user": {
    "id": 1,
    "name": "Layla Hassan",
    "email": "layla@example.com",
    "avatar_url": "https://scontent.xx.fbcdn.net/layla.jpg",
    "fb_user_id": "10150000000001",
    "facebook_token_expires_at": "2026-11-18T10:00:00+00:00",
    "facebook_token_valid": true,
    "subscription": {
      "status": "active",
      "expires_at": "2026-12-31T23:59:59+00:00",
      "activated_at": "2026-09-19T10:00:00+00:00",
      "license_key": "*****-*****-*****-B3LDV"
    },
    "connected_pages_count": 1,
    "last_login_at": "2026-09-19T10:00:00+00:00",
    "created_at": "2026-09-19T10:00:00+00:00"
  }
}
```

أخطاء محتملة: `auth.invalid_state`، `auth.facebook_failed`، `validation.failed`.

#### `GET /api/me`

يعيد نفس كائن `user` السابق داخل `data`. `email` قد يكون `null` (حسابات فيسبوك المسجلة برقم هاتف).
لعميل بلا اشتراك: `subscription = { "status": "none", "expires_at": null, "activated_at": null, "license_key": null }`.

#### `POST /api/auth/license-key/activate`

```json
{ "key": "P4Z8R-HC2WN-T6KJQ-M9YXE" }
```

يقبل أي حالة أحرف، ومع أو بدون شرطات أو مسافات (`p4z8r hc2wn t6kjq m9yxe` صالح).

```json
{
  "data": {
    "status": "active",
    "expires_at": "2027-03-31T23:59:59+00:00",
    "activated_at": "2026-09-19T10:00:00+00:00",
    "license_key": "*****-*****-*****-M9YXE"
  }
}
```

أخطاء محتملة: `license_key.invalid`، `license_key.already_used`، `license_key.expired`، `license_key.does_not_extend`،
`request.too_many_attempts`.

#### `POST /api/auth/logout`

يلغي التوكن الحالي فقط → `204`.

### 5.2 الصفحات

#### `GET /api/pages`

صفحات العميل على فيسبوك (من Graph API) مع حالة الربط. **غير مقسّمة لصفحات**، ومرتبة بالاسم.

```json
{
  "data": [
    {
      "page_id": "222222222222",
      "name": "Cafe Nour",
      "category": "Restaurant",
      "picture_url": "https://scontent.xx.fbcdn.net/nour.jpg",
      "tasks": ["MODERATE", "MESSAGING"],
      "can_connect": true,
      "is_connected": false,
      "connected_by_another_account": false,
      "facebook_page_id": null
    },
    {
      "page_id": "111111111111",
      "name": "متجر الورد",
      "category": "Shopping & retail",
      "picture_url": "https://scontent.xx.fbcdn.net/rose.jpg",
      "tasks": ["MODERATE", "MESSAGING", "MANAGE"],
      "can_connect": true,
      "is_connected": true,
      "connected_by_another_account": false,
      "facebook_page_id": 1
    }
  ]
}
```

- `can_connect = false`: العميل لا يملك صلاحيتي إدارة التعليقات والرسائل على الصفحة، فعطّل زر الربط.
- `connected_by_another_account = true`: الصفحة مربوطة عند عميل آخر، فعطّل زر الربط واعرض السبب.

أخطاء محتملة: `facebook.token_expired`، `facebook.request_failed`.

#### `POST /api/pages/{pageId}/connect`

`pageId` هو معرّف فيسبوك. يعيد `201` عند أول ربط و`200` عند إعادة ربط صفحة سبق ربطها
(وتعود قواعدها وتدفقاتها السابقة للعمل).

```json
{
  "data": {
    "id": 2,
    "page_id": "222222222222",
    "name": "Cafe Nour",
    "category": "Restaurant",
    "picture_url": "https://scontent.xx.fbcdn.net/nour.jpg",
    "is_connected": true,
    "connected_at": "2026-09-19T10:00:00+00:00",
    "created_at": "2026-09-19T10:00:00+00:00",
    "automation_rules_count": 0,
    "active_automation_rules_count": 0,
    "bot_flows_count": 0
  }
}
```

أخطاء محتملة: `page.not_available`، `page.connected_by_another_account`، `facebook.token_expired`، `facebook.request_failed`.

#### `DELETE /api/pages/{pageId}`

يلغي الربط → `204`. القواعد والتدفقات تبقى محفوظة وتتوقف عن العمل. خطأ محتمل: `page.not_connected`.

#### `GET /api/pages/connected`

الصفحات المربوطة من قاعدة البيانات (سريع، ومناسب للقوائم المنسدلة). مقسّمة لصفحات، والترتيب الافتراضي `name asc`.
كل عنصر بنفس شكل رد الربط أعلاه.

#### `GET /api/pages/{pageId}`

تفاصيل صفحة للعميل، مربوطة حالياً أو سبق ربطها، مع عدادات إضافية: `conversations_count` و`activity_logs_count`.

### 5.3 قواعد الأتمتة

| الطريقة | المسار | الرد |
| --- | --- | --- |
| GET | `/api/rules` | قائمة مقسّمة |
| POST | `/api/rules` | `201` + القاعدة |
| GET | `/api/rules/{id}` | القاعدة |
| PUT / PATCH | `/api/rules/{id}` | القاعدة بعد التعديل |
| DELETE | `/api/rules/{id}` | `204` |

**جسم الإنشاء:**

| الحقل | النوع | القواعد |
| --- | --- | --- |
| `facebook_page_id` | رقم | مطلوب، صفحة مربوطة يملكها العميل |
| `name` | نص | مطلوب، حتى 120 حرفاً |
| `trigger_type` | `comment` / `message` | مطلوب |
| `match_type` | `exact` / `contains` / `starts_with` / `any` | مطلوب |
| `keywords` | مصفوفة نصوص | مطلوبة إلا مع `any`؛ حتى 50 كلمة، كل كلمة حتى 100 حرف وبدون تكرار |
| `response_text` | نص / `null` | حتى 2000 حرف؛ مطلوب مع `message` |
| `private_reply_text` | نص / `null` | حتى 2000 حرف؛ ممنوع مع `message` |
| `is_active` | منطقي | اختياري، الافتراضي `true` |

مع `trigger_type = comment` يجب إرسال `response_text` (رد علني على التعليق) أو `private_reply_text`
(رسالة خاصة على ماسنجر لصاحب التعليق) أو كليهما.

```json
{
  "facebook_page_id": 1,
  "name": "استفسار السعر",
  "trigger_type": "comment",
  "match_type": "contains",
  "keywords": ["السعر", "price", "بكم"],
  "response_text": "أرسلنا لك التفاصيل على الخاص 🌹",
  "private_reply_text": "أهلاً! أسعارنا تبدأ من 10 دولار.",
  "is_active": true
}
```

**الرد:**

```json
{
  "data": {
    "id": 1,
    "name": "استفسار السعر",
    "facebook_page": { "id": 1, "page_id": "111111111111", "name": "متجر الورد", "is_connected": true },
    "trigger_type": "comment",
    "match_type": "contains",
    "keywords": ["السعر", "price", "بكم"],
    "response_text": "أرسلنا لك التفاصيل على الخاص 🌹",
    "private_reply_text": "أهلاً! أسعارنا تبدأ من 10 دولار.",
    "is_active": true,
    "created_at": "2026-09-19T10:00:00+00:00",
    "updated_at": "2026-09-19T10:00:00+00:00"
  }
}
```

**التعديل** (`PATCH` أو `PUT`): أرسل الحقول المتغيرة فقط، مثل `{ "is_active": false }`. التحقق يتم على الحالة
النهائية بعد الدمج، فتحويل القاعدة إلى `message` بدون `response_text` يُرفض. عند التحويل إلى `message` يُحذف
`private_reply_text` تلقائياً.

**ملاحظات على المطابقة:** المطابقة لا تفرّق بين حالة الأحرف، وتتجاهل التشكيل والتطويل، وتوحّد
أ/إ/آ → ا، وة → ه، وى → ي، والأرقام الهندية → العربية. أول قاعدة مطابقة (الأقدم) هي التي تُنفَّذ.

### 5.4 تدفقات البوت

| الطريقة | المسار | الرد |
| --- | --- | --- |
| GET | `/api/bot-flows` | قائمة مقسّمة |
| POST | `/api/bot-flows` | `201` + التدفق |
| GET | `/api/bot-flows/{id}` | التدفق |
| PUT / PATCH | `/api/bot-flows/{id}` | التدفق بعد التعديل |
| DELETE | `/api/bot-flows/{id}` | `204` |

**الجسم:** `facebook_page_id` (مطلوب)، `name` (مطلوب، حتى 120 حرفاً)، `is_active` (اختياري)، `flow_json` (مطلوب،
انظر [القسم 8](#8-صيغة-تدفق-البوت-flow_json)). في التعديل، إرسال `flow_json` يستبدل التعريف كاملاً.

**الصور:** كل خطوة تقبل `image_url` اختيارياً. ارفعي الصورة أولاً عبر [`POST /api/media`](#56-رفع-الصور)
وضعي الرابط الراجع في `image_url`، أو استخدمي أي رابط `https` منشور مسبقاً. الخطوة يجب أن تحتوي نصاً أو صورة
على الأقل (يمكن الاثنان معاً).

```json
{
  "data": {
    "id": 1,
    "name": "القائمة الرئيسية",
    "facebook_page": { "id": 1, "page_id": "111111111111", "name": "متجر الورد", "is_connected": true },
    "is_active": true,
    "flow_json": {
      "triggers": ["القائمة", "menu"],
      "match_type": "exact",
      "start_step": "welcome",
      "steps": [
        {
          "id": "welcome",
          "text": "أهلاً بك! كيف نقدر نساعدك؟",
          "options": [
            { "label": "الأسعار", "next_step": "prices" },
            { "label": "الموقع", "next_step": "location" }
          ]
        },
        { "id": "prices", "text": "أسعارنا تبدأ من 10 دولار.", "options": [] },
        { "id": "location", "text": "نحن في وسط المدينة.", "options": [] }
      ]
    },
    "steps_count": 3,
    "created_at": "2026-09-19T10:00:00+00:00",
    "updated_at": "2026-09-19T10:00:00+00:00"
  }
}
```

### 5.5 سجل النشاط

| الطريقة | المسار |
| --- | --- |
| GET | `/api/activity-logs` |
| GET | `/api/activity-logs/{id}` |

```json
{
  "data": {
    "id": 1,
    "event_type": "comment_reply",
    "status": "success",
    "payload": {
      "rule_id": 1,
      "rule_name": "استفسار السعر",
      "post_id": "111111111111_900",
      "comment_id": "900_1234",
      "sender_id": "5550001",
      "sender_name": "Omar",
      "incoming_text": "بكم السعر؟",
      "response_text": "أرسلنا لك التفاصيل على الخاص 🌹"
    },
    "facebook_page": { "id": 1, "page_id": "111111111111", "name": "متجر الورد", "is_connected": true },
    "created_at": "2026-09-19T10:00:00+00:00"
  }
}
```

محتوى `payload` حسب `event_type`:

| `event_type` | الحقول |
| --- | --- |
| `comment_reply`، `private_reply` | `rule_id`, `rule_name`, `post_id`, `comment_id`, `sender_id`, `sender_name`, `incoming_text`, `response_text` |
| `message_reply` | `rule_id`, `rule_name`, `psid`, `incoming_text`, `response_text` |
| `flow_step` | `flow_id`, `flow_name`, `step_id`, `psid`, `incoming_text`, `response_text` |

عند `status = failed` يُضاف `error: { code, message }`، وهو خطأ فيسبوك الأصلي ومفيد لصفحة التفاصيل.
وفي أحداث `flow_step` يظهر `image_url` إن كانت الخطوة تحتوي صورة.

### 5.6 رفع الصور

#### `POST /api/media`

`multipart/form-data` بحقل واحد اسمه `file`. لا ترسلي `Content-Type` يدوياً؛ اتركي المتصفح يضبطه.

| الشرط | القيمة |
| --- | --- |
| الأنواع المقبولة | JPEG, PNG, GIF, WebP |
| الحجم الأقصى | 8 ميجابايت (قابل للتغيير بـ `META_MEDIA_MAX_KILOBYTES`) |

```json
{
  "data": {
    "path": "bot-media/1/9xKq2fV7v1sVvQ0b8mVYt3u2c1pQ.jpg",
    "url": "https://api.example.com/storage/bot-media/1/9xKq2fV7v1sVvQ0b8mVYt3u2c1pQ.jpg",
    "mime_type": "image/jpeg",
    "size": 184320
  }
}
```

يعود `201`. استخدمي `url` في `image_url` داخل خطوة التدفق، واحتفظي بـ `path` إن أردتِ حذف الصورة لاحقاً.
ملفات كل عميلة معزولة في مجلد خاص بها.

```ts
const form = new FormData()
form.append('file', file)
const { data } = await api.post('/media', form)
step.image_url = data.data.url
```

#### `DELETE /api/media`

```json
{ "path": "bot-media/1/9xKq2fV7v1sVvQ0b8mVYt3u2c1pQ.jpg" }
```

يعود `204`. محاولة حذف ملف عميلة أخرى أو مسار خارج مجلدها تعيد `404 resource.not_found`.

> الصورة يجب أن تكون على رابط عام يصل إليه فيسبوك. في التطوير المحلي لن تعمل روابط `localhost`؛
> استخدمي نفقاً (مثل Cloudflare) واضبطي `APP_URL` عليه لأن رابط الصورة يُبنى منه.

---

## 6. endpoints الأدمن

كل الطلبات تتطلب `Authorization: Bearer {admin_token}` ما عدا تسجيل الدخول.

### 6.1 الدخول

#### `POST /api/admin/login`

```json
{ "email": "owner@example.com", "password": "secret-password" }
```

```json
{
  "token": "1|8w7XvedOiqE0aBaHLFrxVzhraQbupgNu54KEvtBW8b5473b4",
  "admin": {
    "id": 1,
    "name": "Platform Owner",
    "email": "owner@example.com",
    "last_login_at": "2026-09-19T10:00:00+00:00",
    "created_at": "2026-09-19T10:00:00+00:00"
  }
}
```

خطأ محتمل: `auth.invalid_credentials` (نفس الرسالة للبريد الخاطئ وكلمة المرور الخاطئة).

- `GET /api/admin/me` يعيد `{ data: admin }`.
- `POST /api/admin/logout` يعيد `204`.

### 6.2 العملاء

#### `GET /api/admin/clients`

```json
{
  "data": [
    {
      "id": 1,
      "name": "Layla Hassan",
      "email": "layla@example.com",
      "avatar_url": "https://scontent.xx.fbcdn.net/layla.jpg",
      "subscription_status": "active",
      "subscription_expires_at": "2026-12-31T23:59:59+00:00",
      "connected_pages_count": 1,
      "last_login_at": "2026-09-19T09:30:00+00:00",
      "created_at": "2026-09-19T10:00:00+00:00"
    }
  ],
  "links": { "...": "..." },
  "meta": { "...": "..." }
}
```

#### `GET /api/admin/clients/{id}`

حقول القائمة، ويُضاف إليها:

```json
{
  "data": {
    "fb_user_id": "10150000000001",
    "facebook_token_expires_at": "2026-11-18T10:00:00+00:00",
    "active_license_key": {
      "id": 1,
      "key": "K7M2Q-XR4TP-9WHNC-B3LDV",
      "expires_at": "2026-12-31T23:59:59+00:00",
      "used_at": "2026-09-19T10:00:00+00:00"
    },
    "pages": [
      {
        "id": 1,
        "page_id": "111111111111",
        "name": "متجر الورد",
        "category": "Shopping & retail",
        "picture_url": "https://scontent.xx.fbcdn.net/rose.jpg",
        "is_connected": true,
        "connected_at": "2026-09-10T12:00:00+00:00"
      }
    ],
    "license_keys_history": [
      { "id": 1, "key": "K7M2Q-XR4TP-9WHNC-B3LDV", "expires_at": "2026-12-31T23:59:59+00:00", "used_at": "2026-09-19T10:00:00+00:00" }
    ],
    "automation_rules_count": 1,
    "bot_flows_count": 1,
    "activity_logs_count": 1
  }
}
```

### 6.3 مفاتيح الاشتراك

| الطريقة | المسار | الرد |
| --- | --- | --- |
| GET | `/api/admin/license-keys` | قائمة مقسّمة |
| POST | `/api/admin/license-keys` | `201` + مصفوفة المفاتيح الجديدة |
| GET | `/api/admin/license-keys/{id}` | المفتاح |
| DELETE | `/api/admin/license-keys/{id}` | `204` (للمفاتيح غير المستخدمة فقط) |

**التوليد:**

```json
{ "expires_at": "2027-03-31T23:59:59Z", "note": "Ramadan offer", "quantity": 2 }
```

- `expires_at` مطلوب، ويجب أن يكون في المستقبل، وهو نفسه تاريخ انتهاء اشتراك العميل الذي سيفعّل المفتاح.
- `quantity` من 1 إلى 100، والافتراضي 1.
- `note` اختيارية، حتى 500 حرف.

```json
{
  "data": [
    {
      "id": 2,
      "key": "CJ47U-FBCH6-S2JAK-DLH5Z",
      "status": "available",
      "is_used": false,
      "is_expired": false,
      "expires_at": "2027-03-31T23:59:59+00:00",
      "used_at": null,
      "used_by": null,
      "created_by": { "id": 1, "name": "Platform Owner" },
      "note": "Ramadan offer",
      "created_at": "2026-09-19T10:00:00+00:00"
    }
  ]
}
```

- `created_at` هو تاريخ الإصدار.
- `used_by` يكون `{ id, name, email }` بعد استخدام المفتاح.
- حذف مفتاح مستخدم يعيد `422 license_key.used_cannot_be_deleted`، فأخفِ زر الحذف في صفحة التفاصيل عندما يكون `is_used = true`.

### 6.4 الإحصائيات

#### `GET /api/admin/stats`

```json
{
  "data": {
    "clients": { "total": 1, "active_subscriptions": 1, "expired_subscriptions": 0, "without_subscription": 0 },
    "pages": { "connected": 1, "total": 1 },
    "automation": { "replies_total": 1, "replies_last_30_days": 1, "failed_total": 0 },
    "license_keys": { "issued": 1, "used": 1, "remaining": 0, "expired_unused": 0 }
  }
}
```

- `replies_*` تحسب الردود الآلية الناجحة فقط.
- `remaining` هي المفاتيح غير المستخدمة وغير المنتهية.

### 6.5 سجل النشاط

`GET /api/admin/activity-logs` و`GET /api/admin/activity-logs/{id}`: نفس شكل سجل العميل، مع حقل إضافي
`client: { id, name, email }` وفلتر إضافي `user_id`.

---

## 7. القيم الثابتة (Enums)

| الحقل | القيم |
| --- | --- |
| `subscription.status` / `subscription_status` | `active`, `expired`, `none` |
| `license_key.status` | `available`, `used`, `expired` |
| `trigger_type` | `comment`, `message` |
| `match_type` (قواعد) | `exact`, `contains`, `starts_with`, `any` |
| `flow_json.match_type` | `exact`, `contains`, `starts_with` |
| `event_type` | `comment_reply`, `private_reply`, `message_reply`, `flow_step` |
| `activity status` | `success`, `failed` |

---

## 8. صيغة تدفق البوت `flow_json`

```json
{
  "triggers": ["القائمة", "menu"],
  "match_type": "exact",
  "start_step": "welcome",
  "steps": [
    {
      "id": "welcome",
      "text": "...",
      "image_url": "https://api.example.com/storage/bot-media/1/menu.jpg",
      "options": [ { "label": "الأسعار", "next_step": "prices" } ]
    },
    { "id": "prices", "text": "...", "image_url": null, "options": [] }
  ]
}
```

| الحقل | القواعد |
| --- | --- |
| `triggers` | من 1 إلى 20 نصاً، كل نص حتى 100 حرف وبدون تكرار |
| `match_type` | `exact` / `contains` / `starts_with` |
| `start_step` | معرّف خطوة موجودة |
| `steps` | من 1 إلى 50 خطوة |
| `steps[].id` | حروف لاتينية وأرقام و`_` و`-`، حتى 40 حرفاً، وفريد داخل التدفق |
| `steps[].text` | حتى 2000 حرف، مطلوب إلا إذا كانت الخطوة تحتوي `image_url` |
| `steps[].image_url` | اختياري، رابط `https` حتى 2048 حرفاً (من [`POST /api/media`](#56-رفع-الصور) أو رابط خارجي) |
| `steps[].options` | حتى 13 خياراً (حد Messenger)؛ يمكن حذفها أو إرسال `[]` |
| `options[].label` | حتى 20 حرفاً (حد Messenger)، وفريد داخل نفس الخطوة |
| `options[].next_step` | معرّف خطوة موجودة |

**الصور في ماسنجر:** ماسنجر لا يسمح بنص وصورة في رسالة واحدة، لذلك الخطوة التي تحتوي الاثنين تُرسل كرسالتين:
الصورة أولاً ثم النص ومعه الأزرار. الخطوة التي فيها صورة فقط تحمل الأزرار على الصورة نفسها. وإن فشل إرسال
الصورة يُسجَّل الحدث كـ `failed` ولا يُرسل النص ولا تتقدم المحادثة للخطوة التالية.

**كيف يعمل على ماسنجر:** عندما يكتب المستخدم أحد `triggers` تُرسل خطوة البداية، وتظهر الخيارات كأزرار رد سريع.
الضغط على زر، أو كتابة نص الخيار نفسه، ينقل المستخدم إلى `next_step`. الخطوة التي بلا خيارات تنهي التدفق.
التدفقات لها أولوية على قواعد الرسائل.

**أخطاء التحقق** تعود بمسار دقيق، مثل `flow_json.steps.2.options.0.next_step`، لتعرضها بجانب الخطوة والخيار المعنيين
في محرر التدفق.

---

## 9. خريطة الجداول وصفحات التفاصيل

حسب قواعد التصميم: النقر على الصف يفتح صفحة التفاصيل، والإجراءات تكون في شريط أعلى صفحة التفاصيل.

| الجدول | القائمة | التفاصيل (عند النقر على الصف) | `sort` المسموح | الفلاتر | `search` في |
| --- | --- | --- | --- | --- | --- |
| الصفحات المربوطة | `GET /api/pages/connected` | `GET /api/pages/{page_id}` ⚠️ | `name`, `connected_at`, `created_at` (افتراضي `name asc`) | — | اسم الصفحة |
| القواعد | `GET /api/rules` | `GET /api/rules/{id}` | `name`, `trigger_type`, `match_type`, `is_active`, `created_at`, `updated_at` | `facebook_page_id`, `trigger_type`, `is_active` | الاسم |
| تدفقات البوت | `GET /api/bot-flows` | `GET /api/bot-flows/{id}` | `name`, `is_active`, `created_at`, `updated_at` | `facebook_page_id`, `is_active` | الاسم |
| سجل النشاط | `GET /api/activity-logs` | `GET /api/activity-logs/{id}` | `created_at`, `event_type`, `status` | `facebook_page_id`, `event_type`, `status`, `date_from`, `date_to` | اسم الصفحة |
| العملاء (أدمن) | `GET /api/admin/clients` | `GET /api/admin/clients/{id}` | `name`, `email`, `subscription_expires_at`, `connected_pages_count`, `last_login_at`, `created_at` | `subscription_status` | الاسم أو البريد |
| مفاتيح الاشتراك (أدمن) | `GET /api/admin/license-keys` | `GET /api/admin/license-keys/{id}` | `created_at`, `expires_at`, `used_at`, `key`, `is_used` | `status` | المفتاح أو الملاحظة أو اسم/بريد العميل |
| سجل النشاط (أدمن) | `GET /api/admin/activity-logs` | `GET /api/admin/activity-logs/{id}` | `created_at`, `event_type`, `status` | كالعميل + `user_id` | اسم الصفحة |

⚠️ صفحة تفاصيل الصفحة المربوطة تُفتح بـ `page_id` (معرّف فيسبوك) وليس بـ `id`.

- `date_from` و`date_to` بصيغة `YYYY-MM-DD`، وكلاهما شامل لليوم كاملاً.
- `GET /api/pages` (صفحات فيسبوك) ليست جدولاً مقسّماً؛ اعرضها كبطاقات أو قائمة مع زر ربط لكل صفحة.

---

## 10. مجموعة Postman

الملف: [`docs/postman/Meta-Automation-API.postman_collection.json`](postman/Meta-Automation-API.postman_collection.json)
(Postman → Import).

- **11 مجلداً و37 طلباً**، ولكل طلب مثال رد حقيقي محفوظ في تبويب Examples.
- عدّل متغيرات المجموعة (تبويب Variables): `base_url`، `locale`، `admin_email`، `admin_password`.
- «تسجيل دخول الأدمن» يحفظ `admin_token` تلقائياً، و«إكمال الدخول (callback)» يحفظ `client_token`.
- طلبات الإنشاء تحفظ المعرّفات (`rule_id`، `bot_flow_id`، `license_key_id`، ...) لتعمل طلبات العرض والتعديل والحذف بعدها مباشرة.
- دخول فيسبوك يحتاج متصفحاً:
  1. نفّذ «رابط الدخول» وافتح `url` في المتصفح.
  2. بعد الموافقة انسخ `code` و`state` من رابط إعادة التوجيه إلى المتغيرين `oauth_code` و`oauth_state`.
  3. نفّذ «إكمال الدخول».
- مجلد **Webhook** يحاكي أحداث Meta (تعليق جديد، رسالة ماسنجر) لاختبار الأتمتة محلياً، ويحسب توقيع
  `X-Hub-Signature-256` تلقائياً من المتغير `app_secret` (نفس `FACEBOOK_CLIENT_SECRET`). يحتاج تشغيل `php artisan queue:work`.
