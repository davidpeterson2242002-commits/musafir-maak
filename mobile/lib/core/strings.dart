import 'utils/formatters.dart';

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
  static const driverContinue = 'كمّل تسجيلك كسائق';
  static const driverContinueBody = 'باقي ليك خطوات بسيطة عشان ترسل الطلب.';
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
  static String ratingsCount(int n) => Fmt.count(n, one: 'تقييم واحد', two: 'تقييمين', few: 'تقييمات', many: 'تقييم');
  static const comingSoon = 'قريباً';
  static const suspendedBanner = 'حسابك موقوف مؤقتاً. تقدر تشوف حجوزاتك لكن ما تقدر تحجز أو تنشر رحلات.';

  // Search results
  static const routeArrow = '←';
  static String tripsFound(int n) => Fmt.count(n, one: 'رحلة واحدة', two: 'رحلتين', few: 'رحلات', many: 'رحلة');
  static const filters = 'تصفية';
  static const sortBy = 'الترتيب';
  static const sortEarliest = 'الأبكر';
  static const sortCheapest = 'الأرخص';
  static const sortRating = 'الأعلى تقييماً';
  static const timeOfDay = 'وقت الانطلاق';
  static const timeMorning = 'الصباح';
  static const timeAfternoon = 'الظهر';
  static const timeEvening = 'المساء';
  static const timeNight = 'الليل';
  static const vehicleType = 'نوع العربية';
  static const womenOnlyFilter = 'رحلات النساء والعوائل فقط';
  static const minRating = 'تقييم السائق';
  static const anyRating = 'الكل';
  static const applyFilters = 'اعرض النتائج';
  static const clearFilters = 'امسح التصفية';
  static const noTripsTitle = 'ما في رحلات في اليوم ده';
  static const noTripsBody = 'جرّب يوم قريب، أو خلّينا ننبّهك أول ما سائق ينشر رحلة على الطريق ده.';
  static const noTripsFiltered = 'ما في رحلات بالتصفية دي. خفّف الشروط وجرّب تاني.';
  static const nearbyDays = 'أيام قريبة فيها رحلات';
  static const alertMe = 'نبّهني لمّا تنزل رحلة';
  static const alertCreated = 'تمام — بننبّهك أول ما تنزل رحلة على الطريق ده';
  static const routeClosedTitle = 'الطريق موقوف مؤقتاً';
  static const womenOnly = 'نساء وعوائل';
  static const acceptsParcels = 'يقبل ارساليات';
  static String seatsLeft(int n) =>
      Fmt.count(n, one: 'مقعد واحد متاح', two: 'مقعدين متاحين', few: 'مقاعد متاحة', many: 'مقعد متاح');
  static const perSeat = 'للمقعد';
  static const parcelFrom = 'الارسالية تبدأ من';
  static const newDriver = 'سائق جديد';

  // Trip details
  static const tripDetails = 'تفاصيل الرحلة';
  static const pickupPoints = 'نقاط التحرك';
  static const dropoffPoints = 'نقاط النزول';
  static const luggage = 'الشنط';
  static String luggageAllowance(int large, int small) {
    final parts = [
      if (large > 0) large == 1 ? 'شنطة كبيرة' : '$large شنط كبيرة',
      if (small > 0) small == 1 ? 'شنطة صغيرة' : '$small شنط صغيرة',
    ];
    return parts.isEmpty ? 'بدون شنط' : '${parts.join(' + ')} لكل راكب';
  }

  static String extraBag(String price) => 'الشنطة الإضافية بـ $price';
  static const noExtraBags = 'السائق ما بياخد شنط إضافية';
  static const driverNotes = 'ملاحظات السائق';
  static const vehicle = 'العربية';
  static const plateAfterAccept = 'رقم اللوحة يظهر بعد قبول الحجز';
  static const womenOnlyNotice = 'الرحلة دي للنساء والعوائل فقط';
  static String tripsDone(int n) => Fmt.count(n, one: 'رحلة واحدة', two: 'رحلتين', few: 'رحلات', many: 'رحلة');
  static const bookSeat = 'احجز مقعد';
  static const viewMyBooking = 'افتح حجزي';
  static const tripNotBookable = 'الرحلة دي ما متاحة للحجز حالياً';
  static const estimatedArrival = 'الوصول المتوقع';

  // Booking request
  static const bookingRequest = 'طلب الحجز';
  static const pickupPoint = 'نقطة التحرك';
  static const extraBags = 'شنط إضافية';
  static const withFamily = 'معاي عائلتي';
  static const withFamilyHelp = 'الرحلة للنساء والعوائل — أكّد إنك مسافر مع عائلتك.';
  static const messageToDriver = 'رسالة للسائق (اختياري)';
  static const total = 'الإجمالي';
  static const payDriverDirectly = 'تدفع للسائق مباشرة — كاش أو تحويل.';
  static const sendRequest = 'أرسل طلب الحجز';
  static const verifyThenBook = 'وثّق هويتك وأكمل الحجز';
  static const idNeededTitle = 'توثيق الهوية مطلوب قبل أول حجز';
  static const idNeededBody = 'صورة هويتك وسيلفي — مرة واحدة بس، والسائق بيعرف إنك شخص موثّق.';
  static const requestSent = 'أرسلنا طلبك للسائق';

  // Booking status
  static const bookingTitle = 'الحجز';
  static const waitingDriver = 'في انتظار رد السائق';
  static String expiresIn(String time) => 'الطلب ينتهي بعد $time لو السائق ما رد';
  static const accepted = 'تم قبول حجزك';
  static const acceptedBody = 'تواصل مع السائق وكن في نقطة التحرك قبل الموعد.';
  static const rejected = 'السائق ما قبل الطلب';
  static const expired = 'انتهت مهلة الطلب بدون رد';
  static const cancelledByYou = 'ألغيت الحجز';
  static const cancelledByDriver = 'السائق ألغى الرحلة';
  static const completed = 'اكتملت الرحلة';
  static const noShow = 'السائق سجّل إنك ما حضرت';
  static const noShowDisputed = 'اعتراضك قيد المراجعة';
  static const disputeNoShow = 'اعترض — أنا حضرت';
  static const disputeHint = 'اكتب الحصل بالتفصيل';
  static const disputeSent = 'وصلنا اعتراضك وبنراجعه';
  static const sendDispute = 'أرسل الاعتراض';
  static const driver = 'السائق';
  static const callDriver = 'اتصل';
  static const whatsappDriver = 'واتساب';
  static const chat = 'محادثة';
  static const plate = 'اللوحة';
  static const shareTrip = 'شارك الرحلة مع أهلك';
  static const shareWhatsapp = 'أرسلها على واتساب';
  static const copyText = 'انسخ النص';
  static const copied = 'تم النسخ';
  static const cancelRequest = 'ألغِ الطلب';
  static const cancelBooking = 'ألغِ الحجز';
  static const cancelBookingConfirm = 'تلغي الحجز ده؟';
  static const cancelLateWarning = 'الإلغاء المتكرر قبل الرحلة بوقت قصير بيوقف حسابك عن الحجز مؤقتاً.';
  static const cancelReason = 'سبب الإلغاء (اختياري)';
  static const bookingCancelled = 'تم إلغاء الحجز';
  static const otherTrips = 'رحلات تانية في نفس اليوم';
  static const rateTrip = 'قيّم الرحلة';
  static String seatsCount(int n) => Fmt.count(n, one: 'مقعد واحد', two: 'مقعدين', few: 'مقاعد', many: 'مقعد');
  static const savedCopy = 'نسخة محفوظة — افتح الإنترنت لتحديثها';
  static const chatSoon = 'المحادثة تجي في التحديث القادم. اتصل بالسائق مباشرة.';

  // My bookings
  static const upcoming = 'القادمة';
  static const past = 'السابقة';
  static const cancelledTab = 'الملغاة';
  static const noPastBookings = 'ما عندك رحلات سابقة';
  static const noCancelledBookings = 'ما في حجوزات ملغاة';
  static const nextTrip = 'رحلتك القادمة';

  // Identity verification
  static const idTitle = 'توثيق الهوية';
  static const idIntro = 'نحتاج صورة هويتك وسيلفي عشان الكل يسافر مع ناس معروفين. صورك محفوظة عندنا وما بيشوفها غير فريق المراجعة.';
  static const idType = 'نوع الهوية';
  static const nationalId = 'بطاقة قومية';
  static const passport = 'جواز سفر';
  static const selfie = 'سيلفي لوجهك';
  static const selfieHelp = 'بالكاميرا الأمامية، في مكان مضيء، بدون نظارة شمس.';
  static const idFront = 'البطاقة — الوجه الأمامي';
  static const idBack = 'البطاقة — الوجه الخلفي';
  static const passportPage = 'صفحة البيانات في الجواز';
  static const docHelp = 'كل الكتابة واضحة ومقروءة، بدون لمعة.';
  static const takePhoto = 'صوّر';
  static const retakePhoto = 'أعد التصوير';
  static const uploaded = 'تم الرفع';
  static const uploading = 'جارٍ الرفع…';
  static const submitForReview = 'أرسل للمراجعة';
  static const idSubmitted = 'أرسلنا مستنداتك للمراجعة';
  static const idPendingLong = 'مستنداتك عندنا وبنراجعها، في العادة خلال يوم أو يومين. بنرسل ليك إشعار أول ما نخلص.';
  static const idVerifiedLong = 'هويتك موثّقة. شارة التوثيق ظاهرة في حسابك للسائقين والركاب.';
  static const idRejectedIntro = 'ما قدرنا نقبل المستندات للأسباب دي:';
  static const continueBooking = 'كمّل الحجز';
  static const done = 'تمام';

  // Rating
  static String rateTitle(String name) => 'كيف كانت رحلتك مع $name؟';
  static const rateComment = 'تعليق (اختياري)';
  static const sendRating = 'أرسل التقييم';
  static const ratingSent = 'شكراً — وصلنا تقييمك';
  static const chooseStars = 'اختر عدد النجوم';
  static const rateLater = 'بعدين';
  static String ratePrompt(String name) => 'قيّم رحلتك مع $name';
  static String rateCustomer(String name) => 'قيّم $name';

  // Route alerts
  static const alertsEmptyTitle = 'ما عندك تنبيهات';
  static const alertsEmptyBody = 'أضف طريق، وبنرسل ليك إشعار أول ما سائق ينشر رحلة عليه.';
  static const addAlert = 'أضف تنبيه';
  static const anyDate = 'أي تاريخ';
  static const specificDate = 'تاريخ محدد';
  static const saveAlert = 'احفظ التنبيه';
  static const alertDeleted = 'تم حذف التنبيه';
  static const alertEnded = 'انتهى';
  static const delete = 'حذف';

  // Parcels (ارسالية)
  static const sendParcel = 'أرسل ارسالية';
  static const sendParcelWithTrip = 'أرسل ارسالية مع الرحلة دي';
  static const parcelRequest = 'طلب ارسالية';
  static const parcelDescription = 'شنو في الارسالية؟';
  static const parcelDescriptionHint = 'مثلاً: ملابس وأحذية في كرتونة';
  static const parcelDescriptionShort = 'اكتب وصف واضح للارسالية';
  static const parcelSize = 'الحجم';
  static const sizeSmallHelp = 'ظرف أو كيس صغير';
  static const sizeMediumHelp = 'شنطة يد أو كرتونة صغيرة';
  static const sizeLargeHelp = 'شنطة سفر أو كرتونة كبيرة';
  static const parcelPhoto = 'صورة الارسالية';
  static const parcelPhotoHelp = 'السائق يطابقها مع الارسالية عند الاستلام.';
  static const parcelPhotoRequired = 'صوّر الارسالية';
  static const receiverName = 'اسم المستلم';
  static const receiverPhone = 'رقم المستلم';
  static const receiverPhoneHelp = 'نرسل له رمز التسليم على واتساب.';
  static const receiverNameRequired = 'اكتب اسم المستلم';
  static const whoPays = 'منو يدفع للسائق؟';
  static const iPay = 'أنا أدفع';
  static const receiverPays = 'المستلم يدفع';
  static const noteToDriver = 'ملاحظة للسائق (اختياري)';
  static const prohibitedTitle = 'ممنوع إرسالها';
  static const prohibitedConfirm = 'أؤكد إن الارسالية ما فيها ممنوعات وأنا مسؤول عن محتواها';
  static const prohibitedRequired = 'أكّد إن الارسالية ما فيها ممنوعات';
  static const sendParcelRequest = 'أرسل الطلب للسائق';
  static const verifyThenSend = 'وثّق هويتك وأرسل الطلب';
  static const parcelPrice = 'سعر التوصيل';
  static const parcelTitle = 'الارسالية';
  static const parcelWaiting = 'في انتظار رد السائق';
  static const parcelAccepted = 'السائق قبل الارسالية';
  static const parcelAcceptedBody = 'سلّم الارسالية للسائق في نقطة التحرك قبل موعد الرحلة.';
  static const parcelPickedUp = 'الارسالية مع السائق';
  static const parcelPickedUpBody = 'المستلم يدّي رمز التسليم للسائق لمّا يستلم الارسالية.';
  static const parcelDelivered = 'تم تسليم الارسالية';
  static const parcelRejected = 'السائق ما قبل الارسالية';
  static const parcelExpired = 'انتهت مهلة الطلب بدون رد';
  static const parcelRefused = 'السائق رفض يستلم الارسالية';
  static const parcelCancelled = 'أُلغيت الارسالية';
  static const parcelDisputed = 'المشكلة قيد المراجعة';
  static const parcelDisputedBody = 'فريق الدعم بيراجع البلاغ وبيتواصل معاك.';
  static const deliveryCode = 'رمز التسليم';
  static const deliveryCodeHelp = 'المستلم يدّيه للسائق عند الاستلام فقط.';
  static const receiverNotified = 'وصل الرمز للمستلم على واتساب';
  static const receiverNotNotified = 'الرمز ما وصل للمستلم على واتساب — أرسله ليه إنت';
  static const shareWithReceiver = 'أرسل التفاصيل للمستلم';
  static const receiver = 'المستلم';
  static const pickupPhoto = 'صورة الاستلام من السائق';
  static const cancelParcel = 'ألغِ الارسالية';
  static const cancelParcelConfirm = 'تلغي الارسالية دي؟';
  static const reportProblem = 'بلّغ عن مشكلة في الارسالية';
  static const parcelProblemHint = 'اكتب المشكلة: ما وصلت؟ ناقصة؟ تالفة؟';
  static const sendReport = 'أرسل البلاغ';
  static const parcelsTab = 'ارسالياتي';
  static const noParcelsTitle = 'ما عندك ارساليات';
  static const noParcelsBody = 'ابحث عن رحلة في الرئيسية واختر "ارسالية" عشان ترسل مع سائق مسافر.';
  static const noParcelSlots = 'السائق ما بياخد ارساليات تانية في الرحلة دي';

  // Chat
  static const typeMessage = 'اكتب رسالة';
  static const chatClosed = 'المحادثة مقفولة';
  static const numberHidden = 'الأرقام تتخفى قبل قبول الحجز — بعد القبول يظهر رقم السائق في صفحة الحجز.';
  static const noMessagesYet = 'ابدأ المحادثة — اسأل السائق عن أي تفصيل قبل الرحلة.';
  static const you = 'إنت: ';
  static const messageFailed = 'الرسالة ما اتبعتت. حاول تاني.';

  // Public profile, report, block
  static const profile = 'الملف الشخصي';
  static const reviews = 'التقييمات';
  static const noReviews = 'ما في تقييمات لسه';
  static const asDriver = 'كسائق';
  static const asPassenger = 'كراكب';
  static const driverSince = 'سائق منذ';
  static const report = 'بلاغ';
  static const reportUser = 'بلّغ عن المستخدم';
  static const reportReason = 'سبب البلاغ';
  static const reportDetails = 'اكتب التفاصيل (اختياري)';
  static const chooseReason = 'اختر سبب البلاغ';
  static const block = 'حظر';
  static const unblock = 'إلغاء الحظر';
  static String blockConfirm(String name) => 'تحظر $name؟';
  static const blockBody = 'ما بتظهر ليك رحلاته وما بيقدر يراسلك أو يحجز معاك.';
  static const blocked = 'تم الحظر';
  static const unblocked = 'تم إلغاء الحظر';

  // Driver registration
  static const driverRegTitle = 'التسجيل كسائق';
  static const driverIntroTitle = 'سافر وعربيتك مليانة';
  static const driverBenefit1 = 'املأ مقاعدك الفاضية مع ركاب موثّقين';
  static const driverBenefit2 = 'اكسب من توصيل الارساليات في طريقك';
  static const driverBenefit3 = 'شارة سائق موثّق تزيد ثقة الركاب';
  static const driverNeedsTitle = 'جهّز الحاجات دي';
  static const driverNeed1 = 'البطاقة القومية أو الجواز';
  static const driverNeed2 = 'رخصة القيادة';
  static const driverNeed3 = 'شهادة البحث';
  static const driverNeed4 = '4 صور واضحة للعربية';
  static const driverFeesTitle = 'كيف العمولة؟';
  static const driverFeesBody = 'أول حجوزاتك مجانية. بعدها عمولة بسيطة على كل حجز تقبله، وتسدّدها مرة في الأسبوع ببنكك أو فوري أو كاش. الراكب يدفع ليك مباشرة.';
  static const startRegistration = 'ابدأ التسجيل';
  static const stepIdentity = 'الهوية والسيلفي';
  static const stepLicence = 'رخصة القيادة';
  static const stepVehicle = 'العربية وصورها';
  static const stepDone = 'مكتمل';
  static const stepTodo = 'ناقص';
  static const licencePhoto = 'صورة رخصة القيادة';
  static const acceptDriverTerms = 'أوافق على شروط السائقين (العمولة وقواعد السلوك)';
  static const acceptDriverTermsPrefix = 'أوافق على ';
  static const driverTerms = 'شروط السائقين';
  static const acceptDriverTermsSuffix = ' (العمولة وقواعد السلوك)';
  static const driverTermsRequired = 'وافق على شروط السائقين عشان ترسل الطلب';
  static const submitApplication = 'أرسل الطلب للمراجعة';
  static const completeStepsFirst = 'كمّل الخطوات الثلاث أول';
  static const applicationSent = 'أرسلنا طلبك للمراجعة';
  static const driverPendingLong = 'مستنداتك قيد المراجعة، في العادة خلال 24 إلى 48 ساعة. بنرسل ليك إشعار أول ما نخلص.';
  static const driverRejectedTitle = 'الطلب يحتاج تعديل';
  static const driverActiveLong = 'حسابك كسائق مفعّل. حوّل لوضع السائق من أعلى الشاشة الرئيسية عشان تنشر رحلاتك.';
  static const driverSuspended = 'حساب السائق موقوف. تواصل مع الدعم.';
  static const myVehicles = 'عربياتي';
  static const addVehicle = 'أضف عربية';
  static const vehicleDetails = 'بيانات العربية';
  static const vehicleMake = 'الماركة';
  static const vehicleMakeHint = 'تويوتا';
  static const vehicleModel = 'الموديل';
  static const vehicleModelHint = 'هايس';
  static const vehicleColor = 'اللون';
  static const vehicleYear = 'سنة الصنع (اختياري)';
  static const plateNumber = 'رقم اللوحة';
  static const passengerSeats = 'مقاعد الركاب (بدون السائق)';
  static const fieldRequired = 'مطلوب';
  static const saveAndAddPhotos = 'احفظ وأضف الصور';
  static const vehiclePhotos = 'صور العربية';
  static const photoFront = 'من الأمام (اللوحة ظاهرة)';
  static const photoBack = 'من الخلف';
  static const photoSide = 'من الجنب';
  static const photoInterior = 'من الداخل';
  static const photoRegistration = 'شهادة البحث';
  static const vehicleSaved = 'تم حفظ بيانات العربية';
  static const sendVehicleForReview = 'أرسل العربية للمراجعة';
  static const vehicleSent = 'أرسلنا العربية للمراجعة';
  static const deleteVehicle = 'احذف العربية';
  static const deleteVehicleConfirm = 'تحذف العربية دي؟';

  // Driver mode
  static const passengerMode = 'راكب';
  static const driverMode = 'سائق';
  static const tabMyTrips = 'رحلاتي';
  static const driverHomeTitle = 'رحلاتك وطلباتك';
  static const postTrip = 'انشر رحلة';
  static const editTrip = 'تعديل الرحلة';
  static const newRequests = 'طلبات جديدة';
  static const noRequests = 'ما في طلبات حجز جديدة';
  static const upcomingTrips = 'رحلاتك القادمة';
  static const noDriverTripsTitle = 'ما عندك رحلات';
  static const noDriverTripsBody = 'انشر رحلتك الجاية، والركاب يلقوها في البحث ويرسلوا ليك طلبات الحجز.';
  static const ongoing = 'الجارية';
  static const balanceOwed = 'المستحق عليك';
  static String freeBookingsLeft(int n) =>
      Fmt.count(n, one: 'باقي ليك حجز مجاني واحد', two: 'باقي ليك حجزين مجانيين', few: 'حجوزات مجانية باقية', many: 'حجز مجاني باقي');
  static String dueOn(String date) => 'موعد السداد: $date';
  static const frozenBanner = 'حسابك مجمّد لحد ما تسدّد المستحق. رحلاتك المقبولة ماشة عادي، لكن ما بتقدر تنشر رحلات أو تقبل طلبات جديدة.';
  static const limitBanner = 'وصلت الحد الأعلى للمستحق. سدّد عشان تقدر تقبل طلبات جديدة.';
  static const noApprovedVehicle = 'ما عندك عربية معتمدة. أضف عربية أو انتظر مراجعتها.';
  static const payNow = 'سدّد';
  static const tripVehicle = 'العربية';
  static const departureDate = 'تاريخ الانطلاق';
  static const departureTime = 'ساعة الانطلاق';
  static const chooseTime = 'اختر الساعة';
  static const seatsOffered = 'المقاعد المعروضة';
  static const pricePerSeat = 'سعر المقعد (جنيه)';
  static const pickupPointsHelp = 'أماكن معروفة يتلمّ فيها الركاب، مثلاً: موقف شندي — بحري';
  static const dropoffPointsHelp = 'أماكن النزول في مدينة الوصول';
  static const pointHint = 'اسم المكان';
  static const addPoint = 'أضف نقطة';
  static const pointRequired = 'اكتب نقطة واحدة على الأقل';
  static const luggagePerPassenger = 'الشنط المسموحة لكل راكب';
  static const largeBags = 'شنط كبيرة';
  static const smallBags = 'شنط صغيرة';
  static const extraBagPriceLabel = 'سعر الشنطة الإضافية (اتركه فاضي لو ما بتاخد)';
  static const acceptParcelsSwitch = 'أقبل ارساليات في الرحلة دي';
  static const priceSmall = 'صغيرة';
  static const priceMedium = 'متوسطة';
  static const priceLarge = 'كبيرة';
  static const parcelPricesLabel = 'أسعار الارساليات (جنيه)';
  static const maxParcelsLabel = 'أقصى عدد ارساليات';
  static const womenOnlySwitch = 'رحلة للنساء والعوائل فقط';
  static const tripNotes = 'ملاحظات (اختياري): وقفات، تكييف، تدخين…';
  static const publishTrip = 'انشر الرحلة';
  static const tripPublished = 'تم نشر الرحلة';
  static const tripUpdated = 'تم تعديل الرحلة';
  static const priceRequired = 'اكتب سعر المقعد';
  static const timeRequired = 'اختر ساعة الانطلاق';
  static const parcelPricesRequired = 'اكتب أسعار الارساليات الثلاثة';
  static const manageTrip = 'إدارة الرحلة';
  static String seatsBooked(int booked, int total) => '$booked من $total مقاعد محجوزة';
  static const passengers = 'الركاب';
  static const noPassengersYet = 'ما في حجوزات على الرحلة دي لسه';
  static const startTrip = 'بدأت الرحلة';
  static const startTripConfirm = 'تأكّد إنك اتحركت؟ الركاب بيوصلهم إشعار.';
  static const finishTrip = 'وصلنا — أكمل الرحلة';
  static const finishTripConfirm = 'تأكّد إن الرحلة اكتملت؟';
  static const cancelTrip = 'ألغِ الرحلة';
  static const cancelTripReason = 'سبب الإلغاء — بيظهر للركاب';
  static const cancelTripWarning = 'إلغاء رحلة فيها ركاب مقبولين بيأثر على تقييمك، والإلغاء المتكرر بيوقف حسابك.';
  static const tripCancelled = 'تم إلغاء الرحلة';
  static const tripStarted = 'بالسلامة — بدأت الرحلة';
  static const tripCompleted = 'اكتملت الرحلة';
  static const accept = 'اقبل';
  static const reject = 'ارفض';
  static const rejectConfirm = 'ترفض الطلب ده؟';
  static const requestAccepted = 'قبلت الحجز — ظهر رقم الراكب';
  static const requestRejected = 'تم رفض الطلب';
  static String feeForBooking(String amount) => 'عمولة الحجز ده: $amount';
  static const feeFree = 'حجز مجاني — بدون عمولة';
  static const idUnderReview = 'هويته قيد المراجعة';
  static const markNoShow = 'ما حضر';
  static const markNoShowConfirm = 'تسجّل إن الراكب ما حضر؟ العمولة بترجع ليك، والراكب يقدر يعترض.';
  static const noShowMarked = 'تم تسجيل عدم الحضور';
  static const hiddenByAdmin = 'الرحلة مخفية من البحث بواسطة الإدارة. تواصل مع الدعم.';
  static String pendingCount(int n) => Fmt.count(n, one: 'طلب واحد بانتظارك', two: 'طلبين بانتظارك', few: 'طلبات بانتظارك', many: 'طلب بانتظارك');

  // Driver parcels
  static const parcelRequests = 'طلبات ارساليات';
  static const parcelsOnThisTrip = 'الارساليات';
  static const sender = 'المرسل';
  static String toReceiver(String name, String city) => 'إلى $name في $city';
  static const receiverPaysTag = 'المستلم يدفع';
  static const senderPaysTag = 'المرسل يدفع';
  static const confirmPickup = 'استلمت الارسالية — صوّرها';
  static const pickupHelp = 'طابق الارسالية مع صورة المرسل قبل ما تستلمها. لو مختلفة أو مشكوك فيها، ارفض الاستلام.';
  static const parcelPickedUpMsg = 'تم تسجيل استلام الارسالية';
  static const refusePickup = 'ارفض الاستلام';
  static const refuseReason = 'السبب (يظهر للمرسل)';
  static const parcelRefusedMsg = 'تم رفض استلام الارسالية';
  static const deliverParcel = 'سلّمت الارسالية — أدخل الرمز';
  static const enterDeliveryCode = 'رمز التسليم من المستلم';
  static const deliveryCodeHint = '4 أرقام';
  static const confirmDelivery = 'أكّد التسليم';
  static const parcelDeliveredMsg = 'تم تسليم الارسالية';
  static String attemptsLeft(int n) => 'باقي $n محاولات لإدخال الرمز';
  static const parcelAcceptedMsg = 'قبلت الارسالية';
  static const parcelRejectedMsg = 'تم رفض الارسالية';
  static const senderPhoto = 'صورة المرسل';

  // Balance and payments
  static const balanceTitle = 'الرصيد والسداد';
  static const walletBalance = 'رصيد المحفظة';
  static const nothingOwed = 'ما عليك مستحقات';
  static String freezesOn(String date) => 'لو ما سدّدت، يتجمّد الحساب في $date';
  static const overdue = 'فات موعد السداد';
  static String pendingPayment(String amount) => 'دفعة $amount قيد المراجعة';
  static String settleEvery(int days) => 'السداد كل $days أيام';
  static const payBalance = 'سدّد المستحق';
  static const ledger = 'كشف الحساب';
  static const myPayments = 'دفعاتي';
  static const noLedger = 'ما في حركات لسه';
  static const payTitle = 'سداد المستحق';
  static const choosePaymentMethod = 'اختر طريقة الدفع';
  static const noPaymentMethods = 'ما في طرق دفع متاحة حالياً. تواصل مع الدعم.';
  static const accountName = 'اسم الحساب';
  static const accountNumber = 'رقم الحساب';
  static const copyNumber = 'انسخ';
  static const agentLocations = 'أماكن الدفع كاش';
  static const amountPaid = 'المبلغ المدفوع (جنيه)';
  static const amountRequired = 'اكتب المبلغ';
  static const referenceNumber = 'رقم العملية';
  static const referenceRequired = 'اكتب رقم العملية من الإشعار';
  static const receiptPhoto = 'صورة الإشعار';
  static const receiptHelp = 'لقطة شاشة من تطبيق البنك أو صورة الإيصال.';
  static const receiptRequired = 'أرفق صورة الإشعار';
  static const chooseMethodFirst = 'اختر طريقة الدفع';
  static const sendPayment = 'أرسل الدفعة للمراجعة';
  static const paymentSent = 'وصلتنا الدفعة — بنراجعها وبنحدّث رصيدك';

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
