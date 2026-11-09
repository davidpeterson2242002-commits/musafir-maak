<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Parcel;
use App\Models\Trip;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Reports from booking / trip / chat / profile (spec 5.10). */
class ReportController extends Controller
{
    /** POST /reports {reported_user_id, reason, details?, subject_type?, subject_id?} */
    public function store(Request $request, ReportService $reports): JsonResponse
    {
        $data = $request->validate([
            'reported_user_id' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'details' => ['nullable', 'string', 'max:2000'],
            'subject_type' => ['nullable', 'required_with:subject_id', 'in:booking,parcel,trip,conversation'],
            'subject_id' => ['nullable', 'required_with:subject_type', 'integer'],
        ], [], ['reason' => 'سبب البلاغ', 'details' => 'التفاصيل']);

        $subject = isset($data['subject_type']) ? match ($data['subject_type']) {
            'booking' => Booking::findOrFail($data['subject_id']),
            'parcel' => Parcel::findOrFail($data['subject_id']),
            'trip' => Trip::findOrFail($data['subject_id']),
            'conversation' => Conversation::findOrFail($data['subject_id']),
        } : null;

        $report = $reports->create($request->user(), User::findOrFail($data['reported_user_id']), ReportReason::from($data['reason']), $data['details'] ?? null, $subject);

        return response()->json([
            'message' => 'شكراً، وصلنا بلاغك وسيراجعه فريقنا.'.($report->priority ? ' بلاغات السلامة لها أولوية.' : ''),
            'data' => ['id' => $report->id, 'status' => $report->status->value],
        ], 201);
    }
}
