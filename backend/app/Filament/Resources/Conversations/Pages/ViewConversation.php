<?php

namespace App\Filament\Resources\Conversations\Pages;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Services\AuditLogger;
use Filament\Resources\Pages\ViewRecord;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        // Reading a private chat is itself an auditable action.
        app(AuditLogger::class)->log('chat.viewed', $this->getRecord());
    }
}
