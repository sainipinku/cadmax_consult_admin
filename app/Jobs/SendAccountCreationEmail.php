<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Mail\AccountCreatedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAccountCreationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $name;
    protected $username;
    protected $password;
    protected $departmentNames;
    protected $designationNames;
    protected $loginUrl;
    protected $accountType;

    public function __construct($email, $name, $username, $password, $departmentNames, $designationNames, $loginUrl = null, $accountType = 'member')
    {
        $this->email = $email;
        $this->name = $name;
        $this->username = $username;
        $this->password = $password;
        $this->departmentNames = $departmentNames;
        $this->designationNames = $designationNames;
        $this->loginUrl = $loginUrl;
        $this->accountType = $accountType;
    }

    public function handle(): void
    {
        Mail::to($this->email)->send(
            new AccountCreatedMail(
                $this->name,
                $this->username,
                $this->password,
                $this->departmentNames,
                $this->designationNames,
                $this->loginUrl,
                $this->accountType,
                $this->email
            )
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('Account creation email job failed after all attempts', [
            'recipient' => $this->email,
            'exception' => get_class($exception),
        ]);
    }
}
