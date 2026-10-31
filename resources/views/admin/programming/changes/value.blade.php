@foreach($values as $key => $value)
@if(!str_ends_with($key, '_id') && !str_ends_with($key, '_ids'))<div>{{ is_array($value) ? implode(', ', $value) : $value }}</div>@endif
@endforeach
