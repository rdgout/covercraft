<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('dashboard.branch', [$repository, $branch]) }}" class="text-blue-600 hover:underline text-sm">&larr; {{ $branch }}</a>
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100 mt-2 font-mono">{{ $file->file_path }}</h1>
                <div class="flex items-center space-x-4 mt-1">
                    @php $pct = $file->coverage_percentage; @endphp
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $pct >= 80 ? 'bg-green-100 text-green-800' : ($pct >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                        {{ $pct }}% covered
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $file->covered_lines }}/{{ $file->total_lines }} lines</span>
                </div>
            </div>

            @if(isset($error))
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-yellow-800">{{ $error }}</p>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm font-mono border-collapse">
                        <tbody>
                            @if(!empty($sourceLines))
                                @foreach($sourceLines as $index => $line)
                                    @php
                                        $lineNum = $index + 1;
                                        $lineData = $lineCoverage[$lineNum] ?? null;
                                        $bgClass = $lineData !== null
                                            ? ($lineData['covered'] ? 'bg-green-50 dark:bg-green-950' : 'bg-red-50 dark:bg-red-950')
                                            : 'bg-white dark:bg-gray-800';
                                        $lineNumBg = $lineData !== null
                                            ? ($lineData['covered'] ? 'bg-green-100 dark:bg-green-900' : 'bg-red-100 dark:bg-red-900')
                                            : 'bg-gray-100 dark:bg-gray-700';
                                        $hitBg = $lineData !== null
                                            ? ($lineData['covered'] ? 'bg-green-200 dark:bg-green-800' : 'bg-red-200 dark:bg-red-800')
                                            : 'bg-gray-200 dark:bg-gray-600';
                                    @endphp
                                    <tr class="{{ $bgClass }}">
                                        <td class="px-3 py-0.5 text-right {{ $lineNumBg }} text-gray-600 dark:text-gray-400 select-none border-r border-gray-300 dark:border-gray-600 w-16">
                                            {{ $lineNum }}
                                        </td>
                                        <td class="px-2 py-0.5 text-center {{ $hitBg }} text-gray-700 dark:text-gray-300 select-none border-r border-gray-300 dark:border-gray-600 w-12">
                                            @if($lineData !== null)
                                                @if($lineData['covered'])
                                                    <span class="text-xs">{{ $lineData['count'] }}</span>
                                                @else
                                                    <span class="text-xs">0</span>
                                                @endif
                                            @else
                                                &nbsp;
                                            @endif
                                        </td>
                                        <td class="px-4 py-0.5 whitespace-pre text-gray-900 dark:text-gray-100">{{ $line ?: ' ' }}</td>
                                    </tr>
                                @endforeach
                            @else
                                @php
                                    $maxLine = !empty($lineCoverage) ? max(array_keys($lineCoverage)) : 0;
                                @endphp
                                @for($lineNum = 1; $lineNum <= $maxLine; $lineNum++)
                                    @php
                                        $lineData = $lineCoverage[$lineNum] ?? null;
                                        $bgClass = $lineData !== null
                                            ? ($lineData['covered'] ? 'bg-green-50 dark:bg-green-950' : 'bg-red-50 dark:bg-red-950')
                                            : 'bg-white dark:bg-gray-800';
                                    @endphp
                                    <tr class="{{ $bgClass }}">
                                        <td class="px-3 py-0.5 text-right text-gray-400 dark:text-gray-500 select-none border-r border-gray-200 dark:border-gray-700 w-12">{{ $lineNum }}</td>
                                        <td class="px-3 py-0.5 text-center w-12 border-r border-gray-200 dark:border-gray-700">
                                            @if($lineData !== null)
                                                @if($lineData['covered'])
                                                    <span class="text-green-600 text-xs">{{ $lineData['count'] }}x</span>
                                                @else
                                                    <span class="text-red-500 text-xs">0x</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-3 py-0.5 whitespace-pre text-gray-500 dark:text-gray-400 italic">
                                            (Source code unavailable - showing coverage data only)
                                        </td>
                                    </tr>
                                @endfor
                            @endif

                            @if(empty($sourceLines) && $maxLine === 0)
                                <tr>
                                    <td colspan="3" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">No coverage data available.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
