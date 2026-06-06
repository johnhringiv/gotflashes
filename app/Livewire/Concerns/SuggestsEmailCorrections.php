<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Services\EmailSuggestionService;
use Livewire\Attributes\Locked;

/**
 * Adds a "did you mean ...?" email typo suggestion to a Livewire form.
 *
 * The host component must expose a public string $email and an 'email'
 * validation rule. Call refreshEmailSuggestion() from updated() after the
 * email is normalized; the view renders $emailSuggestion and wires the
 * applyEmailSuggestion() action.
 */
trait SuggestsEmailCorrections
{
    /** The suggested correction for the current email, or null. Server-computed; #[Locked] blocks client writes. */
    #[Locked]
    public ?string $emailSuggestion = null;

    /** Recompute the suggestion for the current email value. */
    protected function refreshEmailSuggestion(): void
    {
        $this->emailSuggestion = EmailSuggestionService::suggest($this->email);
    }

    /** Accept the suggested correction and re-validate the email field. */
    public function applyEmailSuggestion(): void
    {
        if ($this->emailSuggestion === null) {
            return;
        }

        // Normalize on apply too: the service already lowercases, but this keeps
        // the stored value consistent regardless of how the suggestion was built.
        $this->email = User::normalizeEmail($this->emailSuggestion);
        $this->emailSuggestion = null;
        $this->resetErrorBag('email');
        $this->validateOnly('email');
    }
}
