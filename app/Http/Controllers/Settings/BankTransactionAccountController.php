<?php


namespace App\Http\Controllers\Settings;


use App\Enums\BankTransactionAccountType;
use App\Facades\Accounts;
use App\Models\BankTransactionAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BankTransactionAccountController
{
    public function store(Request $request)
    {
        $account = Accounts::current();

        Gate::authorize('update', $account);

        $request->validate([
            'bank_account_type' => ['required', 'string', Rule::enum(BankTransactionAccountType::class)],
            'bank_account_name' => ['required', 'string', 'max:191'],
            'bank_account_iban' => ['required', 'string', 'max:191', Rule::unique(BankTransactionAccount::class, 'iban')->where('account_id', $account->id)],
        ]);

        /** @var BankTransactionAccount $bankAccount */
        $bankAccount = $account->bankTransactionAccounts()->create([
            'handle' => BankTransactionAccount::randomHandle(),
            'type' => ($bankAccountType = $request->enum('bank_account_type', BankTransactionAccountType::class)),
            'name' => $request->input('bank_account_name'),
            'iban' => $request->input('bank_account_iban'),
        ]);

        if ($bankAccountType->worksThroughMailNotifications()) {
            Inertia::flash('completeBankMailIntegration', [
                'email' => $bankAccount->getInboundMail(),
                'helpLink' => $bankAccountType->getConfigurationHelpLink(),
            ]);
        }
        return back();
    }

    public function destroy(Request $request, BankTransactionAccount $account)
    {
        Gate::authorize('delete', $account);

        $includingPayments = $request->boolean('payments');

        DB::transaction(function () use ($account, $includingPayments) {
            if ($includingPayments) {
                // TODO: Zmazat aj platby
            }

            $account->delete();
        });

        return back();
    }
}
