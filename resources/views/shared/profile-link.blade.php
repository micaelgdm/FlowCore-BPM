@include('shared.theme-toggle')
@php($profileInitials = collect(preg_split('/\s+/', trim(auth()->user()->name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''))
<a class="fc-button fc-button-secondary fc-profile-nav" href="{{ route('profile.edit') }}">
    @if (auth()->user()->profile_photo_path)
        <img class="fc-profile-avatar-image" src="{{ route('profile.photo') }}" alt="" aria-hidden="true">
    @else
        <span class="fc-profile-avatar" aria-hidden="true">{{ $profileInitials }}</span>
    @endif
    <span>Meu perfil</span>
</a>
