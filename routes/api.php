<?php

use Anomaly\UsersModule\User\Contract\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Generic headless authentication endpoints backed by Passport.
|
*/

Route::get('/user', function (Request $request) {
    return response()->json($request->user());
})->middleware('auth:api');

Route::post('/logout', function (Request $request) {
    $user = $request->user();

    if ($user && method_exists($user, 'token') && $user->token()) {
        $user->token()->revoke();
    }

    return response()->json(['status' => 'OK']);
})->middleware('auth:api');

Route::post('/login', function (Request $request, UserRepositoryInterface $users) {
    $credentials = Validator::make($request->all(), [
        'login' => 'nullable|string',
        'email' => 'nullable|email',
        'username' => 'nullable|string',
        'password' => 'required|string',
    ])->validate();

    $login = $credentials['login']
        ?? $credentials['email']
        ?? $credentials['username']
        ?? null;

    if (!$login) {
        return response()->json([
            'message' => 'A login identifier is required.',
        ], 422);
    }

    $configuredLogin = config('anomaly.module.users::config.login', 'email');
    $resolvedUser = filter_var($login, FILTER_VALIDATE_EMAIL)
        ? $users->findByEmail($login)
        : (method_exists($users, 'findByUsername') ? $users->findByUsername($login) : null);

    if (!$resolvedUser && !filter_var($login, FILTER_VALIDATE_EMAIL)) {
        $resolvedUser = $users->findByEmail($login);
    }

    $grantUsername = $login;

    if ($resolvedUser) {
        $grantUsername = $configuredLogin === 'username'
            ? $resolvedUser->username
            : $resolvedUser->email;
    }

    $tokenRequest = Request::create(
        '/oauth/token',
        'POST',
        [
            'grant_type' => 'password',
            'client_id' => env('REACT_CLIENT_ID'),
            'client_secret' => env('REACT_CLIENT_SECRET'),
            'username' => $grantUsername,
            'password' => $credentials['password'],
            'scope' => '',
        ],
        $request->cookies->all(),
        [],
        $request->server->all()
    );

    $tokenRequest->headers->replace($request->headers->all());
    $tokenRequest->headers->set('Accept', 'application/json');
    $tokenRequest->headers->set('Content-Type', 'application/x-www-form-urlencoded');

    if ($request->hasSession()) {
        $tokenRequest->setLaravelSession($request->session());
    }

    $response = App::handle($tokenRequest);
    $payload = json_decode($response->getContent(), true);

    if ($response->getStatusCode() >= 400) {
        return response()->json($payload ?: ['message' => 'Login failed.'], $response->getStatusCode());
    }

    if ($resolvedUser) {
        $payload['user'] = $resolvedUser->toArray();
    }

    return response()->json($payload, $response->getStatusCode());
});

Route::post('/register', function (Request $request, UserRepositoryInterface $users) {
    $data = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|string|min:8',
        'username' => 'nullable|string|max:150',
        'first_name' => 'nullable|string|max:150',
        'last_name' => 'nullable|string|max:150',
        'display_name' => 'nullable|string|max:255',
        'auto_login' => 'nullable|boolean',
    ])->validate();

    if ($users->findByEmail($data['email'])) {
        return response()->json([
            'message' => 'A user with that email already exists.',
        ], 422);
    }

    $username = $data['username'] ?? Str::slug((string) $data['email'], '-');

    if (method_exists($users, 'findByUsername') && $users->findByUsername($username)) {
        $username = $username . '-' . Str::lower(Str::random(6));
    }

    $savedUser = $users->create([
        'email' => $data['email'],
        'username' => $username,
        'display_name' => $data['display_name'] ?? trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
        'first_name' => $data['first_name'] ?? '',
        'last_name' => $data['last_name'] ?? '',
        'enabled' => true,
        'activated' => true,
    ]);

    $user = $users->find($savedUser->id);
    $user->password = $data['password'];
    $users->save($user);

    $configuredLogin = config('anomaly.module.users::config.login', 'email');
    $grantUsername = $configuredLogin === 'username'
        ? $user->username
        : $user->email;

    $responsePayload = [
        'status' => 'OK',
        'user' => $user->fresh()->toArray(),
    ];

    if ($request->boolean('auto_login', true)) {
        $tokenRequest = Request::create(
            '/oauth/token',
            'POST',
            [
                'grant_type' => 'password',
                'client_id' => env('REACT_CLIENT_ID'),
                'client_secret' => env('REACT_CLIENT_SECRET'),
                'username' => $grantUsername,
                'password' => $data['password'],
                'scope' => '',
            ],
            $request->cookies->all(),
            [],
            $request->server->all()
        );

        $tokenRequest->headers->replace($request->headers->all());
        $tokenRequest->headers->set('Accept', 'application/json');
        $tokenRequest->headers->set('Content-Type', 'application/x-www-form-urlencoded');

        if ($request->hasSession()) {
            $tokenRequest->setLaravelSession($request->session());
        }

        $tokenResponse = App::handle($tokenRequest);
        $tokenPayload = json_decode($tokenResponse->getContent(), true);

        if ($tokenResponse->getStatusCode() < 400 && is_array($tokenPayload)) {
            $responsePayload = array_merge($responsePayload, $tokenPayload);
        }
    }

    return response()->json($responsePayload, 201);
});
