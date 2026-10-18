<!-- Departments -->
@if($departments->isNotEmpty())
    <section>
        <div class="flex items-end justify-between gap-4 mb-3">
            <h2 class="font-display text-xl lg:text-2xl font-bold text-dark">{{ __('Shop by department') }}</h2>
            <a href="{{ route('categories.index') }}" class="shrink-0 whitespace-nowrap text-sm font-semibold text-primary hover:underline">{{ __('See all') }}</a>
        </div>
        <div class="grid grid-cols-4 lg:grid-cols-8 gap-2 lg:gap-3">
            @foreach($departments as $dept)
                <a href="{{ route('categories.show', $dept) }}" class="group bg-white border border-gray-200 rounded-xl p-2.5 lg:p-4 flex flex-col items-center text-center gap-2 hover:border-primary hover:shadow-sm">
                    <span class="w-11 h-11 lg:w-14 lg:h-14 rounded-full bg-primary-50 text-primary flex items-center justify-center group-hover:bg-primary group-hover:text-white transition"><x-icon :name="$dept->slug" class="w-5 h-5 lg:w-7 lg:h-7" /></span>
                    <span class="text-[11px] sm:text-xs lg:text-sm font-semibold leading-tight text-dark">{{ $dept->localized_name }}</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
