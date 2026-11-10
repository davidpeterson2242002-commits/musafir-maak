import '../core/utils/api_time.dart';
import 'parcel.dart';
import 'trip.dart';

/// A parcel as the trip's driver sees it (spec D11/D12).
class DriverParcel {
  const DriverParcel({
    required this.id,
    required this.tripId,
    required this.status,
    required this.statusLabel,
    required this.sender,
    required this.description,
    required this.sizeLabel,
    required this.receiverName,
    required this.receiverCity,
    required this.payer,
    required this.price,
    this.senderPhone,
    this.receiverPhone,
    this.photoUrl,
    this.message,
    this.expiresAt,
    this.codeAttemptsLeft,
    this.feeAmount = 0,
    this.feeFree = false,
    this.conversationId,
  });

  final int id;
  final int tripId;
  final ParcelStatus status;
  final String statusLabel;
  final PublicUser sender;
  final String description;
  final String sizeLabel;
  final String receiverName;
  final String receiverCity;

  /// `sender` or `receiver`: who hands the driver the money.
  final String payer;
  final int price;

  /// Phones appear once the parcel is accepted.
  final String? senderPhone;
  final String? receiverPhone;

  /// The sender's photo, to compare with the real parcel at pickup.
  final String? photoUrl;
  final String? message;
  final DateTime? expiresAt;

  /// Wrong-code tries left before support has to step in (while picked up).
  final int? codeAttemptsLeft;
  final int feeAmount;
  final bool feeFree;
  final int? conversationId;

  bool get receiverPays => payer == 'receiver';

  factory DriverParcel.fromJson(Map<String, dynamic> json) {
    final fee = json['fee'] is Map ? json['fee'] as Map : const {};

    return DriverParcel(
      id: json['id'] as int,
      tripId: (json['trip_id'] as num?)?.toInt() ?? 0,
      status: ParcelStatus.parse(json['status'] as String?),
      statusLabel: json['status_label'] as String? ?? '',
      sender: PublicUser.fromJson(Map<String, dynamic>.from(json['sender'] as Map)),
      description: json['description'] as String? ?? '',
      sizeLabel: json['size_label'] as String? ?? '',
      receiverName: json['receiver_name'] as String? ?? '',
      receiverCity: json['receiver_city'] as String? ?? '',
      payer: json['payer'] as String? ?? 'sender',
      price: (json['price'] as num?)?.toInt() ?? 0,
      senderPhone: json['sender_phone'] as String?,
      receiverPhone: json['receiver_phone'] as String?,
      photoUrl: json['photo_url'] as String?,
      message: json['message'] as String?,
      expiresAt: ApiTime.instant(json['expires_at']),
      codeAttemptsLeft: (json['code_attempts_left'] as num?)?.toInt(),
      feeAmount: (fee['amount'] as num?)?.toInt() ?? 0,
      feeFree: fee['free'] as bool? ?? false,
      conversationId: (json['conversation_id'] as num?)?.toInt(),
    );
  }
}

/// `GET /driver/balance` (spec D13).
class DriverBalance {
  const DriverBalance({
    this.balanceOwed = 0,
    this.walletBalance = 0,
    this.settlementDueAt,
    this.freezesAt,
    this.overdue = false,
    this.frozen = false,
    this.freeBookingsRemaining = 0,
    this.pendingPayments = 0,
    this.cycleDays = 7,
  });

  final int balanceOwed;
  final int walletBalance;
  final DateTime? settlementDueAt;

  /// Due date plus the grace period.
  final DateTime? freezesAt;
  final bool overdue;
  final bool frozen;
  final int freeBookingsRemaining;

  /// Total of payments sent and still waiting for the finance team.
  final int pendingPayments;
  final int cycleDays;

  factory DriverBalance.fromJson(Map<String, dynamic> json) {
    final terms = json['fee_terms'] is Map ? json['fee_terms'] as Map : const {};

    return DriverBalance(
      balanceOwed: (json['balance_owed'] as num?)?.toInt() ?? 0,
      walletBalance: (json['wallet_balance'] as num?)?.toInt() ?? 0,
      settlementDueAt: ApiTime.wall(json['settlement_due_at']),
      freezesAt: ApiTime.wall(json['freezes_at']),
      overdue: json['overdue'] as bool? ?? false,
      frozen: json['status'] == 'frozen',
      freeBookingsRemaining: (json['free_bookings_remaining'] as num?)?.toInt() ?? 0,
      pendingPayments: (json['pending_payments'] as num?)?.toInt() ?? 0,
      cycleDays: (terms['cycle_days'] as num?)?.toInt() ?? 7,
    );
  }
}

/// One line of the driver's statement: a fee, a refund, a payment.
class LedgerEntry {
  const LedgerEntry({required this.id, required this.label, required this.amount, required this.balanceAfter, this.note, this.createdAt});

  final int id;
  final String label;

  /// Signed: positive adds to what the driver owes, negative reduces it.
  final int amount;
  final int balanceAfter;
  final String? note;
  final DateTime? createdAt;

  factory LedgerEntry.fromJson(Map<String, dynamic> json) => LedgerEntry(
        id: json['id'] as int,
        label: json['type_label'] as String? ?? '',
        amount: (json['amount'] as num?)?.toInt() ?? 0,
        balanceAfter: (json['balance_after'] as num?)?.toInt() ?? 0,
        note: json['note'] as String?,
        createdAt: ApiTime.wall(json['created_at']),
      );
}

/// A way to pay the platform, set up in the admin panel (bank, Fawry, cash agent…).
class PaymentMethod {
  const PaymentMethod({
    required this.id,
    required this.name,
    this.accountName,
    this.accountNumber,
    this.instructions,
    this.agentLocations = const [],
    this.requiresReference = false,
  });

  final int id;
  final String name;
  final String? accountName;
  final String? accountNumber;
  final String? instructions;
  final List<String> agentLocations;
  final bool requiresReference;

  factory PaymentMethod.fromJson(Map<String, dynamic> json) => PaymentMethod(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        accountName: json['account_name'] as String?,
        accountNumber: json['account_number'] as String?,
        instructions: json['instructions'] as String?,
        agentLocations: (json['agent_locations'] as List? ?? const []).map((e) => e.toString()).toList(),
        requiresReference: json['requires_reference'] as bool? ?? false,
      );
}

/// A payment the driver reported (spec D14).
class SettlementPayment {
  const SettlementPayment({
    required this.id,
    required this.amount,
    required this.method,
    required this.status,
    required this.statusLabel,
    this.rejectReason,
    this.createdAt,
  });

  final int id;
  final int amount;
  final String method;

  /// pending / confirmed / rejected.
  final String status;
  final String statusLabel;
  final String? rejectReason;
  final DateTime? createdAt;

  factory SettlementPayment.fromJson(Map<String, dynamic> json) => SettlementPayment(
        id: json['id'] as int,
        amount: (json['amount'] as num?)?.toInt() ?? 0,
        method: json['payment_method'] as String? ?? '',
        status: json['status'] as String? ?? 'pending',
        statusLabel: json['status_label'] as String? ?? '',
        rejectReason: json['reject_reason'] as String?,
        createdAt: ApiTime.wall(json['created_at']),
      );
}
