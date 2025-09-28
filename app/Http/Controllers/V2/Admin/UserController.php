<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserGenerate;
use App\Http\Requests\Admin\UserSendMail;
use App\Http\Requests\Admin\UserUpdate;
use App\Jobs\SendEmailJob;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuthService;
use App\Services\UserService;
use App\Traits\QueryOperators;
use App\Utils\Helper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    use QueryOperators;

    public function resetSecret(Request $request)
    {
        $user = User::find($request->input('id'));
        if (!$user)
            return $this->fail([400202, 'User does not exist']);
        $user->token = Helper::guid();
        $user->uuid = Helper::guid(true);
        return $this->success($user->save());
    }

    /**
     * Apply filters and sorts to the query builder
     *
     * @param Request $request
     * @param Builder $builder
     * @return void
     */
    private function applyFiltersAndSorts(Request $request, Builder $builder): void
    {
        $this->applyFilters($request, $builder);
        $this->applySorting($request, $builder);
    }

    /**
     * Apply filters to the query builder
     *
     * @param Request $request
     * @param Builder $builder
     * @return void
     */
    private function applyFilters(Request $request, Builder $builder): void
    {
        if (!$request->has('filter')) {
            return;
        }

        collect($request->input('filter'))->each(function ($filter) use ($builder) {
            $field = $filter['id'];
            $value = $filter['value'];

            $builder->where(function ($query) use ($field, $value) {
                $this->buildFilterQuery($query, $field, $value);
            });
        });
    }

    /**
     * Build the filter query based on field and value
     *
     * @param Builder $query
     * @param string $field
     * @param mixed $value
     * @return void
     */
    private function buildFilterQuery(Builder $query, string $field, mixed $value): void
    {
        // Handle association queries
        if (str_contains($field, '.')) {
            [$relation, $relationField] = explode('.', $field);
            $query->whereHas($relation, function ($q) use ($relationField, $value) {
                if (is_array($value)) {
                    $q->whereIn($relationField, $value);
                } else if (is_string($value) && str_contains($value, ':')) {
                    [$operator, $filterValue] = explode(':', $value, 2);
                    $this->applyQueryCondition($q, $relationField, $operator, $filterValue);
                } else {
                    $q->where($relationField, 'like', "%{$value}%");
                }
            });
            return;
        }

        // Handle array values for 'in' operations
        if (is_array($value)) {
            $query->whereIn($field === 'group_ids' ? 'group_id' : $field, $value);
            return;
        }

        // Handle operator-based filtering
        if (!is_string($value) || !str_contains($value, ':')) {
            $query->where($field, 'like', "%{$value}%");
            return;
        }

        [$operator, $filterValue] = explode(':', $value, 2);

        // Convert numeric strings to appropriate types
        if (is_numeric($filterValue)) {
            $filterValue = strpos($filterValue, '.') !== false
                ? (float) $filterValue
                : (int) $filterValue;
        }

        // Handle calculated fields
        $queryField = match ($field) {
            'total_used' => DB::raw('(u + d)'),
            default => $field
        };

        $this->applyQueryCondition($query, $queryField, $operator, $filterValue);
    }

    /**
     * Apply sorting to the query builder
     *
     * @param Request $request
     * @param Builder $builder
     * @return void
     */
    private function applySorting(Request $request, Builder $builder): void
    {
        if (!$request->has('sort')) {
            return;
        }

        collect($request->input('sort'))->each(function ($sort) use ($builder) {
            $field = $sort['id'];
            $direction = $sort['desc'] ? 'DESC' : 'ASC';
            $builder->orderBy($field, $direction);
        });
    }

    /**
     * Fetch paginated user list with filters and sorting
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function fetch(Request $request)
    {
        $current = $request->input('current', 1);
        $pageSize = $request->input('pageSize', 10);

        $userModel = User::with(['plan:id,name', 'invite_user:id,email', 'group:id,name'])
            ->select(DB::raw('*, (u+d) as total_used'));

        $this->applyFiltersAndSorts($request, $userModel);

        $users = $userModel->orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', $current);

        $users->getCollection()->transform(function ($user): array {
            return self::transformUserData($user);
        });

        return $this->paginate($users);
    }

    /**
     * Transform user data for response
     *
     * @param User $user
     * @return array<string, mixed>
     */
    public static function transformUserData(User $user): array
    {
        $user = $user->toArray();
        $user['balance'] = $user['balance'] / 100;
        $user['commission_balance'] = $user['commission_balance'] / 100;
        $user['subscribe_url'] = Helper::getSubscribeUrl($user['token']);
        return $user;
    }

    public function getUserInfoById(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => 'User ID cannot be empty'
        ]);
        $user = User::find($request->input('id'))->load('invite_user');
        return $this->success($user);
    }

    public function update(UserUpdate $request)
    {
        $params = $request->validated();

        $user = User::find($request->input('id'));
        if (!$user) {
            return $this->fail([400202, 'User does not exist']);
        }
        if (isset($params['email'])) {
            if (User::where('email', $params['email'])->first() && $user->email !== $params['email']) {
                return $this->fail([400201, 'Email is already in use']);
            }
        }
        // Handle password
        if (isset($params['password'])) {
            $params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);
            $params['password_algo'] = NULL;
        } else {
            unset($params['password']);
        }
        // Handle subscription plan
        if (isset($params['plan_id'])) {
            $plan = Plan::find($params['plan_id']);
            if (!$plan) {
                return $this->fail([400202, 'Subscription plan does not exist']);
            }
            $params['group_id'] = $plan->group_id;
        }
        // Handle invite user
        if ($request->input('invite_user_email') && $inviteUser = User::where('email', $request->input('invite_user_email'))->first()) {
            $params['invite_user_id'] = $inviteUser->id;
        } else {
            $params['invite_user_id'] = null;
        }

        if (isset($params['banned']) && (int) $params['banned'] === 1) {
            $authService = new AuthService($user);
            $authService->removeAllSessions();
        }
        if (isset($params['balance'])) {
            $params['balance'] = $params['balance'] * 100;
        }
        if (isset($params['commission_balance'])) {
            $params['commission_balance'] = $params['commission_balance'] * 100;
        }

        try {
            $user->update($params);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, 'Save failed']);
        }
        return $this->success(true);
    }

    /**
     * Export user data to CSV format
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function dumpCSV(Request $request)
    {
        ini_set('memory_limit', '-1');
        gc_enable(); // Enable garbage collection

        // Optimize query: use with to preload plan relationship, avoid N+1 problem
        $query = User::with('plan:id,name')
            ->orderBy('id', 'asc')
            ->select([
                'email',
                'balance',
                'commission_balance',
                'transfer_enable',
                'u',
                'd',
                'expired_at',
                'token',
                'plan_id'
            ]);

        $this->applyFiltersAndSorts($request, $query);

        $filename = 'users_' . date('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            // Open output stream
            $output = fopen('php://output', 'w');

            // Add BOM mark to ensure Excel displays Chinese correctly
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Write CSV header
            fputcsv($output, [
                'Email',
                'Balance',
                'Commission',
                'Total Traffic',
                'Remaining Traffic',
                'Plan Expiry Time',
                'Subscription Plan',
                'Subscription URL'
            ]);

            // Process data in batches to reduce memory usage
            $query->chunk(500, function ($users) use ($output) {
                foreach ($users as $user) {
                    try {
                        $row = [
                            $user->email,
                            number_format($user->balance / 100, 2),
                            number_format($user->commission_balance / 100, 2),
                            Helper::trafficConvert($user->transfer_enable),
                            Helper::trafficConvert($user->transfer_enable - ($user->u + $user->d)),
                            $user->expired_at ? date('Y-m-d H:i:s', $user->expired_at) : 'Permanent',
                            $user->plan ? $user->plan->name : 'No subscription',
                            Helper::getSubscribeUrl($user->token)
                        ];
                        fputcsv($output, $row);
                    } catch (\Exception $e) {
                        Log::error('CSV export error: ' . $e->getMessage(), [
                            'user_id' => $user->id,
                            'email' => $user->email
                        ]);
                        continue; // Continue processing next record
                    }
                }

                // Clean memory
                gc_collect_cycles();
            });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    public function generate(UserGenerate $request)
    {
        if ($request->input('email_prefix')) {
            $email = $request->input('email_prefix') . '@' . $request->input('email_suffix');

            if (User::where('email', $email)->exists()) {
                return $this->fail([400201, 'Email already exists in the system']);
            }

            $userService = app(UserService::class);
            $user = $userService->createUser([
                'email' => $email,
                'password' => $request->input('password') ?? $email,
                'plan_id' => $request->input('plan_id'),
                'expired_at' => $request->input('expired_at'),
            ]);

            if (!$user->save()) {
                return $this->fail([500, 'Generation failed']);
            }
            return $this->success(true);
        }

        if ($request->input('generate_count')) {
            return $this->multiGenerate($request);
        }
    }

    private function multiGenerate(Request $request)
    {
        $userService = app(UserService::class);
        $usersData = [];

        for ($i = 0; $i < $request->input('generate_count'); $i++) {
            $email = Helper::randomChar(6) . '@' . $request->input('email_suffix');
            $usersData[] = [
                'email' => $email,
                'password' => $request->input('password') ?? $email,
                'plan_id' => $request->input('plan_id'),
                'expired_at' => $request->input('expired_at'),
            ];
        }



        try {
            DB::beginTransaction();
            $users = [];
            foreach ($usersData as $userData) {
                $user = $userService->createUser($userData);
                $user->save();
                $users[] = $user;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail([500, 'Generation failed']);
        }

        // Check if CSV export is requested
        if ($request->input('download_csv')) {
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="users.csv"',
            ];
            $callback = function () use ($users, $request) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Account', 'Password', 'Expiry Time', 'UUID', 'Creation Time', 'Subscription URL']);
                foreach ($users as $user) {
                    $user = $user->refresh();
                    $expireDate = $user['expired_at'] === NULL ? 'Permanent' : date('Y-m-d H:i:s', $user['expired_at']);
                    $createDate = date('Y-m-d H:i:s', $user['created_at']);
                    $password = $request->input('password') ?? $user['email'];
                    $subscribeUrl = Helper::getSubscribeUrl($user['token']);
                    fputcsv($handle, [$user['email'], $password, $expireDate, $user['uuid'], $createDate, $subscribeUrl]);
                }
                fclose($handle);
            };
            return response()->streamDownload($callback, 'users.csv', $headers);
        }

        // Default return JSON
        $data = collect($users)->map(function ($user) use ($request) {
            return [
                'email' => $user['email'],
                'password' => $request->input('password') ?? $user['email'],
                'expired_at' => $user['expired_at'] === NULL ? 'Permanent' : date('Y-m-d H:i:s', $user['expired_at']),
                'uuid' => $user['uuid'],
                'created_at' => date('Y-m-d H:i:s', $user['created_at']),
                'subscribe_url' => Helper::getSubscribeUrl($user['token']),
            ];
        });
        return response()->json([
            'code' => 0,
            'message' => 'Batch generation successful',
            'data' => $data,
        ]);
    }

    public function sendMail(UserSendMail $request)
    {
        ini_set('memory_limit', '-1');
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $request->input('sort') ? $request->input('sort') : 'created_at';
        $builder = User::orderBy($sort, $sortType);
        $this->applyFiltersAndSorts($request, $builder);

        $subject = $request->input('subject');
        $content = $request->input('content');
        $templateValue = [
            'name' => admin_setting('app_name', 'XBoard'),
            'url' => admin_setting('app_url'),
            'content' => $content
        ];

        $chunkSize = 1000;

        $builder->chunk($chunkSize, function ($users) use ($subject, $templateValue, &$totalProcessed) {
            foreach ($users as $user) {
                dispatch(new SendEmailJob([
                    'email' => $user->email,
                    'subject' => $subject,
                    'template_name' => 'notify',
                    'template_value' => $templateValue
                ], 'send_email_mass'));
            }
        });

        return $this->success(true);
    }

    public function ban(Request $request)
    {
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $request->input('sort') ? $request->input('sort') : 'created_at';
        $builder = User::orderBy($sort, $sortType);
        $this->applyFilters($request, $builder);
        try {
            $builder->update([
                'banned' => 1
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, 'Processing failed']);
        }

        return $this->success(true);
    }

    /**
     * Delete user and associated data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:App\Models\User,id'
        ], [
            'id.required' => 'User ID cannot be empty',
            'id.exists' => 'User does not exist'
        ]);
        $user = User::find($request->input('id'));
        try {
            DB::beginTransaction();
            $user->orders()->delete();
            $user->codes()->delete();
            $user->stat()->delete();
            $user->tickets()->delete();
            $user->delete();
            DB::commit();
            return $this->success(true);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
            return $this->fail([500, 'Deletion failed']);
        }
    }
}
