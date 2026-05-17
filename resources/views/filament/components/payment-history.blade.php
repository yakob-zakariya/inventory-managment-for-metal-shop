<div class="fi-ta-ctn divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/10 dark:bg-gray-900 dark:ring-white/10">
    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 text-start dark:divide-white/5">
        <thead class="divide-y divide-gray-200 dark:divide-white/5">
            <tr class="bg-gray-50 dark:bg-white/5">
                <th class="fi-ta-header-cell px-3 py-3.5 sm:first-of-type:ps-6 sm:last-of-type:pe-6">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Date</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Amount</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Method</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Account</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Recorded By</span>
                </th>
                <th class="fi-ta-header-cell px-3 py-3.5 sm:last-of-type:pe-6">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">Notes</span>
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 whitespace-nowrap dark:divide-white/5">
            @foreach($payments as $payment)
                <tr class="fi-ta-row hover:bg-gray-50 dark:hover:bg-white/5">
                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                        <div class="fi-ta-col-wrp px-3 py-4">
                            <div class="text-sm text-gray-950 dark:text-white">
                                {{ $payment->payment_date->format('M d, Y') }}
                            </div>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                        <div class="fi-ta-col-wrp px-3 py-4">
                            <div class="text-sm font-semibold text-success-600 dark:text-success-400">
                                ETB {{ number_format($payment->amount, 2) }}
                            </div>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                        <div class="fi-ta-col-wrp px-3 py-4">
                            <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30" style="--c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
                                {{ $payment->payment_method->label() }}
                            </span>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                        <div class="fi-ta-col-wrp px-3 py-4">
                            <div class="text-sm text-gray-950 dark:text-white">
                                {{ $payment->account->name }}
                            </div>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                        <div class="fi-ta-col-wrp px-3 py-4">
                            <div class="text-sm text-gray-950 dark:text-white">
                                {{ $payment->user->name }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $payment->created_at->format('M d, Y H:i') }}
                            </div>
                        </div>
                    </td>
                    <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                        <div class="fi-ta-col-wrp px-3 py-4">
                            <div class="text-sm text-gray-950 dark:text-white">
                                {{ $payment->notes ? Str::limit($payment->notes, 30) : '-' }}
                            </div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
