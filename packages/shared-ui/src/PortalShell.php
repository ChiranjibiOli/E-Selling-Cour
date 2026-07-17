<?php

declare(strict_types=1);

namespace CourseHub\SharedUi;

final class PortalShell
{
    public static function page(string $title, string $body, string $accent = '#7a5c2e'): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $safeTitle . '</title>'
            . '<style>:root{--accent:' . htmlspecialchars($accent, ENT_QUOTES, 'UTF-8') . ';}'
            . '*{box-sizing:border-box}body{margin:0;background:#f4efe7;color:#191713;font-family:Inter,Arial,sans-serif}'
            . 'main{min-height:100vh;display:grid;place-items:center;padding:32px}.card{width:min(480px,100%);padding:32px;border:1px solid #d8cfc1;border-radius:24px;background:#fffdfa;box-shadow:0 24px 70px rgba(30,24,16,.12)}'
            . 'h1{margin:0 0 10px;font-family:Georgia,serif;font-size:42px;line-height:1}p{color:#686158;line-height:1.65}'
            . 'form{display:grid;gap:16px;margin-top:24px}label{display:grid;gap:7px;font-weight:700;font-size:14px}input{width:100%;min-height:48px;padding:0 14px;border:1px solid #cfc5b7;border-radius:12px;background:white;font:inherit}'
            . 'button,.button{min-height:48px;display:inline-flex;align-items:center;justify-content:center;padding:0 18px;border:0;border-radius:12px;background:var(--accent);color:white;font-weight:800;text-decoration:none;cursor:pointer}'
            . '.secondary{background:#211e19}.notice{margin-top:18px;padding:12px 14px;border-radius:12px;background:#f0e7d9;color:#51483d}.error{background:#fbe7e4;color:#812d23}.links{display:flex;flex-wrap:wrap;gap:12px;margin-top:18px}.eyebrow{color:var(--accent);font-size:12px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}</style>'
            . '</head><body><main><section class="card">' . $body . '</section></main></body></html>';
    }

    /** @param array<string, mixed>|null $result */
    public static function login(string $portal, string $heading, ?array $result = null, bool $allowSignup = false): string
    {
        $safePortal = htmlspecialchars($portal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeHeading = htmlspecialchars($heading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = '';

        if ($result !== null) {
            $ok = (bool) ($result['ok'] ?? false);
            $text = (string) (($result['data']['message'] ?? $result['data']['error'] ?? 'Request completed.'));
            $message = '<div class="notice' . ($ok ? '' : ' error') . '">' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
        }

        $signup = $allowSignup
            ? '<a class="button secondary" href="/register">Create account</a>'
            : '';

        return '<span class="eyebrow">' . ucfirst($safePortal) . ' portal</span>'
            . '<h1>' . $safeHeading . '</h1>'
            . '<p>This portal authenticates through the identity service. The interface and role policy remain isolated from the other portals.</p>'
            . $message
            . '<form method="post" action="/login">'
            . '<input type="hidden" name="portal" value="' . $safePortal . '">'
            . '<label>Email<input type="email" name="email" maxlength="150" autocomplete="username" required></label>'
            . '<label>Password<input type="password" name="password" maxlength="200" autocomplete="current-password" required></label>'
            . '<button type="submit">Sign in</button>'
            . '</form><div class="links">'
            . '<a class="button secondary" href="/oauth/google">Google OAuth</a>'
            . $signup
            . '</div>';
    }
}
