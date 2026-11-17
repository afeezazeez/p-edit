# PromptEdit

Laravel, Vue, and Inertia image-generation assessment app backed by Pixelforge.

## Operator cost report

All accounts use the regular sign-in flow. New accounts have the `user` role. To grant operator access after deploying the role migration, run this once in the production Laravel Cloud Commands console:

```bash
php artisan tinker
```

Then at the Tinker prompt, promote the intended operator account by email:

```php
DB::table('users')->where('email', 'operator@example.com')->update(['role' => 'admin']);
```

That account can then sign in normally and open **Cost report**. No public admin registration or separate admin login exists. The report compares successful generation costs grouped by UTC completion day with Pixelforge's usage report. Profit is user charges minus Pixelforge costs and excludes other operating costs.
