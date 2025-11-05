<div>
    @if($shouldShow)
        <div class="alert alert-warning shadow-lg" role="alert">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1">
                <h3 class="font-bold">Please verify your email address</h3>
                <div class="text-sm">You won't receive award notifications or important updates until you verify.</div>
            </div>
            <div class="flex-none">
                <button wire:click="resendVerification" class="btn btn-sm btn-ghost" wire:loading.attr="disabled" wire:target="resendVerification">
                    <span wire:loading.remove wire:target="resendVerification">Resend Verification Email</span>
                    <span wire:loading wire:target="resendVerification">
                        <span class="loading loading-spinner loading-xs"></span>
                        Sending...
                    </span>
                </button>
            </div>
        </div>
    @endif
</div>
