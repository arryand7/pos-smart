# Gate User Identity Map

Graphify queries identify `app/Models/User.php` as the canonical authentication model. It has one-to-one `santri` and `wali` profiles; financial balances and limits belong to `Santri`, while transaction history references santri and cashier users. Roles are canonicalized by `App\Enums\UserRole` and checked by `EnsureSessionRole`/`EnsureRole`. Existing Gate OAuth uses `sso_sub`; provisioning adds the separate permanent `gate_user_uuid` and does not replace OAuth subjects or passwords.

Admin UI is server-rendered Blade through `resources/views/layouts/admin.blade.php`, with Vite entries `resources/css/app.css` and `resources/js/app.js`.
