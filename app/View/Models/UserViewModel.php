<?php


namespace App\View\Models;


use App\Facades\Accounts;
use App\Models\Account;
use App\Models\User;
use StackTrace\Ui\ViewModel;

class UserViewModel extends ViewModel
{
    public function __construct(
        protected User $user
    ) { }

    public function toView(): array
    {
        $currentAccount = Accounts::current();

        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'emailVerifiedAt' => $this->user->email_verified_at,
            'avatar' => null,
            'accounts' => $this->user->accounts->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->company->business_name,
                'current' => $account->is($currentAccount),
            ]),
            'features' => (object) $this->user->features()->all(),
        ];
    }
}
