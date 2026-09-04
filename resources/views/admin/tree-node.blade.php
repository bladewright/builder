{{-- One place in the arrangement tree. Components hold; blocks link to their words. --}}
<li style="margin-left: {{ $depth * 1.25 }}rem">
    <div class="flex items-center gap-2 rounded-md px-2 py-1 {{ $node['kind'] === 'component' ? 'bg-gray-50 dark:bg-gray-800/60' : '' }}">
        <span class="inline-block rounded-full px-2 py-0.5 text-[0.6875rem] font-semibold {{ $node['kind'] === 'component' ? 'bg-bw-accent/10 text-bw-accent' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">{{ $node['type'] }}</span>
        @if ($node['url'])
            <a href="{{ $node['url'] }}" class="truncate font-semibold text-inherit hover:underline">{{ $node['name'] }}</a>
        @else
            <span class="truncate font-semibold">{{ $node['name'] }}</span>
        @endif
    </div>

    @if ($node['children'] !== [])
        <ul class="m-0 mt-1 list-none space-y-1 border-l border-gray-200 p-0 pl-3 dark:border-gray-700" style="margin-left: {{ $depth * 1.25 + 0.5 }}rem">
            @foreach ($node['children'] as $child)
                @include('bladewright::admin.tree-node', ['node' => $child, 'depth' => 0])
            @endforeach
        </ul>
    @endif
</li>
