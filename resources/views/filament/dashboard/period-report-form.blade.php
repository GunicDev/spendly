<div
    x-data="{
        from: '',
        to: '',
        type: 'all',
        generate() {
            this.$refs.from.setCustomValidity('');
            this.$refs.to.setCustomValidity('');

            if (! this.from) {
                this.$refs.from.setCustomValidity('Select a start date.');
                this.$refs.from.reportValidity();

                return;
            }

            if (! this.to) {
                this.$refs.to.setCustomValidity('Select an end date.');
                this.$refs.to.reportValidity();

                return;
            }

            if (this.from.localeCompare(this.to) === 1) {
                this.$refs.to.setCustomValidity('End date must be after or equal to start date.');
                this.$refs.to.reportValidity();

                return;
            }

            const url = new URL(@js(route('expenses.period-report.print')), window.location.origin);
            url.searchParams.set('from', this.from);
            url.searchParams.set('to', this.to);
            url.searchParams.set('type', this.type);

            window.open(url.toString(), '_blank', 'noopener');
        },
    }"
>
    <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
        
        <label class="block" style="flex: 1 1 150px;">
            <span class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                    Type
                </span>
            </span>
            <select
                class="fi-select-input block w-full rounded-lg border-none bg-white px-3 py-1.5 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 outline-none transition duration-75 focus:ring-2 focus:ring-primary-600 disabled:text-gray-500 disabled:ring-gray-950/20 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:focus:ring-primary-500 sm:text-sm sm:leading-6"
                x-model="type"
            >
                <option value="all">All</option>
                <option value="expense">Expense</option>
                <option value="income">Income</option>
            </select>
        </label>
        <label class="block" style="flex: 1 1 150px;">
            <span class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                    From <sup class="text-danger-600 dark:text-danger-400">*</sup>
                </span>
            </span>
            <input
                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-1.5 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 disabled:text-gray-500 disabled:ring-gray-950/20 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500 dark:focus:ring-primary-500 sm:text-sm sm:leading-6"
                type="date"
                x-ref="from"
                x-model="from"
                required
            >
        </label>
        <label class="block" style="flex: 1 1 150px;">
            <span class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                    To <sup class="text-danger-600 dark:text-danger-400">*</sup>
                </span>
            </span>
            <input
                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-1.5 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 disabled:text-gray-500 disabled:ring-gray-950/20 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500 dark:focus:ring-primary-500 sm:text-sm sm:leading-6"
                type="date"
                x-ref="to"
                x-model="to"
                required
            >
        </label>

    </div>

    <div class="flex flex-wrap items-center gap-3" style="margin-top: 1.5rem;">
        <button
            class="fi-btn fi-color-primary fi-btn-color-primary fi-size-md inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm outline-none transition duration-75 hover:bg-primary-500 focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-primary-500 dark:hover:bg-primary-400 dark:focus-visible:ring-primary-500"
            type="button"
            x-on:click="generate"
        >
            Generate report
        </button>

        <button
            style="margin-left: 1.0rem;"
            class="fi-btn fi-color-gray fi-btn-color-gray fi-size-md inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-950 shadow-sm ring-1 ring-gray-950/10 outline-none transition duration-75 hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:hover:bg-white/10 dark:focus-visible:ring-primary-500"
            type="button"
            x-on:click="$dispatch('close-modal', { id: $el.closest('[data-fi-modal-id]').dataset.fiModalId })"
        >
            Cancel
        </button>
    </div>
</div>
