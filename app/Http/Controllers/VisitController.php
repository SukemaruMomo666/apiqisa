<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Illuminate\Http\Request;
use Carbon\Carbon;

class VisitController extends Controller
{
    // Fungsi menerima data dari website (POST)
    public function store(Request $request)
    {
        Visit::create([
            'session_id' => $request->sessionId ?? 'anonymous',
            'url' => $request->url,
            'referrer' => $request->referrer,
            'device' => $request->device ?? (preg_match('/Mobile/i', $request->header('User-Agent')) ? 'Mobile' : 'Desktop'),
            'utm_source' => $request->utm_source,
            'utm_medium' => $request->utm_medium,
            'utm_campaign' => $request->utm_campaign,
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json(['success' => true]);
    }

    // Fungsi ngirim data ke /cekpengunjung (GET)
    public function index(Request $request)
    {
        $query = Visit::query();
        $filter = $request->query('filter', 'all');

        // Filter Tanggal
        if ($filter !== 'all') {
            if ($filter === 'today') {
                $query->whereDate('created_at', Carbon::today());
            } elseif ($filter === 'week') {
                $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
            } elseif ($filter === 'month') {
                $query->whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year);
            } elseif ($filter === 'year') {
                $query->whereYear('created_at', Carbon::now()->year);
            } elseif ($filter === 'custom' && $request->has('start') && $request->has('end')) {
                $query->whereBetween('created_at', [
                    Carbon::parse($request->start)->startOfDay(), 
                    Carbon::parse($request->end)->endOfDay()
                ]);
            }
        }

        $data = $query->get();
        $totalVisitors = $data->count();
        $todayVisitors = Visit::whereDate('created_at', Carbon::today())->count(); 
        
        $adClicks = $data->filter(fn($v) => $v->utm_source || $v->utm_campaign)->count();
        $conversionRate = $totalVisitors > 0 ? round(($adClicks / $totalVisitors) * 100, 1) . '%' : '0%';

        // Hitung Sumber Traffic
        $sourcesCount = [];
        foreach ($data as $v) {
            $sourceName = "Direct";
            if ($v->utm_source) {
                $sourceName = $v->utm_source;
            } elseif ($v->referrer && !str_contains($v->referrer, 'qisa.studio')) {
                $host = parse_url($v->referrer, PHP_URL_HOST);
                $sourceName = $host ? str_replace('www.', '', $host) : 'Direct';
            }
            $sourcesCount[$sourceName] = ($sourcesCount[$sourceName] ?? 0) + 1;
        }

        arsort($sourcesCount);
        $colors = ["bg-blue-500", "bg-accent", "bg-emerald-500", "bg-purple-500", "bg-orange-500"];
        $sources = [];
        $i = 0;
        foreach (array_slice($sourcesCount, 0, 5) as $name => $count) {
            $sources[] = ['name' => $name, 'count' => $count, 'color' => $colors[$i % count($colors)]];
            $i++;
        }

        // Live Feed 10 terakhir
        $recentVisits = $data->sortByDesc('created_at')->take(10)->values()->map(function($v) {
            $sourceName = "Direct";
            if ($v->utm_source) $sourceName = $v->utm_source;
            elseif ($v->referrer) {
                $host = parse_url($v->referrer, PHP_URL_HOST);
                if ($host) $sourceName = str_replace('www.', '', $host);
            }
            return [
                'id' => (string) $v->id,
                'source' => $sourceName,
                'utm_campaign' => $v->utm_campaign ?? '-',
                'device' => $v->device,
                'time' => $v->created_at->diffForHumans()
            ];
        });

        return response()->json([
            'totalVisitors' => $totalVisitors,
            'todayVisitors' => $todayVisitors,
            'adClicks' => $adClicks,
            'conversionRate' => $conversionRate,
            'sources' => $sources,
            'recentVisits' => $recentVisits
        ]);
    }
}
