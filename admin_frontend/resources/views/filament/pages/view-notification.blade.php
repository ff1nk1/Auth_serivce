<x-filament-panels::page>
    @php
        $n = $this->notification;
        $recordId = \App\Filament\Pages\ViewNotification::recordIdFrom($n);
        $payloadJson = json_encode($n['payload'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    @endphp

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6 space-y-4 text-sm">
        <dl class="grid gap-3 sm:grid-cols-[minmax(8rem,auto)_1fr]">
            <dt class="font-medium text-gray-500 dark:text-gray-400">Record ID</dt>
            <dd class="break-all font-mono text-xs">{{ $recordId }}</dd>

            <dt class="font-medium text-gray-500 dark:text-gray-400">Message ID</dt>
            <dd>{{ $n['message_id'] ?? '—' }}</dd>

            <dt class="font-medium text-gray-500 dark:text-gray-400">Event type</dt>
            <dd>{{ $n['event_type'] ?? '—' }}</dd>

            <dt class="font-medium text-gray-500 dark:text-gray-400">Recipient name</dt>
            <dd>{{ $n['name'] ?? '—' }}</dd>

            <dt class="font-medium text-gray-500 dark:text-gray-400">Email</dt>
            <dd>{{ $n['email'] ?? '—' }}</dd>

            <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt>
            <dd>
                <x-filament::badge>{{ $n['status'] ?? '—' }}</x-filament::badge>
            </dd>

            <dt class="font-medium text-gray-500 dark:text-gray-400">Retries</dt>
            <dd>{{ $n['retries'] ?? 0 }}</dd>

            <dt class="font-medium text-gray-500 dark:text-gray-400">Moved to DLQ</dt>
            <dd>{{ ! empty($n['moved_to_dlq']) ? 'Yes' : 'No' }}</dd>

            @if (! empty($n['created_at']))
                <dt class="font-medium text-gray-500 dark:text-gray-400">Created at</dt>
                <dd>{{ $n['created_at'] }}</dd>
            @endif

            @if (! empty($n['time']))
                <dt class="font-medium text-gray-500 dark:text-gray-400">Sent at (unix)</dt>
                <dd>{{ $n['time'] }}</dd>
            @endif
        </dl>

        @if (! empty($n['error_message']))
            <div class="rounded-lg border border-danger-300 bg-danger-50 p-4 dark:border-danger-500/50 dark:bg-danger-500/10">
                <p class="font-medium text-danger-700 dark:text-danger-400">Send error</p>
                <p class="mt-1 text-danger-600 dark:text-danger-300">{{ $n['error_message'] }}</p>
            </div>
        @endif

        <div>
            <p class="mb-2 font-medium text-gray-700 dark:text-gray-200">Payload</p>
            <pre class="max-h-96 overflow-auto rounded-lg bg-gray-50 p-4 text-xs dark:bg-gray-950">{{ $payloadJson ?: '—' }}</pre>
        </div>
    </div>
</x-filament-panels::page>
