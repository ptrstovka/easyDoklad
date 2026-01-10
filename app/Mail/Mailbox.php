<?php


namespace App\Mail;


use App\Banking\BankTransactionMailHandler;
use App\Models\BankTransactionAccount;
use BeyondCode\Mailbox\InboundEmail;
use Illuminate\Support\Str;
use ZBateson\MailMimeParser\Header\HeaderConsts;

class Mailbox
{
    public function __construct(
        protected BankTransactionMailHandler $bankTransactionMailHandler,
    ) { }

    /**
     * Called when an email has been received by application.
     */
    public function __invoke(InboundEmail $email): void
    {
        $receiver = $email->message()->getHeaderValue(HeaderConsts::TO);

        if ($bankTransactionAccountHandle = $this->matchesScope('b', $receiver)) {
            if ($account = BankTransactionAccount::query()->firstWhere('handle', $bankTransactionAccountHandle)) {
                $this->bankTransactionMailHandler->handle($account, $email);
            }
        }
    }

    /**
     * Determine whether the receiver address maches given scope. Returns the scope identifier.
     */
    protected function matchesScope(string $scope, string $email): ?string
    {
        $identifier = Str::match('/^'.preg_quote($scope).'\+([a-z0-9]{6,12})@'.preg_quote(config('app.mailbox_domain')).'$/', $email);

        return $identifier != '' ? $identifier : null;
    }
}
