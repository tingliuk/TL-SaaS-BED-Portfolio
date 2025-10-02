<x-admin-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight">
            {{ __('Category Admin Trash') }}
        </h2>
    </x-slot>

    <section class="py-6 mx-12 space-y-4">

        <nav class="flex flex-row justify-between">
            <div class="flex gap-4 justify-end">

                <x-link-primary-button class="" href="{{ route('admin.categories.create') }}">
                    <i class="fa-solid fa-plus pr-2"></i>
                    {{ __('Add Category') }}
                </x-link-primary-button>

                <x-link-secondary-button href="{{ route('admin.categories.index') }}"
                                         class="bg-gray-900 text-white hover:bg-gray-500">
                    <i class="fa-solid fa-list pr-2"></i>
                    {{ __('All Categories') }}
                </x-link-secondary-button>

                <form method="post"
                      action="{{ route('admin.categories.trash.recover.all') }}"
                      class="">
                    @csrf
                    <x-primary-button class="justify-center bg-gray-500! hover:bg-gray-900! text-white"
                                      type="submit">
                        <i class="fa-solid fa-recycle text-lg pr-2"></i>
                        {{ __('Recover All') }}
                    </x-primary-button>
                </form>

                <form method="post"
                      action="{{ route('admin.categories.trash.remove.all') }}"
                      class="">
                    @csrf
                    @method('delete')
                    <x-secondary-button class="overflow-hidden justify-center"
                                        type="submit">
                        <i class="fa-solid fa-delete-left text-lg pr-2"></i>
                        {{ __('Remove All') }}
                    </x-secondary-button>
                </form>
            </div>


            <div class="flex gap-6">

                <form action="{{ route('admin.categories.trash') }}" name="searchForm"
                      class="flex flex-inline gap-2 align-top">
                    <x-text-input name="search" class="px-2 py-1 border border-gray-200" :value="$search??''"/>
                    <x-primary-button class="" type="submit">
                        <i class="fa-solid fa-search pr-2"></i>
                        {{ __(' Search Deleted') }}
                    </x-primary-button>
                </form>

                <x-link-secondary-button href="{{ route('admin.categories.trash') }}">
                    <i class="fa-solid fa-list pr-2"></i>
                    {{ __(' All Deleted') }}
                </x-link-secondary-button>

            </div>

        </nav>


        <table class="table w-full">
            <thead class="bg-black text-white overflow-hidden">
            <tr>
                <td class="w-2/3 p-2 rounded-tl-lg">
                    {{ __('Title') }}
                </td>
                <td class="p-2 pr-8 text-right">
                    {{ __('# Jokes') }}
                </td>
                <td class="w-1/6 p-2 rounded-tr-lg">
                    {{ __('Actions') }}
                </td>
            </tr>
            </thead>
            <tbody>

            @forelse($categories as $category)
                <tr class="hover:bg-gray-300 transition">
                    <td class="p-2 font-medium ">
                        {{ $category->title }}
                    </td>

                    <td class="p-2 ">
                        {{ $category->jokes_count }}
                    </td>

                    <td class="p-2 flex gap-4">

                        <form method="post"
                              action="{{ route('admin.categories.trash.recover.one', $category) }}">
                            @csrf

                            <x-primary-button class="overflow-hidden justify-center"
                                              type="submit">
                                <i class="fa-solid fa-recycle text-lg"></i>
                                <span class="sr-only">
                                    {{ __('Recover') }}
                                </span>
                            </x-primary-button>
                        </form>

                        <form method="post"
                              action="{{ route('admin.categories.trash.remove.one', $category) }}">
                            @csrf

                            @method('delete')

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
                    <td colspan="3" class="p-2 pt-4">
                        {{ __('No items in the trash') }}
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
