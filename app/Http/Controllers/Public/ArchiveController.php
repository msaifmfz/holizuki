<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Concerns\BuildsPublicPostCards;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Support\DbExpressions;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class ArchiveController extends Controller
{
    use BuildsPublicPostCards;

    public function __invoke(Request $request, ?string $year = null, ?string $month = null): Response
    {
        $selectedYear = $year === null ? null : (int) $year;
        $selectedMonth = $month === null ? null : (int) $month;
        $postsQuery = $this->publicPostQuery();

        if ($selectedYear !== null) {
            $postsQuery->whereYear('published_at', $selectedYear);
        }

        if ($selectedMonth !== null) {
            $postsQuery->whereMonth('published_at', $selectedMonth);
        }

        if ($selectedYear !== null && ! (clone $postsQuery)->exists()) {
            abort(404);
        }

        $posts = $postsQuery
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Post $post): array => $this->postCard($post));
        $page = max(1, $request->integer('page', 1));

        return Inertia::render('public/archive', [
            'period' => $this->period($selectedYear, $selectedMonth),
            'periods' => $this->periods(),
            'posts' => $posts,
            'seo' => Seo::make(
                title: $this->title($selectedYear, $selectedMonth),
                canonical: route('public.archive', array_filter([
                    'year' => $selectedYear,
                    'month' => $selectedMonth === null ? null : sprintf('%02d', $selectedMonth),
                    'page' => $page > 1 ? $page : null,
                ], static fn (mixed $value): bool => $value !== null)),
                prevUrl: $posts->previousPageUrl(),
                nextUrl: $posts->nextPageUrl(),
            ),
        ]);
    }

    /** @return array{year: int|null, month: int|null, label: string} */
    private function period(?int $year, ?int $month): array
    {
        $label = match (true) {
            $year === null => 'All posts',
            $month === null => (string) $year,
            default => Date::create($year, $month, 1)->format('F Y'),
        };

        return ['year' => $year, 'month' => $month, 'label' => $label];
    }

    /** @return list<array{year: int, months: list<array{year: int, month: int, label: string, posts_count: int}>, posts_count: int}> */
    private function periods(): array
    {
        $yearExpression = DbExpressions::yearOf('published_at');
        $monthExpression = DbExpressions::monthOf('published_at');

        $rows = Post::query()
            ->published()
            ->whereNotNull('published_at')
            ->selectRaw($yearExpression.' as archive_year, '.$monthExpression.' as archive_month, count(*) as posts_count')
            ->groupByRaw($yearExpression.', '.$monthExpression)
            ->orderByRaw('archive_year desc, archive_month desc')
            ->get();

        $periods = [];

        foreach ($rows as $row) {
            $year = $row->getAttribute('archive_year');
            $month = $row->getAttribute('archive_month');
            $postsCount = $row->getAttribute('posts_count');
            if (! is_numeric($year)) {
                continue;
            }
            if (! is_numeric($month)) {
                continue;
            }
            if (! is_numeric($postsCount)) {
                continue;
            }

            $year = (int) $year;
            $month = (int) $month;
            $postsCount = (int) $postsCount;

            $periods[$year] ??= ['year' => $year, 'months' => [], 'posts_count' => 0];
            $periods[$year]['months'][] = [
                'year' => $year,
                'month' => $month,
                'label' => Date::create($year, $month, 1)->format('F'),
                'posts_count' => $postsCount,
            ];
            $periods[$year]['posts_count'] += $postsCount;
        }

        return array_values($periods);
    }

    private function title(?int $year, ?int $month): string
    {
        $period = $this->period($year, $month)['label'];

        return ($year === null ? 'Archive' : $period.' archive').' — '.Seo::siteName();
    }
}
