@extends('layouts.app')
@section('page-title', 'Quiz Bank')

@section('sidebar-nav')
@include('quizzes._sidebar')
@endsection

@php
  $u = auth()->user();
  $role = $u->role;
  $rolePrefix = $role;
  $isReadOnlyDatabase = $u?->isStaff();
  $createRoute  = "$rolePrefix.quizzes.create";
  $editRoute    = "$rolePrefix.quizzes.edit";
  $destroyRoute = "$rolePrefix.quizzes.destroy";
  $importRoute   = "$rolePrefix.quizzes.import";
  $indexRoute   = "$rolePrefix.quizzes.index";

  // Determine drilldown stage
  $stage = 'weeks';
  if ($weekFilter !== null && $weekFilter !== '' && $categoryFilter) $stage = 'quizzes';
  elseif ($weekFilter !== null && $weekFilter !== '') $stage = 'categories';

  $catColors = [
    'Chess'             => ['#dcfce7', '#166534'],
    'Science'           => ['#dbeafe', '#1e40af'],
    'Funny'             => ['#fce7f3', '#9d174d'],
    'Riddles'           => ['#ede9fe', '#6d28d9'],
    'General Knowledge' => ['#fef3c7', '#92400e'],
    'World'             => ['#cffafe', '#0e7490'],
    // Legacy fallbacks
    'GK'    => ['#fef3c7', '#92400e'],
    'Maths' => ['#dbeafe', '#1e40af'],
    'Chess' => ['#dcfce7', '#166534'],
    'Fun'   => ['#fce7f3', '#9d174d'],
    'Other' => ['#e0e7ff', '#4338ca'],
  ];
@endphp

