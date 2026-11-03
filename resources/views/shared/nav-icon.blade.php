<svg class="fc-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($icon)
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            @break
        @case('groups')
            <circle cx="9" cy="7" r="3.5"/><path d="M2 20v-1.5A4.5 4.5 0 0 1 6.5 14h5a4.5 4.5 0 0 1 4.5 4.5V20zM16 4.2a3.5 3.5 0 0 1 0 6.6M18 14h.5a3.5 3.5 0 0 1 3.5 3.5V20h-4"/>
            @break
        @case('processes')
            <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
            @break
        @case('notifications')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>
            @break
        @case('audit')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2M3 4v5h5"/>
            @break
        @case('profile')
            <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
            @break
        @case('logout')
            <path d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>
            @break
        @case('tasks')
            <rect x="4" y="4" width="16" height="17" rx="2"/><path d="M9 4.5h6M8 11l2 2 5-5M8 17h8"/>
            @break
        @case('requests')
            <path d="M4 4h16v16H4zM4 13h4l2 3h4l2-3h4M8 8h8"/>
            @break
        @case('editor')
            <rect x="2.5" y="8" width="5" height="8" rx="1"/><rect x="16.5" y="3" width="5" height="7" rx="1"/><rect x="16.5" y="14" width="5" height="7" rx="1"/><path d="M7.5 12h4a3 3 0 0 0 3-3V6.5a3 3 0 0 1 3-3.5M11.5 12a3 3 0 0 1 3 3v.5a3 3 0 0 0 3 3.5"/>
            @break
        @case('form')
            <path d="M7 3h8l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM14 3v5h5M9 13h6M9 17h6"/><path d="M2 7v14a2 2 0 0 0 2 2h11"/>
            @break
        @case('access')
            <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/><path d="M9 12l2 2 4-4"/>
            @break
        @case('back')
            <path d="M19 12H5M12 19l-7-7 7-7"/>
            @break
        @case('open')
            <path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM12 11v6M9 14l3-3 3 3"/>
            @break
        @case('export')
            <path d="M12 3v12M7 10l5 5 5-5M4 20h16"/>
            @break
        @case('save')
            <path d="M5 3h12l4 4v14H3V5a2 2 0 0 1 2-2zM7 3v6h10V3M7 21v-8h10v8"/>
            @break
        @case('publish')
            <path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>
            @break
        @case('discard')
            <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v5M14 11v5"/>
            @break
    @endswitch
</svg>
