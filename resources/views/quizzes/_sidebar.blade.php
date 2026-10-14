@php $role = auth()->user()?->role; @endphp
@switch($role)
  @case('admin')   @include('admin._sidebar')   @break
  @case('staff')   @include('staff._sidebar')   @break
  @default         @include('admin._sidebar')
@endswitch
