<div>
	@if ($f->key !== 'intro')<label>{{ $f->label }}@if ($f->required) *@endif</label>@endif
	@if ($f->type === 'textarea')
	<textarea name="{{ $f->name }}" rows="5" maxlength="{{ $f->max }}" @if ($f->required) required @endif>{{ $f->value }}</textarea>
	@elseif ($f->type === 'select')
	<select name="{{ $f->name }}" @if ($f->required) required @endif>
		<option value="">{{ lang('commerce.af_choose') }}</option>
		@foreach ($f->options as $af_opt)<option value="{{ $af_opt }}" @if ($f->value === $af_opt) selected @endif>{{ $af_opt }}</option>@endforeach
	</select>
	@elseif ($f->type === 'file')
	<input type="file" name="{{ $f->name }}" accept=".pdf,.jpg,.jpeg,.png,.webp" @if ($f->required && $f->value === '') required @endif />
	@if ($f->file_url !== '')<small class="af-file"><a href="{{ $f->file_url }}" target="_blank" rel="noopener">{{ lang('commerce.af_file_view') }}</a> · {{ lang('commerce.af_file_replace') }}</small>@endif
	@else
	<input type="{{ in_array($f->input, ['tel', 'email'], true) ? $f->input : 'text' }}" name="{{ $f->name }}" maxlength="{{ $f->max }}" value="{{ $f->value }}" @if ($f->type === 'number') inputmode="decimal" @endif @if ($f->required) required @endif />
	@endif
	@if ($f->help !== '' && $f->key !== 'shop_id')<small class="af-help">{{ $f->help }}</small>@endif
</div>
