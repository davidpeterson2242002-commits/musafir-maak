/// All user-facing text, in Sudanese-friendly Arabic (spec section 3).
///
/// Kept as plain constants while the app is Arabic-only; when English is added
/// (roadmap phase 3) this becomes the Arabic entry of the localisation files.
class S {
  const S._();

  static const appName = 'مسافر معاك';

  // Onboarding
  static const onboardTitle1 = 'سافر بسعر مناسب';
  static const onboardBody1 = 'احجز مقعدك مع سائق مسافر لنفس مدينتك، وادفع له مباشرة.';
  static const onboardTitle2 = 'سائقون موثّقون';
  static const onboardBody2 = 'نراجع هوية كل سائق وعربيته قبل ما ينشر أي رحلة، وتشوف تقييمات الركاب قبله.';
  static const onboardTitle3 = 'أرسل ارساليتك';
  static const onboardBody3 = 'سلّم ارساليتك لسائق مسافر، والمستلم يستلمها برمز تسليم يصله على واتساب.';
  static const start = 'ابدأ';
  static const next = 'التالي';
  static const skip = 'تخطّي';
  static const haveAccount = 'عندي حساب — تسجيل الدخول';

  // Phone + OTP
  static const phoneTitle = 'رقم هاتفك';
  static const phoneBody = 'نرسل لك رمز التحقق على واتساب.';
  static const phoneHint = '0912345678';
  static const phoneInvalid = 'أدخل رقم سوداني صحيح مثل 0912345678';
  static const sendCode = 'أرسل الرمز على واتساب';
  static const otpTitle = 'أدخل رمز التحقق';
  static String otpBody(String phone) => 'أرسلنا الرمز على واتساب للرقم $phone';
  static const verify = 'تأكيد الرمز';
  static const changeNumber = 'تغيير الرقم';
  static const resendCode = 'أعد إرسال الرمز';
  static String resendIn(String time) => 'تقدر تطلب رمز جديد بعد $time';
  static const codeResent = 'أرسلنا رمز جديد على واتساب';

  // Profile setup
  static const setupTitle = 'عرّفنا بيك';
  static const setupBody = 'السائقون والركاب يشوفون اسمك وصورتك قبل الرحلة.';
  static const fullName = 'الاسم الكامل';
  static const nameTooShort = 'اكتب اسمك كامل';
  static const gender = 'الجنس';
  static const male = 'ذكر';
  static const female = 'أنثى';
  static const genderHelp = 'ما بتقدر تغيّره بعدين — الرحلات المخصصة للنساء والعوائل تعتمد عليه.';
  static const genderRequired = 'اختر الجنس';
  static const city = 'المدينة';
  static const cityRequired = 'اختر مدينتك';
  static const chooseCity = 'اختر المدينة';
  static const searchCity = 'ابحث عن مدينة';
  static const noCityFound = 'ما لقينا مدينة بالاسم ده';
  static const emailOptional = 'البريد الإلكتروني (اختياري)';
  static const emailHelp = 'نرسل عليه الإيصالات وكشف الحساب.';
  static const emailInvalid = 'البريد الإلكتروني غير صحيح';
  static const whatsappNumber = 'رقم الواتساب';
  static const whatsappHelp = 'لو الواتساب على رقم غير رقم الدخول.';
  static const addPhoto = 'أضف صورة';
  static const changePhoto = 'تغيير الصورة';
  static const photoFromCamera = 'التقط صورة';
  static const photoFromGallery = 'اختر من الصور';
  static const acceptTermsPrefix = 'أوافق على ';
  static const terms = 'الشروط والأحكام';
  static const and = ' و';
  static const privacy = 'سياسة الخصوصية';
  static const termsRequired = 'وافق على الشروط وسياسة الخصوصية عشان تكمل';
  static const createAccount = 'أنشئ الحساب';

