<x-admin-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight">
            {{ __('Category Admin') }}
        </h2>
    </x-slot>


    <section class="py-12 mx-12 space-y-4">

        <form class="flex flex-col gap-4"
              method="post"
              action="{{ route('admin.categories.destroy', $category) }}"
        >
            @csrf
            @method('delete')

            <div class="my-6 bg-gray-200 text-red-500 rounded-lg font-semibold flex flex-row overflow-hidden">

                <p class="overflow-hidden p-6 bg-black"><i class="fa-solid fa-warning text-amber-500 text-7xl"></i>

                <div class="grow ml-6 p-6">
                <h3 class="pb-4 text-2xl">
                    {{ __('Warning! Deleting category') }} - {{ $category->title }}
                </h3>

                <p class="text-xl">
                    {{ __('Are you sure you wish to delete the') }} "{{ $category->title }}" {{ __('category') }}?
                </p>
                </div>
            </div>

            <div class="flex flex-row justify-start">
                <x-link-primary-button href="{{ route('admin.categories.index') }}"
                                         class="px-12 mr-6 ">
                    <i class="fa-solid fa-cancel text-lg pr-2"></i>
                    Cancel
                </x-link-primary-button>

                <x-secondary-button type="submit" class="mr-6 text-red-600">
                    <i class="fa-solid fa-delete-left pr-2 text-lg"></i>
                    Confirm
                </x-secondary-button>
            </div>

        </form>

    </section>

</x-admin-layout>
