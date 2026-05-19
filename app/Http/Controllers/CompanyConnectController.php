<?php

namespace App\Http\Controllers;

use App\Models\BulletinPost;
use App\Models\Company;
use App\Models\CompanyConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyConnectController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Connect/Index', $this->pageData($request));
    }

    public function spectate(Request $request, string $connectCode): Response
    {
        $user = $request->user();
        $myCompany = $user->company;
        abort_if(!$myCompany, 403);

        $company = Company::where('connect_code', strtoupper($connectCode))->with('users')->firstOrFail();

        if ($company->id === $myCompany->id) {
            return redirect()->route('connect')->withErrors(['lookup' => 'That is your own company.']);
        }

        $status = $myCompany->connectionStatusWith($company);
        $connectionId = null;

        if ($status === 'received_pending' || $status === 'accepted') {
            $conn = $myCompany->receivedConnections()
                ->where('from_company_id', $company->id)
                ->where('status', $status === 'accepted' ? 'accepted' : 'pending')
                ->first()
                ??
                $myCompany->sentConnections()
                ->where('to_company_id', $company->id)
                ->where('status', $status === 'accepted' ? 'accepted' : 'pending')
                ->first();
            $connectionId = $conn?->id;
        }

        $isConnected = $status === 'accepted';

        $projectsCount = \App\Models\Project::where('company_id', $company->id)->count();

        $partners = $company->connectedCompanies()->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'logo_url' => $c->logo_url,
                'color' => $c->color,
                'connect_code' => $c->connect_code,
            ];
        });

        $feedPosts = [];
        if ($isConnected) {
            $feedPosts = BulletinPost::where('company_id', $company->id)
                ->with('user')
                ->latest()
                ->take(10)
                ->get()
                ->map(function ($post) {
                    $parts = explode(' ', $post->user->name);
                    $initials = collect($parts)->take(2)->map(fn($p) => strtoupper(substr($p, 0, 1)))->join('');
                    return [
                        'id' => $post->id,
                        'content' => $post->content,
                        'image' => $post->image,
                        'created_at' => $post->created_at->diffForHumans(),
                        'user' => [
                            'name' => $post->user->name,
                            'initials' => $initials ?: strtoupper(substr($post->user->name, 0, 1)),
                        ],
                    ];
                });
        }

        $companyData = [
            'id' => $company->id,
            'name' => $company->name,
            'logo_url' => $company->logo_url,
            'color' => $company->color,
            'connect_code' => $company->connect_code,
            'admin' => $company->users()->where('is_company_admin', true)->first()?->name,
            'user_count' => $company->users()->count(),
            'connection_status' => $status,
            'connection_id' => $connectionId,
            'projects_count' => $projectsCount,
            'partners' => $partners,
        ];

        return Inertia::render('Connect/Profile', [
            'company' => $companyData,
            'myCompanyCode' => $myCompany->connect_code,
            'feedPosts' => $feedPosts,
            'isConnected' => $isConnected,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        $myCompany = $user->company;
        abort_if(!$myCompany, 403);

        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $target = Company::findOrFail($validated['company_id']);

        if ($target->id === $myCompany->id) {
            return redirect()->back()->withErrors(['connect' => 'You cannot connect with yourself.']);
        }

        $existing = CompanyConnection::where(function ($q) use ($myCompany, $target) {
            $q->where('from_company_id', $myCompany->id)->where('to_company_id', $target->id);
        })->orWhere(function ($q) use ($myCompany, $target) {
            $q->where('from_company_id', $target->id)->where('to_company_id', $myCompany->id);
        })->first();

        if ($existing) {
            return redirect()->back()->withErrors(['connect' => 'Connection already exists.']);
        }

        CompanyConnection::create([
            'from_company_id' => $myCompany->id,
            'to_company_id' => $target->id,
            'status' => 'pending',
        ]);

        return redirect()->back();
    }

    public function accept(CompanyConnection $connection): RedirectResponse
    {
        $myCompany = request()->user()->company;
        abort_if(!$myCompany, 403);
        abort_if($connection->to_company_id !== $myCompany->id, 403);

        $connection->update(['status' => 'accepted']);

        return redirect()->back();
    }

    public function decline(CompanyConnection $connection): RedirectResponse
    {
        $myCompany = request()->user()->company;
        abort_if(!$myCompany, 403);
        abort_if($connection->to_company_id !== $myCompany->id, 403);

        $connection->delete();

        return redirect()->back();
    }

    public function destroy(CompanyConnection $connection): RedirectResponse
    {
        $myCompany = request()->user()->company;
        abort_if(!$myCompany, 403);

        $belongsToMe = $connection->from_company_id === $myCompany->id
            || $connection->to_company_id === $myCompany->id;

        abort_if(!$belongsToMe, 403);

        $connection->delete();

        return redirect()->back();
    }

    public function pendingCount(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->company_id) {
            return response()->json(['count' => 0]);
        }

        $count = CompanyConnection::where('to_company_id', $user->company_id)
            ->where('status', 'pending')
            ->count();

        return response()->json(['count' => $count]);
    }

    private function pageData(Request $request): array
    {
        $user = $request->user();
        $company = $user->company;

        abort_if(!$company, 403, 'You are not part of a company.');

        $company->load(['sentConnections.receiver', 'receivedConnections.sender']);

        $myConnections = $company->connectedCompanies()->with('users')->get()->map(function ($c) use ($company) {
            $conn = $company->sentConnections()
                ->where('to_company_id', $c->id)
                ->where('status', 'accepted')
                ->first()
                ??
                $company->receivedConnections()
                ->where('from_company_id', $c->id)
                ->where('status', 'accepted')
                ->first();
            return [
                'connection_id' => $conn?->id,
                'id' => $c->id,
                'name' => $c->name,
                'logo_url' => $c->logo_url,
                'color' => $c->color,
                'connect_code' => $c->connect_code,
                'admin' => $c->users->where('is_company_admin', true)->first()?->name,
                'user_count' => $c->users->count(),
            ];
        });

        $pendingReceived = $company->receivedConnections()
            ->where('status', 'pending')
            ->with('sender')
            ->get()
            ->map(function ($conn) {
                return [
                    'id' => $conn->id,
                    'from_company' => [
                        'id' => $conn->sender->id,
                        'name' => $conn->sender->name,
                        'logo_url' => $conn->sender->logo_url,
                        'color' => $conn->sender->color,
                        'connect_code' => $conn->sender->connect_code,
                    ],
                ];
            });

        return [
            'myCompany' => [
                'id' => $company->id,
                'name' => $company->name,
                'logo_url' => $company->logo_url,
                'color' => $company->color,
                'connect_code' => $company->connect_code,
                'admin' => $company->users()->where('is_company_admin', true)->first()?->name,
                'user_count' => $company->users()->count(),
            ],
            'myConnections' => $myConnections,
            'pendingReceived' => $pendingReceived,
        ];
    }
}