@push('styles')
<style>
  /* ── Drilldown tiles ─────────────────────────────────────────────── */
  .qb-breadcrumb{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text2);margin-bottom:18px;flex-wrap:wrap}
  .qb-breadcrumb a{color:var(--accent);font-weight:600;text-decoration:none}
  .qb-breadcrumb a:hover{text-decoration:underline}
  .qb-breadcrumb .sep{color:var(--text3)}

  .qb-tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-bottom:24px}
  .qb-tile{display:block;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:22px;text-decoration:none;color:var(--text);transition:border-color .15s var(--ease);position:relative;overflow:hidden}
  .qb-tile:hover{border-color:var(--accent)}
  .qb-tile-title{font-size:18px;font-weight:800;letter-spacing:-.01em;margin-bottom:4px}
  .qb-tile-sub{font-size:12px;color:var(--text3);font-weight:500}
  .qb-tile-count{position:absolute;top:14px;right:14px;background:var(--accent-50);color:var(--accent-dark);font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px}

  /* category tile color variants */
  .qb-cat-tile{display:block;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;text-decoration:none;color:var(--text);transition:all .2s var(--ease);position:relative;overflow:hidden}
  .qb-cat-tile:hover{border-color:var(--accent)}

  /* ── Quiz card list (final stage) ──────────────────────────────── */
  .qb-quiz-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;transition:all .2s var(--ease);box-shadow:var(--shadow-xs)}
  .qb-quiz-card:hover{box-shadow:var(--shadow);border-color:var(--border-strong)}
  .qb-quiz-num{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:8px;background:var(--accent);color:#fff;font-weight:800;font-size:13px;margin-right:8px}
  .qb-question-img{max-height:140px;max-width:100%;object-fit:contain;border-radius:8px;margin-bottom:8px;background:#f3f4f6}
  .qb-opt-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:8px}
  .qb-opt{border:1px solid var(--border);border-radius:8px;padding:8px 10px;font-size:12px;display:flex;align-items:center;gap:6px;background:#fff}
  .qb-opt.right{border-color:#10b981;background:#ecfdf5;font-weight:600;color:#065f46}
  .qb-opt-letter{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:6px;background:var(--surface3);font-weight:700;font-size:11px;flex-shrink:0}
  .qb-opt.right .qb-opt-letter{background:#10b981;color:#fff}
  .qb-opt-img{max-height:50px;max-width:100%;object-fit:contain;border-radius:4px}

  .qb-empty{text-align:center;padding:60px 24px;border:2px dashed var(--border);border-radius:14px;background:var(--surface2)}

  @media (max-width:540px){
    .qb-tiles{grid-template-columns:1fr 1fr;gap:10px}
    .qb-tile{padding:16px}
    .qb-tile-title{font-size:15px}
  }
</style>
@endpush

@section('content')

<x-ui.page-header title="Quiz Bank">
  @unless($isReadOnlyDatabase)
    <x-slot:actions>
      <x-ui.button :href="route($importRoute)" variant="secondary">Upload Weekly CSV</x-ui.button>
      <x-ui.button :href="route($createRoute)" variant="primary">New Quiz</x-ui.button>
    </x-slot:actions>
  @endunless
</x-ui.page-header>

{{-- Breadcrumb --}}
<div class="qb-breadcrumb">
  <a href="{{ route($indexRoute) }}">All weeks</a>
  @if($stage !== 'weeks')
    <span class="sep">/</span>
    <a href="{{ route($indexRoute, ['week' => $weekFilter]) }}">Week {{ $weekFilter }}</a>
  @endif
  @if($stage === 'quizzes')
    <span class="sep">/</span>
    <span style="color:var(--text);font-weight:600">{{ $categoryFilter }}</span>
  @endif
</div>

<div class="sh">
  <div class="sh-title">
    @if($stage === 'weeks')        Pick a week
    @elseif($stage === 'categories') Week {{ $weekFilter }} — pick a category
    @else                            Week {{ $weekFilter }} · {{ $categoryFilter }}
    @endif
  </div>
</div>

{{-- ──────────── STAGE 1 — WEEK TILES ──────────── --}}
@if($stage === 'weeks')

  @if($weekTiles->isEmpty())
    <div class="qb-empty">
      <div class="font-bold mb-2" style="font-size:16px">No quizzes yet</div>
      @unless($isReadOnlyDatabase)
        <a class="btn btn-primary" href="{{ route($createRoute) }}">New Quiz</a>
      @endunless
    </div>
  @else
    <div class="qb-tiles">
      @foreach($weekTiles as $w)
        <a class="qb-tile" href="{{ route($indexRoute, ['week' => $w->week]) }}">
          <span class="qb-tile-count">{{ $w->cnt }} {{ $w->cnt === 1 ? 'quiz' : 'quizzes' }}</span>
          <div class="qb-tile-title">Week {{ $w->week }}</div>
          <div class="qb-tile-sub">View categories</div>
        </a>
      @endforeach
    </div>
  @endif

{{-- ──────────── STAGE 2 — CATEGORY TILES ──────────── --}}
@elseif($stage === 'categories')

  @if($categoryTiles->isEmpty())
    <div class="qb-empty">
      <div class="font-bold mb-2" style="font-size:16px">No quizzes for week {{ $weekFilter }}</div>
      <p class="text-muted mb-3">All entries for this week were deleted.</p>
      <a class="btn btn-outline" href="{{ route($indexRoute) }}">Back to Weeks</a>
    </div>
  @else
    <div class="qb-tiles">
      @foreach($categoryTiles as $c)
        @php
          $clrs  = $catColors[$c->category] ?? ['#f1f5f9','#475569'];
        @endphp
        <a class="qb-cat-tile" href="{{ route($indexRoute, ['week' => $weekFilter, 'category' => $c->category]) }}"
           style="border-left:4px solid {{ $clrs[1] }}">
          <span class="qb-tile-count" style="background:{{ $clrs[0] }};color:{{ $clrs[1] }}">{{ $c->cnt }} {{ $c->cnt === 1 ? 'quiz' : 'quizzes' }}</span>
          <div class="qb-tile-title">{{ $c->category }}</div>
          <div class="qb-tile-sub">View questions</div>
        </a>
      @endforeach
    </div>
  @endif

{{-- ──────────── STAGE 3 — QUIZ LIST ──────────── --}}
@else

  @if($quizzes->isEmpty())
    <div class="qb-empty">
      <div class="font-bold mb-2" style="font-size:16px">No quizzes in {{ $categoryFilter }} for week {{ $weekFilter }}</div>
      @unless($isReadOnlyDatabase)
        <a class="btn btn-primary" href="{{ route($createRoute) }}">New Quiz</a>
      @endunless
    </div>
  @else
    <div class="grid-2">
      @foreach($quizzes as $quiz)
        <div class="qb-quiz-card">
          <div class="flex items-center justify-between mb-2" style="gap:8px;flex-wrap:wrap">
            <div style="font-weight:700;font-size:13px;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
              <span class="qb-quiz-num">{{ $loop->iteration }}</span>
              <span class="badge {{ $quiz->type === 'picture' ? 'badge-live' : 'badge-student' }}">
                {{ $quiz->type === 'picture' ? 'Picture' : 'Text' }}
              </span>
              <span class="badge badge-active">Age {{ $quiz->age_min }}–{{ $quiz->age_max }}</span>
            </div>
            <div class="text-xs text-muted">{{ $quiz->time_limit_seconds }} seconds</div>
          </div>

          @if($quiz->type === 'picture' && $quiz->image_path)
            <img src="{{ $quiz->questionImageUrl() }}" alt="" class="qb-question-img">
          @endif
          @if($quiz->question_text)
            <div style="font-size:14px;font-weight:600;margin-bottom:8px;line-height:1.4">{{ $quiz->question_text }}</div>
          @endif

          <div class="qb-opt-grid">
            @foreach($quiz->optionsForRender() as $i => $opt)
              @php $isCorrect = $i === (int)$quiz->correct_index; @endphp
              <div class="qb-opt {{ $isCorrect ? 'right' : '' }}">
                <span class="qb-opt-letter">{{ ['A','B','C','D'][$i] }}</span>
                @if($opt['kind'] === 'image' && $opt['image'])
                  <img src="{{ $opt['image'] }}" class="qb-opt-img">
                @else
                  <span style="flex:1;min-width:0">{{ $opt['text'] }}</span>
                @endif
                @if($isCorrect)<span style="margin-left:auto">Correct</span>@endif
              </div>
            @endforeach
          </div>

          @unless($isReadOnlyDatabase)
            <div class="flex gap-2 mt-3">
              <a class="btn btn-sm btn-outline" href="{{ route($editRoute, $quiz) }}">Edit</a>
            </div>
          @endunless
        </div>
      @endforeach
    </div>
  @endif

@endif

@endsection
