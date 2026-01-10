<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Redirect the user to the GitHub authentication page.
     */
    public function redirectToGitHub(): RedirectResponse
    {
        return Socialite::driver('github')->redirect();
    }

    /**
     * Obtain the user information from GitHub.
     */
    public function handleGitHubCallback(): RedirectResponse
    {
        try {
            $githubUser = Socialite::driver('github')->user();

            // Find or create user
            $user = User::updateOrCreate(
                ['github_id' => $githubUser->getId()],
                [
                    'username' => $githubUser->getNickname() ?? $githubUser->getName(),
                    'email' => $githubUser->getEmail(),
                    'github_avatar_url' => $githubUser->getAvatar(),
                ]
            );

            // Log the user in
            Auth::login($user, true);

            return redirect()->intended('/chat');
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Authentication failed. Please try again.');
        }
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

