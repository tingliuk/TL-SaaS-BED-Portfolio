<x-admin-layout>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight">
            {{ __('Category Admin') }}
        </h2>
    </x-slot>


    <section class="py-12 mx-12 space-y-4">

        <nav class="flex flex-row justify-between">
            <x-link-primary-button class="bg-gray-500!" href="{{ route('admin.categories.index') }}">
                All Categories
            </x-link-primary-button>

        </nav>

        <form class="flex flex-col gap-4"
              method="post"
              action="{{ route('admin.categories.update', $category) }}"
        >
            @csrf
            @method('patch')

            <h3>Edit Category Details</h3>
            <div class="flex flex-col gap-1">
                <x-input-label for="title" :value="__('Title')"/>

                <x-text-input id="title" class="block mt-1 w-full"
                              type="text"
                              name="title"
                              value="{{ old('description') ?? $category->title }}"
                />

                <x-input-error :messages="$errors->get('title')" class="mt-2"/>
            </div>

            <div class="flex flex-col gap-1">
                <x-input-label for="description" :value="__('Description')"/>

                <x-text-input id="description" class="block mt-1 w-full"
                              type="text"
                              name="description"
                              value="{{ old('description') ?? $category->description }}"/>

                <x-input-error :messages="$errors->get('description')" class="mt-2"/>
            </div>

            <div class="flex flex-row justify-start">
                <x-input-label></x-input-label>
                <x-primary-button type="submit" class="mr-6 px-12">
                    <i class="fa-solid fa-save pr-2 text-lg"></i>
                    Save
                </x-primary-button>
                <x-link-secondary-button href="{{ back() }}">
                    <i class="fa-solid fa-cancel pr-2 text-lg"></i>
                    Cancel
                </x-link-secondary-button>
            </div>

        </form>

    </section>

</x-admin-layout>
