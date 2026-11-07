@if(session('success'))<div class="alert alert-success app-feedback" role="status"><x-ui.icon name="check" /><div>{{ __(session('success')) }}</div></div>@endif
@if(session('error'))<div class="alert alert-danger app-feedback" role="alert"><x-ui.icon name="info" /><div>{{ __(session('error')) }}</div></div>@endif
@if($errors->any())
<div class="alert alert-danger app-feedback" role="alert" tabindex="-1" id="form-feedback"><x-ui.icon name="info" /><div><strong>{{ __('Please check the highlighted fields.') }}</strong><ul>@foreach($errors->all() as $message)<li>{{ __($message) }}</li>@endforeach</ul></div></div>
@endif