  // Home
  static String hello(String name) => 'أهلاً $name';
  static const whereTo = 'ماشي وين؟';
  static const trip = 'رحلة';
  static const parcel = 'ارسالية';
  static const from = 'من';
  static const to = 'إلى';
  static const travelDate = 'تاريخ السفر';
  static const today = 'اليوم';
  static const tomorrow = 'بكرة';
  static const seats = 'عدد المقاعد';
  static const searchTrips = 'ابحث عن رحلة';
  static const searchParcelTrips = 'ابحث عن سائق للارسالية';
  static const swapCities = 'بدّل المدينتين';
  static const popularRoutes = 'طرق مطلوبة';
  static const routeClosed = 'موقوف مؤقتاً';
  static const sameCity = 'اختر مدينتين مختلفتين';
  static const chooseBothCities = 'اختر مدينة الانطلاق والوصول';
  static const comingInNextUpdate = 'نتائج البحث تجي في التحديث القادم من التطبيق.';

  // Tabs
  static const tabHome = 'الرئيسية';
  static const tabBookings = 'حجوزاتي';
  static const tabMessages = 'الرسائل';
  static const tabProfile = 'حسابي';
  static const noBookingsTitle = 'ما عندك حجوزات';
  static const noBookingsBody = 'لمّا تحجز مقعد أو ترسل ارسالية تلقاها هنا، وتقدر تفتحها حتى بدون إنترنت.';
  static const noMessagesTitle = 'ما في رسائل';
  static const noMessagesBody = 'محادثاتك مع السائقين والركاب تظهر هنا بعد أول حجز.';
  static const findTrip = 'ابحث عن رحلة';

  // Profile
  static const editProfile = 'تعديل الحساب';
  static const saveChanges = 'احفظ التغييرات';
  static const changesSaved = 'تم حفظ التغييرات';
  static const photoUpdated = 'تم تحديث الصورة';
  static const verifyId = 'وثّق هويتك';
  static const verifyIdBody = 'مطلوب قبل أول حجز، وتاخد بيه شارة التوثيق.';
  static const idPending = 'هويتك قيد المراجعة';
  static const idPendingBody = 'نراجعها في العادة خلال يوم أو يومين.';
  static const idVerified = 'هوية موثّقة';
  static const idRejected = 'لم تُقبل مستندات الهوية';
  static const idRejectedBody = 'افتح التوثيق وعدّل المستندات وأعد الإرسال.';
  static const becomeDriver = 'سجّل كسائق';
  static const becomeDriverBody = 'املأ مقاعدك الفاضية واكسب من الارساليات.';
  static const driverPending = 'طلب السائق قيد المراجعة';
  static const driverActive = 'حساب السائق مفعّل';
  static const routeAlerts = 'تنبيهات الطرق';
  static const helpSupport = 'المساعدة والدعم';
  static const contactWhatsapp = 'تواصل معنا على واتساب';
  static const prohibitedItems = 'ممنوعات الارساليات';
  static const logout = 'تسجيل الخروج';
  static const logoutConfirm = 'تسجّل خروج من الجهاز ده؟';
  static const deleteAccount = 'حذف الحساب';
  static const deleteAccountConfirm = 'يتحذف حسابك وما تقدر تدخل بيه تاني. لو رجعت سجّلت بنفس الرقم تبدأ بحساب جديد.';
  static const deleteAccountAction = 'احذف حسابي';
  static const cancel = 'إلغاء';
  static const memberSince = 'عضو منذ';
  static const noRatingsYet = 'بدون تقييمات';
  static String ratingsCount(int n) => '$n تقييم';
  static const comingSoon = 'قريباً';
  static const suspendedBanner = 'حسابك موقوف مؤقتاً. تقدر تشوف حجوزاتك لكن ما تقدر تحجز أو تنشر رحلات.';

  // Connection and errors
  static const offline = 'ما في اتصال بالإنترنت — بنعرض آخر بيانات محفوظة';
  static const noConnection = 'ما في اتصال بالإنترنت. تأكد من الشبكة وحاول تاني.';
  static const timeout = 'الاتصال بطيء شديد. حاول تاني.';
  static const serverError = 'حصلت مشكلة عندنا. حاول بعد شوية.';
  static const unknownError = 'حصل خطأ غير متوقع. حاول تاني.';
  static const sessionExpired = 'انتهت الجلسة. سجّل دخولك من جديد.';
  static const retry = 'حاول تاني';
  static const loadFailed = 'ما قدرنا نحمّل البيانات';
}
