@props(['types' => \App\Enums\IndexType::cases(), 'defaultType' => null, 'reportId' => null, 'class' => ''])

<div class="quick-add-bar {{ $class }}" x-data="quickAddBar"
    @click.outside="menuOpen = false" @keydown.escape.window="menuOpen = false"
    @keydown.ctrl.k.window.prevent="menuOpen = !menuOpen"
    @keydown.meta.k.window.prevent="menuOpen = !menuOpen">
    <div id="quick-add-options" x-cloak x-show="menuOpen" class="quick-add-options" aria-label="Choose entry type">
        <p class="section-kicker px-3 py-2">Add an entry</p>
        @foreach ($types as $type)
            <button type="button" class="quick-add-option" @click="openModal('{{ $type->value }}')" aria-haspopup="dialog">
                <span>{{ $type->label() }}</span><span aria-hidden="true">+</span>
            </button>
        @endforeach
    </div>
    <button type="button" class="primary-button" @click="menuOpen = !menuOpen"
        :aria-expanded="menuOpen" aria-controls="quick-add-options">
        <span aria-hidden="true">+</span> Add entry
    </button>
</div>
