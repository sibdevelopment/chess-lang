{{-- Delegates to the role-specific sidebar. The request()->routeIs('*.quiz-sessions*')
     check inside each partial highlights the Quiz Sessions item. --}}
@php $role = auth()->user()?->role; @endphp
@switch($role)
  @case('admin')   @include('admin._sidebar')   @break
  @case('staff')   @include('staff._sidebar')   @break
  @case('coach')   @include('coach._sidebar')   @break
  @default         @include('student._sidebar')
@endswitch
