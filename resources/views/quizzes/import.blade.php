@extends('layouts.app')
@section('page-title', 'Upload Weekly Quizzes')

@section('sidebar-nav')
@include('quizzes._sidebar')
@endsection

@push('styles')
<style>
  .qi-wrap{max-width:900px;margin:0 auto}
  .qi-hero{padding:24px;border:1px solid #cbdaf8;border-radius:16px;background:linear-gradient(145deg,#fff,#f3f7ff);margin-bottom:16px}
  .qi-hero h2{font-size:20px;font-weight:800;margin:0 0 7px;color:#14213d}
  .qi-hero p{margin:0;color:var(--text2);font-size:13px;line-height:1.55}
  .qi-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:18px}
  .qi-step{padding:13px;border:1px solid #dfe6f3;border-radius:12px;background:#fff}
  .qi-step strong,.qi-step small{display:block}.qi-step strong{font-size:12px}.qi-step small{margin-top:4px;color:var(--text3);font-size:10px;line-height:1.4}
  .qi-upload{padding:26px;border:2px dashed var(--border-strong);border-radius:14px;background:var(--surface2);text-align:center}
  .qi-upload input{display:block;margin:14px auto 0;max-width:100%}
  .qi-categories{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px}.qi-categories span{padding:5px 9px;border-radius:999px;background:#edf3ff;color:#3157d5;font-size:10px;font-weight:700}
  .qi-columns{overflow:auto;margin-top:12px}.qi-columns code{white-space:nowrap;font-size:10px}
  @media(max-width:700px){.qi-steps{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
  $indexRoute = 'admin.quizzes.index';
@endphp
<div class="qi-wrap">
  <x-ui.page-header title="Upload Weekly Quizzes">
    <x-slot:actions><x-ui.button :href="route($indexRoute)" variant="secondary">Back to Quiz Bank</x-ui.button></x-slot:actions>
  </x-ui.page-header>

  <section class="qi-hero">
    <h2>One CSV can add many weeks and categories</h2>
    <p>Designed for children aged 5–8. Every row becomes one question with four options and one correct answer.</p>
    <div class="qi-steps">
      <article class="qi-step"><strong>1. Download template</strong><small>Keep the column names unchanged.</small></article>
      <article class="qi-step"><strong>2. Add your questions</strong><small>Use A, B, C or D in the correct-answer column.</small></article>
      <article class="qi-step"><strong>3. Upload once</strong><small>The Quiz Bank automatically groups questions by week and category.</small></article>
    </div>
  </section>

  @if($errors->any())
    <div class="ui-form-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <section class="card">
    <div class="aj-card-heading">
      <div><div class="card-title">CSV upload</div><p class="text-sm text-muted">Maximum 1,000 questions or 5 MB per upload.</p></div>
      <a class="btn btn-outline" href="{{ route('admin.quizzes.template') }}">Download CSV Template</a>
    </div>
    <form method="POST" action="{{ route('admin.quizzes.import.store') }}" enctype="multipart/form-data">
      @csrf
      <div class="qi-upload">
        <strong>Select completed CSV file</strong>
        <input type="file" name="quiz_file" accept=".csv,text/csv" required>
      </div>
      <div class="qi-categories">
        @foreach($defaultCategories as $category)<span>{{ $category }}</span>@endforeach
      </div>
      <div class="qi-columns"><strong class="text-xs">Required columns:</strong><br><code>week, category, age_min, age_max, question, option_a, option_b, option_c, option_d, correct_answer</code></div>
      <p class="text-xs text-muted mt-3">All uploaded questions automatically receive a fixed 2-minute answer time.</p>
      <div class="flex justify-end mt-4"><button class="btn btn-primary btn-lg" type="submit">Upload Questions</button></div>
    </form>
  </section>
</div>
@endsection
