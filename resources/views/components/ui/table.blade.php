@props([
  'minWidth' => '880px', // force horizontal scroll on small screens
  'stickyHead' => true,
])
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full" style="min-width: {{ $minWidth }};">
      @isset($head)
        <thead class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide {{ $stickyHead ? 'sticky top-0 z-10' : '' }}">
          {{ $head }}
        </thead>
      @endisset

      <tbody class="divide-y divide-slate-100 text-sm">
        {{ $slot }}
      </tbody>

      @isset($foot)
        <tfoot>
          {{ $foot }}
        </tfoot>
      @endisset
    </table>
  </div>
</div>
