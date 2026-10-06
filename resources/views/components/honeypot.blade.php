{{-- Spam trap: hidden from people and screen readers, bots tend to fill it. --}}
<div class="hp-field" aria-hidden="true">
    <label for="hp-{{ $attributes->get('id', 'f') }}">Jangan isi kolom ini</label>
    <input id="hp-{{ $attributes->get('id', 'f') }}" name="kb_trap" type="text" tabindex="-1" autocomplete="off" value="">
</div>
