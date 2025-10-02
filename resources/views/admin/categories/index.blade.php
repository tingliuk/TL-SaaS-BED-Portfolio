<x-admin-layout>

    <x-slot name="header">
        {{--        h2.font-semibold.text-xl.text-white.leading-tight --}}
        <h2 class="font-semibold text-xl text-white leading-tight">
            {{ __('Category Admin') }}
        </h2>
    </x-slot>

    <section class="py-6 mx-12 space-y-4">

        <nav class="flex flex-row justify-between">

            <x-link-primary-button
                href="{{ route('admin.categories.create') }}">
                <i class="fa-solid fa-plus pr-2"></i>
                {{ __('Add Category') }}
            </x-link-primary-button>

            <div class="flex gap-6">

                {{-- TODO: Need to add trash route --}}
                <x-link-secondary-button
                    href="{{ route('admin.categories.index') }}">
                    @if($trashCount>0)
                        <i class="fa-solid fa-trash pr-2 text-black"></i>
                        {{ __('Trash is full') }}
                    @else
                        <i class="fa-solid fa-trash-can pr-2"></i>
                        {{ __('Trash is empty') }}
                    @endif
                </x-link-secondary-button>

                <form action="{{ route('admin.categories.index') }}"
                      name="searchForm"
                      class="flex flex-inline gap-2 align-top">

                    <x-text-input name="search"
                                  class="px-2 py-1 border border-gray-200"
                                  :value="$search??''"/>

                    <x-primary-button type="submit">
                        <i class="fa-solid fa-search pr-2"></i>
                        {{ __('Search') }}
                    </x-primary-button>

                </form>

                <x-link-secondary-button
                    href="{{ route('admin.categories.index') }}">
                    <i class="fa-solid fa-list pr-2"></i>
                    {{ __('Show All') }}
                </x-link-secondary-button>
            </div>

        </nav>

        <table class="table w-full">
            <thead class="bg-black text-gray-200 overflow-hidden">
            <tr>
                <th class="p-2 w-2/3 rounded-tl-lg">
                    {{ __('Title') }}
                </th>
                <th class="p-2 pr-8 text-right">
                    {{ __('No. Jokes') }}
                </th>
                <th class="p-2 w-1/6 rounded-tr-lg">
                    {{ __('Actions') }}
                </th>
            </tr>
            </thead>
            <tbody>

            @forelse($categories as $category)
                <tr>
                    <td class="p-2 font-medium border-b border-b-gray-400">
                        {{ $category->title }}
                    </td>
                    <td class="p-2 pr-8 text-right border-b border-b-gray-400">
                        {{ $category->jokes_count }}
                    </td>
                    <td class="p-2 border-b border-b-gray-400">
                        <form
                            action="{{ route('admin.categories.delete', $category) }}"
                            class="grid grid-cols-3 gap-4"
                            method="post">

                            <x-link-primary-button
                                class="overflow-hidden justify-center"
                                href="{{ route('admin.categories.show', $category) }}">
                                <i class="fa-solid fa-eye text-lg"></i>
                                <span class="sr-only">
                                    {{ __('Show') }}
                                </span>
                            </x-link-primary-button>

                            <x-link-primary-button
                                class="bg-gray-700! hover:bg-gray-500! overflow-hidden justify-center"
                                href="{{ route('admin.categories.edit', $category) }}">
                                <i class="fa-solid fa-edit text-lg"></i>
                                <span class="sr-only">
                                     {{ __('Edit') }}
                                </span>
                            </x-link-primary-button>

                            <x-secondary-button class="overflow-hidden justify-center"
                                                type="submit">
                                <i class="fa-solid fa-delete-left text-lg"></i>
                                <span class="sr-only ">
                                    {{ __('Delete') }}
                                </span>
                            </x-secondary-button>

                        </form>
                    </td>
                </tr>

            @empty
            <tr>
                <td colspan="3" class="p-4">
                    {{ __('No categories available') }}
                </td>
            </tr>
            @endforelse
            </tbody>
            <tfoot>
            <tr>
                <td colspan="3" class="pt-4">
                    {{ $categories->links() }}
                </td>
            </tr>
            </tfoot>

        </table>

    </section>

</x-admin-layout>
