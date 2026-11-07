@props(['name' => 'chart'])
<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
@case('sparkles')<path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5zM3 3v4M1 5h4"/>@break
@case('bell')<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>@break
@case('support')<path d="M4 13v-1a8 8 0 0 1 16 0v1M4 12H2v7h4v-7zM20 12h2v7h-4v-7zM18 19c0 2-3 3-6 3"/>@break
@case('box')<path d="m12 2 9 5v10l-9 5-9-5V7zM3 7l9 5 9-5M12 12v10M7 4.5l9 5"/>@break
@case('document')<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>@break
@case('people')<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>@break
@case('rupee')<path d="M6 3h12M6 7h12M6 3c9 0 9 8 0 8l10 10"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('arrow')<path d="M5 12h14M14 7l5 5-5 5"/>@break
@case('download')<path d="M12 3v12M7 10l5 5 5-5M5 16v5h14v-5"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>@break
@case('tax')<path d="M9 3H5v18l4-2 3 2 3-2 4 2V3h-4M9 3v4h6V3zM8 12h8M8 16h5"/>@break
@case('home')<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>@break
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('close')<path d="m6 6 12 12M18 6 6 18"/>@break
@case('chevron')<path d="m9 5 7 7-7 7"/>@break
@case('down')<path d="m6 9 6 6 6-6"/>@break
@case('settings')<path d="m9 3-.6 2.2-2 .9-2-.6-2 3.5L4 10.5v2L2.4 14l2 3.5 2-.6 2 .9L9 20h4l.6-2.2 2-.9 2 .6 2-3.5-1.6-1.5v-2L19.6 9l-2-3.5-2 .6-2-.9L13 3z"/><circle cx="11" cy="11.5" r="3"/>@break
@case('logout')<path d="M9 3H4v18h5M10 12h11m-4-4 4 4-4 4"/>@break
@case('card')<rect x="2" y="4" width="20" height="16" rx="3"/><path d="M2 9h20M6 15h4"/>@break
@case('edit')<path d="m16 3 5 5-12 12-6 1 1-6zM14 5l5 5"/>@break
@case('trash')<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7"/>@break
@case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>@break
@case('check')<path d="m5 12 4 4L19 6"/>@break
@case('info')<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>@break
@case('print')<path d="M6 9V3h12v6M6 17H3V9h18v8h-3M6 14h12v7H6zM17 11h.01"/>@break
@case('back')<path d="M19 12H5m5-5-5 5 5 5"/>@break
@case('building')<path d="M4 21V3h11v18M15 9h5v12M8 7h3M8 11h3M8 15h3M2 21h20"/>@break
@case('eye')<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12"/><circle cx="12" cy="12" r="3"/>@break
@case('shield')<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6zM8 12l3 3 5-6"/>@break
@default<path d="M3 3v18h18M7 15l5-6 4 3 5-8"/>
@endswitch
</svg>
