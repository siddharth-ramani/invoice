@props(['records'])
<div class="app-server-pagination"><p class="app-muted">{{ __('Showing :first–:last of :total records',['first'=>$records->firstItem() ?? 0,'last'=>$records->lastItem() ?? 0,'total'=>$records->total()]) }}</p>
@if($records->hasPages())
<nav aria-label="{{ __('Pagination') }}"><ul class="pagination">
<li class="page-item {{ $records->onFirstPage() ? 'disabled' : '' }}">@if($records->onFirstPage())<span class="page-link" aria-disabled="true">{{ __('Previous') }}</span>@else<a class="page-link" href="{{ $records->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>@endif</li>
@php $pages = array_unique(array_filter([1,$records->currentPage()-1,$records->currentPage(),$records->currentPage()+1,$records->lastPage()],fn($page)=>$page>=1 && $page<=$records->lastPage())); sort($pages); $previous=0; @endphp
@foreach($pages as $page)
@if($previous && $page>$previous+1)<li class="page-item disabled"><span class="page-link" aria-hidden="true">…</span></li>@endif
<li class="page-item {{ $page === $records->currentPage() ? 'active' : '' }}">@if($page === $records->currentPage())<span class="page-link" aria-current="page">{{ $page }}</span>@else<a class="page-link" href="{{ $records->url($page) }}" aria-label="{{ __('Go to page :page',['page'=>$page]) }}">{{ $page }}</a>@endif</li>
@php $previous=$page; @endphp
@endforeach
<li class="page-item {{ $records->hasMorePages() ? '' : 'disabled' }}">@if($records->hasMorePages())<a class="page-link" href="{{ $records->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>@else<span class="page-link" aria-disabled="true">{{ __('Next') }}</span>@endif</li>
</ul></nav>
@endif
</div>
