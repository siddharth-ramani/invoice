@props(['action', 'version' => null, 'message' => __('Delete this record? This action cannot be undone.')])
<form method="POST" action="{{ $action }}" data-confirm="{{ $message }}">@csrf @method('DELETE') @if($version)<input type="hidden" name="original_version" value="{{ $version }}">@endif<x-ui.button variant="danger" icon="trash" class="btn-sm">{{ __('Delete') }}</x-ui.button></form>
