<?php

namespace App\Filament\Support;

use App\Models\Conversation;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;

/** Read-only chat transcript for admins (reports and flagged chats only — spec 5.8). */
final class ChatLog
{
    public static function entry(\Closure $conversation): RepeatableEntry
    {
        return RepeatableEntry::make('chat_log')
            ->hiddenLabel()
            ->state(function ($record) use ($conversation) {
                /** @var Conversation|null $c */
                $c = $conversation($record);

                return $c ? $c->messages()->with('sender')->orderBy('id')->limit(300)->get()->all() : [];
            })
            ->schema([
                Grid::make(12)->schema([
                    TextEntry::make('sender.name')->hiddenLabel()->weight('bold')->columnSpan(2),
                    TextEntry::make('body')->hiddenLabel()->columnSpan(6)
                        ->color(fn ($record) => $record->flagged ? 'danger' : null),
                    TextEntry::make('original_body')->hiddenLabel()->columnSpan(2)->placeholder('')
                        ->state(fn ($record) => $record->was_masked ? 'الأصل: '.$record->original_body : null)
                        ->color('warning')->size('sm'),
                    TextEntry::make('created_at')->hiddenLabel()->columnSpan(2)->size('sm')->color('gray')
                        ->dateTime('m-d H:i')->timezone(config('app.display_timezone')),
                ]),
            ])
            ->placeholder('لا توجد رسائل')
            ->contained(false);
    }
}
